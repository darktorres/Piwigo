<?php
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

namespace Piwigo\admin\inc;

use PclZip;
use Piwigo\inc\dblayer\functions_mysqli;
use Piwigo\inc\functions_mail;
use Piwigo\inc\functions_url;
use Piwigo\inc\functions_user;

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

class updates
{
  var $types = array();
  var $plugins;
  var $themes;
  var $languages;
  var $missing = array();
  var $default_plugins = array();
  var $default_themes = array();
  var $default_languages = array();
  var $merged_extensions = array();
  var $merged_extension_url = 'http://piwigo.org/download/merged_extensions.txt';

  function __construct($page='updates')
  {
    $this->types = array('plugins', 'themes', 'languages');

    if (in_array($page, $this->types))
    {
      $this->types = array($page);
    }
    $this->default_themes = array('modus', 'elegant', 'smartpocket');
    $this->default_plugins = array('AdminTools', 'TakeATour', 'language_switch', 'LocalFilesEditor');

    foreach ($this->types as $type)
    {
      $className = "Piwigo\\admin\\inc\\{$type}";
      $this->{$type} = new $className();
    }
  }

  static function check_piwigo_upgrade()
  {
    $_SESSION['need_update'.PHPWG_VERSION] = null;

    if (preg_match('/(\d+\.\d+)\.(\d+)/', PHPWG_VERSION, $matches)
      and @functions::fetchRemote(PHPWG_URL.'/download/all_versions.php?rand='.md5(uniqid(rand(), true)), $result))
    {
      $all_versions = @explode("\n", $result);
      $new_version = trim($all_versions[0]);
      $_SESSION['need_update'.PHPWG_VERSION] = version_compare(PHPWG_VERSION, $new_version, '<');
    }
  }

  /**
   * finds new versions of Piwigo on Piwigo.org.
   *
   * @since 2.9
   * @return array (
   *   'piwigo.org-checked' => has piwigo.org been checked?,
   *   'is_dev' => are we on a dev version?,
   *   'minor_version' => new minor version available,
   *   'major_version' => new major version available,
   * )
   */
  function get_piwigo_new_versions()
  {
    global $conf;

    $new_versions = array(
      'piwigo.org-checked' => false,
      'is_dev' => true,
      );
    
    if (preg_match('/^(\d+\.\d+)\.(\d+)$/', PHPWG_VERSION))
    {
      $new_versions['is_dev'] = false;
      $actual_branch = \Piwigo\inc\functions::get_branch_from_version(PHPWG_VERSION);

      $url = PHPWG_URL.'/download/all_versions.php';
      $url.= '?rand='.md5(uniqid(rand(), true)); // Avoid server cache
      $url.= '&show_requirements';
      $url.= '&origin_hash='.sha1($conf['secret_key'].functions_url::get_absolute_root_url());

      if (@functions::fetchRemote($url, $result)
          and $all_versions = @explode("\n", $result)
          and is_array($all_versions))
      {
        $new_versions['piwigo.org-checked'] = true;
        $last_version = trim($all_versions[0]);
        list($last_version_number, $last_version_php) = explode('/', trim($all_versions[0]));

        if (version_compare(PHPWG_VERSION, $last_version_number, '<'))
        {
          $last_branch = \Piwigo\inc\functions::get_branch_from_version($last_version_number);

          if ($last_branch == $actual_branch)
          {
            $new_versions['minor'] = $last_version_number;
            $new_versions['minor_php'] = $last_version_php;
          }
          else
          {
            $new_versions['major'] = $last_version_number;
            $new_versions['major_php'] = $last_version_php;

            // Check if new version exists in same branch
            foreach ($all_versions as $version)
            {
              list($version_number, $version_php) = explode('/', trim($version));
              $branch = \Piwigo\inc\functions::get_branch_from_version($version_number);

              if ($branch == $actual_branch)
              {
                if (version_compare(PHPWG_VERSION, $version_number, '<'))
                {
                  $new_versions['minor'] = $version_number;
                  $new_versions['minor_php'] = $version_php;
                }
                break;
              }
            }
          }
        }
      }
    }
    return $new_versions;
  }

