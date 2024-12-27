<?php

declare(strict_types=1);

/**********************************
 * REQUIRED PATH TO THE TPL FILE */

$TOUR_PATH = PHPWG_PLUGINS_PATH . 'TakeATour/tours/2_9_0/tour.tpl';

/*********************************/

$template->assign('TAT_HAS_ORPHANS', get_orphans() !== []);

// category id for example of delete options. To illustrate the new
// features, we need an album with photos.
if (! isset($_SESSION['TAT_tour29_delete_cat_id'])) {
    $query = <<<SQL
        SELECT category_id
        FROM image_category
        LIMIT 1;
        SQL;
    $rows = query2array($query);
    $_SESSION['TAT_tour29_delete_cat_id'] = count($rows) == 0 ? -1 : $rows[0]['category_id'];
}

if ($_SESSION['TAT_tour29_delete_cat_id'] > 0) {
    $template->assign('TAT_tour29_delete_cat_id', $_SESSION['TAT_tour29_delete_cat_id']);
}

if (! isset($_SESSION['TAT_tour29_image_id'])) {
    $query = <<<SQL
        SELECT id
        FROM images
        ORDER BY id DESC
        LIMIT 1;
        SQL;
    $images = query2array($query);
    $_SESSION['TAT_tour29_image_id'] = count($images) == 0 ? -1 : $images[0]['id'];
}

if ($_SESSION['TAT_tour29_image_id'] > 0) {
    $template->assign('TAT_tour29_image_id', $_SESSION['TAT_tour29_image_id']);
}

$template->assign('TAT_tour29_history_url', 'admin.php?page=stats&year=' . date('Y') . '&month=' . date('n'));

$query = <<<SQL
    SELECT COUNT(*)
    FROM tags;
    SQL;
[$counter] = pwg_db_fetch_row(pwg_query($query));
if ($counter > 0) {
    $template->assign('TAT_tour29_has_tags', true);
}
