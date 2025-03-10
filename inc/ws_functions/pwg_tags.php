<?php
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

namespace Piwigo\inc\ws_functions;

use Piwigo\admin\inc\functions_admin;
use Piwigo\inc\dblayer\functions_mysqli;
use Piwigo\inc\functions;
use Piwigo\inc\functions_plugins;
use Piwigo\inc\functions_tag;
use Piwigo\inc\functions_url;
use Piwigo\inc\PwgError;
use Piwigo\inc\PwgNamedArray;
use Piwigo\inc\PwgNamedStruct;
use Piwigo\inc\ws_functions;

class pwg_tags
{
  /**
   * API method
   * Returns a list of tags
   * @param array{
   *     sort_by_counter: bool,
   * } $params
   */
  static function ws_tags_getList($params, &$service)
  {
    $tags = functions_tag::get_available_tags();
    if ($params['sort_by_counter'])
    {
      usort($tags, function($a, $b) {  return -$a["counter"]+$b["counter"]; });
    }
    else
    {
      usort($tags, '\Piwigo\inc\functions_html::tag_alpha_compare');
    }

    for ($i=0; $i<count($tags); $i++)
    {
      $tags[$i]['id'] = (int)$tags[$i]['id'];
      $tags[$i]['counter'] = (int)$tags[$i]['counter'];
      $tags[$i]['url'] = functions_url::make_index_url(
        array(
          'section'=>'tags',
          'tags'=>array($tags[$i])
          )
        );
    }

    return array(
      'tags' => new PwgNamedArray(
        $tags,
        'tag',
        ws_functions::ws_std_get_tag_xml_attributes()
        )
      );
  }

  /**
   * API method
   * Returns the list of tags as you can see them in administration
   * @param array $params
   *
   * Only admin can run this method and permissions are not taken into
   * account.
   */
  static function ws_tags_getAdminList($params, &$service)
  {
    return array(
      'tags' => new PwgNamedArray(
        functions_tag::get_all_tags(),
        'tag',
        ws_functions::ws_std_get_tag_xml_attributes()
        )
      );
  }

  /**
   * API method
   * Returns a list of images for tags
   * @param array{
   *     tag_id?: int[], 
   *     tag_url_name?: string[], 
   *     tag_name?: string[], 
   *     tag_mode_and: bool, 
   *     per_page: int, 
   *     page: int, 
   *     order: string,
   * } $params
   */
  static function ws_tags_getImages($params, &$service)
  {
    // first build all the tag_ids we are interested in
    $tags = functions_tag::find_tags($params['tag_id'], $params['tag_url_name'], $params['tag_name']);
    $tags_by_id = array();
    foreach ($tags as $tag)
    {
      $tags['id'] = (int)$tag['id'];
      $tags_by_id[ $tag['id'] ] = $tag;
    }
    unset($tags);
    $tag_ids = array_keys($tags_by_id);

    $where_clauses = ws_functions::ws_std_image_sql_filter($params);
    if (!empty($where_clauses))
    {
      $where_clauses = implode(' AND ', $where_clauses);
    }

    $order_by = ws_functions::ws_std_image_sql_order($params, 'i.');
    if (!empty($order_by))
    {
      $order_by = 'ORDER BY '.$order_by;
    }
    $image_ids = functions_tag::get_image_ids_for_tags(
      $tag_ids,
      $params['tag_mode_and'] ? 'AND' : 'OR',
      $where_clauses,
      $order_by
      );

    $count_set = count($image_ids);
    $image_ids = array_slice($image_ids, $params['per_page']*$params['page'], $params['per_page'] );

    $image_tag_map = array();
    // build list of image ids with associated tags per image
    if (!empty($image_ids) and !$params['tag_mode_and'])
    {
      $query = '
  SELECT image_id, GROUP_CONCAT(tag_id) AS tag_ids
    FROM '. IMAGE_TAG_TABLE .'
    WHERE tag_id IN ('. implode(',', $tag_ids) .')
      AND image_id IN ('. implode(',', $image_ids) .')
    GROUP BY image_id
  ;';
      $result = functions_mysqli::pwg_query($query);

      while ($row = functions_mysqli::pwg_db_fetch_assoc($result))
      {
        $row['image_id'] = (int)$row['image_id'];
        $image_tag_map[ $row['image_id'] ] = explode(',', $row['tag_ids']);
      }
    }

    $images = array();
    if (!empty($image_ids))
    {
      $rank_of = array_flip($image_ids);
      $favorite_ids = functions_url::get_user_favorites();

      $query = '
  SELECT *
    FROM '. IMAGES_TABLE .'
    WHERE id IN ('. implode(',',$image_ids) .')
  ;';
      $result = functions_mysqli::pwg_query($query);

      while ($row = functions_mysqli::pwg_db_fetch_assoc($result))
      {
        $image = array();
        $image['rank'] = $rank_of[ $row['id'] ];
        $image['is_favorite'] = isset($favorite_ids[ $row['id'] ]);

        foreach (array('id', 'width', 'height', 'hit') as $k)
        {
          if (isset($row[$k]))
          {
            $image[$k] = (int)$row[$k];
          }
        }
        foreach (array('file', 'name', 'comment', 'date_creation', 'date_available') as $k)
        {
          $image[$k] = $row[$k];
        }
        $image = array_merge( $image, ws_functions::ws_std_get_urls($row) );

        $image_tag_ids = ($params['tag_mode_and']) ? $tag_ids : $image_tag_map[$image['id']];
        $image_tags = array();
        foreach ($image_tag_ids as $tag_id)
        {
          $url = functions_url::make_index_url(
            array(
              'section'=>'tags',
              'tags'=> array($tags_by_id[$tag_id])
              )
            );
          $page_url = functions_url::make_picture_url(
            array(
              'section'=>'tags',
              'tags'=> array($tags_by_id[$tag_id]),
              'image_id' => $row['id'],
              'image_file' => $row['file'],
              )
            );
          $image_tags[] = array(
            'id' => (int)$tag_id,
            'url' => $url,
            'page_url' => $page_url,
            );
        }

        $image['tags'] = new PwgNamedArray($image_tags, 'tag', ws_functions::ws_std_get_tag_xml_attributes() );
        $images[] = $image;
      }

      usort($images, '\Piwigo\inc\functions_category::rank_compare');
      unset($rank_of);
    }

    return array(
      'paging' => new PwgNamedStruct(
        array(
          'page' => $params['page'],
          'per_page' => $params['per_page'],
          'count' => count($images),
          'total_count' => $count_set,
          )
        ),
      'images' => new PwgNamedArray(
        $images,
        'image',
        ws_functions::ws_std_get_image_xml_attributes()
        )
      );
  }