  /**
   * Checks for new versions of Piwigo. Notify webmasters if new versions are available, but not too often, see
   * $conf['update_notify_reminder_period'] parameter.
   *
   * @since 2.9
   */
  function notify_piwigo_new_versions()
  {
    global $conf;

    if (!\Piwigo\inc\functions::pwg_is_dbconf_writeable())
    {
      return;
    }

    $new_versions = $this->get_piwigo_new_versions();
    \Piwigo\inc\functions::conf_update_param('update_notify_last_check', date('c'));

    if ($new_versions['is_dev'])
    {
      return;
    }

    $new_versions_string = join(
      ' & ',
      array_intersect_key(
        $new_versions,
        array_fill_keys(array('minor', 'major'), 1)
        )
      );

    if (empty($new_versions_string))
    {
      return;
    }

    // In which case should we notify?
    // 1. never notified
    // 2. new versions
    // 3. no new versions but reminder needed

    $notify = false;
    if (!isset($conf['update_notify_last_notification']))
    {
      $notify = true;
    }
    else
    {
      $conf['update_notify_last_notification'] = \Piwigo\inc\functions::safe_unserialize($conf['update_notify_last_notification']);
      $last_notification = $conf['update_notify_last_notification']['notified_on'];

      if ($new_versions_string != $conf['update_notify_last_notification']['version'])
      {
        $notify = true;
      }
      elseif (
        $conf['update_notify_reminder_period'] > 0
        and strtotime($last_notification) < strtotime($conf['update_notify_reminder_period'].' seconds ago')
        )
      {
        $notify = true;
      }
    }

    if ($notify)
    {
      // send email
      include_once(PHPWG_ROOT_PATH.'inc/functions_mail.php');

      functions_mail::switch_lang_to(functions_user::get_default_language());

      $content = \Piwigo\inc\functions::l10n('Hello,');
      $content.= "\n\n".\Piwigo\inc\functions::l10n(
        'Time has come to update your Piwigo with version %s, go to %s',
        $new_versions_string,
        functions_url::get_absolute_root_url().'admin.php?page=updates'
        );
      $content.= "\n\n".\Piwigo\inc\functions::l10n('It only takes a few clicks.');
      $content.= "\n\n".\Piwigo\inc\functions::l10n('Running on an up-to-date Piwigo is important for security.');

      functions_mail::pwg_mail_admins(
        array(
          'subject' => \Piwigo\inc\functions::l10n('Piwigo %s is available, please update', $new_versions_string),
          'content' => $content,
          'content_format' => 'text/plain',
          ),
        array(
          'filename' => 'notification_admin',
          ),
        false, // do not exclude current user
        true // only webmasters
        );

      functions_mail::switch_lang_back();

      // save notify
      \Piwigo\inc\functions::conf_update_param(
        'update_notify_last_notification',
        array(
          'version' => $new_versions_string,
          'notified_on' => date('c'),
          )
        );
    }
  }

  function get_server_extensions($version=PHPWG_VERSION)
  {
    global $user;

    $get_data = array(
      'format' => 'php',
    );

    // Retrieve PEM versions
    $versions_to_check = array();
    $url = PEM_URL . '/api/get_version_list.php';
    if (functions::fetchRemote($url, $result, $get_data) and $pem_versions = @unserialize($result))
    {
      if (!preg_match('/^\d+\.\d+\.\d+$/', $version))
      {
        $version = $pem_versions[0]['name'];
      }
      $branch = \Piwigo\inc\functions::get_branch_from_version($version);
      foreach ($pem_versions as $pem_version)
      {
        if (strpos($pem_version['name'], $branch) === 0)
        {
          $versions_to_check[] = $pem_version['id'];
        }
      }
    }
    if (empty($versions_to_check))
    {
      return false;
    }

    // Extensions to check
    $ext_to_check = array();
    foreach ($this->types as $type)
    {
      $fs = 'fs_'.$type;
      foreach ($this->$type->$fs as $ext)
      {
        if (isset($ext['extension']))
        {
          $ext_to_check[$ext['extension']] = $type;
        }
      }
    }

    // Retrieve PEM plugins infos
    $url = PEM_URL . '/api/get_revision_list.php';
    $get_data = array_merge($get_data, array(
      'last_revision_only' => 'true',
      'version' => implode(',', $versions_to_check),
      'lang' => substr($user['language'], 0, 2),
      'get_nb_downloads' => 'true',
      )
    );

    $post_data = array();
    if (!empty($ext_to_check))
    {
      $post_data['extension_include'] = implode(',', array_keys($ext_to_check));
    }

    if (functions::fetchRemote($url, $result, $get_data, $post_data))
    {
      $pem_exts = @unserialize($result);
      if (!is_array($pem_exts))
      {
        return false;
      }

      $servers = array();
      
      foreach ($pem_exts as $ext)
      {
        if (isset($ext_to_check[$ext['extension_id']]))
        {
          $type = $ext_to_check[$ext['extension_id']];
          
          if (!isset($servers[$type]))
          {
            $servers[$type] = array();
          }

          $servers[$type][ $ext['extension_id'] ] = $ext;
          
          unset($ext_to_check[$ext['extension_id']]);
        }
      }

      foreach ($servers as $server_type => $extension_list)
      {
        $server_string = 'server_'.$server_type;

        $this->$server_type->$server_string = $extension_list;
      }
      
      $this->check_missing_extensions($ext_to_check);
      return true;
    }
    return false;
  }

