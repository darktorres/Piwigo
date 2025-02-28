<?php
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

namespace Piwigo\inc;

use DateInterval;
use DateTime;
use Piwigo\admin\inc\functions_history;
use Piwigo\admin\inc\functions_notification_by_mail;
use Piwigo\inc\dblayer\functions_mysqli;
use Piwigo\inc\Template;
use Piwigo\themes\smartpocket\SPThumbPicker;
use SmartyException;
use stdClass;
use uagent_info;
use function modus_smarty_prefilter;

include_once( PHPWG_ROOT_PATH .'inc/functions_plugins.php' );
include_once( PHPWG_ROOT_PATH .'inc/functions_user.php' );
include_once( PHPWG_ROOT_PATH .'inc/functions_cookie.php' );
include_once( PHPWG_ROOT_PATH .'inc/functions_session.php' );
include_once( PHPWG_ROOT_PATH .'inc/functions_category.php' );
include_once( PHPWG_ROOT_PATH .'inc/functions_html.php' );
include_once( PHPWG_ROOT_PATH .'inc/functions_tag.php' );
include_once( PHPWG_ROOT_PATH .'inc/functions_url.php' );
include_once( PHPWG_ROOT_PATH .'inc/derivative_params.php');
include_once( PHPWG_ROOT_PATH .'inc/derivative_std_params.php');


class functions
{
  /**
   * returns the current microsecond since Unix epoch
   *
   * @return int
   */
  static function micro_seconds()
  {
    $t1 = explode(' ', microtime());
    $t2 = explode('.', $t1[0]);
    $t2 = $t1[1].substr($t2[1], 0, 6);
    return $t2;
  }

  /**
   * returns a float value coresponding to the number of seconds since
   * the unix epoch (1st January 1970) and the microseconds are precised
   * e.g. 1052343429.89276600
   *
   * @return float
   */
  static function get_moment()
  {
    return microtime(true);
  }

  /**
   * returns the number of seconds (with 3 decimals precision)
   * between the start time and the end time given
   *
   * @param float $start
   * @param float $end
   * @return string "$TIME s"
   */
  static function get_elapsed_time($start, $end)
  {
    return number_format($end - $start, 3, '.', ' ').' s';
  }

  /**
   * returns the part of the string after the last "."
   *
   * @param string $filename
   * @return string
   */
  static function get_extension( $filename )
  {
    return substr( strrchr( $filename, '.' ), 1, strlen ( $filename ) );
  }

  /**
   * returns the part of the string before the last ".".
   * get_filename_wo_extension( 'test.tar.gz' ) = 'test.tar'
   *
   * @param string $filename
   * @return string
   */
  static function get_filename_wo_extension( $filename )
  {
    $pos = strrpos( $filename, '.' );
    return ($pos===false) ? $filename : substr( $filename, 0, $pos);
  }

  /** no option for mkgetdir() */
  const MKGETDIR_NONE = 0;
  /** sets mkgetdir() recursive */
  const MKGETDIR_RECURSIVE = 1;
  /** sets mkgetdir() exit script on error */
  const MKGETDIR_DIE_ON_ERROR = 2;
  /** sets mkgetdir() add a index.htm file */
  const MKGETDIR_PROTECT_INDEX = 4;
  /** sets mkgetdir() add a .htaccess file*/
  const MKGETDIR_PROTECT_HTACCESS = 8;
  /** default options for mkgetdir() = MKGETDIR_RECURSIVE | MKGETDIR_DIE_ON_ERROR | MKGETDIR_PROTECT_INDEX */
  const MKGETDIR_DEFAULT = self::MKGETDIR_RECURSIVE | self::MKGETDIR_DIE_ON_ERROR | self::MKGETDIR_PROTECT_INDEX;

  /**
   * creates directory if not exists and ensures that directory is writable
   *
   * @param string $dir
   * @param int $flags combination of MKGETDIR_xxx
   * @return bool
   */
  static function mkgetdir($dir, $flags=self::MKGETDIR_DEFAULT)
  {
    if ( !is_dir($dir) )
    {
      global $conf;
      if (substr(PHP_OS, 0, 3) == 'WIN')
      {
        $dir = str_replace('/', DIRECTORY_SEPARATOR, $dir);
      }
      $umask = umask(0);
      $mkd = @mkdir($dir, $conf['chmod_value'], ($flags&self::MKGETDIR_RECURSIVE) ? true:false );
      umask($umask);
      if ($mkd==false)
      {
        !($flags&self::MKGETDIR_DIE_ON_ERROR) or functions_html::fatal_error( "$dir ".self::l10n('no write access'));
        return false;
      }
      if( $flags&self::MKGETDIR_PROTECT_HTACCESS )
      {
        $file = $dir.'/.htaccess';
        file_exists($file) or @file_put_contents( $file, 'deny from all' );
      }
      if( $flags&self::MKGETDIR_PROTECT_INDEX )
      {
        $file = $dir.'/index.htm';
        file_exists($file) or @file_put_contents( $file, 'Not allowed!' );
      }
    }
    if ( !is_writable($dir) )
    {
      !($flags&self::MKGETDIR_DIE_ON_ERROR) or functions_html::fatal_error( "$dir ".self::l10n('no write access'));
      return false;
    }
    return true;
  }

  /**
   * finds out if a string is in ASCII, UTF-8 or other encoding
   *
   * @param string $str
   * @return int *0* if _$str_ is ASCII, *1* if UTF-8, *-1* otherwise
   */
  static function qualify_utf8($Str)
  {
    $ret = 0;
    for ($i=0; $i<strlen($Str); $i++)
    {
      if (ord($Str[$i]) < 0x80) continue; # 0bbbbbbb
      $ret = 1;
      if ((ord($Str[$i]) & 0xE0) == 0xC0) $n=1; # 110bbbbb
      elseif ((ord($Str[$i]) & 0xF0) == 0xE0) $n=2; # 1110bbbb
      elseif ((ord($Str[$i]) & 0xF8) == 0xF0) $n=3; # 11110bbb
      elseif ((ord($Str[$i]) & 0xFC) == 0xF8) $n=4; # 111110bb
      elseif ((ord($Str[$i]) & 0xFE) == 0xFC) $n=5; # 1111110b
      else return -1; # Does not match any model
      for ($j=0; $j<$n; $j++)
      { # n bytes matching 10bbbbbb follow ?
        if ((++$i == strlen($Str)) || ((ord($Str[$i]) & 0xC0) != 0x80))
          return -1;
      }
    }
    return $ret;
  }

