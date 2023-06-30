<?php declare(strict_types=1);
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * @package functions\admin\metadata
 */


include_once(PHPWG_ROOT_PATH.'/include/functions_metadata.inc.php');


/**
 * Returns IPTC metadata to sync from a file, depending on IPTC mapping.
 * @toto : clean code (factorize foreach)
 *
 * @param string $file
 * @return array
 */
function get_sync_iptc_data(string $file): array
{
  global $conf;

  $map = $conf['use_iptc_mapping'];

  $iptc = get_iptc_data($file, $map);

  foreach ($iptc as $pwg_key => $value)
  {
    if (in_array($pwg_key, array('date_creation', 'date_available')))
    {
      if (preg_match('/(\d{4})(\d{2})(\d{2})/', $value, $matches))
      {
        $year = $matches[1];
        $month = $matches[2];
        $day = $matches[3];

        if (!checkdate($month, $day, $year))
        {
          // we suppose the year is correct
          $month = 1;
          $day = 1;
        }

        $iptc[$pwg_key] = $year.'-'.$month.'-'.$day;
      }
    }
  }

  if (isset($iptc['keywords']))
  {
    $iptc['keywords'] = metadata_normalize_keywords_string($iptc['keywords']);
  }

  foreach ($iptc as $pwg_key => $value)
  {
    $iptc[$pwg_key] = addslashes($value);
  }

  return $iptc;
}

/**
 * Returns EXIF metadata to sync from a file, depending on EXIF mapping.
 *
 * @param string $file
 * @return array
 */
function get_sync_exif_data(string $file): array
{
  global $conf;

  $exif = get_exif_data($file, $conf['use_exif_mapping']);

  foreach ($exif as $pwg_key => $value)
  {
    if (in_array($pwg_key, array('date_creation', 'date_available')))
    {
      if (is_numeric($value) && (int) $value == $value) 
      {
        // UNIX timestamp
        $exif[$pwg_key] = DateTime::createFromFormat('U', $value)->format('Y-m-d H:i:s');

        if ($exif[$pwg_key] === '0000-00-00' || !validateDate($exif[$pwg_key])) 
        {
          $exif[$pwg_key] = Null;
        }
      } elseif (preg_match('/^(\d{4}).(\d{2}).(\d{2}).(\d{2}).(\d{2}).(\d{2})/', $value, $matches)) 
      {
        // YYYY-MM-DDTHH:MM:SS
        $exif[$pwg_key] = $matches[1].'-'.$matches[2].'-'.$matches[3].' '.$matches[4].':'.$matches[5].':'.$matches[6];
        if ($exif[$pwg_key] === '0000-00-00 00:00:00' || !validateDate($exif[$pwg_key])) 
        {
          $exif[$pwg_key] = Null;
        }
      }
      elseif (preg_match('/^(\d{4}).(\d{2}).(\d{2})/', $value, $matches))
      {
        // YYYY-MM-DD
        $exif[$pwg_key] = $matches[1].'-'.$matches[2].'-'.$matches[3];

        if ($exif[$pwg_key] === '0000-00-00' || !validateDate($exif[$pwg_key], 'Y-m-d')) {
          $exif[$pwg_key] = NULL;
        }
      }
      else
      {
        unset($exif[$pwg_key]);
        continue;
      }
    }

    if (in_array($pwg_key, array('keywords', 'tags')))
    {
      $exif[$pwg_key] = metadata_normalize_keywords_string($exif[$pwg_key]);
    }
    
    if ($exif[$pwg_key] !== Null && !is_numeric($exif[$pwg_key]))
    {
      $exif[$pwg_key] = addslashes($exif[$pwg_key]);
    }
  }

  return $exif;
}

/**
 * @param $date
 * @param string $format
 * @return bool
 */
function validateDate($date, string $format = 'Y-m-d H:i:s'): bool
{
  $d = DateTime::createFromFormat($format, $date);
  return $d && $d->format($format) == $date;
}

/**
 * Get all potential file metadata fields, including IPTC and EXIF.
 *
 * @return string[]
 */
function get_sync_metadata_attributes(): array
{
  global $conf;

  $update_fields = array('filesize', 'width', 'height');

  if ($conf['use_exif'])
  {
    $update_fields =
      array_merge(
        $update_fields,
        array_keys($conf['use_exif_mapping']),
        array('latitude', 'longitude')
        );
  }

  if ($conf['use_iptc'])
  {
    $update_fields =
      array_merge(
        $update_fields,
        array_keys($conf['use_iptc_mapping'])
        );
  }

  return array_unique($update_fields);
}

/**
 * Get all metadata of a file.
 *
 * @param array $infos (path[, representative_ext])
 * @return array|false includes data provided in $infos
 */