  // Check all extensions upgrades
  function check_extensions()
  {
    global $conf;

    if (!$this->get_server_extensions())
    {
      return false;
    }

    $_SESSION['extensions_need_update'] = array();

    foreach ($this->types as $type)
    {
      $fs = 'fs_'.$type;
      $server = 'server_'.$type;
      $server_ext = $this->$type->$server;
      $fs_ext = $this->$type->$fs;

      $ignore_list = array();
      $need_upgrade = array();

      foreach($fs_ext as $ext_id => $fs_ext)
      {
        if (isset($fs_ext['extension']) and isset($server_ext[$fs_ext['extension']]))
        {
          $ext_info = $server_ext[$fs_ext['extension']];

          if (!\Piwigo\inc\functions::safe_version_compare($fs_ext['version'], $ext_info['revision_name'], '>='))
          {
            if (in_array($ext_id, $conf['updates_ignored'][$type]))
            {
              $ignore_list[] = $ext_id;
            }
            else
            {
              $_SESSION['extensions_need_update'][$type][$ext_id] = $ext_info['revision_name'];
            }
          }
        }
      }
      $conf['updates_ignored'][$type] = $ignore_list;
    }
    \Piwigo\inc\functions::conf_update_param('updates_ignored', functions_mysqli::pwg_db_real_escape_string(serialize($conf['updates_ignored'])));
  }

  // Check if extension have been upgraded since last check
  function check_updated_extensions()
  {
    foreach ($this->types as $type)
    {
      if (!empty($_SESSION['extensions_need_update'][$type]))
      {
        $fs = 'fs_'.$type;
        foreach($this->$type->$fs as $ext_id => $fs_ext)
        {
          if (isset($_SESSION['extensions_need_update'][$type][$ext_id])
            and \Piwigo\inc\functions::safe_version_compare($fs_ext['version'], $_SESSION['extensions_need_update'][$type][$ext_id], '>='))
          {
            // Extension have been upgraded
            $this->check_extensions();
            break;
          }
        }
      }
    }
  }

  function check_missing_extensions($missing)
  {
    foreach ($missing as $id => $type)
    {
      $fs = 'fs_'.$type;
      $default = 'default_'.$type;
      foreach ($this->$type->$fs as $ext_id => $ext)
      {
        if (isset($ext['extension']) and $id == $ext['extension']
          and !in_array($ext_id, $this->$default)
          and !in_array($ext['extension'], $this->merged_extensions))
        {
          $this->missing[$type][] = $ext;
          break;
        }
      }
    }
  }

  function get_merged_extensions($version)
  {
    if (functions::fetchRemote($this->merged_extension_url, $result))
    {
      $rows = explode("\n", $result);
      foreach ($rows as $row)
      {
        if (preg_match('/^(\d+\.\d+): *(.*)$/', $row, $match))
        {
          if (version_compare($version, $match[1], '>='))
          {
            $extensions = explode(',', trim($match[2]));
            $this->merged_extensions = array_merge($this->merged_extensions, $extensions);
          }
        }
      }
    }
  }

  static function process_obsolete_list($file)
  {
    if (file_exists(PHPWG_ROOT_PATH.$file)
      and $old_files = file(PHPWG_ROOT_PATH.$file, FILE_IGNORE_NEW_LINES)
      and !empty($old_files))
    {
      $old_files[] = $file;
      foreach($old_files as $old_file)
      {
        $path = PHPWG_ROOT_PATH.$old_file;
        if (is_file($path))
        {
          @unlink($path);
        }
        elseif (is_dir($path))
        {
          functions::deltree($path, PHPWG_ROOT_PATH.'_trash');
        }
      }
    }
  }

