<?php

declare(strict_types=1);

/**********************************
 * REQUIRED PATH TO THE TPL FILE */

$TOUR_PATH = PHPWG_PLUGINS_PATH . 'TakeATour/tours/edit_photos/tour.tpl';

/*********************************/

if (defined('IN_ADMIN') && IN_ADMIN) {
    add_event_handler('loc_end_element_set_global', TAT_FC_14(...));
    add_event_handler('loc_end_picture_modify', TAT_FC_16(...));
    add_event_handler('loc_end_picture_modify', TAT_FC_17(...));
}

function TAT_FC_14(): void
{
    global $template;
    $template->set_prefilter('batch_manager_global', TAT_FC_14_prefilter(...));
}

function TAT_FC_14_prefilter(
    string $content
): array|string {
    $search = '<span class="wrap2';
    $replacement = '{counter print=false assign=TAT_FC_14}<span {if $TAT_FC_14==1}id="TAT_FC_14"{/if} class="wrap2';
    $content = str_replace($search, $replacement, $content);
    $search = 'target="_blank">{\'Edit\'';
    $replacement = ">{'Edit'";
    return str_replace($search, $replacement, $content);
}

function TAT_FC_16(): void
{
    global $template;
    $template->set_prefilter('picture_modify', TAT_FC_16_prefilter(...));
}

function TAT_FC_16_prefilter(
    string $content
): array|string {
    $search = "<strong>{'Linked albums'|translate}</strong>";
    $replacement = '<span id="TAT_FC_16"><strong>{\'Linked albums\'|translate}</strong></span>';
    return str_replace($search, $replacement, $content);
}

function TAT_FC_17(): void
{
    global $template;
    $template->set_prefilter('picture_modify', TAT_FC_17_prefilter(...));
}

function TAT_FC_17_prefilter(
    string $content
): array|string {
    $search = "<strong>{'Representation of albums'|translate}</strong>";
    $replacement = '<span id="TAT_FC_17"><strong>{\'Representation of albums\'|translate}</strong></span>';
    return str_replace($search, $replacement, $content);
}

/**********************
 *    Preparse part   *
 **********************/
//picture id
if (isset($_GET['page']) && preg_match('/^photo-(\d+)(?:-(.*))?$/', (string) $_GET['page'], $matches)) {
    $_GET['image_id'] = $matches[1];
}

check_input_parameter('image_id', $_GET, false, PATTERN_ID);
if (isset($_GET['image_id']) && pwg_get_session_var('TAT_image_id') == null) {
    $template->assign('TAT_image_id', $_GET['image_id']);
    pwg_set_session_var('TAT_image_id', $_GET['image_id']);
} elseif (is_numeric(pwg_get_session_var('TAT_image_id'))) {
    $template->assign('TAT_image_id', pwg_get_session_var('TAT_image_id'));
} else {
    $random_function = DB_RANDOM_FUNCTION;
    $query = <<<SQL
        SELECT id
        FROM images
        ORDER BY {$random_function}
        LIMIT 1;
        SQL;
    $row = pwg_db_fetch_assoc(pwg_query($query));
    $template->assign('TAT_image_id', $row['id']);
}

//album id
if (isset($_GET['page']) && preg_match('/^album-(\d+)(?:-(.*))?$/', (string) $_GET['page'], $matches)) {
    $_GET['cat_id'] = $matches[1];
}

check_input_parameter('cat_id', $_GET, false, PATTERN_ID);
if (isset($_GET['cat_id']) && pwg_get_session_var('TAT_cat_id') == null) {
    $template->assign('TAT_cat_id', $_GET['cat_id']);
    pwg_set_session_var('TAT_cat_id', $_GET['cat_id']);
} elseif (is_numeric(pwg_get_session_var('TAT_cat_id'))) {
    $template->assign('TAT_cat_id', pwg_get_session_var('TAT_cat_id'));
} else {
    $random_function = DB_RANDOM_FUNCTION;
    $query = <<<SQL
        SELECT id
        FROM categories
        ORDER BY {$random_function}
        LIMIT 1;
        SQL;
    $row = pwg_db_fetch_assoc(pwg_query($query));
    $template->assign('TAT_cat_id', $row['id']);
}

global $conf;
if (isset($conf['enable_synchronization'])) {
    $template->assign('TAT_FTP', $conf['enable_synchronization']);
}
