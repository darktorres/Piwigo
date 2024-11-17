<?php

declare(strict_types=1);

/**********************************
 * REQUIRED PATH TO THE TPL FILE */

$TOUR_PATH = PHPWG_PLUGINS_PATH . 'TakeATour/tours/2_8_0/tour.tpl';

/*********************************/

$template->assign('TAT_HAS_ORPHANS', get_orphans() !== []);

// category id for notification new features
if (! isset($_SESSION['TAT_cat_id'])) {
    $query = <<<SQL
        SELECT MAX(id) AS cat_id
        FROM categories;
        SQL;
    $row = pwg_db_fetch_assoc(pwg_query($query));
    $_SESSION['TAT_cat_id'] = $row['cat_id'];
}

$template->assign('TAT_cat_id', $_SESSION['TAT_cat_id']);
