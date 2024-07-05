<?php

use Piwigo\inc\dblayer\Mysqli;
use Piwigo\inc\FunctionsSession;
use function Piwigo\inc\check_input_parameter;
use function Piwigo\inc\get_root_url;
use function Piwigo\inc\make_index_url;

/**********************************
 * REQUIRED PATH TO THE TPL FILE */

$TOUR_PATH = PHPWG_PLUGINS_PATH . 'TakeATour/tours/2_7_0/tour.tpl';

/*********************************/

/**********************
 *    Preparse part   *
 **********************/
$template->assign(
    'TAT_index',
    make_index_url([
        'section' => 'categories',
    ])
);
$template->assign('TAT_search', get_root_url() . 'search.php');

//picture id
if (isset($_GET['page']) && preg_match('/^photo-(\d+)(?:-(.*))?$/', (string) $_GET['page'], $matches)) {
    $_GET['image_id'] = $matches[1];
}

check_input_parameter('image_id', $_GET, false, PATTERN_ID);
if (isset($_GET['image_id']) && FunctionsSession::pwg_get_session_var('TAT_image_id') == null) {
    $template->assign('TAT_image_id', $_GET['image_id']);
    FunctionsSession::pwg_set_session_var('TAT_image_id', $_GET['image_id']);
} elseif (is_numeric(FunctionsSession::pwg_get_session_var('TAT_image_id'))) {
    $template->assign('TAT_image_id', FunctionsSession::pwg_get_session_var('TAT_image_id'));
} else {
    $query = '
    SELECT id
      FROM ' . IMAGES_TABLE . '
      ORDER BY RAND()
      LIMIT 1  
    ;';
    $row = Mysqli::pwg_db_fetch_assoc(Mysqli::pwg_query($query));
    $template->assign('TAT_image_id', $row['id']);
}