  /**
   * Remove accents from a UTF-8 or ISO-8859-1 string (from wordpress)
   *
   * @param string $string
   * @return string
   */
  static function remove_accents($string)
  {
    $utf = self::qualify_utf8($string);
    if ( $utf == 0 )
    {
      return $string; // ascii
    }

    if ( $utf > 0 )
    {
      $chars = array(
      // Decompositions for Latin-1 Supplement
      "\xc3\x80"=>'A', "\xc3\x81"=>'A',
      "\xc3\x82"=>'A', "\xc3\x83"=>'A',
      "\xc3\x84"=>'A', "\xc3\x85"=>'A',
      "\xc3\x87"=>'C', "\xc3\x88"=>'E',
      "\xc3\x89"=>'E', "\xc3\x8a"=>'E',
      "\xc3\x8b"=>'E', "\xc3\x8c"=>'I',
      "\xc3\x8d"=>'I', "\xc3\x8e"=>'I',
      "\xc3\x8f"=>'I', "\xc3\x91"=>'N',
      "\xc3\x92"=>'O', "\xc3\x93"=>'O',
      "\xc3\x94"=>'O', "\xc3\x95"=>'O',
      "\xc3\x96"=>'O', "\xc3\x99"=>'U',
      "\xc3\x9a"=>'U', "\xc3\x9b"=>'U',
      "\xc3\x9c"=>'U', "\xc3\x9d"=>'Y',
      "\xc3\x9f"=>'s', "\xc3\xa0"=>'a',
      "\xc3\xa1"=>'a', "\xc3\xa2"=>'a',
      "\xc3\xa3"=>'a', "\xc3\xa4"=>'a',
      "\xc3\xa5"=>'a', "\xc3\xa7"=>'c',
      "\xc3\xa8"=>'e', "\xc3\xa9"=>'e',
      "\xc3\xaa"=>'e', "\xc3\xab"=>'e',
      "\xc3\xac"=>'i', "\xc3\xad"=>'i',
      "\xc3\xae"=>'i', "\xc3\xaf"=>'i',
      "\xc3\xb1"=>'n', "\xc3\xb2"=>'o',
      "\xc3\xb3"=>'o', "\xc3\xb4"=>'o',
      "\xc3\xb5"=>'o', "\xc3\xb6"=>'o',
      "\xc3\xb9"=>'u', "\xc3\xba"=>'u',
      "\xc3\xbb"=>'u', "\xc3\xbc"=>'u',
      "\xc3\xbd"=>'y', "\xc3\xbf"=>'y',
      // Decompositions for Latin Extended-A
      "\xc4\x80"=>'A', "\xc4\x81"=>'a',
      "\xc4\x82"=>'A', "\xc4\x83"=>'a',
      "\xc4\x84"=>'A', "\xc4\x85"=>'a',
      "\xc4\x86"=>'C', "\xc4\x87"=>'c',
      "\xc4\x88"=>'C', "\xc4\x89"=>'c',
      "\xc4\x8a"=>'C', "\xc4\x8b"=>'c',
      "\xc4\x8c"=>'C', "\xc4\x8d"=>'c',
      "\xc4\x8e"=>'D', "\xc4\x8f"=>'d',
      "\xc4\x90"=>'D', "\xc4\x91"=>'d',
      "\xc4\x92"=>'E', "\xc4\x93"=>'e',
      "\xc4\x94"=>'E', "\xc4\x95"=>'e',
      "\xc4\x96"=>'E', "\xc4\x97"=>'e',
      "\xc4\x98"=>'E', "\xc4\x99"=>'e',
      "\xc4\x9a"=>'E', "\xc4\x9b"=>'e',
      "\xc4\x9c"=>'G', "\xc4\x9d"=>'g',
      "\xc4\x9e"=>'G', "\xc4\x9f"=>'g',
      "\xc4\xa0"=>'G', "\xc4\xa1"=>'g',
      "\xc4\xa2"=>'G', "\xc4\xa3"=>'g',
      "\xc4\xa4"=>'H', "\xc4\xa5"=>'h',
      "\xc4\xa6"=>'H', "\xc4\xa7"=>'h',
      "\xc4\xa8"=>'I', "\xc4\xa9"=>'i',
      "\xc4\xaa"=>'I', "\xc4\xab"=>'i',
      "\xc4\xac"=>'I', "\xc4\xad"=>'i',
      "\xc4\xae"=>'I', "\xc4\xaf"=>'i',
      "\xc4\xb0"=>'I', "\xc4\xb1"=>'i',
      "\xc4\xb2"=>'IJ', "\xc4\xb3"=>'ij',
      "\xc4\xb4"=>'J', "\xc4\xb5"=>'j',
      "\xc4\xb6"=>'K', "\xc4\xb7"=>'k',
      "\xc4\xb8"=>'k', "\xc4\xb9"=>'L',
      "\xc4\xba"=>'l', "\xc4\xbb"=>'L',
      "\xc4\xbc"=>'l', "\xc4\xbd"=>'L',
      "\xc4\xbe"=>'l', "\xc4\xbf"=>'L',
      "\xc5\x80"=>'l', "\xc5\x81"=>'L',
      "\xc5\x82"=>'l', "\xc5\x83"=>'N',
      "\xc5\x84"=>'n', "\xc5\x85"=>'N',
      "\xc5\x86"=>'n', "\xc5\x87"=>'N',
      "\xc5\x88"=>'n', "\xc5\x89"=>'N',
      "\xc5\x8a"=>'n', "\xc5\x8b"=>'N',
      "\xc5\x8c"=>'O', "\xc5\x8d"=>'o',
      "\xc5\x8e"=>'O', "\xc5\x8f"=>'o',
      "\xc5\x90"=>'O', "\xc5\x91"=>'o',
      "\xc5\x92"=>'OE', "\xc5\x93"=>'oe',
      "\xc5\x94"=>'R', "\xc5\x95"=>'r',
      "\xc5\x96"=>'R', "\xc5\x97"=>'r',
      "\xc5\x98"=>'R', "\xc5\x99"=>'r',
      "\xc5\x9a"=>'S', "\xc5\x9b"=>'s',
      "\xc5\x9c"=>'S', "\xc5\x9d"=>'s',
      "\xc5\x9e"=>'S', "\xc5\x9f"=>'s',
      "\xc5\xa0"=>'S', "\xc5\xa1"=>'s',
      "\xc5\xa2"=>'T', "\xc5\xa3"=>'t',
      "\xc5\xa4"=>'T', "\xc5\xa5"=>'t',
      "\xc5\xa6"=>'T', "\xc5\xa7"=>'t',
      "\xc5\xa8"=>'U', "\xc5\xa9"=>'u',
      "\xc5\xaa"=>'U', "\xc5\xab"=>'u',
      "\xc5\xac"=>'U', "\xc5\xad"=>'u',
      "\xc5\xae"=>'U', "\xc5\xaf"=>'u',
      "\xc5\xb0"=>'U', "\xc5\xb1"=>'u',
      "\xc5\xb2"=>'U', "\xc5\xb3"=>'u',
      "\xc5\xb4"=>'W', "\xc5\xb5"=>'w',
      "\xc5\xb6"=>'Y', "\xc5\xb7"=>'y',
      "\xc5\xb8"=>'Y', "\xc5\xb9"=>'Z',
      "\xc5\xba"=>'z', "\xc5\xbb"=>'Z',
      "\xc5\xbc"=>'z', "\xc5\xbd"=>'Z',
      "\xc5\xbe"=>'z', "\xc5\xbf"=>'s',
      // Decompositions for Latin Extended-B
      "\xc8\x98"=>'S', "\xc8\x99"=>'s',
      "\xc8\x9a"=>'T', "\xc8\x9b"=>'t',
      // Euro Sign
      "\xe2\x82\xac"=>'E',
      // GBP (Pound) Sign
      "\xc2\xa3"=>'');

      $string = strtr($string, $chars);
    }
    else
    {
      // Assume ISO-8859-1 if not UTF-8
      $chars['in'] = chr(128).chr(131).chr(138).chr(142).chr(154).chr(158)
        .chr(159).chr(162).chr(165).chr(181).chr(192).chr(193).chr(194)
        .chr(195).chr(196).chr(197).chr(199).chr(200).chr(201).chr(202)
        .chr(203).chr(204).chr(205).chr(206).chr(207).chr(209).chr(210)
        .chr(211).chr(212).chr(213).chr(214).chr(216).chr(217).chr(218)
        .chr(219).chr(220).chr(221).chr(224).chr(225).chr(226).chr(227)
        .chr(228).chr(229).chr(231).chr(232).chr(233).chr(234).chr(235)
        .chr(236).chr(237).chr(238).chr(239).chr(241).chr(242).chr(243)
        .chr(244).chr(245).chr(246).chr(248).chr(249).chr(250).chr(251)
        .chr(252).chr(253).chr(255);

      $chars['out'] = "EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy";

      $string = strtr($string, $chars['in'], $chars['out']);
      $double_chars['in'] = array(chr(140), chr(156), chr(198), chr(208), chr(222), chr(223), chr(230), chr(240), chr(254));
      $double_chars['out'] = array('OE', 'oe', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th');
      $string = str_replace($double_chars['in'], $double_chars['out'], $string);
    }

    return $string;
  }

  /**
   * removes accents from a string and converts it to lower case
   *
   * @param string $term
   * @return string
   */
  static function pwg_transliterate($term)
  {
    if (function_exists('mb_strtolower') && defined('PWG_CHARSET'))
    {
      return self::remove_accents( mb_strtolower($term, PWG_CHARSET) );
    }
    else
    {
      return self::remove_accents( strtolower($term) );
    }
  }

  /**
   * simplify a string to insert it into an URL
   *
   * @param string $str
   * @return string
   */
  static function str2url($str)
  {
    $str = $safe = self::pwg_transliterate($str);
    $str = preg_replace('/[^\x80-\xffa-z0-9_\s\'\:\/\[\],-]/','',$str);
    $str = preg_replace('/[\s\'\:\/\[\],-]+/',' ',trim($str));
    $res = str_replace(' ','_',$str);

    if (empty($res))
    {
      $res = str_replace(' ','_', $safe);
    }

    return $res;
  }

  /**
   * returns an array with a list of {language_code => language_name}
   *
   * @return string[]
   */
  static function get_languages()
  {
    $query = '
  SELECT id, name
    FROM '.LANGUAGES_TABLE.'
    ORDER BY name ASC
  ;';
    $result = functions_mysqli::pwg_query($query);

    $languages = array();
    while ($row = functions_mysqli::pwg_db_fetch_assoc($result))
    {
      if (is_dir(PHPWG_ROOT_PATH.'language/'.$row['id']))
      {
        $languages[ $row['id'] ] = $row['name'];
      }
    }

    return $languages;
  }

  /**
   * Does the current user must log visits in history table
   *
   * @since 14
   *
   * @param int $image_id
   * @param string $image_type
   *
   * @return bool
   */
  static function do_log($image_id = null, $image_type = null)
  {
    global $conf;

    $do_log = $conf['log'];
    if (functions_user::is_admin())
    {
      $do_log = $conf['history_admin'];
    }
    if (functions_user::is_a_guest())
    {
      $do_log = $conf['history_guest'];
    }

    $do_log = functions_plugins::trigger_change('pwg_log_allowed', $do_log, $image_id, $image_type);

    return $do_log;
  }

  /**
   * log the visit into history table
   *
   * @param int $image_id
   * @param string $image_type
   * @return bool
   */
  static function pwg_log($image_id = null, $image_type = null, $format_id = null)
  {
    global $conf, $user, $page;

    $update_last_visit = false;
    if (empty($user['last_visit']) or strtotime($user['last_visit']) < time()-$conf['session_length'])
    {
      $update_last_visit = true;
    }
    $update_last_visit = functions_plugins::trigger_change('pwg_log_update_last_visit', $update_last_visit);

    if ($update_last_visit)
    {
      $query = '
  UPDATE '.USER_INFOS_TABLE.'
    SET last_visit = NOW(),
        lastmodified = lastmodified
    WHERE user_id = '.$user['id'].'
  ';
      functions_mysqli::pwg_query($query);
    }

    if (!self::do_log($image_id, $image_type))
    {
      return false;
    }

    $tags_string = null;
    if ('tags'==@$page['section'])
    {
      $tags_string = implode(',', $page['tag_ids']);

      if (strlen($tags_string) > 50)
      {
        // we need to truncate, mysql won't accept a too long string
        $tags_string = substr($tags_string, 0, 50);
        // the last tag_id may have been truncated itself, so we must remove it
        $tags_string = substr($tags_string, 0, strrpos($tags_string, ','));
      }
    }

    $ip = $_SERVER['REMOTE_ADDR'];
    // In case of "too long" ipv6 address, we take only the 15 first chars.
    //
    // It would be "cleaner" to increase length of history.IP to 50 chars, but
    // the alter table is very long on such a big table. We should plan this
    // for a future version, once history table is kept "smaller".
    if (strpos($ip,':') !== false and strlen($ip) > 15)
    {
      $ip = substr($ip, 0, 15);
    }

    // If plugin developers add their own sections, Piwigo will automatically add it in the history.section enum column
    if (isset($page['section']))
    {
      // set cache if not available
      if (!isset($conf['history_sections_cache']))
      {
        self::conf_update_param('history_sections_cache', functions_mysqli::get_enums(HISTORY_TABLE, 'section'), true);
      }

      $conf['history_sections_cache'] = self::safe_unserialize($conf['history_sections_cache']);

      if (
        in_array($page['section'], $conf['history_sections_cache'])
        or in_array(strtolower($page['section']), array_map('strtolower', $conf['history_sections_cache']))
      )
      {
        $section = $page['section'];
      }
      elseif (preg_match('/^[a-zA-Z0-9_-]+$/', $page['section']))
      {
        $history_sections = functions_mysqli::get_enums(HISTORY_TABLE, 'section');
        $history_sections[] = $page['section'];

        // alter history table structure, to include a new section
        functions_mysqli::pwg_query('ALTER TABLE '.HISTORY_TABLE.' CHANGE section section enum(\''.implode("','", array_unique($history_sections)).'\') DEFAULT NULL;');

        // and refresh cache
        self::conf_update_param('history_sections_cache', functions_mysqli::get_enums(HISTORY_TABLE, 'section'), true);

        $section = $page['section'];
      }
    }

    $query = '
  INSERT INTO '.HISTORY_TABLE.'
    (
      date,
      time,
      user_id,
      IP,
      section,
      category_id,
      search_id,
      image_id,
      image_type,
      format_id,
      auth_key_id,
      tag_ids
    )
    VALUES
    (
      CURRENT_DATE,
      CURRENT_TIME,
      '.$user['id'].',
      \''.$ip.'\',
      '.(isset($section) ? "'".$section."'" : 'NULL').',
      '.(isset($page['category']['id']) ? $page['category']['id'] : 'NULL').',
      '.(isset($page['search_id']) ? $page['search_id'] : 'NULL').',
      '.(isset($image_id) ? $image_id : 'NULL').',
      '.(isset($image_type) ? "'".$image_type."'" : 'NULL').',
      '.(isset($format_id) ? $format_id : 'NULL').',
      '.(isset($page['auth_key_id']) ? $page['auth_key_id'] : 'NULL').',
      '.(isset($tags_string) ? "'".$tags_string."'" : 'NULL').'
    )
  ;';
    functions_mysqli::pwg_query($query);

    $history_id = functions_mysqli::pwg_db_insert_id(HISTORY_TABLE);
    if ($history_id % 1000 == 0)
    {
      include_once(PHPWG_ROOT_PATH.'admin/inc/functions_history.php');
      functions_history::history_summarize(50000);
    }

    if ($conf['history_autopurge_every'] > 0 and $history_id % $conf['history_autopurge_every'] == 0)
    {
      include_once(PHPWG_ROOT_PATH.'admin/inc/functions_history.php');
      functions_history::history_autopurge();
    }

    return true;
  }

  static function pwg_activity($object, $object_id, $action, $details=array())
  {
    global $user;

    // in case of uploadAsync, do not log the automatic login as an independant activity
    if (isset($_REQUEST['method']) and 'pwg.images.uploadAsync' == $_REQUEST['method'] and 'login' == $action)
    {
      return;
    }

    if (isset($_REQUEST['method']) and 'pwg.plugins.performAction' == $_REQUEST['method'] and $_REQUEST['action'] != $action)
    {
      // for example, if you "restore" a plugin, the internal sequence will perform deactivate/uninstall/install/activate.
      // We only want to keep the last call to pwg_activity with the "restore" action.
      return;
    }

    $object_ids = $object_id;
    if (!is_array($object_id))
    {
      $object_ids = array($object_id);
    }

    if (isset($_REQUEST['method']))
    {
      $details['method'] = $_REQUEST['method'];
    }
    else
    {
      $details['script'] = self::script_basename();

      if ('admin' == $details['script'] and isset($_GET['page']))
      {
        $details['script'].= '/'.$_GET['page'];
      }
    }

    if ('autoupdate' == $action)
    {
      // autoupdate on a plugin can happen anywhere, the "script/method" is not meaningfull
      unset($details['method']);
      unset($details['script']);
    }

    $user_agent = null;
    if ('user' == $object and 'login' == $action and isset($_SERVER['HTTP_USER_AGENT']))
    {
      $user_agent = strip_tags($_SERVER['HTTP_USER_AGENT']);
    }

    if ('photo' == $object and 'add' == $action and !isset($details['sync']))
    {
      $details['added_with'] = 'app';
      if (isset($_SERVER['HTTP_REFERER']) and preg_match('/page=photos_add/', $_SERVER['HTTP_REFERER']))
      {
        $details['added_with'] = 'browser';
      }
    }

    if (in_array($object, array('album', 'photo')) and 'delete' == $action and isset($_GET['page']) and 'site_update' == $_GET['page'])
    {
      $details['sync'] = true;
    }

    if ('tag' == $object and 'delete' == $action and isset($_POST['destination_tag']))
    {
      $details['action'] = 'merge';
      $details['destination_tag'] = $_POST['destination_tag'];
    }

    $inserts = array();
    $details_insert = functions_mysqli::pwg_db_real_escape_string(serialize($details));
    $ip_address = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
    $session_id = !empty(session_id()) ? session_id() : 'none';

    foreach ($object_ids as $loop_object_id)
    {
      $performed_by = $user['id'] ?? 0; // on a plugin autoupdate, $user is not yet loaded

      if ('logout' == $action)
      {
        $performed_by = $loop_object_id;
      }

      $inserts[] = array(
        'object' => $object,
        'object_id' => $loop_object_id,
        'action' => $action,
        'performed_by' => $performed_by,
        'session_idx' => $session_id,
        'ip_address' => $ip_address,
        'details' => $details_insert,
        'user_agent' => functions_mysqli::pwg_db_real_escape_string($user_agent),
      );
    }

    functions_mysqli::mass_inserts(ACTIVITY_TABLE, array_keys($inserts[0]), $inserts);
  }

  /**
   * Computes the difference between two dates.
   * returns a DateInterval object or a stdClass with the same attributes
   * http://stephenharris.info/date-intervals-in-php-5-2
   *
   * @param DateTime $date1
   * @param DateTime $date2
   * @return DateInterval|stdClass
   */
  static function dateDiff($date1, $date2)
  {
    if (version_compare(PHP_VERSION, '5.3.0') >= 0)
    {
      return $date1->diff($date2);
    }

    $diff = new stdClass();

    //Make sure $date1 is ealier
    $diff->invert = $date2 < $date1;
    if ($diff->invert)
    {
      list($date1, $date2) = array($date2, $date1);
    }

    //Calculate R values
    $R = ($date1 <= $date2 ? '+' : '-');
    $r = ($date1 <= $date2 ? '' : '-');

    //Calculate total days
    $diff->days = round(abs($date1->format('U') - $date2->format('U'))/86400);

    //A leap year work around - consistent with DateInterval
    $leap_year = $date1->format('m-d') == '02-29';
    if ($leap_year)
    {
      $date1->modify('-1 day');
    }

    //Years, months, days, hours
    $periods = array('years'=>-1, 'months'=>-1, 'days'=>-1, 'hours'=>-1);

    foreach ($periods as $period => &$i)
    {
      if ($period == 'days' && $leap_year)
      {
        $date1->modify('+1 day');
      }

      while ($date1 <= $date2 )
      {
        $date1->modify('+1 '.$period);
        $i++;
      }

      //Reset date and record increments
      $date1->modify('-1 '.$period);
    }

    list($diff->y, $diff->m, $diff->d, $diff->h) = array_values($periods);

    //Minutes, seconds
    $diff->s = round(abs($date1->format('U') - $date2->format('U')));
    $diff->i = floor($diff->s/60);
    $diff->s = $diff->s - $diff->i*60;

    return $diff;
  }

  /**
   * converts a string into a DateTime object
   *
   * @param int|string timestamp or datetime string
   * @param string $format input format respecting date() syntax
   * @return DateTime|false
   */
  static function str2DateTime($original, $format=null)
  {
    if (empty($original))
    {
      return false;
    }
    
    if ($original instanceof DateTime)
    {
      return $original;
    }

    if (!empty($format) && version_compare(PHP_VERSION, '5.3.0') >= 0)// from known date format
    {
      return DateTime::createFromFormat('!'.$format, $original); // ! char to reset fields to UNIX epoch
    }
    else
    {
      $t = trim($original, '0123456789');
      if (empty($t)) // from timestamp
      {
        return new DateTime('@'.$original);
      }
      else // from unknown date format (assuming something like Y-m-d H:i:s)
      {
        $ymdhms = array();
        $tok = strtok($original, '- :/');
        while ($tok !== false)
        {
          $ymdhms[] = $tok;
          $tok = strtok('- :/');
        }

        if (count($ymdhms)<3) return false;
        if (!isset($ymdhms[3])) $ymdhms[3] = 0;
        if (!isset($ymdhms[4])) $ymdhms[4] = 0;
        if (!isset($ymdhms[5])) $ymdhms[5] = 0;

        $date = new DateTime();
        $date->setDate($ymdhms[0], $ymdhms[1], $ymdhms[2]);
        $date->setTime($ymdhms[3], $ymdhms[4], $ymdhms[5]);
        return $date;
      }
    }
  }

  /**
   * returns a formatted and localized date for display
   *
   * @param int|string timestamp or datetime string
   * @param array $show list of components displayed, default is ['day_name', 'day', 'month', 'year']
   *    THIS PARAMETER IS PLANNED TO CHANGE
   * @param string $format input format respecting date() syntax
   * @return string
   */
  static function format_date($original, $show=null, $format=null)
  {
    global $lang;

    $date = self::str2DateTime($original, $format);

    if (!$date)
    {
      return self::l10n('N/A');
    }

    if ($show === null || $show === true)
    {
      $show = array('day_name', 'day', 'month', 'year');
    }

    // TODO use IntlDateFormatter for proper i18n

    $print = '';
    if (in_array('day_name', $show))
      $print.= $lang['day'][ $date->format('w') ].' ';

    if (in_array('day', $show))
      $print.= $date->format('j').' ';

    if (in_array('month', $show))
      $print.= $lang['month'][ $date->format('n') ].' ';

    if (in_array('year', $show))
      $print.= $date->format('Y').' ';

    if (in_array('time', $show))
    {
      $temp = $date->format('H:i');
      if ($temp != '00:00')
      {
        $print.= $temp.' ';
      }
    }

    return trim($print);
  }

  /**
   * Format a "From ... to ..." string from two dates
   * @param string $from
   * @param string $to
   * @param boolean $full
   * @return string
   */
  static function format_fromto($from, $to, $full=false)
  {
    $from = self::str2DateTime($from);
    $to = self::str2DateTime($to);

    if ($from->format('Y-m-d') == $to->format('Y-m-d'))
    {
      return self::format_date($from);
    }
    else
    {
      if ($full || $from->format('Y') != $to->format('Y'))
      {
        $from_str = self::format_date($from);
      }
      else if ($from->format('m') != $to->format('m'))
      {
        $from_str = self::format_date($from, array('day_name', 'day', 'month'));
      }
      else
      {
        $from_str = self::format_date($from, array('day_name', 'day'));
      }
      $to_str = self::format_date($to);

      return self::l10n('from %s to %s', $from_str, $to_str);
    }
  }

  /**
   * Works out the time since the given date
   *
   * @param int|string timestamp or datetime string
   * @param string $stop year,month,week,day,hour,minute,second
   * @param string $format input format respecting date() syntax
   * @param bool $with_text append "ago" or "in the future"
   * @param bool $with_weeks
   * @return string
   */
  static function time_since($original, $stop='minute', $format=null, $with_text=true, $with_week=true, $only_last_unit=false)
  {
    $date = self::str2DateTime($original, $format);

    if (!$date)
    {
      return self::l10n('N/A');
    }

    $now = new DateTime();
    $diff = self::dateDiff($now, $date);

    $chunks = array(
      'year' => $diff->y,
      'month' => $diff->m,
      'week' => 0,
      'day' => $diff->d,
      'hour' => $diff->h,
      'minute' => $diff->i,
      'second' => $diff->s,
    );

    // DateInterval does not contain the number of weeks
    if ($with_week)
    {
      $chunks['week'] = (int)floor($chunks['day']/7);
      $chunks['day'] = $chunks['day'] - $chunks['week']*7;
    }

    $j = array_search($stop, array_keys($chunks));

    $print = ''; $i=0;
    
    if (!$only_last_unit)
    {
      foreach ($chunks as $name => $value)
      {
        if ($value != 0)
        {
          $print.= ' '.self::l10n_dec('%d '.$name, '%d '.$name.'s', $value);
        }
        if (!empty($print) && $i >= $j)
        {
          break;
        }
        $i++;
      }
    } else {
      $reversed_chunks_names = array_keys($chunks);
      while ($print == '' && $i<count($reversed_chunks_names )) 
      {
        $name = $reversed_chunks_names[$i];
        $value = $chunks[$name];
        if ($value != 0)
        {
          $print = self::l10n_dec('%d '.$name, '%d '.$name.'s', $value);
        }
        if (!empty($print) && $i >= $j)
        {
          break;
        }
        $i++;
      }
    }

    $print = trim($print);

    if ($with_text)
    {
      if ($diff->invert)
      {
        $print = self::l10n('%s ago', $print);
      }
      else
      {
        $print = self::l10n('%s in the future', $print);
      }
    }

    return $print;
  }

  /**
   * transform a date string from a format to another (MySQL to d/M/Y for instance)
   *
   * @param string $original
   * @param string $format_in respecting date() syntax
   * @param string $format_out respecting date() syntax
   * @param string $default if _$original_ is empty
   * @return string
   */
  static function transform_date($original, $format_in, $format_out, $default=null)
  {
    if (empty($original)) return $default;
    $date = self::str2DateTime($original, $format_in);
    return $date->format($format_out);
  }

  /**
   * append a variable to _$debug_ global
   *
   * @param string $string
   */
  static function pwg_debug( $string )
  {
    global $debug,$t2,$page;

    $now = explode( ' ', microtime() );
    $now2 = explode( '.', $now[0] );
    $now2 = $now[1].'.'.$now2[1];
    $time = number_format( $now2 - $t2, 3, '.', ' ').' s';
    $debug .= '<p>';
    $debug.= '['.$time.', ';
    $debug.= $page['count_queries'].' queries] : '.$string;
    $debug.= "</p>\n";
  }

  /**
   * Redirects to the given URL (HTTP method).
   * once this function called, the execution doesn't go further
   * (presence of an exit() instruction.
   *
   * @param string $url
   * @return void
   */
  static function redirect_http( $url )
  {
    if (ob_get_length () !== FALSE)
    {
      ob_clean();
    }
    // default url is on html format
    $url = html_entity_decode($url);
    header('Request-URI: '.$url);
    header('Content-Location: '.$url);
    header('Location: '.$url);
    exit();
  }

  /**
   * Redirects to the given URL (HTML method).
   * once this function called, the execution doesn't go further
   * (presence of an exit() instruction.
   *
   * @param string $url
   * @param string $msg
   * @param integer $refresh_time
   * @return void
   */
  static function redirect_html( $url , $msg = '', $refresh_time = 0)
  {
    global $user, $template, $lang_info, $conf, $lang, $t2, $page, $debug;

    if (!isset($lang_info) || !isset($template) )
    {
      $user = functions_user::build_user( $conf['guest_id'], true);
      self::load_language('common.lang');
      functions_plugins::trigger_notify('loading_lang');
      self::load_language('lang', PHPWG_ROOT_PATH.PWG_LOCAL_DIR, array('no_fallback'=>true, 'local'=>true) );
      $template = new Template(PHPWG_ROOT_PATH.'themes', functions_user::get_default_theme());
    }
    elseif (defined('IN_ADMIN') and IN_ADMIN)
    {
      $template = new Template(PHPWG_ROOT_PATH.'themes', functions_user::get_default_theme());
    }

    if (empty($msg))
    {
      $msg = nl2br(self::l10n('Redirection...'));
    }

    $refresh = $refresh_time;
    $url_link = $url;
    $title = 'redirection';

    $template->set_filenames( array( 'redirect' => 'redirect.tpl' ) );

    include( PHPWG_ROOT_PATH.'inc/page_header.php' );

    $template->set_filenames( array( 'redirect' => 'redirect.tpl' ) );
    $template->assign('REDIRECT_MSG', $msg);

    $template->parse('redirect');

    include( PHPWG_ROOT_PATH.'inc/page_tail.php' );

    exit();
  }

  /**
   * Redirects to the given URL (automatically choose HTTP or HTML method).
   * once this function called, the execution doesn't go further
   * (presence of an exit() instruction.
   *
   * @param string $url
   * @param string $msg
   * @param integer $refresh_time
   * @return void
   */
  static function redirect( $url , $msg = '', $refresh_time = 0)
  {
    global $conf;

    // with RefeshTime <> 0, only html must be used
    if ($conf['default_redirect_method']=='http'
        and $refresh_time==0
        and !headers_sent()
      )
    {
      self::redirect_http($url);
    }
    else
    {
      self::redirect_html($url, $msg, $refresh_time);
    }
  }

  /**
   * returns available themes
   *
   * @param bool $show_mobile
   * @return array
   */
  static function get_pwg_themes($show_mobile=false)
  {
    global $conf;

    $themes = array();

    $query = '
  SELECT
      id,
      name
    FROM '.THEMES_TABLE.'
    ORDER BY name ASC
  ;';
    $result = functions_mysqli::pwg_query($query);
    while ($row = functions_mysqli::pwg_db_fetch_assoc($result))
    {
      if ($row['id'] == $conf['mobile_theme'])
      {
        if (!$show_mobile)
        {
          continue;
        }
        $row['name'] .= ' ('.self::l10n('Mobile').')';
      }
      if (self::check_theme_installed($row['id']))
      {
        $themes[ $row['id'] ] = $row['name'];
      }
    }

    // plugins want remove some themes based on user status maybe?
    $themes = functions_plugins::trigger_change('get_pwg_themes', $themes);

    return $themes;
  }

  /**
   * check if a theme is installed (directory exsists)
   *
   * @param string $theme_id
   * @return bool
   */
  static function check_theme_installed($theme_id)
  {
    global $conf;

    return file_exists($conf['themes_dir'].'/'.$theme_id.'/'.'themeconf.php');
  }

  /**
   * Transforms an original path to its pwg representative
   *
   * @param string $path
   * @param string $representative_ext
   * @return string
   */
  static function original_to_representative($path, $representative_ext)
  {
    $pos = strrpos($path, '/');
    $path = substr_replace($path, 'pwg_representative/', $pos+1, 0);
    $pos = strrpos($path, '.');
    return substr_replace($path, $representative_ext, $pos+1);
  }

  /**
   * Transforms an original path to its format
   *
   * @param string $path
   * @param string $format_ext
   * @return string
   */
  static function original_to_format($path, $format_ext)
  {
    $pos = strrpos($path, '/');
    $path = substr_replace($path, 'pwg_format/', $pos+1, 0);
    $pos = strrpos($path, '.');
    return substr_replace($path, $format_ext, $pos+1);
  }

  /**
   * get the full path of an image
   *
   * @param array $element_info element information from db (at least 'path')
   * @return string
   */
  static function get_element_path($element_info)
  {
    $path = $element_info['path'];
    if ( !functions_url::url_is_remote($path) )
    {
      $path = PHPWG_ROOT_PATH.$path;
    }
    return $path;
  }


  /**
   * fill the current user caddie with given elements, if not already in caddie
   *
   * @param int[] $elements_id
   */
  static function fill_caddie($elements_id)
  {
    global $user;

    $query = '
  SELECT element_id
    FROM '.CADDIE_TABLE.'
    WHERE user_id = '.$user['id'].'
  ;';
    $in_caddie = functions_mysqli::query2array($query, null, 'element_id');

    $caddiables = array_diff($elements_id, $in_caddie);

    $datas = array();

    foreach ($caddiables as $caddiable)
    {
      $datas[] = array(
        'element_id' => $caddiable,
        'user_id' => $user['id'],
        );
    }

    if (count($caddiables) > 0)
    {
      functions_mysqli::mass_inserts(CADDIE_TABLE, array('element_id','user_id'), $datas);
    }
  }

  /**
   * returns the element name from its filename.
   * removes file extension and replace underscores by spaces
   *
   * @param string $filename
   * @return string name
   */
  static function get_name_from_file($filename)
  {
    return str_replace('_',' ',self::get_filename_wo_extension($filename));
  }

  /**
   * translation function.
   * returns the corresponding value from _$lang_ if existing else the key is returned
   * if more than one parameter is provided sprintf is applied
   *
   * @param string $key
   * @param mixed $args,... optional arguments
   * @return string
   */
  static function l10n($key)
  {
    global $lang, $conf;

    if ( ($val=@$lang[$key]) === null)
    {
      if ($conf['debug_l10n'] and !isset($lang[$key]) and !empty($key))
      {
        trigger_error('[l10n] language key "'. $key .'" not defined', E_USER_WARNING);
      }
      $val = $key;
    }

    if (func_num_args() > 1)
    {
      $args = func_get_args();
      $val = vsprintf($val, array_slice($args, 1));
    }

    return $val;
  }

  /**
   * returns the printf value for strings including %d
   * returned value is concorded with decimal value (singular, plural)
   *
   * @param string $singular_key
   * @param string $plural_key
   * @param int $decimal
   * @return string
   */
  static function l10n_dec($singular_key, $plural_key, $decimal)
  {
    global $lang_info;

    return
      sprintf(
        self::l10n((
          (($decimal > 1) or ($decimal == 0 and $lang_info['zero_plural']))
            ? $plural_key
            : $singular_key
          )), $decimal);
  }

  /**
   * returns a single element to use with l10n_args
   *
   * @param string $key translation key
   * @param mixed $args arguments to use on sprintf($key, args)
   *   if args is a array, each values are used on sprintf
   * @return string
   */
  static function get_l10n_args($key, $args='')
  {
    if (is_array($args))
    {
      $key_arg = array_merge(array($key), $args);
    }
    else
    {
      $key_arg = array($key,  $args);
    }
    return array('key_args' => $key_arg);
  }

  /**
   * returns a string formated with l10n elements.
   * it is usefull to "prepare" a text and translate it later
   * @see get_l10n_args()
   *
   * @param array $key_args one l10n_args element or array of l10n_args elements
   * @param string $sep used when translated elements are concatened
   * @return string
   */
  static function l10n_args($key_args, $sep = "\n")
  {
    if (is_array($key_args))
    {
      foreach ($key_args as $key => $element)
      {
        if (isset($result))
        {
          $result .= $sep;
        }
        else
        {
          $result = '';
        }

        if ($key === 'key_args')
        {
          array_unshift($element, self::l10n(array_shift($element))); // translate the key
          $result .= call_user_func_array('sprintf', $element);
        }
        else
        {
          $result .= self::l10n_args($element, $sep);
        }
      }
    }
    else
    {
      functions_html::fatal_error('l10n_args: Invalid arguments');
    }

    return $result;
  }

  /**
   * returns the corresponding value from $themeconf if existing or an empty string
   *
   * @param string $key
   * @return string
   */
  static function get_themeconf($key)
  {
    return $GLOBALS['template']->get_themeconf($key);
  }

  /**
   * Returns webmaster mail address depending on $conf['webmaster_id']
   *
   * @return string
   */
  static function get_webmaster_mail_address()
  {
    global $conf;

    $query = '
  SELECT '.$conf['user_fields']['email'].'
    FROM '.USERS_TABLE.'
    WHERE '.$conf['user_fields']['id'].' = '.$conf['webmaster_id'].'
  ;';
    list($email) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query($query));

    $email = functions_plugins::trigger_change('get_webmaster_mail_address', $email);

    return $email;
  }