  /**
   * API method
   * Adds a tag
   * @param array{
   *     name: string,
   * } $params
   */
  static function ws_tags_add($params, &$service)
  {
    include_once(PHPWG_ROOT_PATH.'admin/inc/functions_admin.php');

    $creation_output = functions_admin::create_tag($params['name']);

    if (isset($creation_output['error']))
    {
      return new PwgError(WS_ERR_INVALID_PARAM, $creation_output['error']);
    }

    functions::pwg_activity('tag', $creation_output['id'], 'add');

    $query = '
  SELECT name, url_name 
  FROM `'.TAGS_TABLE.'`
  WHERE id = '.$creation_output['id'].';';

  $new_tag = functions_mysqli::query2array($query);

    return array(
      'info' => $creation_output['info'],
      'id' => $creation_output['id'],
      'name' => $new_tag[0]['name'],
      'url_name' => $new_tag[0]['url_name']
    );
  }

  static function ws_tags_delete($params, &$service) 
  {
    include_once(PHPWG_ROOT_PATH.'admin/inc/functions_admin.php');

    if (functions::get_pwg_token() != $params['pwg_token'])
    {
      return new PwgError(403, 'Invalid security token');
    }

  $query = '
  SELECT COUNT(*)
    FROM `'. TAGS_TABLE .'`
    WHERE id in ('.implode(',', $params['tag_id']) .')
  ;';
    list($count) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query($query));
    if ($count != count($params['tag_id']))
    {
      return new PwgError(WS_ERR_INVALID_PARAM, 'All tags does not exist.');
    }


    $tag_ids = $params['tag_id'];

