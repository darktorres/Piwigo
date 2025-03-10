<?php
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

//--------------------------------------------------------------------- include
use Piwigo\inc\dblayer\functions_mysqli;
use Piwigo\inc\functions;
use Piwigo\inc\functions_plugins;
use Piwigo\inc\functions_search;
use Piwigo\inc\functions_tag;
use Piwigo\inc\functions_user;

define('PHPWG_ROOT_PATH','./');
include_once( PHPWG_ROOT_PATH.'inc/common.php' );
include_once(PHPWG_ROOT_PATH.'inc/functions_search.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
functions_user::check_status(ACCESS_GUEST);

functions_plugins::trigger_notify('loc_begin_search');

// +-----------------------------------------------------------------------+
// | Create a default search                                               |
// +-----------------------------------------------------------------------+

$search = array(
  'mode' => 'AND',
  'fields' => array()
);

// list of filters in user preferences
// allwords, cat, tags, author, added_by, filetypes, date_posted
$default_fields = array('allwords', 'cat', 'tags', 'author');
if (functions_user::is_a_guest() or functions_user::is_generic())
{
  $fields = $default_fields;
}
else
{
  $fields = functions_user::userprefs_get_param('gallery_search_filters', $default_fields);
}

$words = array();
if (!empty($_GET['q']))
{
  $words = functions_search::split_allwords($_GET['q']);
}

if (count($words) > 0 or in_array('allwords', $fields))
{
  $search['fields']['allwords'] = array(
    'words' => $words,
    'mode' => 'AND',
    'fields' => array('file', 'name', 'comment', 'tags', 'author', 'cat-title', 'cat-desc'),
  );
}

$cat_ids = array();
if (isset($_GET['cat_id']))
{
  functions::check_input_parameter('cat_id', $_GET, false, PATTERN_ID);
  $cat_ids = array($_GET['cat_id']);
}

if (count($cat_ids) > 0 or in_array('cat', $fields))
{
  $search['fields']['cat'] = array(
    'words' => $cat_ids,
    'sub_inc' => true,
  );
}

if (count(functions_tag::get_available_tags()) > 0)
{
  $tag_ids = array();
  if (isset($_GET['tag_id']))
  {
    functions::check_input_parameter('tag_id', $_GET, false, '/^\d+(,\d+)*$/');
    $tag_ids = explode(',', $_GET['tag_id']);
  }

  if (count($tag_ids) > 0 or in_array('tags', $fields))
  {
    $search['fields']['tags'] = array(
      'words' => $tag_ids,
      'mode'  => 'AND',
    );
  }
}

if (in_array('author', $fields))
{
  // does this Piwigo has authors for current user?
  $query = '
SELECT
    id
  FROM '.IMAGES_TABLE.' AS i
    JOIN '.IMAGE_CATEGORY_TABLE.' AS ic ON ic.image_id = i.id
  '.functions_user::get_sql_condition_FandF(
    array(
      'forbidden_categories' => 'category_id',
      'visible_categories' => 'category_id',
      'visible_images' => 'id'
      ),
    ' WHERE '
    ).'
    AND author IS NOT NULL
    LIMIT 1
;';
  $first_author = functions_mysqli::query2array($query);

  if (count($first_author) > 0)
  {
    $search['fields']['author'] = array(
      'words' => array(),
      'mode' => 'OR',
    );
  }
}

foreach (array('added_by', 'filetypes', 'date_posted') as $field)
{
  if (in_array($field, $fields))
  {
    $search['fields'][$field] = array();
  }
}

list($search_uuid, $search_url) = functions_search::save_search($search);
functions::redirect($search_url);
?>