  /**
   * Add configuration parameters from database to global $conf array
   *
   * @param string $condition SQL condition
   * @return void
   */
  static function load_conf_from_db($condition = '')
  {
    global $conf;

    $query = '
  SELECT param, value
  FROM '.CONFIG_TABLE.'
  '.(!empty($condition) ? 'WHERE '.$condition : '').'
  ;';
    $result = functions_mysqli::pwg_query($query);

    if ((functions_mysqli::pwg_db_num_rows($result) == 0) and !empty($condition))
    {
      functions_html::fatal_error('No configuration data');
    }

    while ($row = functions_mysqli::pwg_db_fetch_assoc($result))
    {
      $val = isset($row['value']) ? $row['value'] : '';
      // If the field is true or false, the variable is transformed into a boolean value.
      if ($val == 'true')
      {
        $val = true;
      }
      elseif ($val == 'false')
      {
        $val = false;
      }
      $conf[ $row['param'] ] = $val;
    }

    functions_plugins::trigger_notify('load_conf', $condition);
  }

  /**
   * Is the config table currentable writeable?
   *
   * @since 14
   *
   * @return boolean
   */
  static function pwg_is_dbconf_writeable()
  {
    list($param, $value) = array('pwg_is_dbconf_writeable_'.functions_session::generate_key(12), date('c').' '.functions_session::generate_key(20));

    self::conf_update_param($param, $value);
    list($dbvalue) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query('SELECT value FROM '.CONFIG_TABLE.' WHERE param = \''.$param.'\''));

    if ($dbvalue != $value)
    {
      return false;
    }