function get_sync_metadata(array $infos): false|array
{
  global $conf;
  $file = PHPWG_ROOT_PATH.$infos['path'];
  $fs = filesize($file);

  if ($fs===false)
  {
    return false;
  }

  $infos['filesize'] = floor($fs/1024);

  $is_tiff = false;

  if (isset($infos['representative_ext']))
  {
    if ($image_size = getimagesize($file))
    {
      $type = $image_size[2];

      if (IMAGETYPE_TIFF_MM == $type || IMAGETYPE_TIFF_II == $type)
      {
        // in case of TIFF files, we want to use the original file and not
        // the representative for EXIF/IPTC, but we need the representative
        // for width/height (to compute the multiple size dimensions)
        $is_tiff = true;
      }
    }

    $file = original_to_representative($file, $infos['representative_ext']);
  }

  if (in_array(mime_content_type($file), array('image/svg+xml', 'image/svg')))
  {
    $xml = file_get_contents($file);

    $xmlget = simplexml_load_string($xml);
    $xmlattributes = $xmlget->attributes();
    $width = (int) $xmlattributes->width; 
    $height = (int) $xmlattributes->height;
    $vb = (string) $xmlattributes->viewBox;

    if (isset($width) && $width != "")
    {
      $infos['width'] = $width;
    } elseif (isset($vb))
    {
      $infos['width'] = explode(" ", $vb)[2];
    }

    if (isset($height) && $height != "")
    {
      $infos['height'] = $height;
    } elseif (isset($vb))
    {
      $infos['height'] = explode(" ", $vb)[3];
    }
  }

  if ($image_size = getimagesize($file))
  {
    $infos['width'] = $image_size[0];
    $infos['height'] = $image_size[1];
  }

  if ($is_tiff)
  {
    // back to original file
    $file = PHPWG_ROOT_PATH.$infos['path'];
  }

  if ($conf['use_exif'])
  {
    $exif = get_sync_exif_data($file);
    $infos = array_merge($infos, $exif);
  }

  if ($conf['use_iptc'])
  {
    $iptc = get_sync_iptc_data($file);
    $infos = array_merge($infos, $iptc);
  }

  return $infos;
}

/**
 * Sync all metadata of a list of images.
 * Metadata are fetched from original files and saved in database.
 *
 * @param int[] $ids
 */
function sync_metadata(array $ids): void
{
  global $conf;

  if (!defined('CURRENT_DATE'))
  {
    define('CURRENT_DATE', date('Y-m-d'));
  }

  $datas = array();
  $tags_of = array();

  $query = '
SELECT id, path, representative_ext
  FROM '.IMAGES_TABLE.'
  WHERE id IN (
'.wordwrap(implode(', ', $ids), 160).'
)
;';

  $result = pwg_query($query);
  while ($data = pwg_db_fetch_assoc($result))
  {
    $data = get_sync_metadata($data);
    if ($data === false)
    {
      continue;
    }
    // print_r($data);
    $id = $data['id'];
    foreach (array('keywords', 'tags') as $key)
    {
      if (isset($data[$key]))
      {
        if (!isset($tags_of[$id]))
        {
          $tags_of[$id] = array();
        }

        foreach (explode(',', $data[$key]) as $tag_name)
        {
          $tags_of[$id][] = tag_id_from_tag_name($tag_name);
        }
      }
    }

    $data['date_metadata_update'] = CURRENT_DATE;
    
    $datas[] = $data;
  }

  if (count($datas) > 0)
  {
    $update_fields = get_sync_metadata_attributes();
    $update_fields[] = 'date_metadata_update';

    $update_fields = array_diff(
      $update_fields,
      array('tags', 'keywords')
            );

    mass_updates(
      IMAGES_TABLE,
      array(
        'primary' => array('id'),
        'update'  => $update_fields
        ),
      $datas,
      MASS_UPDATES_SKIP_EMPTY
      );
  }

  set_tags_of($tags_of);
}

/**
 * Returns an array associating element id (images.id) with its complete
 * path in the filesystem
 *
 * @param int|string $category_id
 * @param int $site_id
 * @param bool $recursive
 * @param bool $only_new
 * @return array
 */
function get_filelist(int|string $category_id = '', int $site_id=1, bool $recursive = false,
                      bool       $only_new = false): array
{
  // filling $cat_ids : all categories required
  $cat_ids = array();

  $query = '
SELECT id
  FROM '.CATEGORIES_TABLE.'
  WHERE site_id = '.$site_id.'
    AND dir IS NOT NULL';
  if (is_numeric($category_id))
  {
    if ($recursive)
    {
      $query.= '
    AND uppercats '.DB_REGEX_OPERATOR.' \'(^|,)'.$category_id.'(,|$)\'
';
    }
    else
    {
      $query.= '
    AND id = '.$category_id.'
';
    }
  }
  $query.= '
;';
  $result = pwg_query($query);
  while ($row = pwg_db_fetch_assoc($result))
  {
    $cat_ids[] = $row['id'];
  }

  if (count($cat_ids) == 0)
  {
    return array();
  }

  $query = '
SELECT id, path, representative_ext
  FROM '.IMAGES_TABLE.'
  WHERE storage_category_id IN ('.implode(',', $cat_ids).')';
  if ($only_new)
  {
    $query.= '
    AND date_metadata_update IS NULL
';
  }
  $query.= '
;';
  return query2array($query, 'id');
}

/**
 * Returns the list of keywords (future tags) correctly separated with
 * commas. Other separators are converted into commas.
 *
 * @param string $keywords_string
 * @return string
 */
function metadata_normalize_keywords_string(string $keywords_string): string
{
  global $conf;
  
  $keywords_string = preg_replace($conf['metadata_keyword_separator_regex'], ',', $keywords_string);
  $keywords_string = preg_replace('/,+/', ',', $keywords_string);
  $keywords_string = trim($keywords_string, ",");
      
  return implode(
    ',',
    array_unique(
      explode(
        ',',
        $keywords_string
        )
      )
    );
}