  static function upgrade_to($upgrade_to, &$step, $check_current_version=true)
  {
    global $page, $conf, $template;

    if ($check_current_version and !version_compare($upgrade_to, PHPWG_VERSION, '>'))
    {
      // TODO why redirect to a plugin page? maybe a remaining code from when
      // the update system was provided as a plugin?
      \Piwigo\inc\functions::redirect(functions_url::get_root_url().'admin.php?page=plugin-'.basename(dirname(__FILE__)));
    }

    $obsolete_list = null;

    if ($step == 2)
    {
      $code = \Piwigo\inc\functions::get_branch_from_version(PHPWG_VERSION).'.x_to_'.$upgrade_to;
      $dl_code = str_replace(array('.', '_'), '', $code);
      $remove_path = $code;
      // no longer try to delete files on a minor upgrade
      // $obsolete_list = 'obsolete.list';
    }
    else
    {
      $code = $upgrade_to;
      $dl_code = $code;
      $remove_path = version_compare($code, '2.0.8', '>=') ? 'piwigo' : 'piwigo-'.$code;
      $obsolete_list = PHPWG_ROOT_PATH.'install/obsolete.list';
    }

    if (empty($page['errors']))
    {
      $path = PHPWG_ROOT_PATH.$conf['data_location'].'update';
      $filename = $path.'/'.$code.'.zip';
      @\Piwigo\inc\functions::mkgetdir($path);

      $chunk_num = 0;
      $end = false;
      $zip = @fopen($filename, 'w');

      while (!$end)
      {
        $chunk_num++;
        if (@functions::fetchRemote(PHPWG_URL.'/download/dlcounter.php?code='.$dl_code.'&chunk_num='.$chunk_num, $result)
          and $input = @unserialize($result))
        {
          if (0 == $input['remaining'])
          {
            $end = true;
          }
          @fwrite($zip, base64_decode($input['data']));
        }
        else
        {
          $end = true;
        }
      }
      @fclose($zip);

      if (@filesize($filename))
      {
        $zip = new PclZip($filename);
        if ($result = $zip->extract(PCLZIP_OPT_PATH, PHPWG_ROOT_PATH,
                                    PCLZIP_OPT_REMOVE_PATH, $remove_path,
                                    PCLZIP_OPT_SET_CHMOD, 0755,
                                    PCLZIP_OPT_REPLACE_NEWER))
        {
          //Check if all files were extracted
          $error = '';
          foreach($result as $extract)
          {
            if (!in_array($extract['status'], array('ok', 'filtered', 'already_a_directory')))
            {
              // Try to change chmod and extract
              if (@chmod(PHPWG_ROOT_PATH.$extract['filename'], 0777)
                and ($res = $zip->extract(PCLZIP_OPT_BY_NAME, $remove_path.'/'.$extract['filename'],
                                          PCLZIP_OPT_PATH, PHPWG_ROOT_PATH,
                                          PCLZIP_OPT_REMOVE_PATH, $remove_path,
                                          PCLZIP_OPT_SET_CHMOD, 0755,
                                          PCLZIP_OPT_REPLACE_NEWER))
                and isset($res[0]['status'])
                and $res[0]['status'] == 'ok')
              {
                continue;
              }
              else
              {
                $error .= $extract['filename'].': '.$extract['status']."\n";
              }
            }
          }

          if (empty($error))
          {
            if (!empty($obsolete_list))
            {
              self::process_obsolete_list($obsolete_list);
            }

            functions::deltree(PHPWG_ROOT_PATH.$conf['data_location'].'update');
            functions::invalidate_user_cache(true);
            \Piwigo\inc\functions::conf_update_param('piwigo_installed_version', $upgrade_to);
            \Piwigo\inc\functions::pwg_activity('system', ACTIVITY_SYSTEM_CORE, 'update', array('from_version'=>PHPWG_VERSION, 'to_version'=>$upgrade_to));

            if ($step == 2)
            {
              // only delete compiled templates on minor update. Doing this on
              // a major update might even encounter fatal error if Smarty
              // changes. Anyway, a compiled template purge will be performed
              // by upgrade.php
              $template->delete_compiled_templates();
              \Piwigo\inc\functions::conf_delete_param('fs_quick_check_last_check');

              $page['infos'][] = \Piwigo\inc\functions::l10n('Update Complete');
              $page['infos'][] = $upgrade_to;
              $page['updated_version'] = $upgrade_to;
              $step = -1;
            }
            else
            {
              \Piwigo\inc\functions::redirect(PHPWG_ROOT_PATH.'upgrade.php?now=');
            }
          }
          else
          {
            file_put_contents(PHPWG_ROOT_PATH.$conf['data_location'].'update/log_error.txt', $error);
            
            $page['errors'][] = \Piwigo\inc\functions::l10n(
              'An error has occured during extract. Please check files permissions of your piwigo installation.<br><a href="%s">Click here to show log error</a>.',
              functions_url::get_root_url().$conf['data_location'].'update/log_error.txt'
              );
          }
        }
        else
        {
          functions::deltree(PHPWG_ROOT_PATH.$conf['data_location'].'update');
          $page['errors'][] = \Piwigo\inc\functions::l10n('An error has occured during upgrade.');
        }
      }
      else
      {
        $page['errors'][] = \Piwigo\inc\functions::l10n('Piwigo cannot retrieve upgrade file from server');
      }
    }
  }
}

?>