    self::conf_delete_param($param);
    return true;
  }

  /**
   * Add or update a config parameter
   *
   * @param string $param
   * @param string $value
   * @param boolean $updateGlobal update global *$conf* variable
   * @param callable $parser function to apply to the value before save in database
        (eg: serialize, json_encode) will not be applied to *$conf* if *$parser* is *true*
  */
  static function conf_update_param($param, $value, $updateGlobal=false, $parser=null)
  {
    if ($parser != null)
    {
      $dbValue = call_user_func($parser, $value);
    }
    else if (is_array($value) || is_object($value))
    {
      $dbValue = addslashes(serialize($value));
    }
    else
    {
      $dbValue = functions_mysqli::boolean_to_string($value);
    }

    $query = '
  INSERT INTO
    '.CONFIG_TABLE.' (param, value)
    VALUES(\''.$param.'\', \''.$dbValue.'\')
    ON DUPLICATE KEY UPDATE value = \''.$dbValue.'\'
  ;';

    functions_mysqli::pwg_query($query);

    if ($updateGlobal)
    {
      global $conf;
      $conf[$param] = $value;
    }
  }

  /**
   * Delete one or more config parameters
   * @since 2.6
   *
   * @param string|string[] $params
   */
  static function conf_delete_param($params)
  {
    global $conf;

    if (!is_array($params))
    {
      $params = array($params);
    }
    if (empty($params))
    {
      return;
    }

    $query = '
  DELETE FROM '.CONFIG_TABLE.'
    WHERE param IN(\''. implode('\',\'', $params) .'\')
  ;';
    functions_mysqli::pwg_query($query);

    foreach ($params as $param)
    {
      unset($conf[$param]);
    }
  }

  /**
   * Return a default value for a configuration parameter.
   * @since 2.8
   *
   * @param string $param the configuration value to be extracted (if it exists)
   * @param mixed $default_value the default value for the configuration value if it does not exist.
   *
   * @return mixed The configuration value if the variable exists, otherwise the default.
   */
  static function conf_get_param($param, $default_value=null)
  {
    global $conf;
    
    if (isset($conf[$param]))
    {
      return $conf[$param];
    }
    return $default_value;
  }


  /**
   * Apply *unserialize* on a value only if it is a string
   * @since 2.7
   *
   * @param array|string $value
   * @return array
   */
  static function safe_unserialize($value)
  {
    if (is_string($value))
    {
      return unserialize($value);
    }
    return $value;
  }

  /**
   * Apply *json_decode* on a value only if it is a string
   * @since 2.7
   *
   * @param array|string $value
   * @return array
   */
  static function safe_json_decode($value)
  {
    if (is_string($value))
    {
      return json_decode($value, true);
    }
    return $value;
  }

  /**
   * Prepends and appends strings at each value of the given array.
   *
   * @param array $array
   * @param string $prepend_str
   * @param string $append_str
   * @return array
   */
  static function prepend_append_array_items($array, $prepend_str, $append_str)
  {
    array_walk($array, function(&$value, $key) use($prepend_str,$append_str) { $value = "$prepend_str$value$append_str"; } );
    return $array;
  }

  /**
   * creates an simple hashmap based on a SQL query.
   * choose one to be the key, another one to be the value.
   * @deprecated 2.6
   *
   * @param string $query
   * @param string $keyname
   * @param string $valuename
   * @return array
   */
  static function simple_hash_from_query($query, $keyname, $valuename)
  {
    return functions_mysqli::query2array($query, $keyname, $valuename);
  }

  /**
   * creates an associative array based on a SQL query.
   * choose one to be the key
   * @deprecated 2.6
   *
   * @param string $query
   * @param string $keyname
   * @return array
   */
  static function hash_from_query($query, $keyname)
  {
    return functions_mysqli::query2array($query, $keyname);
  }

  /**
   * creates a numeric array based on a SQL query.
   * if _$fieldname_ is empty the returned value will be an array of arrays
   * if _$fieldname_ is provided the returned value will be a one dimension array
   * @deprecated 2.6
   *
   * @param string $query
   * @param string $fieldname
   * @return array
   */
  static function array_from_query($query, $fieldname=false)
  {
    if (false === $fieldname)
    {
      return functions_mysqli::query2array($query);
    }
    else
    {
      return functions_mysqli::query2array($query, null, $fieldname);
    }
  }

  /**
   * Return the basename of the current script.
   * The lowercase case filename of the current script without extension
   *
   * @return string
   */
  static function script_basename()
  {
    global $conf;

    foreach (array('SCRIPT_NAME', 'SCRIPT_FILENAME', 'PHP_SELF') as $value)
    {
      if (!empty($_SERVER[$value]))
      {
        $filename = strtolower($_SERVER[$value]);
        if ($conf['php_extension_in_urls'] and self::get_extension($filename)!=='php')
          continue;
        $basename = basename($filename, '.php');
        if (!empty($basename))
        {
          return $basename;
        }
      }
    }
    return '';
  }

  /**
   * Return $conf['filter_pages'] value for the current page
   *
   * @param string $value_name
   * @return mixed
   */
  static function get_filter_page_value($value_name)
  {
    global $conf;

    $page_name = self::script_basename();

    if (isset($conf['filter_pages'][$page_name][$value_name]))
    {
      return $conf['filter_pages'][$page_name][$value_name];
    }
    elseif (isset($conf['filter_pages']['default'][$value_name]))
    {
      return $conf['filter_pages']['default'][$value_name];
    }
    else
    {
      return null;
    }
  }

  /**
   * return the character set used by Piwigo
   * @return string
   */
  static function get_pwg_charset()
  {
    $pwg_charset = 'utf-8';
    if (defined('PWG_CHARSET'))
    {
      $pwg_charset = PWG_CHARSET;
    }
    return $pwg_charset;
  }

  /**
   * returns the parent (fallback) language of a language.
   * if _$lang_id_ is null it applies to the current language
   * @since 2.6
   *
   * @param string $lang_id
   * @return string|null
   */
  static function get_parent_language($lang_id=null)
  {
    if (empty($lang_id))
    {
      global $lang_info;
      return !empty($lang_info['parent']) ? $lang_info['parent'] : null;
    }
    else
    {
      $f = PHPWG_ROOT_PATH.'language/'.$lang_id.'/common.lang.php';
      if (file_exists($f))
      {
        include($f);
        return !empty($lang_info['parent']) ? $lang_info['parent'] : null;
      }
    }
    return null;
  }

  /**
   * includes a language file or returns the content of a language file
   *
   * tries to load in descending order:
   *   param language, user language, default language
   *
   * @param string $filename
   * @param string $dirname
   * @param mixed options can contain
   *     @option string language - language to load
   *     @option bool return - if true the file content is returned
   *     @option bool no_fallback - if true do not load default language
   *     @option bool|string force_fallback - force pre-loading of another language
   *        default language if *true* or specified language
   *     @option bool local - if true load file from local directory
   * @return boolean|string
   */
  static function load_language($filename, $dirname = '', $options = array())
  {
    global $user, $language_files;

    // keep trace of plugins loaded files for switch_lang_to() function
    if (!empty($dirname) && !empty($filename) && !@$options['return']
      && !isset($language_files[$dirname][$filename]))
    {
      $language_files[$dirname][$filename] = $options;
    }

    if (!@$options['return'])
    {
      $filename .= '.php';
    }
    if (empty($dirname))
    {
      $dirname = PHPWG_ROOT_PATH;
    }
    $dirname .= 'language/';

    $default_language = (defined('PHPWG_INSTALLED') and !defined('UPGRADES_PATH')) ?
        functions_user::get_default_language() : PHPWG_DEFAULT_LANGUAGE;

    // construct list of potential languages
    $languages = array();
    if (!empty($options['language']))
    { // explicit language
      $languages[] = $options['language'];
    }
    if (!empty($user['language']))
    { // use language
      $languages[] = $user['language'];
    }
    if (($parent = self::get_parent_language()) != null)
    { // parent language
      // this is only for when the "child" language is missing
      $languages[] = $parent;
    }
    if (isset($options['force_fallback']))
    { // fallback language
      // this is only for when the main language is missing
      if ($options['force_fallback'] === true)
      {
        $options['force_fallback'] = $default_language;
      }
      $languages[] = $options['force_fallback'];
    }
    if (!@$options['no_fallback'])
    { // default language
      $languages[] = $default_language;
    }

    $languages = array_unique($languages);

    // find first existing
    $source_file       = '';
    $selected_language = '';
    foreach ($languages as $language)
    {
      $f = @$options['local'] ?
        $dirname.$language.'.'.$filename:
        $dirname.$language.'/'.$filename;

      if (file_exists($f))
      {
        $selected_language = $language;
        $source_file = $f;
        break;
      }
    }
    
    if (!empty($source_file))
    {
      if (!@$options['return'])
      {
        // load forced fallback
        if (isset($options['force_fallback']) && $options['force_fallback'] != $selected_language)
        {
          @include(str_replace($selected_language, $options['force_fallback'], $source_file));
        }

        // load language content
        @include($source_file);
        $load_lang = @$lang;
        $load_lang_info = @$lang_info;

        // access already existing values
        global $lang, $lang_info;
        if (!isset($lang)) $lang = array();
        if (!isset($lang_info)) $lang_info = array();

        // load parent language content directly in global
        if (!empty($load_lang_info['parent']))
          $parent_language = $load_lang_info['parent'];
        else if (!empty($lang_info['parent']))
          $parent_language = $lang_info['parent'];
        else 
          $parent_language = null;

        if (!empty($parent_language) && $parent_language != $selected_language)
        {
          @include(str_replace($selected_language, $parent_language, $source_file));
        }

        // merge contents
        $lang = array_merge($lang, (array)$load_lang);
        $lang_info = array_merge($lang_info, (array)$load_lang_info);
        return true;
      }
      else
      {
        $content = @file_get_contents($source_file);
        //Note: target charset is always utf-8 $content = convert_charset($content, 'utf-8', $target_charset);
        return $content;
      }
    }

    return false;
  }

  /**
   * converts a string from a character set to another character set
   *
   * @param string $str
   * @param string $source_charset
   * @param string $dest_charset
   */
  static function convert_charset($str, $source_charset, $dest_charset)
  {
    if ($source_charset==$dest_charset)
      return $str;
    if ($source_charset=='iso-8859-1' and $dest_charset=='utf-8')
    {
      return utf8_encode($str);
    }
    if ($source_charset=='utf-8' and $dest_charset=='iso-8859-1')
    {
      return utf8_decode($str);
    }
    if (function_exists('iconv'))
    {
      return iconv($source_charset, $dest_charset.'//TRANSLIT', $str);
    }
    if (function_exists('mb_convert_encoding'))
    {
      return mb_convert_encoding( $str, $dest_charset, $source_charset );
    }
    return $str; // TODO
  }

  /**
   * makes sure a index.htm protects the directory from browser file listing
   *
   * @param string $dir
   */
  static function secure_directory($dir)
  {
    $file = $dir.'/index.htm';
    if (!file_exists($file))
    {
      @file_put_contents($file, 'Not allowed!');
    }
  }

  /**
   * returns a "secret key" that is to be sent back when a user posts a form
   *
   * @param int $valid_after_seconds - key validity start time from now
   * @param string $aditionnal_data_to_hash
   * @return string
   */
  static function get_ephemeral_key($valid_after_seconds, $aditionnal_data_to_hash = '')
  {
    global $conf;
    $time = round(microtime(true), 1);
    return $time.':'.$valid_after_seconds.':'
      .hash_hmac(
        'md5',
        $time.substr($_SERVER['REMOTE_ADDR'],0,5).$valid_after_seconds.$aditionnal_data_to_hash,
        $conf['secret_key']);
  }

  /**
   * verify a key sent back with a form
   *
   * @param string $key
   * @param string $aditionnal_data_to_hash
   * @return bool
   */
  static function verify_ephemeral_key($key, $aditionnal_data_to_hash = '')
  {
    global $conf;
    $time = microtime(true);
    $key = explode( ':', @$key );
    if ( count($key)!=3
      or $key[0]>$time-(float)$key[1] // page must have been retrieved more than X sec ago
      or $key[0]<$time-3600 // 60 minutes expiration
      or hash_hmac(
          'md5', $key[0].substr($_SERVER['REMOTE_ADDR'],0,5).$key[1].$aditionnal_data_to_hash, $conf['secret_key']
        ) != $key[2]
      )
    {
      return false;
    }
    return true;
  }

  /**
   * return an array which will be sent to template to display navigation bar
   *
   * @param string $url base url of all links
   * @param int $nb_elements
   * @param int $start
   * @param int $nb_element_page
   * @param bool $clean_url
   * @param string $param_name
   * @return array
   */
  static function create_navigation_bar($url, $nb_element, $start, $nb_element_page, $clean_url = false, $param_name='start')
  {
    global $conf;

    $navbar = array();
    $pages_around = $conf['paginate_pages_around'];
    $start_str = $clean_url ? '/'.$param_name.'-' : (strpos($url, '?')===false ? '?':'&amp;').$param_name.'=';

    if (!isset($start) or !is_numeric($start) or (is_numeric($start) and $start < 0))
    {
      $start = 0;
    }

    // navigation bar useful only if more than one page to display !
    if ($nb_element > $nb_element_page)
    {
      $url_start = $url.$start_str;

      $cur_page = $navbar['CURRENT_PAGE'] = $start / $nb_element_page + 1;
      $maximum = ceil($nb_element / $nb_element_page);

      $start = $nb_element_page * round( $start / $nb_element_page );
      $previous = $start - $nb_element_page;
      $next = $start + $nb_element_page;
      $last = ($maximum - 1) * $nb_element_page;

      // link to first page and previous page?
      if ($cur_page != 1)
      {
        $navbar['URL_FIRST'] = $url;
        $navbar['URL_PREV'] = $previous > 0 ? $url_start.$previous : $url;
      }
      // link on next page and last page?
      if ($cur_page != $maximum)
      {
        $navbar['URL_NEXT'] = $url_start.($next < $last ? $next : $last);
        $navbar['URL_LAST'] = $url_start.$last;
      }

      // pages to display
      $navbar['pages'] = array();
      $navbar['pages'][1] = $url;
      for ($i = max( floor($cur_page) - $pages_around , 2), $stop = min( ceil($cur_page) + $pages_around + 1, $maximum);
          $i < $stop; $i++)
      {
        $navbar['pages'][$i] = $url.$start_str.(($i - 1) * $nb_element_page);
      }
      $navbar['pages'][$maximum] = $url_start.$last;
      $navbar['NB_PAGE']=$maximum;
    }
    return $navbar;
  }

  /**
   * return an array which will be sent to template to display recent icon
   *
   * @param string $date
   * @param bool $is_child_date
   * @return array
   */
  static function get_icon($date, $is_child_date = false)
  {
    global $cache, $user;

    if (empty($date))
    {
      return false;
    }

    if (!isset($cache['get_icon']['title']))
    {
      $cache['get_icon']['title'] = self::l10n(
        'photos posted during the last %d days',
        $user['recent_period']
        );
    }

    $icon = array(
      'TITLE' => $cache['get_icon']['title'],
      'IS_CHILD_DATE' => $is_child_date,
      );

    if (isset($cache['get_icon'][$date]))
    {
      return $cache['get_icon'][$date] ? $icon : array();
    }

    if (!isset($cache['get_icon']['sql_recent_date']))
    {
      // Use MySql date in order to standardize all recent "actions/queries"
      $cache['get_icon']['sql_recent_date'] = functions_mysqli::pwg_db_get_recent_period($user['recent_period']);
    }

    $cache['get_icon'][$date] = $date > $cache['get_icon']['sql_recent_date'];

    return $cache['get_icon'][$date] ? $icon : array();
  }

  /**
   * check token comming from form posted or get params to prevent csrf attacks.
   * if pwg_token is empty action doesn't require token
   * else pwg_token is compare to server token
   *
   * @return void access denied if token given is not equal to server token
   */
  static function check_pwg_token()
  {
    if (!empty($_REQUEST['pwg_token']))
    {
      if (self::get_pwg_token() != $_REQUEST['pwg_token'])
      {
        functions_html::access_denied();
      }
    }
    else
    {
      functions_html::bad_request('missing token');
    }
  }

  /**
   * get pwg_token used to prevent csrf attacks
   *
   * @return string
   */
  static function get_pwg_token()
  {
    global $conf;

    return hash_hmac('md5', session_id(), $conf['secret_key']);
  }

  /*
  * breaks the script execution if the given value doesn't match the given
  * pattern. This should happen only during hacking attempts.
  *
  * @param string $param_name
  * @param array $param_array
  * @param boolean $is_array
  * @param string $pattern
  * @param boolean $mandatory
  */
  static function check_input_parameter($param_name, $param_array, $is_array, $pattern, $mandatory=false)
  {
    $param_value = null;
    if (isset($param_array[$param_name]))
    {
      $param_value = $param_array[$param_name];
    }

    // it's ok if the input parameter is null
    if (empty($param_value))
    {
      if ($mandatory)
      {
        functions_html::fatal_error('[Hacking attempt] the input parameter "'.$param_name.'" is not valid');
      }
      return true;
    }

    if ($is_array)
    {
      if (!is_array($param_value))
      {
        functions_html::fatal_error('[Hacking attempt] the input parameter "'.$param_name.'" should be an array');
      }

      foreach ($param_value as $key => $item_to_check)
      {
        if (!preg_match(PATTERN_ID, $key) or !preg_match($pattern, $item_to_check))
        {
          functions_html::fatal_error('[Hacking attempt] an item is not valid in input parameter "'.$param_name.'"');
        }
      }
    }
    else
    {
      if (!preg_match($pattern, $param_value))
      {
        functions_html::fatal_error('[Hacking attempt] the input parameter "'.$param_name.'" is not valid');
      }
    }
  }

  /**
   * get localized privacy level values
   *
   * @return string[]
   */
  static function get_privacy_level_options()
  {
    global $conf;

    $options = array();
    $label = '';
    foreach (array_reverse($conf['available_permission_levels']) as $level)
    {
      if (0 == $level)
      {
        $label = self::l10n('Everybody');
      }
      else
      {
        if (strlen($label))
        {
          $label .= ', ';
        }
        $label .= self::l10n( sprintf('Level %d', $level) );
      }
      $options[$level] = $label;
    }
    return $options;
  }


  /**
   * return the branch from the version. For example version 11.1.2 is on branch 11
   *
   * @param string $version
   * @return string
   */
  static function get_branch_from_version($version)
  {
    // the algorithm is a bit complicated to just retrieve the first digits before
    // the first ".". It's because before version 11.0.0, we used to take the 2 first
    // digits, ie version 2.2.4 was on branch 2.2
    return implode('.', array_slice(explode('.', $version), 0, 1));
  }

  /**
   * return the device type: mobile, tablet or desktop
   *
   * @return string
   */
  static function get_device()
  {
    $device = functions_session::pwg_get_session_var('device');

    if (is_null($device))
    {
      $uagent_obj = new uagent_info();
      if ($uagent_obj->DetectSmartphone())
      {
        $device = 'mobile';
      }
      elseif ($uagent_obj->DetectTierTablet())
      {
        $device = 'tablet';
      }
      else
      {
        $device = 'desktop';
      }
      functions_session::pwg_set_session_var('device', $device);
    }

    return $device;
  }

  /**
   * return true if mobile theme should be loaded
   *
   * @return bool
   */
  static function mobile_theme()
  {
    global $conf;

    if (empty($conf['mobile_theme']))
    {
      return false;
    }

    if (isset($_GET['mobile']))
    {
      $is_mobile_theme = functions_mysqli::get_boolean($_GET['mobile']);
      functions_session::pwg_set_session_var('mobile_theme', $is_mobile_theme);
    }
    else
    {
      $is_mobile_theme = functions_session::pwg_get_session_var('mobile_theme');
    }

    if (is_null($is_mobile_theme))
    {
      $is_mobile_theme = (self::get_device() == 'mobile');
      functions_session::pwg_set_session_var('mobile_theme', $is_mobile_theme);
    }

    return $is_mobile_theme;
  }

  /**
   * check url format
   *
   * @param string $url
   * @return bool
   */
  static function url_check_format($url)
  {
    if (strpos($url, '"') !== false)
    {
      return false;
    }

    if (strncmp($url, 'http://', 7) !== 0 and strncmp($url, 'https://', 8) !== 0)
    {
      return false;
    }

    return filter_var($url, FILTER_VALIDATE_URL)!==false;
  }

  /**
   * check email format
   *
   * @param string $mail_address
   * @return bool
   */
  static function email_check_format($mail_address)
  {
    return filter_var($mail_address, FILTER_VALIDATE_EMAIL)!==false;
  }

  /**
   * returns the number of available comments for the connected user
   *
   * @return int
   */
  static function get_nb_available_comments()
  {
    global $user;
    if (!isset($user['nb_available_comments']))
    {
      $where = array();
      if ( !functions_user::is_admin() )
        $where[] = 'validated=\'true\'';
      $where[] = functions_user::get_sql_condition_FandF
        (
          array
            (
              'forbidden_categories' => 'category_id',
              'forbidden_images' => 'ic.image_id'
            ),
          '', true
        );

      $query = '
  SELECT COUNT(DISTINCT(com.id))
    FROM '.IMAGE_CATEGORY_TABLE.' AS ic
      INNER JOIN '.COMMENTS_TABLE.' AS com
      ON ic.image_id = com.image_id
    WHERE '.implode('
      AND ', $where);
      list($user['nb_available_comments']) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query($query));

      functions_mysqli::single_update(USER_CACHE_TABLE,
        array('nb_available_comments'=>$user['nb_available_comments']),
        array('user_id'=>$user['id'])
        );
    }
    return $user['nb_available_comments'];
  }

  /**
   * Compare two versions with version_compare after having converted
   * single chars to their decimal values.
   * Needed because version_compare does not understand versions like '2.5.c'.
   * @since 2.6
   *
   * @param string $a
   * @param string $b
   * @param string $op
   */
  static function safe_version_compare($a, $b, $op=null)
  {
    $replace_chars   = function($m)  { return ord(strtolower($m[1])); };

    // add dot before groups of letters (version_compare does the same thing)
    $a = preg_replace('#([0-9]+)([a-z]+)#i', '$1.$2', $a);
    $b = preg_replace('#([0-9]+)([a-z]+)#i', '$1.$2', $b);

    // apply ord() to any single letter
    $a = preg_replace_callback('#\b([a-z]{1})\b#i', $replace_chars, $a);
    $b = preg_replace_callback('#\b([a-z]{1})\b#i', $replace_chars, $b);

    if (empty($op))
    {
      return version_compare($a, $b);
    }
    else
    {
      return version_compare($a, $b, $op);
    }
  }

  /**
   * Checks if the lounge needs to be emptied automatically.
   *
   * @since 12
   */
  static function check_lounge()
  {
    global $conf;

    if (!isset($conf['lounge_active']) or !$conf['lounge_active'])
    {
      return;
    }

    if (isset($_REQUEST['method']) and in_array($_REQUEST['method'], array('pwg.images.upload', 'pwg.images.uploadAsync')))
    {
      return;
    }

    // is the oldest photo in the lounge older than lounge maximum waiting time?
    $query = '
  SELECT
      image_id,
      date_available,
      NOW() AS dbnow
    FROM '.LOUNGE_TABLE.'
      JOIN '.IMAGES_TABLE.' ON image_id = id
    ORDER BY image_id ASC
    LIMIT 1
  ;';
    $voyagers = functions_mysqli::query2array($query);
    if (count($voyagers))
    {
      $voyager = $voyagers[0];
      $age = strtotime($voyager['dbnow']) - strtotime($voyager['date_available']);

      if ($age > $conf['lounge_max_duration'])
      {
        include_once(PHPWG_ROOT_PATH.'admin/inc/functions.php');
        \Piwigo\admin\inc\functions::empty_lounge();
      }
    }
  }

  static function guess_mime_type($ext)
  {
    switch ( strtolower($ext) )
    {
      case "jpe": case "jpeg":
      case "jpg": $ctype="image/jpeg"; break;
      case "png": $ctype="image/png"; break;
      case "gif": $ctype="image/gif"; break;
      case "webp": $ctype="image/webp"; break;
      case "tiff":
      case "tif": $ctype="image/tiff"; break;
      case "txt": $ctype="text/plain"; break;
      case "html":
      case "htm": $ctype="text/html"; break;
      case "xml": $ctype="text/xml"; break;
      case "pdf": $ctype="application/pdf"; break;
      case "zip": $ctype="application/zip"; break;
      case "ogg": $ctype="application/ogg"; break;
      default: $ctype="application/octet-stream";
    }
    return $ctype;
  }

  static function do_error( $code, $str )
  {
    functions_html::set_status_header( $code );
    echo $str ;
    exit();
  }

  static function cmpCat($a, $b) 
  {
    if ($a['rank'] == $b['rank']) 
    {
      return 0;
    }
    return ($a['rank'] < $b['rank']) ? -1 : 1;
  }
  
  static function assocToOrderedTree($assocT) 
  {
    global $nb_photos_in, $nb_sub_photos, $is_forbidden;
  
    $orderedTree = array();
  
    foreach($assocT as $cat) 
    {
      $orderedCat = array();
      $orderedCat['rank'] = $cat['cat']['rank'];
      $orderedCat['name'] = $cat['cat']['name'];
      $orderedCat['status'] = $cat['cat']['status'];
      $orderedCat['id'] = $cat['cat']['id'];
      $orderedCat['visible'] = $cat['cat']['visible'];
      $orderedCat['nb_images'] = isset($nb_photos_in[$cat['cat']['id']]) ? $nb_photos_in[$cat['cat']['id']] : 0;
      $orderedCat['last_updates'] = $cat['cat']['lastmodified'];
      $orderedCat['has_not_access'] = isset($is_forbidden[$cat['cat']['id']]);
      $orderedCat['nb_sub_photos'] = isset($nb_sub_photos[$cat['cat']['id']]) ? $nb_sub_photos[$cat['cat']['id']] : 0;
      if (isset($cat['children'])) 
      {
        //Does not update when moving a node
        $orderedCat['nb_subcats'] = count($cat['children']);
        $orderedCat['children'] = self::assocToOrderedTree($cat['children']);
      }
      array_push($orderedTree, $orderedCat);
    }
    usort($orderedTree, '\Piwigo\inc\functions::cmpCat');
    return $orderedTree;
  }
  
  static function get_categories_ref_date($ids, $field='date_available', $minmax='max')
  {
    // we need to work on the whole tree under each category, even if we don't
    // want to sort sub categories
    $category_ids = functions_category::get_subcat_ids($ids);
  
    // search for the reference date of each album
    $query = '
  SELECT
      category_id,
      '.$minmax.'('.$field.') as ref_date
    FROM '.IMAGE_CATEGORY_TABLE.'
      JOIN '.IMAGES_TABLE.' ON image_id = id
    WHERE category_id IN ('.implode(',', $category_ids).')
    GROUP BY category_id
  ;';
    $ref_dates = functions_mysqli::query2array($query, 'category_id', 'ref_date');
  
    // the iterate on all albums (having a ref_date or not) to find the
    // reference_date, with a search on sub-albums
    $query = '
  SELECT
      id,
      uppercats
    FROM '.CATEGORIES_TABLE.'
    WHERE id IN ('.implode(',', $category_ids).')
  ;';
    $uppercats_of = functions_mysqli::query2array($query, 'id', 'uppercats');
  
    foreach (array_keys($uppercats_of) as $cat_id)
    {
      // find the subcats
      $subcat_ids = array();
      
      foreach ($uppercats_of as $id => $uppercats)
      {
        if (preg_match('/(^|,)'.$cat_id.'(,|$)/', $uppercats))
        {
          $subcat_ids[] = $id;
        }
      }
  
      $to_compare = array();
      foreach ($subcat_ids as $id)
      {
        if (isset($ref_dates[$id]))
        {
          $to_compare[] = $ref_dates[$id];
        }
      }
  
      if (count($to_compare) > 0)
      {
        $ref_dates[$cat_id] = 'max' == $minmax ? max($to_compare) : min($to_compare);
      }
      else
      {
        $ref_dates[$cat_id] = null;
      }
    }
  
    // only return the list of $ids, not the sub-categories
    $return = array();
    foreach ($ids as $id)
    {
      $return[$id] = $ref_dates[$id];
    }
    
    return $return;
  }

  static function UC_name_compare($a, $b)
  {
    return strcmp(strtolower($a['NAME']), strtolower($b['NAME']));
  }

  // get_complete_dir returns the concatenation of get_site_url and
  // get_local_dir
  // Example : "pets > rex > 1_year_old" is on the the same site as the
  // Piwigo files and this category has 22 for identifier
  // get_complete_dir(22) returns "./galleries/pets/rex/1_year_old/"
  static function get_complete_dir( $category_id )
  {
    return self::get_site_url($category_id).self::get_local_dir($category_id);
  }

  // get_local_dir returns an array with complete path without the site url
  // Example : "pets > rex > 1_year_old" is on the the same site as the
  // Piwigo files and this category has 22 for identifier
  // get_local_dir(22) returns "pets/rex/1_year_old/"
  static function get_local_dir( $category_id )
  {
    global $page;

    $uppercats = '';
    $local_dir = '';

    if ( isset( $page['plain_structure'][$category_id]['uppercats'] ) )
    {
      $uppercats = $page['plain_structure'][$category_id]['uppercats'];
    }
    else
    {
      $query = 'SELECT uppercats';
      $query.= ' FROM '.CATEGORIES_TABLE.' WHERE id = '.$category_id;
      $query.= ';';
      $row = functions_mysqli::pwg_db_fetch_assoc( functions_mysqli::pwg_query( $query ) );
      $uppercats = $row['uppercats'];
    }

    $upper_array = explode( ',', $uppercats );

    $database_dirs = array();
    $query = 'SELECT id,dir';
    $query.= ' FROM '.CATEGORIES_TABLE.' WHERE id IN ('.$uppercats.')';
    $query.= ';';
    $result = functions_mysqli::pwg_query( $query );
    while( $row = functions_mysqli::pwg_db_fetch_assoc( $result ) )
    {
      $database_dirs[$row['id']] = $row['dir'];
    }
    foreach ($upper_array as $id)
    {
      $local_dir.= $database_dirs[$id].'/';
    }

    return $local_dir;
  }

  // retrieving the site url : "http://domain.com/gallery/" or
  // simply "./galleries/"
  static function get_site_url($category_id)
  {
    global $page;

    $query = '
  SELECT galleries_url
    FROM '.SITES_TABLE.' AS s,'.CATEGORIES_TABLE.' AS c
    WHERE s.id = c.site_id
      AND c.id = '.$category_id.'
  ;';
    $row = functions_mysqli::pwg_db_fetch_assoc(functions_mysqli::pwg_query($query));
    return $row['galleries_url'];
  }

  static function get_min_local_dir($local_dir)
  {
    $full_dir = explode('/', $local_dir);
    if (count($full_dir) <= 3)
    {
      return $local_dir;
    }
    else
    {
      $start = $full_dir[0] . '/' . $full_dir[1];
      $end = end($full_dir);
      $concat = $start . '/&hellip;/' . $end;
      return $concat;
    }
  }

  static function abs_fn_cmp($a, $b)
  {
    return abs($a)-abs($b);
  }
  
  static function make_consecutive( &$orders, $step=50 )
  {
    uasort( $orders, '\Piwigo\inc\functions::abs_fn_cmp' );
    $crt = 1;
    foreach( $orders as $id=>$pos)
    {
      $orders[$id] = $step * ($pos<0 ? -$crt : $crt);
      $crt++;
    }
  }

  /*
  * Do timeout treatment in order to finish to send mails
  *
  * @param $post_keyname: key of check_key post array
  * @param check_key_treated: array of check_key treated
  * @return none
  */
  static function do_timeout_treatment($post_keyname, $check_key_treated = array())
  {
    global $env_nbm, $base_url, $page, $must_repost;

    if ($env_nbm['is_sendmail_timeout'])
    {
      if (isset($_POST[$post_keyname]))
      {
        $post_count = count($_POST[$post_keyname]);
        $treated_count = count($check_key_treated);
        if ($treated_count != 0)
        {
          $time_refresh = ceil((self::get_moment() - $env_nbm['start_time']) * $post_count / $treated_count);
        }
        else
        {
          $time_refresh = 0;
        }
        $_POST[$post_keyname] = array_diff($_POST[$post_keyname], $check_key_treated);

        $must_repost = true;
        $page['errors'][] = self::l10n_dec(
          'Execution time is out, treatment must be continue [Estimated time: %d second].',
          'Execution time is out, treatment must be continue [Estimated time: %d seconds].',
          $time_refresh
          );
      }
    }

  }

  /*
  * Get the authorized_status for each tab
  * return corresponding status
  */
  static function get_tab_status($mode)
  {
    $result = ACCESS_WEBMASTER;
    switch ($mode)
    {
      case 'param':
      case 'subscribe':
        $result = ACCESS_WEBMASTER;
        break;
      case 'send':
        $result = ACCESS_ADMINISTRATOR;
        break;
      default:
        $result = ACCESS_WEBMASTER;
        break;
    }
    return $result;
  }

  /*
  * Inserting News users
  */
  static function insert_new_data_user_mail_notification()
  {
    global $conf, $page, $env_nbm;

    // Set null mail_address empty
    $query = '
  update
    '.USERS_TABLE.'
  set
    '.$conf['user_fields']['email'].' = null
  where
    trim('.$conf['user_fields']['email'].') = \'\';';
    functions_mysqli::pwg_query($query);

    // null mail_address are not selected in the list
    $query = '
  select
    u.'.$conf['user_fields']['id'].' as user_id,
    u.'.$conf['user_fields']['username'].' as username,
    u.'.$conf['user_fields']['email'].' as mail_address
  from
    '.USERS_TABLE.' as u left join '.USER_MAIL_NOTIFICATION_TABLE.' as m on u.'.$conf['user_fields']['id'].' = m.user_id
  where
    u.'.$conf['user_fields']['email'].' is not null and
    m.user_id is null
  order by
    user_id;';

    $result = functions_mysqli::pwg_query($query);

    if (functions_mysqli::pwg_db_num_rows($result) > 0)
    {
      $inserts = array();
      $check_key_list = array();

      while ($nbm_user = functions_mysqli::pwg_db_fetch_assoc($result))
      {
        // Calculate key
        $nbm_user['check_key'] = functions_notification_by_mail::find_available_check_key();

        // Save key
        $check_key_list[] = $nbm_user['check_key'];

        // Insert new nbm_users
        $inserts[] = array(
          'user_id' => $nbm_user['user_id'],
          'check_key' => $nbm_user['check_key'],
          'enabled' => 'false' // By default if false, set to true with specific functions
          );

        $page['infos'][] = self::l10n(
          'User %s [%s] added.',
          stripslashes($nbm_user['username']),
          $nbm_user['mail_address']
          );
      }

      // Insert new nbm_users
      functions_mysqli::mass_inserts(USER_MAIL_NOTIFICATION_TABLE, array('user_id', 'check_key', 'enabled'), $inserts);
      // Update field enabled with specific function
      $check_key_treated = functions_notification_by_mail::do_subscribe_unsubscribe_notification_by_mail
      (
        true,
        $conf['nbm_default_value_user_enabled'],
        $check_key_list
      );

      // On timeout simulate like tabsheet send
      if ($env_nbm['is_sendmail_timeout'])
      {
        $quoted_check_key_list = functions_notification_by_mail::quote_check_key_list(array_diff($check_key_list, $check_key_treated));
        if (count($quoted_check_key_list) != 0 )
        {
          $query = 'delete from '.USER_MAIL_NOTIFICATION_TABLE.' where check_key in ('.implode(",", $quoted_check_key_list).');';
          $result = functions_mysqli::pwg_query($query);

          self::redirect($base_url.functions_url::get_query_string_diff(array(), false), self::l10n('Operation in progress')."\n".self::l10n('Please wait...'));
        }
      }
    }
  }

  /*
  * Apply global functions to mail content
  * return customize mail content rendered
  */
  static function render_global_customize_mail_content($customize_mail_content)
  {
    global $conf;

    if ($conf['nbm_send_html_mail'] and !(strpos($customize_mail_content, '<') === 0))
    {
      // On HTML mail, detects if the content are HTML format.
      // If it's plain text format, convert content to readable HTML
      return nl2br(htmlspecialchars($customize_mail_content));
    }
    else
    {
      return $customize_mail_content;
    }
  }

  /*
  * Send mail for notification to all users
  * Return list of "selected" users for 'list_to_send'
  * Return list of "treated" check_key for 'send'
  */
  static function do_action_send_mail_notification($action = 'list_to_send', $check_key_list = array(), $customize_mail_content = '')
  {
    global $conf, $page, $user, $lang_info, $lang, $env_nbm;
    $return_list = array();

    if (in_array($action, array('list_to_send', 'send')))
    {
      list($dbnow) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query('SELECT NOW();'));

      $is_action_send = ($action == 'send');

      // disabled and null mail_address are not selected in the list
      $data_users = functions_notification_by_mail::get_user_notifications('send', $check_key_list);

      // List all if it's define on options or on timeout
      $is_list_all_without_test = ($env_nbm['is_sendmail_timeout'] or $conf['nbm_list_all_enabled_users_to_send']);

      // Check if exist news to list user or send mails
      if ((!$is_list_all_without_test) or ($is_action_send))
      {
        if (count($data_users) > 0)
        {
          $datas = array();

          if (!isset($customize_mail_content))
          {
            $customize_mail_content = $conf['nbm_complementary_mail_content'];
          }

          $customize_mail_content = 
            functions_plugins::trigger_change('nbm_render_global_customize_mail_content', $customize_mail_content);


          // Prepare message after change language
          if ($is_action_send)
          {
            $msg_break_timeout = self::l10n('Time to send mail is limited. Others mails are skipped.');
          }
          else
          {
            $msg_break_timeout = self::l10n('Prepared time for list of users to send mail is limited. Others users are not listed.');
          }

          // Begin nbm users environment
          functions_notification_by_mail::begin_users_env_nbm($is_action_send);

          foreach ($data_users as $nbm_user)
          {
            if ((!$is_action_send) and functions_notification_by_mail::check_sendmail_timeout())
            {
              // Stop fill list on 'list_to_send', if the quota is override
              $page['infos'][] = $msg_break_timeout;
              break;
            }
            if (($is_action_send) and functions_notification_by_mail::check_sendmail_timeout())
            {
              // Stop fill list on 'send', if the quota is override
              $page['errors'][] = $msg_break_timeout;
              break;
            }

            // set env nbm user
            functions_notification_by_mail::set_user_on_env_nbm($nbm_user, $is_action_send);

            if ($is_action_send)
            {
              $auth = null;
              $add_url_params = array();
              
              $auth_key = functions_user::create_user_auth_key($nbm_user['user_id'], $nbm_user['status']);
              
              if ($auth_key !== false)
              {
                $auth = $auth_key['auth_key'];
                $add_url_params['auth'] = $auth;
              }
              
              functions_url::set_make_full_url();
              // Fill return list of "treated" check_key for 'send'
              $return_list[] = $nbm_user['check_key'];

              if ($conf['nbm_send_detailed_content'])
              {
                $news = functions_notification::news($nbm_user['last_send'], $dbnow, false, $conf['nbm_send_html_mail'], $auth);
                $exist_data = count($news) > 0;
              }
              else
              {
                $exist_data = functions_notification::news_exists($nbm_user['last_send'], $dbnow);
              }

              if ($exist_data)
              {
                $subject = '['.$conf['gallery_title'].'] '.self::l10n('New photos added');

                // Assign current var for nbm mail
                functions_notification_by_mail::assign_vars_nbm_mail_content($nbm_user);

                if (!is_null($nbm_user['last_send']))
                {
                  $env_nbm['mail_template']->assign
                  (
                    'content_new_elements_between',
                    array
                    (
                      'DATE_BETWEEN_1' => $nbm_user['last_send'],
                      'DATE_BETWEEN_2' => $dbnow,
                    )
                  );
                }
                else
                {
                  $env_nbm['mail_template']->assign
                  (
                    'content_new_elements_single',
                    array
                    (
                      'DATE_SINGLE' => $dbnow,
                    )
                  );
                }

                if ($conf['nbm_send_detailed_content'])
                {
                  $env_nbm['mail_template']->assign('global_new_lines', $news);
                }

                $nbm_user_customize_mail_content = 
                  functions_plugins::trigger_change('nbm_render_user_customize_mail_content',
                    $customize_mail_content, $nbm_user);
                if (!empty($nbm_user_customize_mail_content))
                {
                  $env_nbm['mail_template']->assign
                  (
                    'custom_mail_content', $nbm_user_customize_mail_content
                  );
                }

                if ($conf['nbm_send_html_mail'] and $conf['nbm_send_recent_post_dates'])
                {
                  $recent_post_dates = functions_notification::get_recent_post_dates_array(
                    $conf['recent_post_dates']['NBM']);
                  foreach ($recent_post_dates as $date_detail)
                  {
                    $env_nbm['mail_template']->append
                    (
                      'recent_posts',
                      array
                      (
                        'TITLE' => functions_notification::get_title_recent_post_date($date_detail),
                        'HTML_DATA' => functions_notification::get_html_description_recent_post_date($date_detail, $auth)
                      )
                    );
                  }
                }

                $env_nbm['mail_template']->assign
                (
                  array
                  (
                    'GOTO_GALLERY_TITLE' => $conf['gallery_title'],
                    'GOTO_GALLERY_URL' => functions_url::add_url_params(functions_url::get_gallery_home_url(), $add_url_params),
                    'SEND_AS_NAME'      => $env_nbm['send_as_name'],
                  )
                );
                
                $ret = functions_mail::pwg_mail(
                  array(
                    'name' => stripslashes($nbm_user['username']),
                    'email' => $nbm_user['mail_address'],
                    ),
                  array(
                    'from' => $env_nbm['send_as_mail_formated'],
                    'subject' => $subject,
                    'email_format' => $env_nbm['email_format'],
                    'content' => $env_nbm['mail_template']->parse('notification_by_mail', true),
                    'content_format' => $env_nbm['email_format'],
                    'auth_key' => $auth,
                    )
                  );

                if ($ret)
                {
                  functions_notification_by_mail::inc_mail_sent_success($nbm_user);

                  $datas[] = array(
                    'user_id' => $nbm_user['user_id'],
                    'last_send' => $dbnow
                    );
                }
                else
                {
                  functions_notification_by_mail::inc_mail_sent_failed($nbm_user);
                }

                functions_url::unset_make_full_url();
              }
            }
            else
            {
              if (functions_notification::news_exists($nbm_user['last_send'], $dbnow))
              {
                // Fill return list of "selected" users for 'list_to_send'
                $return_list[] = $nbm_user;
              }
            }

            // unset env nbm user
            functions_notification_by_mail::unset_user_on_env_nbm();
          }

          // Restore nbm environment
          functions_notification_by_mail::end_users_env_nbm();

          if ($is_action_send)
          {
            functions_mysqli::mass_updates(
              USER_MAIL_NOTIFICATION_TABLE,
              array(
                'primary' => array('user_id'),
                'update' => array('last_send')
              ),
              $datas
              );

            functions_notification_by_mail::display_counter_info();
          }
        }
        else
        {
          if ($is_action_send)
          {
            $page['errors'][] = self::l10n('No user to send notifications by mail.');
          }
        }
      }
      else
      {
        // Quick List, don't check news
        // Fill return list of "selected" users for 'list_to_send'
        $return_list = $data_users;
      }
    }

    // Return list of "selected" users for 'list_to_send'
    // Return list of "treated" check_key for 'send'
    return $return_list;
  }

  static function parse_sort_variables(
    $sortable_by, $default_field,
    $get_param, $get_rejects,
    $template_var,
    $anchor = '' )
  {
  global $template;
  
  $url_components = parse_url( $_SERVER['REQUEST_URI'] );
  
  $base_url = $url_components['path'];
  
  parse_str($url_components['query'], $vars);
  $is_first = true;
  foreach ($vars as $key => $value)
  {
    if (!in_array($key, $get_rejects) and $key!=$get_param)
    {
      $base_url .= $is_first ? '?' : '&amp;';
      $is_first = false;
  
      if (!in_array($key, array('page', 'psf', 'dpsf', 'pwg_token')))
      {
        functions_html::fatal_error('unexpected URL get key');
      }
  
      $base_url .= urlencode($key).'='.urlencode($value);
    }
  }
  
  $ret = array();
  foreach( $sortable_by as $field)
  {
    $url = $base_url;
    $disp = '↓'; // TODO: an small image is better
  
    if ( $field !== @$_GET[$get_param] )
    {
      if ( !isset($default_field) or $default_field!=$field )
      { // the first should be the default
        $url = functions_url::add_url_params($url, array($get_param=>$field) );
      }
      elseif (isset($default_field) and !isset($_GET[$get_param]) )
      {
        $ret[] = $field;
        $disp = '<em>'.$disp.'</em>';
      }
    }
    else
    {
      $ret[] = $field;
      $disp = '<em>'.$disp.'</em>';
    }
    if ( isset($template_var) )
    {
      $template->assign( $template_var.strtoupper($field),
            '<a href="'.$url.$anchor.'" title="'.self::l10n('Sort order').'">'.$disp.'</a>'
         );
    }
  }
  return $ret;
  }

  static function avg_compare($a, $b)
  {
    $d = $a['avg'] - $b['avg'];
    return ($d==0) ? 0 : ($d<0 ? -1 : 1);
  }
  
  static function count_compare($a, $b)
  {
    $d = $a['count'] - $b['count'];
    return ($d==0) ? 0 : ($d<0 ? -1 : 1);
  }
  
  static function cv_compare($a, $b)
  {
    $d = $b['cv'] - $a['cv']; //desc
    return ($d==0) ? 0 : ($d<0 ? -1 : 1);
  }
  
  static function consensus_dev_compare($a, $b)
  {
    $d = $b['cd'] - $a['cd']; //desc
    return ($d==0) ? 0 : ($d<0 ? -1 : 1);
  }
  
  static function last_rate_compare($a, $b)
  {
    return -strcmp( $a['last_date'], $b['last_date']);
  }

  //Get the last unit of time for years, months, days and hours
  static function get_last($last_number=60, $type='year')
  {
    $query = '
  SELECT
      year,
      month,
      day,
      hour,
      nb_pages
    FROM '.HISTORY_SUMMARY_TABLE;

    if ($type === 'hour')
    {
      $query.= '
    WHERE year IS NOT NULL
      AND month IS NOT NULL
      AND day IS NOT NULL
      AND hour IS NOT NULL
    ORDER BY
      year DESC,
      month DESC,
      day DESC,
      hour DESC
    LIMIT '.$last_number.'
  ;';
    }
    elseif ($type === 'day')
    {
      $query.= '
    WHERE year IS NOT NULL
      AND month IS NOT NULL
      AND day IS NOT NULL
      AND hour IS NULL
    ORDER BY
      year DESC,
      month DESC,
      day DESC
    LIMIT '.$last_number.'
  ;';
    }
    elseif ($type === 'month')
    {
      $query.= '
    WHERE year IS NOT NULL
      AND month IS NOT NULL
      AND day IS NULL
    ORDER BY
      year DESC,
      month DESC
    LIMIT '.$last_number.'
  ;';
    }
    else
    {
      $query.= '
    WHERE year IS NOT NULL
      AND month IS NULL
    ORDER BY
      year DESC
    LIMIT '.$last_number.'
  ;';
    }

    $result = functions_mysqli::pwg_query($query);

    $output = array();
    while ($row = functions_mysqli::pwg_db_fetch_assoc($result))
    {
      $output[] = $row;
    }

    return $output;
  }

  static function get_month_of_last_years ($last = 'all') 
  {

    $query = '
  SELECT
    year,
    month,
    day,
    hour,
    nb_pages
  FROM '.HISTORY_SUMMARY_TABLE.'
  WHERE month IS NOT NULL
    AND day IS NULL
  ORDER BY
    year DESC,
    month DESC';

    if ($last !== 'all') 
    {
      $date = new DateTime();
      $limit = ($last - 1)*12+$date->format('n') - 1;
      $query .= 
  ' LIMIT '.$limit;
      $result = functions_mysqli::query2array($query.';');
      $lastDate = $date->sub(new DateInterval('P'.($last - 1).'Y'.($date->format('n') - 1).'M'));
      return self::set_missing_values('month', $result, $lastDate, new DateTime());
    }

    if (count(functions_mysqli::query2array($query.';')) > 1 ) 
    {
      return self::set_missing_values('month', functions_mysqli::query2array($query.';'));
    } else {
      $last_year_date = new DateTime();
      return self::set_missing_values(
        'month', 
        functions_mysqli::query2array($query.';'),
        $last_year_date->sub(new DateInterval('P1Y')),
        new DateTime()
      );
    }
  }

  static function get_month_stats() 
  {
    $result = array();
    $date = new DateTime();
    $date_last_month = clone $date;
    $date_last_year = clone $date;
    $months = array();

    $date_last_month->sub(new DateInterval('P1M'));
    $date_last_year->sub(new DateInterval('P1Y'));
    $query = '
  SELECT
    year,
    month,
    day,
    hour,
    nb_pages
  FROM '.HISTORY_SUMMARY_TABLE.'
  WHERE 
    (
      (year = '.$date->format('Y').' AND month = '.$date->format('n').')
      OR (year = '.$date_last_month->format('Y').' AND month = '.$date_last_month->format('n').')
      OR (year = '.$date_last_year->format('Y').' AND month = '.$date_last_year->format('n').')
    )
    AND day IS NOT NULL
    AND hour IS NULL
  ORDER BY
    year DESC,
    month DESC
  ;';

    foreach (functions_mysqli::query2array($query) as $value) 
    {
      $date = self::get_date_object($value);
      @$months[$date->format('Y/m/1')][] = $value;
    }

    $actual_date = new DateTime();
    if (!isset($months[$actual_date->format('Y/m/1')])) 
    {
      @$months[$actual_date->format('Y/m/1')][] = array(
        'year' => $actual_date->format('Y'),
        'month'=> $actual_date->format('n'),
        'day'=> null,
        'hour'=> null,
        'nb_pages' => 0
      );
    }

    foreach ($months as $key => $val) 
    {
      $lastDate = new DateTime($key);
      $lastDate = $lastDate->add(new DateInterval('P1M'));
      $lastDate = $lastDate->sub(new DateInterval('P1D'));
      if ($lastDate > new DateTime()) 
      {
        $lastDate = new DateTime();
      }
      $result['month'][] = self::set_missing_values('day',$val, new DateTime($key), $lastDate);
    }

    $query = '
  SELECT
    AVG(nb_pages)
  FROM '.HISTORY_SUMMARY_TABLE.'
  WHERE 
    (
    year = '.$date->format('Y').' OR
    (year = '.($date->format('Y')-1).' and month > '.$date->format('n').')
    ) 
    AND day IS NOT NULL
    AND hour IS NULL
  ORDER BY
    year DESC,
    month DESC
  ;';

    list($result['avg']) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query($query));
    
    return $result;
  }

  // +-----------------------------------------------------------------------+
  // | Set missing rows to 0                                                 |
  // +-----------------------------------------------------------------------+

  static function set_missing_values($unit, $data, $firstDate = null, $lastDate = null)
  {
    $limit = count($data);
    $result = array();
    
    if ($firstDate == null) 
    {
      $date = self::get_date_object($data[count($data) - 1]);
    } else {
      $date = $firstDate;
    }
    if ($lastDate == null) 
    {
      $date_end = self::get_date_object($data[0]);
    } else {
      $date_end = $lastDate;
    }

    //Declare variable according the unit
    if ($unit == 'year') 
    {
      $date_format = 'Y';
      $date_add = 'P1Y';
    } 
    else if ($unit == 'month') 
    {
      $date_format = 'Y-m';
      $date_add = 'P1M';
    } 
    else if ($unit == 'day') 
    {
      $date_format = 'Y-m-d';
      $date_add = 'P1D';
    } 
    else if ($unit == 'hour') 
    {
      $date_format = 'Y-m-d\TH:00';
      $date_add = 'PT1H';
    }

    //Fill an empty array with all the dates
    while ($date <= $date_end) {
      $result[$date->format($date_format)] = 0;
      $date->add(new DateInterval($date_add));
    }

    //Overload with database rows
    foreach ($data as $value) 
    {
      $str = self::get_date_object($value)->format($date_format);
      if (isset($result[$str])) 
      {
        $result[$str] += $value['nb_pages'];
      }
    }

    return $result;
  }

  //Get a DateTime object for a database row
  static function get_date_object($row) 
  {
    $date_string = $row['year'];
      if ($row['month'] != null) 
      {
        $date_string = $date_string.'-'.$row['month'] ;
        if ($row['day'] != null) 
        {
          $date_string = $date_string.'-'.$row['day'];
          if ($row['hour'] != null) 
          {
            $date_string = $date_string.' '.$row['hour'].':00';
          }
        }
      } 
      else 
      {
        $date_string .= '-1';
      }

    return new DateTime($date_string);
  }

  /**
   * creates a Unix timestamp (number of seconds since 1970-01-01 00:00:00
   * GMT) from a MySQL datetime format (2005-07-14 23:01:37)
   *
   * @param string mysql datetime format
   * @return int timestamp
   */
  static function datetime_to_ts($datetime)
  {
    return strtotime($datetime);
  }

  /**
   * creates an ISO 8601 format date (2003-01-20T18:05:41+04:00) from Unix
   * timestamp (number of seconds since 1970-01-01 00:00:00 GMT)
   *
   * function copied from Dotclear project http://dotclear.net
   *
   * @param int timestamp
   * @return string ISO 8601 date format
   */
  static function ts_to_iso8601($ts)
  {
    $tz = date('O',$ts);
    $tz = substr($tz, 0, -2).':'.substr($tz, -2);
    return date('Y-m-d\\TH:i:s',$ts).$tz;
  }

  static function ierror($msg, $code)
  {
    global $logger;
    if ($code==301 || $code==302)
    {
      if (ob_get_length () !== FALSE)
      {
        ob_clean();
      }
      // default url is on html format
      $url = html_entity_decode($msg);
      $logger->debug($code . ' ' . $url, array(
        'url' => $_SERVER['REQUEST_URI'],
        ));
      header('Request-URI: '.$url);
      header('Content-Location: '.$url);
      header('Location: '.$url);
      exit;
    }
    if ($code>=400)
    {
      $protocol = $_SERVER["SERVER_PROTOCOL"];
      if ( ('HTTP/1.1' != $protocol) && ('HTTP/1.0' != $protocol) )
        $protocol = 'HTTP/1.0';
  
      header( "$protocol $code $msg", true, $code );
    }
    //todo improve
    echo $msg;
    $logger->error($code . ' ' . $msg, array(
        'url' => $_SERVER['REQUEST_URI'],
        ));
    exit;
  }
  
  static function time_step( &$step )
  {
    $tmp = $step;
    $step = microtime(true);
    return intval(1000*($step - $tmp));
  }
  
  static function url_to_size($s)
  {
    $pos = strpos($s, 'x');
    if ($pos===false)
    {
      return array((int)$s, (int)$s);
    }
    return array((int)substr($s,0,$pos), (int)substr($s,$pos+1));
  }
  
  static function parse_custom_params($tokens)
  {
    if (count($tokens)<1)
      self::ierror('Empty array while parsing Sizing', 400);
  
    $crop = 0;
    $min_size = null;
  
    $token = array_shift($tokens);
    if ($token[0]=='s')
    {
      $size = self::url_to_size( substr($token,1) );
    }
    elseif ($token[0]=='e')
    {
      $crop = 1;
      $size = $min_size = self::url_to_size( substr($token,1) );
    }
    else
    {
      $size = self::url_to_size( $token );
      if (count($tokens)<2)
        self::ierror('Sizing arr', 400);
  
      $token = array_shift($tokens);
      $crop = derivative_params::char_to_fraction($token);
  
      $token = array_shift($tokens);
      $min_size = self::url_to_size( $token );
    }
    return new DerivativeParams( new SizingParams($size, $crop, $min_size) );
  }
  
  static function parse_request()
  {
    global $conf, $page;
  
    if ( $conf['question_mark_in_urls']==false and
         isset($_SERVER["PATH_INFO"]) and !empty($_SERVER["PATH_INFO"]) )
    {
      $req = $_SERVER["PATH_INFO"];
      $req = str_replace('//', '/', $req);
      $path_count = count( explode('/', $req) );
      $page['root_path'] = PHPWG_ROOT_PATH.str_repeat('../', $path_count-1);
    }
    else
    {
      $req = $_SERVER["QUERY_STRING"];
      if ($pos=strpos($req, '&'))
      {
        $req = substr($req, 0, $pos);
      }
      $req = rawurldecode($req);
      /*foreach (array_keys($_GET) as $keynum => $key)
      {
        $req = $key;
        break;
      }*/
      $page['root_path'] = PHPWG_ROOT_PATH;
    }
  
    $req = ltrim($req, '/');
  
    foreach (preg_split('#/+#', $req) as $token)
    {
      preg_match($conf['sync_chars_regex'], $token) or self::ierror('Invalid chars in request', 400);
    }
  
    $page['derivative_path'] = PHPWG_ROOT_PATH.PWG_DERIVATIVE_DIR.$req;
  
    $pos = strrpos($req, '.');
    $pos!== false || self::ierror('Missing .', 400);
    $ext = substr($req, $pos);
    $page['derivative_ext'] = $ext;
    $req = substr($req, 0, $pos);
  
    $pos = strrpos($req, '-');
    $pos!== false || self::ierror('Missing -', 400);
    $deriv = substr($req, $pos+1);
    $req = substr($req, 0, $pos);
  
    $deriv = explode('_', $deriv);
    foreach (ImageStdParams::get_defined_type_map() as $type => $params)
    {
      if ( derivative_params::derivative_to_url($type) == $deriv[0])
      {
        $page['derivative_type'] = $type;
        $page['derivative_params'] = $params;
        break;
      }
    }
  
    if (!isset($page['derivative_type']))
    {
      if (derivative_params::derivative_to_url(derivative_std_params::IMG_CUSTOM) == $deriv[0])
      {
        $page['derivative_type'] = derivative_std_params::IMG_CUSTOM;
      }
      else
      {
        self::ierror('Unknown parsing type', 400);
      }
    }
    array_shift($deriv);
  
    if ($page['derivative_type'] == derivative_std_params::IMG_CUSTOM)
    {
      $params = $page['derivative_params'] = self::parse_custom_params($deriv);
      ImageStdParams::apply_global($params);
  
      if ($params->sizing->ideal_size[0] < 20 or $params->sizing->ideal_size[1] < 20)
      {
        self::ierror('Invalid size', 400);
      }
      if ($params->sizing->max_crop < 0 or $params->sizing->max_crop > 1)
      {
        self::ierror('Invalid crop', 400);
      }
      $greatest = ImageStdParams::get_by_type(derivative_std_params::IMG_XXLARGE);
  
      $key = array();
      $params->add_url_tokens($key);
      $key = implode('_', $key);
      if (!isset(ImageStdParams::$custom[$key]))
      {
        self::ierror('Size not allowed', 403);
      }
    }
  
    if (is_file(PHPWG_ROOT_PATH.$req.$ext))
    {
      $req = './'.$req; // will be used to match #iamges.path
    }
    elseif (is_file(PHPWG_ROOT_PATH.'../'.$req.$ext))
    {
      $req = '../'.$req;
    }
  
    $page['src_location'] = $req.$ext;
    $page['src_path'] = PHPWG_ROOT_PATH.$page['src_location'];
    $page['src_url'] = $page['root_path'].$page['src_location'];
  }
  
  static function try_switch_source(DerivativeParams $params, $original_mtime)
  {
    global $page;
    if (!isset($page['original_size']))
      return false;
  
    $original_size = $page['original_size'];
    if ($page['rotation_angle']==90 || $page['rotation_angle']==270)
    {
      $tmp = $original_size[0];
      $original_size[0] = $original_size[1];
      $original_size[1] = $tmp;
    }
    $dsize = $params->compute_final_size($original_size);
  
    $use_watermark = $params->use_watermark;
    if ($use_watermark)
    {
      $use_watermark = $params->will_watermark($dsize);
    }
  
    $candidates = array();
    foreach(ImageStdParams::get_defined_type_map() as $candidate)
    {
      if ($candidate->type == $params->type)
        continue;
      if ($candidate->use_watermark != $use_watermark)
        continue;
      if ($candidate->max_width() < $params->max_width() || $candidate->max_height() < $params->max_height())
        continue;
      $candidate_size = $candidate->compute_final_size($original_size);
      if ($dsize != $params->compute_final_size($candidate_size))
        continue;
  
      if ($params->sizing->max_crop==0)
      {
        if ($candidate->sizing->max_crop!=0)
          continue;
      }
      else
      {
        if ($use_watermark && $candidate->use_watermark)
          continue; //a square that requires watermark should not be generated from a larger derivative with watermark, because if the watermark is not centered on the large image, it will be cropped.
        if ($candidate->sizing->max_crop!=0)
          continue; // this could be optimized
        if ($candidate_size[0] < $params->sizing->min_size[0] || $candidate_size[1] < $params->sizing->min_size[1] )
          continue;
      }
      $candidates[] = $candidate;
    }
  
    foreach( array_reverse($candidates) as $candidate)
    {
      $candidate_path = $page['derivative_path'];
      $candidate_path = str_replace( '-'.derivative_params::derivative_to_url($params->type), '-'.derivative_params::derivative_to_url($candidate->type), $candidate_path);
      $candidate_mtime = @filemtime($candidate_path);
      if ($candidate_mtime === false
        || $candidate_mtime < $original_mtime
        || $candidate_mtime < $candidate->last_mod_time)
        continue;
      $params->use_watermark = false;
      $params->sharpen = min(1, $params->sharpen);
      $page['src_path'] = $candidate_path;
      $page['src_url'] = $page['root_path'] . substr($candidate_path, strlen(PHPWG_ROOT_PATH));
      $page['rotation_angle'] = 0;
      return true;
    }
    return false;
  }
  
  static function send_derivative($expires)
  {
    global $page;
  
    if (isset($_GET['ajaxload']) and $_GET['ajaxload'] == 'true')
    {
      include_once(PHPWG_ROOT_PATH.'inc/functions_cookie.php');
      include_once(PHPWG_ROOT_PATH.'inc/functions_url.php');
  
      echo json_encode( array( 'url'=>functions_url::embellish_url(functions_url::get_absolute_root_url().$page['derivative_path']) ) );
      return;
    }
    $fp = fopen($page['derivative_path'], 'rb');
  
    $fstat = fstat($fp);
    header('Last-Modified: '.gmdate('D, d M Y H:i:s', $fstat['mtime']).' GMT');
    if ($expires!==false)
    {
      header('Expires: '.gmdate('D, d M Y H:i:s', $expires).' GMT');
    }
    header('Connection: close');
  
    $ctype="application/octet-stream";
    switch (strtolower($page['derivative_ext']))
    {
      case ".jpe": case ".jpeg": case ".jpg": $ctype="image/jpeg"; break;
      case ".png": $ctype="image/png"; break;
      case ".gif": $ctype="image/gif"; break;
      case ".webp": $ctype="image/webp"; break;
    }
    header("Content-Type: $ctype");
  
    fpassthru($fp);
    fclose($fp);
  }

  /**
   * search an available feed_id
   *
   * @return string feed identifier
   */
  static function find_available_feed_id()
  {
    while (true)
    {
      $key = functions_session::generate_key(50);
      $query = '
  SELECT COUNT(*)
    FROM '.USER_FEED_TABLE.'
    WHERE id = \''.$key.'\'
  ;';
      list($count) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query($query));
      if (0 == $count)
      {
        return $key;
      }
    }
  }

  /**
   * checks the validity of input parameters, fills $page['errors'] and
   * $page['infos'] and send an email with confirmation link
   *
   * @return bool (true if email was sent, false otherwise)
   */
  static function process_password_request()
  {
    global $page, $conf;
    
    if (empty($_POST['username_or_email']))
    {
      $page['errors'][] = self::l10n('Invalid username or email');
      return false;
    }
    
    $user_id = functions_user::get_userid_by_email($_POST['username_or_email']);
      
    if (!is_numeric($user_id))
    {
      $user_id = functions_user::get_userid($_POST['username_or_email']);
    }

    if (!is_numeric($user_id))
    {
      $page['errors'][] = self::l10n('Invalid username or email');
      return false;
    }

    $userdata = functions_user::getuserdata($user_id, false);

    // password request is not possible for guest/generic users
    $status = $userdata['status'];
    if (functions_user::is_a_guest($status) or functions_user::is_generic($status))
    {
      $page['errors'][] = self::l10n('Password reset is not allowed for this user');
      return false;
    }

    if (empty($userdata['email']))
    {
      $page['errors'][] = self::l10n(
        'User "%s" has no email address, password reset is not possible',
        $userdata['username']
        );
      return false;
    }

    $activation_key = functions_session::generate_key(20);

    list($expire) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query('SELECT ADDDATE(NOW(), INTERVAL 1 HOUR)'));

    functions_mysqli::single_update(
      USER_INFOS_TABLE,
      array(
        'activation_key' => functions_user::pwg_password_hash($activation_key),
        'activation_key_expire' => $expire,
        ),
      array('user_id' => $user_id)
      );
    
    $userdata['activation_key'] = $activation_key;

    functions_url::set_make_full_url();
    
    $message = self::l10n('Someone requested that the password be reset for the following user account:') . "\r\n\r\n";
    $message.= self::l10n(
      'Username "%s" on gallery %s',
      $userdata['username'],
      functions_url::get_gallery_home_url()
      );
    $message.= "\r\n\r\n";
    $message.= self::l10n('To reset your password, visit the following address:') . "\r\n";
    $message.= functions_url::get_root_url().'password.php?key='.$activation_key.'-'.urlencode($userdata['email']);
    $message.= "\r\n\r\n";
    $message.= self::l10n('If this was a mistake, just ignore this email and nothing will happen.')."\r\n";

    functions_url::unset_make_full_url();

    $message = functions_plugins::trigger_change('render_lost_password_mail_content', $message);

    $email_params = array(
      'subject' => '['.$conf['gallery_title'].'] '.self::l10n('Password Reset'),
      'content' => $message,
      'email_format' => 'text/plain',
      );

    if (functions_mail::pwg_mail($userdata['email'], $email_params))
    {
      $page['infos'][] = self::l10n('Check your email for the confirmation link');
      return true;
    }
    else
    {
      $page['errors'][] = self::l10n('Error sending email');
      return false;
    }
  }

  /**
   *  checks the activation key: does it match the expected pattern? is it
   *  linked to a user? is this user allowed to reset his password?
   *
   * @return mixed (user_id if OK, false otherwise)
   */
  static function check_password_reset_key($reset_key)
  {
    global $page, $conf;

    list($key, $email) = explode('-', $reset_key, 2);

    if (!preg_match('/^[a-z0-9]{20}$/i', $key))
    {
      $page['errors'][] = self::l10n('Invalid key');
      return false;
    }

    $user_ids = array();
    
    $query = '
  SELECT
    '.$conf['user_fields']['id'].' AS id
    FROM '.USERS_TABLE.'
    WHERE '.$conf['user_fields']['email'].' = \''.functions_mysqli::pwg_db_real_escape_string($email).'\'
  ;';
    $user_ids = functions_mysqli::query2array($query, null, 'id');

    if (count($user_ids) == 0)
    {
      $page['errors'][] = self::l10n('Invalid username or email');
      return false;
    }

    $user_id = null;
    
    $query = '
  SELECT
      user_id,
      status,
      activation_key,
      activation_key_expire,
      NOW() AS dbnow
    FROM '.USER_INFOS_TABLE.'
    WHERE user_id IN ('.implode(',', $user_ids).')
  ;';
    $result = functions_mysqli::pwg_query($query);
    while ($row = functions_mysqli::pwg_db_fetch_assoc($result))
    {
      if (functions_user::pwg_password_verify($key, $row['activation_key']))
      {
        if (strtotime($row['dbnow']) > strtotime($row['activation_key_expire']))
        {
          // key has expired
          $page['errors'][] = self::l10n('Invalid key');
          return false;
        }

        if (functions_user::is_a_guest($row['status']) or functions_user::is_generic($row['status']))
        {
          $page['errors'][] = self::l10n('Password reset is not allowed for this user');
          return false;
        }

        $user_id = $row['user_id'];
      }
    }

    if (empty($user_id))
    {
      $page['errors'][] = self::l10n('Invalid key');
      return false;
    }
    
    return $user_id;
  }

  /**
   * checks the passwords, checks that user is allowed to reset his password,
   * update password, fills $page['errors'] and $page['infos'].
   *
   * @return bool (true if password was reset, false otherwise)
   */
  static function reset_password()
  {
    global $page, $conf;

    if ($_POST['use_new_pwd'] != $_POST['passwordConf'])
    {
      $page['errors'][] = self::l10n('The passwords do not match');
      return false;
    }

    if (!isset($_GET['key']))
    {
      $page['errors'][] = self::l10n('Invalid key');
    }
    
    $user_id = self::check_password_reset_key($_GET['key']);
    
    if (!is_numeric($user_id))
    {
      return false;
    }
      
    functions_mysqli::single_update(
      USERS_TABLE,
      array($conf['user_fields']['password'] => $conf['password_hash']($_POST['use_new_pwd'])),
      array($conf['user_fields']['id'] => $user_id)
      );

    functions_user::deactivate_password_reset_key($user_id);
    functions_user::deactivate_user_auth_keys($user_id);

    $page['infos'][] = self::l10n('Your password has been reset');
    $page['infos'][] = '<a href="'.functions_url::get_root_url().'identification.php">'.self::l10n('Login').'</a>';

    return true;
  }

  /**
   * pwg_nl2br is useful for PHP 5.2 which doesn't accept more than 1
   * parameter on nl2br() (and anyway the second parameter of nl2br does not
   * match what Piwigo gives.
   */
  static function pwg_nl2br($string)
  {
    return nl2br($string);
  }

  // this is the default handler that generates the display for the element
  static function default_picture_content($content, $element_info)
  {
    global $conf;

    if ( !empty($content) )
    {// someone hooked us - so we skip;
      return $content;
    }

    if (isset($_COOKIE['picture_deriv']))
    {
      if ( array_key_exists($_COOKIE['picture_deriv'], ImageStdParams::get_defined_type_map()) )
      {
        functions_session::pwg_set_session_var('picture_deriv', $_COOKIE['picture_deriv']);
      }
      setcookie('picture_deriv', false, 0, functions_cookie::cookie_path() );
    }
    $deriv_type = functions_session::pwg_get_session_var('picture_deriv', $conf['derivative_default_size']);
    $selected_derivative = $element_info['derivatives'][$deriv_type];

    $unique_derivatives = array();
    $show_original = isset($element_info['element_url']);
    $added = array();
    foreach($element_info['derivatives'] as $type => $derivative)
    {
      if ($type==derivative_std_params::IMG_SQUARE || $type==derivative_std_params::IMG_THUMB)
        continue;
      if (!array_key_exists($type, ImageStdParams::get_defined_type_map()))
        continue;
      $url = $derivative->get_url();
      if (isset($added[$url]))
        continue;
      $added[$url] = 1;
      $show_original &= !($derivative->same_as_source());

      // in case we do not display the sizes icon, we only add the selected size to unique_derivatives
      if ($conf['picture_sizes_icon'] or $type == $deriv_type)
        $unique_derivatives[$type]= $derivative;
    }

    global $page, $template;

    if ($show_original)
    {
      $template->assign( 'U_ORIGINAL', $element_info['element_url'] );
    }

    $template->append('current', array(
        'selected_derivative' => $selected_derivative,
        'unique_derivatives' => $unique_derivatives,
      ), true);


    $template->set_filenames(
      array('default_content'=>'picture_content.tpl')
      );

    $template->assign( array(
        'ALT_IMG' => $element_info['file'],
        'COOKIE_PATH' => functions_cookie::cookie_path(),
        )
      );
    return $template->parse( 'default_content', true);
  }

  static function int_delete_gdthumb_cache($pattern) {
    if ($contents = @opendir(PHPWG_ROOT_PATH . PWG_DERIVATIVE_DIR)):
      while (($node = readdir($contents)) !== false):
        if ($node != '.'
            and $node != '..'
            and is_dir(PHPWG_ROOT_PATH . PWG_DERIVATIVE_DIR . $node)):
          \Piwigo\admin\inc\functions::clear_derivative_cache_rec(PHPWG_ROOT_PATH . PWG_DERIVATIVE_DIR . $node, $pattern);
        endif;
      endwhile;
      closedir($contents);
    endif;
  }
  
  static function delete_gdthumb_cache($height) {
    self::int_delete_gdthumb_cache('#.*-cu_s9999x' . $height . '\.[a-zA-Z0-9]{3,4}$#');
    self::int_delete_gdthumb_cache('#.*-cu_s' . $height . 'x9999\.[a-zA-Z0-9]{3,4}$#');
  }

  static function TAT_tour_setup()
  {
    if (!functions_user::is_admin())
    {
      return;
    }
  
    global $template, $TAT_restart, $conf;
    $tour_to_launch=functions_session::pwg_get_session_var('tour_to_launch');
    self::load_language('plugin.lang', PHPWG_PLUGINS_PATH .'TakeATour/', array('force_fallback'=>'en_UK'));
    
    list(, $tour_name) = explode('/', $tour_to_launch);
    self::load_language('tour_'.$tour_name.'.lang', PHPWG_PLUGINS_PATH .'TakeATour/', array('force_fallback'=>'en_UK'));
  
    if (in_array($tour_name, array('edit_photos', 'manage_albums', 'config', 'plugins')))
    {
      // because these tours come from splitting the original "first_contact"
      // tour, we also load this language file
      self::load_language('tour_first_contact.lang', PHPWG_PLUGINS_PATH .'TakeATour/', array('force_fallback'=>'en_UK'));
    }
  
    $template->set_filename('TAT_js_css', PHPWG_PLUGINS_PATH.'TakeATour/tpl/js_css.tpl');
    $template->assign('ADMIN_THEME', $conf['admin_theme']);
    $template->parse('TAT_js_css');
  
    if (isset($TAT_restart) and $TAT_restart)
    {
      $TAT_restart=false;
      $template->assign('TAT_restart',true);
    }
    $tat_path=str_replace(basename($_SERVER['SCRIPT_NAME']),'', $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME']);
    $template->assign('TAT_path', $tat_path);
    $template->assign('ABS_U_ADMIN', functions_url::get_absolute_root_url());// absolute one due to public pages and $conf['question_mark_in_urls'] = false+$conf['php_extension_in_urls'] = false;
  
    // some tours may need admin functions (like 2_8_0 needs get_orphans)
    include_once(PHPWG_ROOT_PATH.'admin/inc/functions.php');
  
    include($tour_to_launch.'/config.php');
    $template->set_filename('TAT_tour_tpl', $TOUR_PATH);
  
    functions_plugins::trigger_notify('TAT_before_parse_tour');
  
    $template->parse('TAT_tour_tpl');
  }
  
  static function TAT_help()
  {
    global $template;
    self::load_language('plugin.lang', PHPWG_PLUGINS_PATH .'TakeATour/');
    $template->set_prefilter('help', '\Piwigo\inc\functions::TAT_help_prefilter');
  }

  static function TAT_help_prefilter($content)
  {
    
    $search = '<div id="helpContent">';
    $replacement = '<div id="helpContent">
  <fieldset>
  <legend>{\'Visit your Piwigo!\'|@translate}</legend>
  <p class="nextStepLink"><a href="admin.php?page=plugin-TakeATour">{\'Take a tour and discover the features of your Piwigo gallery » Go to the available tours\'|@translate}</a></p>
  </fieldset>';
    return(str_replace($search, $replacement, $content));
  
  }
  
  static function TAT_no_photo_yet()
  {
    global $template;
    self::load_language('plugin.lang', PHPWG_PLUGINS_PATH .'TakeATour/');
    $template->set_prefilter('no_photo_yet', '\Piwigo\inc\functions::TAT_no_photo_yet_prefilter');
    $template->assign(
    array(
      'F_ACTION' => functions_url::get_root_url().'admin.php',
      'pwg_token' => self::get_pwg_token()
      )
    );
  }

  static function TAT_no_photo_yet_prefilter($content)
  {
    $search = '<div class="bigButton"><a href="{$next_step_url}">{\'I want to add photos\'|@translate}</a></div>';
    $replacement = '<div class="bigButton"><a href="{$F_ACTION}?submited_tour_path=tours/first_contact&pwg_token={$pwg_token}">{\'Start the Tour\'|@translate}</a></div>';
    return(str_replace($search, $replacement, $content));
  }

  static function rv_cdn_prefilter($source, &$smarty)
  {
    $source = str_replace('src="{$ROOT_URL}{$themeconf.icon_dir}/', 'src="'.RVCDN_ROOT_URL.'{$themeconf.icon_dir}/', $source);
    $source = str_replace('url({$'.'ROOT_URL}', 'url('.RVCDN_ROOT_URL, $source);
    return $source;
  }
  
  static function modus_loc_begin_index()
  {
    global $template;
    $template->set_prefilter('index', '\Piwigo\inc\functions::modus_index_prefilter_1');
    $template->set_prefilter('index', '\Piwigo\inc\functions::modus_index_prefilter_2');
  }
  
  static function modus_index_prefilter_1($content)
  {
    $search = '{combine_css path="themes/default/fontello/css/fontello.css" order=-10}';
    $replacement = '';
    return str_replace($search, $replacement, $content);
  }
  // Add pwg-icon class to search in this set icon
  
  static function modus_index_prefilter_2($content)
  {
    $search = '<span class="pwg-icon-search-folder"></span>';
    $replacement = '<span class="pwg-icon pwg-icon-search-folder"></span>';
    return str_replace($search, $replacement, $content);
  }
  
  static function rv_cdn_combined_script($url, $script)
  {
    if (!$script->is_remote())
      $url = RVCDN_ROOT_URL.$script->path;
    return $url;
  }
  
  static function modus_loc_begin_page_header()
  {
    $all = $GLOBALS['template']->scriptLoader->get_all();
    if ( ($jq = @$all['jquery']) )
      $jq->set_path(RVPT_JQUERY_SRC);
  }
  
  static function modus_combinable_preparse($template)
  {
    global $conf, $template;
    include_once(dirname(__FILE__).'/functions.php');
  
    try {
      $template->smarty->registerPlugin('modifier', 'cssGradient', 'modus_css_gradient');
    } catch(SmartyException $exc) {}
  
    include( dirname(__FILE__).'/skins/'.$conf['modus_theme']['skin'].'.php' );
  
    $template->assign( array(
      'conf' => $conf,
      'skin' => $skin,
      'MODUS_ALBUM_THUMB_SIZE' => intval(@$conf['modus_theme']['album_thumb_size']),
      'SQUARE_WIDTH' => ImageStdParams::get_by_type(derivative_std_params::IMG_SQUARE)->max_width(),
      'loaded_plugins' => $GLOBALS['pwg_loaded_plugins']
      ));
  }
  
  static function modus_css_resolution($params)
  {
    $base = @$params['base'];
    $min = @$params['min'];
    $max = @$params['max'];
  
    $rules = array();
    if (!empty($base))
      $rules[] = $base;
    foreach(array('min','max') as $type)
    {
      if (!empty($$type))
        $rules[] = '(-webkit-'.$type.'-device-pixel-ratio:'.$$type.')';
    }
    $res = implode(' and ', $rules);
  
    $rules = array();
    if (!empty($base))
      $rules[] = $base;
    foreach(array('min','max') as $type)
    {
      if (!empty($$type))
        $rules[] = '('.$type.'-resolution:'.round(96*$$type,1).'dpi)';
    }
    $res .= ','.implode(' and ', $rules);
  
    return $res;
  }
  
  static function modus_thumbs($x, $smarty)
  {
    global $template, $page, $conf;
  
    $default_params = $smarty->getTemplateVars('derivative_params');
    $row_height = $default_params->max_height();
    $device = self::get_device();
    $container_margin = 5;
  
    if ('mobile'==$device)
    {
      $horizontal_margin = floor(0.01*$row_height);
      $container_margin = 0;
    }
    elseif ('tablet'==$device)
      $horizontal_margin = floor(0.015*$row_height);
    else
      $horizontal_margin = floor(0.02*$row_height);
    $vertical_margin = $horizontal_margin+1;
  
    $candidates = array($default_params);
    foreach( ImageStdParams::get_defined_type_map() as $params)
    {
      if ($params->max_height() > $row_height && $params->sizing->max_crop == $default_params->sizing->max_crop )
      {
        $candidates[] = $params;
        if (count($candidates)==3)
          break;
      }
    }
  
    $do_over = 'desktop' == $device;
  
    $new_icon = " <span class=albSymbol title=\"".self::l10n('posted on %s')."\">".MODUS_STR_RECENT.'</span>';
  
    foreach($smarty->getTemplateVars('thumbnails') as $item)
    {
      $src_image = $item['src_image'];
      $new = !empty($item['icon_ts']) ? sprintf($new_icon, self::format_date($item['date_available'])) : '';
  
      $idx=0;
      do {
        $cparams = $candidates[$idx];
        $c = new DerivativeImage($cparams, $src_image);
        $csize = $c->get_size();
        $idx++;
      }
      while($csize[1]<$row_height-2 && $idx<count($candidates));
  
      $a_style = '';
      if ($csize[1] < $row_height)
        $a_style=' style="top:'.floor(($row_height-$csize[1])/2).'px"';
      elseif ($csize[1] > $row_height)
        $csize = $c->get_scaled_size(9999, $row_height);
      if ($do_over) {?>
  <li class="path-ext-<?=$item["path_ext"]?> file-ext-<?=$item["file_ext"]?>" style=width:<?=$csize[0]?>px;height:<?=$row_height?>px><a href="<?=$item['URL']?>"<?=$a_style?>><img src="<?=$c->get_url()?>" width=<?=$csize[0]?> height=<?=$csize[1]?> alt="<?=$item['TN_ALT']?>"></a><div class=overDesc><?=$item['NAME']?><?=$new?></div></li>
  <?php
      } else {?>
  <li class="path-ext-<?=$item["path_ext"]?> file-ext-<?=$item["file_ext"]?>" style=width:<?=$csize[0]?>px;height:<?=$row_height?>px><a href="<?=$item['URL']?>"<?=$a_style?>><img src="<?=$c->get_url()?>" width=<?=$csize[0]?> height=<?=$csize[1]?> alt="<?=$item['TN_ALT']?>"></a></li>
  <?php
      }
    }
  
    $template->block_html_style(null,
  '#thumbnails{text-align:justify;overflow:hidden;margin-left:'.($container_margin-$horizontal_margin).'px;margin-right:'.$container_margin.'px}
  #thumbnails>li{float:left;overflow:hidden;position:relative;margin-bottom:'.$vertical_margin.'px;margin-left:'.$horizontal_margin.'px}#thumbnails>li>a{position:absolute;border:0}');
    $template->block_footer_script(null, 'rvgtProcessor=new RVGThumbs({hMargin:'.$horizontal_margin.',rowHeight:'.$row_height.'});');
  
    $my_base_name = basename(dirname(__FILE__));
    // not async to avoid visible flickering reflow
    $template->scriptLoader->add('modus.arange', 1, array('jquery'), 'themes/'.$my_base_name."/js/thumb.arrange.min.js", 0);
  }
  
  static function modus_on_end_index()
  {
    global $template;
    if (!functions_session::pwg_get_session_var('caps'))
      $template->block_footer_script(null, 'try{document.cookie="caps="+(window.devicePixelRatio?window.devicePixelRatio:1)+"x"+document.documentElement.clientWidth+"x"+document.documentElement.clientHeight+";path='.functions_cookie::cookie_path().'"}catch(er){document.cookie="caps=1x1x1x"+err.message;}');
  
  }
  
  static function modus_get_index_photo_derivative_params($default)
  {
    global $conf;
    if (isset($conf['modus_theme']) && functions_session::pwg_get_session_var('index_deriv')===null)
    {
      $type = $conf['modus_theme']['index_photo_deriv'];
      if ( $caps=functions_session::pwg_get_session_var('caps') )
      {
        if ( ($caps[0]>=2 && $caps[1]>=768) /*Ipad3 always has clientWidth 768 independently of orientation*/
          || $caps[0]>=3
          )
          $type = $conf['modus_theme']['index_photo_deriv_hdpi'];
      }
      $new = @ImageStdParams::get_by_type($type);
      if ($new) return $new;
    }
    return $default;
  }
  
  static function modus_index_category_thumbnails($items)
  {
    global $page, $template, $conf;
  
    if ('categories'!=$page['section'] || !($wh=@$conf['modus_theme']['album_thumb_size']) )
      return $items;;
  
    $template->assign('album_thumb_size', $wh);
  
    $def_params = ImageStdParams::get_custom($wh, $wh, 1, $wh, $wh);
    foreach( ImageStdParams::get_defined_type_map() as $params)
    {
      if ($params->max_height() == $wh)
        $alt_params = $params;
    }
  
    foreach($items as &$item)
    {
      $src_image = $item['representative']['src_image'];
      $src_size = $src_image->get_size();
      
      $item['path_ext'] = strtolower(self::get_extension($item['representative']['path']));
      $item['file_ext'] = strtolower(self::get_extension($item['representative']['file']));
  
      $deriv = null;
      if (isset($alt_params) && $src_size[0]>=$src_size[1])
      {
        $dsize = $alt_params->compute_final_size($src_size);
        if ($dsize[0]>=$wh && $dsize[1]>=$wh)
        {
          $deriv = new DerivativeImage($alt_params, $src_image);
          $rect = new ImageRect($dsize);
          $rect->crop_h( $dsize[0]-$wh, $item['representative']['coi'] );
          $rect->crop_v( $dsize[1]-$wh, $item['representative']['coi'] );
          $l = - $rect->l;
          $t = - $rect->t;
        }
      }
  
      if (!isset($deriv))
      {
        $deriv = new DerivativeImage($def_params, $src_image);
        $dsize = $deriv->get_size();
        $l = intval($wh-$dsize[0])/2;
        $t = intval($wh-$dsize[1])/2;
      }
      $item['modus_deriv'] = $deriv;
  
      if (!empty($item['icon_ts']))
        $item['icon_ts']['TITLE'] = self::time_since($item['max_date_last'], 'month');
  
        $styles = array();
      if ($l<-1 || $l>1)
        $styles[] = 'left:'.(100*$l/$wh).'%';
  
      if ($t<-1 || $t>1)
        $styles[] = 'top:'.$t.'px';
      if (count($styles))
        $styles = ' style='.implode(';', $styles);
      else
        $styles='';
      $item['MODUS_STYLE'] = $styles;
    }
  
    return $items;
  }
  
  static function modus_loc_begin_picture()
  {
    global $conf, $template;
    if ( isset($_GET['slideshow']) )
    {
      $conf['picture_menu'] = false;
      return;
    }
  
    if ( isset($_GET['map']) )
      return;
    $template->append('head_elements', '<script>if(document.documentElement.offsetWidth>1270)document.documentElement.className=\'wide\'</script>');
  }
  
  static function modus_picture_content($content, $element_info)
  {
    global $conf, $picture, $template;
  
    if ( !empty($content) ) // someone hooked us - so we skip;
      return $content;
  
    $unique_derivatives = array();
    $show_original = isset($element_info['element_url']);
    $added = array();
    foreach($element_info['derivatives'] as $type => $derivative)
    {
      if ($type==derivative_std_params::IMG_SQUARE || $type==derivative_std_params::IMG_THUMB)
        continue;
      if (!array_key_exists($type, ImageStdParams::get_defined_type_map()))
        continue;
      $url = $derivative->get_url();
      if (isset($added[$url]))
        continue;
      $added[$url] = 1;
      $show_original &= !($derivative->same_as_source());
      $unique_derivatives[$type]= $derivative;
    }
  
    if (isset($_COOKIE['picture_deriv'])) // ignore persistence
      setcookie('picture_deriv', false, 0, functions_cookie::cookie_path() );
  
    $selected_derivative = null;
    if (isset($_COOKIE['phavsz']))
      $available_size = explode('x', $_COOKIE['phavsz']);
    elseif ( ($caps=functions_session::pwg_get_session_var('caps')) && $caps[0]>1 )
      $available_size = array($caps[0]*$caps[1], $caps[0]*($caps[2]-100), $caps[0]);
  
    if (isset($available_size))
    {
      foreach($unique_derivatives as $derivative)
      {
        $size = $derivative->get_size();
        if (!$size)
          break;
  
        if ($size[0] <= $available_size[0] and $size[1] <= $available_size[1])
          $selected_derivative = $derivative;
        else
        {
          if ($available_size[2]>1 || !$selected_derivative)
            $selected_derivative = $derivative;
          break;
        }
      }
  
      if ($available_size[2]>1 && $selected_derivative)
      {
        $ratio_w = $size[0] / $available_size[0];
        $ratio_h = $size[1] / $available_size[1];
        if ($ratio_w>1 || $ratio_h>1)
        {
          if ($ratio_w > $ratio_h)
            $display_size = array( $available_size[0]/$available_size[2], floor($size[1] / $ratio_w / $available_size[2]) );
          else
            $display_size = array( floor($size[0] / $ratio_h / $available_size[2]), $available_size[1]/$available_size[2] );
        }
        else
          $display_size = array( round($size[0]/$available_size[2]), round($size[1]/$available_size[2]) );
  
        $template->assign( array(
            'rvas_display_size' => $display_size,
            'rvas_natural_size' => $size,
          ));
      }
  
      if (isset($picture['next'])
        and $picture['next']['src_image']->is_original())
      {
        $next_best = null;
        foreach( $picture['next']['derivatives'] as $derivative)
        {
          $size = $derivative->get_size();
          if (!$size)
            break;
          if ($size[0] <= $available_size[0] and $size[1] <= $available_size[1])
            $next_best = $derivative;
          else
          {
            if ($available_size[2]>1 || !$next_best)
               $next_best = $derivative;
            break;
          }
        }
  
        if (isset($next_best))
          $template->assign('U_PREFETCH', $next_best->get_url() );
      }
    }
  
    $as_pending = false;
    if (!$selected_derivative)
    {
      $as_pending = true;
      $selected_derivative = $element_info['derivatives'][ functions_session::pwg_get_session_var('picture_deriv',$conf['derivative_default_size']) ];
    }
  
  
    if ($show_original)
      $template->assign( 'U_ORIGINAL', $element_info['element_url'] );
  
    $template->append('current', array(
        'selected_derivative' => $selected_derivative,
        'unique_derivatives' => $unique_derivatives,
      ), true);
  
  
    $template->set_filenames(
      array('default_content'=>'picture_content_asize.tpl')
      );
  
    $template->assign( array(
        'ALT_IMG' => $element_info['file'],
        'COOKIE_PATH' => functions_cookie::cookie_path(),
        'RVAS_PENDING' => $as_pending,
        )
      );
    return $template->parse( 'default_content', true);
  }

  static function modus_smarty_prefilter_wrap($source)
  {
    include_once(PHPWG_ROOT_PATH.'themes/modus/functions.php');
    return modus_smarty_prefilter($source);
  }

  static function sp_select_all_thumbnails($selection)
  {
    global $page, $template;
  
    $template->assign('page_selection', array_flip($selection));
    $template->assign('thumb_picker', new SPThumbPicker() );
    return $page['items'];
  }
  
  static function sp_select_all_categories($selection)
  {
    global $tpl_thumbnails_var;
    return $tpl_thumbnails_var;
  }
  
  static function sp_end_section_init()
  {
    global $page, $template;
  
    // variables to log history
    $template->assign(
      'smartpocket_log_history',
      array(
        'cat_id' => @$page['category']['id'],
        'section' => @$page['section'],
        'tags_string' => (isset($page['tag_ids']) ? implode(',', $page['tag_ids']) : ''),
        )
      );
  }
  
  static function mobile_link()
  {
    global $template, $conf;
    $config = self::safe_unserialize( $conf['smartpocket'] );
    $template->assign( 'smartpocket', $config );
    if ( !empty($conf['mobile_theme']) && (self::get_device() != 'desktop' || self::mobile_theme()))
    {
      $template->assign(array(
                              'TOGGLE_MOBILE_THEME_URL' => functions_url::add_url_params(htmlspecialchars($_SERVER['REQUEST_URI']),array('mobile' => self::mobile_theme() ? 'false' : 'true')),
        ));
    }
  }

  /**
   * list all tables in an array
   *
   * @return array
   */
  static function get_tables()
  {
    $tables = array();

    $query = '
  SHOW TABLES
  ;';
    $result = functions_mysqli::pwg_query($query);

    while ($row = functions_mysqli::pwg_db_fetch_row($result))
    {
      if (preg_match('/^'.PREFIX_TABLE.'/', $row[0]))
      {
        $tables[] = $row[0];
      }
    }

    return $tables;
  }

  /**
   * list all columns of each given table
   *
   * @return array of array
   */
  static function get_columns_of($tables)
  {
    $columns_of = array();

    foreach ($tables as $table)
    {
      $query = '
  DESC `'.$table.'`
  ;';
      $result = functions_mysqli::pwg_query($query);

      $columns_of[$table] = array();

      while ($row = functions_mysqli::pwg_db_fetch_row($result))
      {
        $columns_of[$table][] = $row[0];
      }
    }

    return $columns_of;
  }

  /**
   */
  static function print_time($message)
  {
    global $last_time;

    $new_time = self::get_moment();
    echo '<pre>['.self::get_elapsed_time($last_time, $new_time).']';
    echo ' '.$message;
    echo '</pre>';
    flush();
    $last_time = $new_time;
  }

  static function save_profile_from_post($userdata, &$errors)
  {
    global $conf, $page;
    $errors = array();
  
    if (!isset($_POST['validate']))
    {
      return false;
    }
  
    $special_user = in_array($userdata['id'], array($conf['guest_id'], $conf['default_user_id']));
    if ($special_user)
    {
      unset(
        $_POST['username'],
        $_POST['mail_address'],
        $_POST['password'],
        $_POST['use_new_pwd'],
        $_POST['passwordConf'],
        $_POST['theme'],
        $_POST['language']
        );
      $_POST['theme'] = functions_user::get_default_theme();
      $_POST['language'] = functions_user::get_default_language();
    }
    
    if (!defined('IN_ADMIN'))
    {
      unset($_POST['username']);
    }
  
    if ($conf['allow_user_customization'] or defined('IN_ADMIN'))
    {
      $int_pattern = '/^\d+$/';
      if (empty($_POST['nb_image_page'])
          or (!preg_match($int_pattern, $_POST['nb_image_page'])))
      {
        $errors[] = self::l10n('The number of photos per page must be a not null scalar');
      }
  
      // periods must be integer values, they represents number of days
      if (!preg_match($int_pattern, $_POST['recent_period'])
          or $_POST['recent_period'] < 0)
      {
        $errors[] = self::l10n('Recent period must be a positive integer value') ;
      }
  
      if (!in_array($_POST['language'], array_keys(self::get_languages())))
      {
        die('Hacking attempt, incorrect language value');
      }
  
      if (!in_array($_POST['theme'], array_keys(self::get_pwg_themes())))
      {
        die('Hacking attempt, incorrect theme value');
      }
    }
  
    if (isset($_POST['mail_address']))
    {
      // if $_POST and $userdata have are same email
      // validate_mail_address allows, however, to check email
      $mail_error = functions_user::validate_mail_address($userdata['id'], $_POST['mail_address']);
      if (!empty($mail_error))
      {
        $errors[] = $mail_error;
      }
    }
  
    if (!empty($_POST['use_new_pwd']))
    {
      // password must be the same as its confirmation
      if ($_POST['use_new_pwd'] != $_POST['passwordConf'])
      {
        $errors[] = self::l10n('The passwords do not match');
      }
  
      if ( !defined('IN_ADMIN') )
      {// changing password requires old password
        $query = '
    SELECT '.$conf['user_fields']['password'].' AS password
      FROM '.USERS_TABLE.'
      WHERE '.$conf['user_fields']['id'].' = \''.$userdata['id'].'\'
    ;';
        list($current_password) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query($query));
  
        if (!$conf['password_verify']($_POST['password'], $current_password))
        {
          $errors[] = self::l10n('Current password is wrong');
        }
      }
    }
  
    if (count($errors) == 0)
    {
      // mass_updates function
      include_once(PHPWG_ROOT_PATH.'admin/inc/functions.php');
  
      $activity_details_tables = array();
  
      if (isset($_POST['mail_address']))
      {
        // update common user informations
        $fields = array($conf['user_fields']['email']);
  
        $data = array();
        $data[ $conf['user_fields']['id'] ] = $userdata['id'];
        $data[ $conf['user_fields']['email'] ] = $_POST['mail_address'];
  
        // password is updated only if filled
        if (!empty($_POST['use_new_pwd']))
        {
          $fields[] = $conf['user_fields']['password'];
          // password is hashed with function $conf['password_hash']
          $data[ $conf['user_fields']['password'] ] = $conf['password_hash']($_POST['use_new_pwd']);
  
          functions_user::deactivate_user_auth_keys($userdata['id']);
        }
        
        // username is updated only if allowed
        if (!empty($_POST['username']))
        {
          if ($_POST['username'] != $userdata['username'] and functions_user::get_userid($_POST['username']))
          {
            $page['errors'][] = self::l10n('this login is already used');
            unset($_POST['redirect']);
          }
          else
          {
            $fields[] = $conf['user_fields']['username'];
            $data[ $conf['user_fields']['username'] ] = $_POST['username'];
            
            // send email to the user
            if ($_POST['username'] != $userdata['username'])
            {
              include_once(PHPWG_ROOT_PATH.'inc/functions_mail.php');
              functions_mail::switch_lang_to($userdata['language']);
              
              $keyargs_content = array(
                self::get_l10n_args('Hello', ''),
                self::get_l10n_args('Your username has been successfully changed to : %s', $_POST['username']),
                );
                
              functions_mail::pwg_mail(
                $_POST['mail_address'],
                array(
                  'subject' => '['.$conf['gallery_title'].'] '.self::l10n('Username modification'),
                  'content' => self::l10n_args($keyargs_content),
                  'content_format' => 'text/plain',
                  )
                );
                
              functions_mail::switch_lang_back();
            }
          }
        }
        
        functions_mysqli::mass_updates(USERS_TABLE,
                     array(
                      'primary' => array($conf['user_fields']['id']),
                      'update' => $fields
                      ),
                     array($data));
  
        if ($_POST['mail_address'] != $userdata['email'])
        {
          functions_user::deactivate_password_reset_key($userdata['id']);
        }
  
        $activity_details_tables[] = 'users';
      }
  
      if ($conf['allow_user_customization'] or defined('IN_ADMIN'))
      {
        // update user "additional" informations (specific to Piwigo)
        $fields = array(
          'nb_image_page', 'language',
          'expand', 'show_nb_hits', 'recent_period', 'theme'
          );
          
        if ($conf['activate_comments'])
        {
          $fields[] = 'show_nb_comments';
        }
  
        $data = array();
        $data['user_id'] = $userdata['id'];
  
        foreach ($fields as $field)
        {
          if (isset($_POST[$field]))
          {
            $data[$field] = $_POST[$field];
          }
        }
        functions_mysqli::mass_updates(USER_INFOS_TABLE,
                     array('primary' => array('user_id'), 'update' => $fields),
                     array($data));
  
        $activity_details_tables[] = 'user_infos';
      }
      functions_plugins::trigger_notify( 'save_profile_from_post', $userdata['id'] );
      self::pwg_activity('user', $userdata['id'], 'edit', array('function'=>__FUNCTION__, 'tables'=>implode(',', $activity_details_tables)));
  
      if (!empty($_POST['redirect']))
      {
        self::redirect($_POST['redirect']);
      }
    }
    return true;
  }
  
  /**
   * Assign template variables, from arguments
   * Used to build profile edition pages
   * 
   * @param string $url_action
   * @param string $url_redirect
   * @param array $userdata
   */
  static function load_profile_in_template($url_action, $url_redirect, $userdata, $template_prefixe=null)
  {
    global $template, $conf;
  
    $template->assign('radio_options',
      array(
        'true' => self::l10n('Yes'),
        'false' => self::l10n('No')));
  
    $template->assign(
      array(
        $template_prefixe.'USERNAME'=>stripslashes($userdata['username']),
        $template_prefixe.'EMAIL'=>@$userdata['email'],
        $template_prefixe.'ALLOW_USER_CUSTOMIZATION'=>$conf['allow_user_customization'],
        $template_prefixe.'ACTIVATE_COMMENTS'=>$conf['activate_comments'],
        $template_prefixe.'NB_IMAGE_PAGE'=>$userdata['nb_image_page'],
        $template_prefixe.'RECENT_PERIOD'=>$userdata['recent_period'],
        $template_prefixe.'EXPAND' =>$userdata['expand'] ? 'true' : 'false',
        $template_prefixe.'NB_COMMENTS'=>$userdata['show_nb_comments'] ? 'true' : 'false',
        $template_prefixe.'NB_HITS'=>$userdata['show_nb_hits'] ? 'true' : 'false',
        $template_prefixe.'REDIRECT' => $url_redirect,
        $template_prefixe.'F_ACTION'=>$url_action,
        ));
  
    $template->assign('template_selection', $userdata['theme']);
    $template->assign('template_options', self::get_pwg_themes());
  
    foreach (self::get_languages() as $language_code => $language_name)
    {
      if (isset($_POST['submit']) or $userdata['language'] == $language_code)
      {
        $template->assign('language_selection', $language_code);
      }
      $language_options[$language_code] = $language_name;
    }
  
    $template->assign('language_options', $language_options);
  
    $special_user = in_array($userdata['id'], array($conf['guest_id'], $conf['default_user_id']));
    $template->assign('SPECIAL_USER', $special_user);
    $template->assign('IN_ADMIN', defined('IN_ADMIN'));
  
    // allow plugins to add their own form data to content
    functions_plugins::trigger_notify( 'load_profile_in_template', $userdata );
  
    $template->assign('PWG_TOKEN', self::get_pwg_token());
  }

  static function get_watermark_filename($list, $candidate, $step = 0)
  {
    global $change_name;
    $change_name = $candidate;
    if ($step != 0)
    {
      $change_name .= '-'.$step;
    }
    if (in_array($change_name, $list))
    {
      return self::get_watermark_filename($list, $candidate, $step+1);
    }
    return $change_name.'.png';
  }
}

?>