    if (count($tag_ids) > 0) 
    {
      functions_admin::delete_tags($params['tag_id']);
      return array('id' => $tag_ids);
    } else {
      return array('id' => array());
    }
  }

  static function ws_tags_rename($params, &$service) 
  {
    include_once(PHPWG_ROOT_PATH.'admin/inc/functions_admin.php');

    if (functions::get_pwg_token() != $params['pwg_token'])
    {
      return new PwgError(403, 'Invalid security token');
    }

    $tag_id = $params['tag_id'];
    $tag_name = strip_tags(stripslashes($params['new_name']));

    // does the tag exist ?
    $query = '
  SELECT COUNT(*)
    FROM `'. TAGS_TABLE .'`
    WHERE id = '. $tag_id .'
  ;';
    list($count) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query($query));
    if ($count == 0)
    {
      return new PwgError(WS_ERR_INVALID_PARAM, 'This tag does not exist.');
    }

    $query = '
  SELECT name
    FROM '.TAGS_TABLE.'
    WHERE id != '.$tag_id.'
  ;';
    $existing_names = functions::array_from_query($query, 'name');

    $update = array();

    if (in_array($tag_name, $existing_names))
    {
      return new PwgError(WS_ERR_INVALID_PARAM, 'This name is already token');
    }
    else if (!empty($tag_name))
    {
      $update = array(
        'name' => functions_mysqli::pwg_db_real_escape_string($tag_name),
        'url_name' => functions_plugins::trigger_change('render_tag_url', $tag_name),
      );

    }

    functions::pwg_activity('tag', $tag_id, 'edit');

    functions_mysqli::single_update(
      TAGS_TABLE,
      $update,
      array('id' => $tag_id)
      );

    $query = '
  SELECT
      id,
      name,
      url_name
    FROM '.TAGS_TABLE.'
    WHERE id = '.$tag_id.'
  ;';

    return functions_mysqli::query2array($query)[0];
  }


  static function ws_tags_duplicate($params, &$service)
  {

    include_once(PHPWG_ROOT_PATH.'admin/inc/functions_admin.php');

    if (functions::get_pwg_token() != $params['pwg_token'])
    {
      return new PwgError(403, 'Invalid security token');
    }

    $tag_id = $params['tag_id'];
    $copy_name = $params['copy_name'];

    // does the tag exist ?
    $query = '
  SELECT COUNT(*)
    FROM `'. TAGS_TABLE .'`
    WHERE id = '. $tag_id .'
  ;';
    list($count) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query($query));
    if ($count == 0)
    {
      return new PwgError(WS_ERR_INVALID_PARAM, 'This tag does not exist.');
    }

    $query = '
  SELECT COUNT(*)
    FROM `'. TAGS_TABLE .'`
    WHERE name = "'. $copy_name .'"
  ;';
    list($count) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query($query));
    if ($count != 0)
    {
      return new PwgError(WS_ERR_INVALID_PARAM, 'This name is already taken.');
    }

    
    functions_mysqli::single_insert(
      TAGS_TABLE,
      array(
        'name' => $copy_name,
        'url_name' => functions_plugins::trigger_change('render_tag_url', $copy_name),
      )
    );
    $destination_tag_id = functions_mysqli::pwg_db_insert_id(TAGS_TABLE);

    functions::pwg_activity('tag', $destination_tag_id, 'add', array('action'=>'duplicate', 'source_tag'=>$tag_id));

    $query = '
  SELECT image_id
    FROM '.IMAGE_TAG_TABLE.'
    WHERE tag_id = '.$tag_id.'
  ;';
    $destination_tag_image_ids = functions::array_from_query($query, 'image_id');

    $inserts = array();
          
    foreach ($destination_tag_image_ids as $image_id)
    {
      $inserts[] = array(
        'tag_id' => $destination_tag_id,
        'image_id' => $image_id
      );
      functions::pwg_activity('photo', $image_id, 'edit', array("add-tag" => $destination_tag_id));
    }

    if (count($inserts) > 0)
    {
      functions_mysqli::mass_inserts(
        IMAGE_TAG_TABLE,
        array_keys($inserts[0]),
        $inserts
      );
    }

    return array(
      'id' => $destination_tag_id,
      'name' => $copy_name,
      'url_name' => functions_plugins::trigger_change('render_tag_url', $copy_name),
      'count' => count($inserts)
    );
  } 

  static function ws_tags_merge($params, &$service)
  {

    if (functions::get_pwg_token() != $params['pwg_token'])
    {
      return new PwgError(403, 'Invalid security token');
    }

    $all_tags = $params['merge_tag_id'];
    array_push($all_tags, $params['destination_tag_id']);

    $all_tags = array_unique($all_tags);
    $merge_tag = array_diff($params['merge_tag_id'], array($params['destination_tag_id']));

    $query = '
  SELECT COUNT(*)
    FROM `'. TAGS_TABLE .'`
    WHERE id in ('.implode(',', $all_tags) .')
  ;';
    list($count) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query($query));
    if ($count != count($all_tags))
    {
      return new PwgError(WS_ERR_INVALID_PARAM, 'All tags does not exist.');
    }

    $image_in_merge_tags = array();
    $image_in_dest = array();
    $image_to_add = array();

    $query = '
  SELECT DISTINCT(image_id) 
    FROM `'. IMAGE_TAG_TABLE .'` 
    WHERE 
      tag_id IN ('.implode(',', $merge_tag) .')
  ;';
    $image_in_merge_tags = functions_mysqli::query2array($query, null, 'image_id');

    $query = '
  SELECT image_id 
    FROM `'. IMAGE_TAG_TABLE .'` 
    WHERE tag_id = '.$params['destination_tag_id'].'
  ;';

    $image_in_dest = functions_mysqli::query2array($query, null, 'image_id');;

    
    $image_to_add = array_diff($image_in_merge_tags, $image_in_dest);

    $inserts = array();
    foreach ($image_to_add as $image)
    {
      $inserts[] = array(
        'tag_id' => $params['destination_tag_id'],
        'image_id' => $image,
        );
    }

    functions_mysqli::mass_inserts(
      IMAGE_TAG_TABLE,
      array('tag_id', 'image_id'),
      $inserts,
      array('ignore'=>true)
      );

    functions::pwg_activity('tag', $params['destination_tag_id'], 'edit');
    foreach ($image_to_add as $image_id) 
    {
      functions::pwg_activity('photo', $image_id, 'edit', array("tag-add" => $params['destination_tag_id']));
    }

    include_once(PHPWG_ROOT_PATH.'admin/inc/functions_admin.php');

    functions_admin::delete_tags($merge_tag);

    $image_in_merged = array_merge($image_in_dest, $image_to_add);

    return array(
      "destination_tag" => $params['destination_tag_id'],
      "deleted_tag" => $params['merge_tag_id'],
      "images_in_merged_tag" => $image_in_merged
    );
  }
}

?>
