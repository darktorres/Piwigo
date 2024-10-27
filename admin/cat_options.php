<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
include_once(PHPWG_ROOT_PATH . 'admin/include/tabsheet.class.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_ADMINISTRATOR);

if ($_POST !== []) {
    check_pwg_token();
    check_input_parameter('cat_true', $_POST, true, PATTERN_ID);
    check_input_parameter('cat_false', $_POST, true, PATTERN_ID);
    check_input_parameter('section', $_GET, false, '/^[a-z0-9_-]+$/i');
}

// +-----------------------------------------------------------------------+
// |                       modification registration                       |
// +-----------------------------------------------------------------------+

if (isset($_POST['falsify']) && isset($_POST['cat_true']) && count($_POST['cat_true']) > 0) {
    switch ($_GET['section']) {
        case 'comments':

            $cat_true_ = implode(',', $_POST['cat_true']);
            $query = "UPDATE categories SET commentable = 'false' WHERE id IN ({$cat_true_});";
            pwg_query($query);
            break;

        case 'visible':

            set_cat_visible($_POST['cat_true'], 'false');
            break;

        case 'status':

            set_cat_status($_POST['cat_true'], 'private');
            break;

        case 'representative':

            $cat_true_ = implode(',', $_POST['cat_true']);
            $query = "UPDATE categories SET representative_picture_id = NULL WHERE id IN ({$cat_true_});";
            pwg_query($query);
            break;

    }

    pwg_activity('album', $_POST['cat_true'], 'edit', [
        'section' => $_GET['section'],
        'action' => 'falsify',
    ]);
} elseif (isset($_POST['trueify']) && isset($_POST['cat_false']) && count($_POST['cat_false']) > 0) {
    switch ($_GET['section']) {
        case 'comments':

            $cat_false_ = implode(',', $_POST['cat_false']);
            $query = "UPDATE categories SET commentable = 'true' WHERE id IN ({$cat_false_});";
            pwg_query($query);
            break;

        case 'visible':

            set_cat_visible($_POST['cat_false'], 'true');
            break;

        case 'status':

            set_cat_status($_POST['cat_false'], 'public');
            break;

        case 'representative':

            // theoretically, all categories in $_POST['cat_false'] contain at
            // least one element, so Piwigo can find a representant.
            set_random_representant($_POST['cat_false']);
            break;

    }

    pwg_activity('album', $_POST['cat_false'], 'edit', [
        'section' => $_GET['section'],
        'action' => 'trueify',
    ]);
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->set_filenames(
    [
        'cat_options' => 'cat_options.tpl',
        'double_select' => 'double_select.tpl',
    ]
);

$page['section'] = $_GET['section'] ?? 'status';
$base_url = PHPWG_ROOT_PATH . 'admin.php?page=cat_options&amp;section=';

$template->assign(
    [
        'U_HELP' => get_root_url() . 'admin/popuphelp.php?page=cat_options',
        'F_ACTION' => $base_url . $page['section'],
    ]
);

// TabSheet
$tabsheet = new tabsheet();
$tabsheet->set_id('cat_options');
$tabsheet->select($page['section']);
$tabsheet->assign();

// +-----------------------------------------------------------------------+
// |                              form display                             |
// +-----------------------------------------------------------------------+

// for each section, categories in the multiselect field can be :
//
// - true : commentable for comment section
// - false : un-commentable for comment section
// - NA : (not applicable) for virtual categories
//
// for true and false status, we associates an array of category ids,
// function display_select_categories will use the given CSS class for each
// option
$cats_true = [];
$cats_false = [];
switch ($page['section']) {
    case 'comments':

        $query_true = "SELECT id, name, uppercats, global_rank FROM categories WHERE commentable = 'true';";
        $query_false = "SELECT id, name, uppercats, global_rank FROM categories WHERE commentable = 'false';";
        $template->assign(
            [
                'L_SECTION' => l10n('Authorize users to add comments on selected albums'),
                'L_CAT_OPTIONS_TRUE' => l10n('Authorized'),
                'L_CAT_OPTIONS_FALSE' => l10n('Forbidden'),
            ]
        );
        break;

    case 'visible':

        $query_true = "SELECT id, name, uppercats, global_rank FROM categories WHERE visible = 'true';";
        $query_false = "SELECT id, name, uppercats, global_rank FROM categories WHERE visible = 'false';";
        $template->assign(
            [
                'L_SECTION' => l10n('Lock albums'),
                'L_CAT_OPTIONS_TRUE' => l10n('Unlocked'),
                'L_CAT_OPTIONS_FALSE' => l10n('Locked'),
            ]
        );
        break;

    case 'status':

        $query_true = "SELECT id, name, uppercats, global_rank FROM categories WHERE status = 'public';";
        $query_false = "SELECT id, name, uppercats, global_rank FROM categories WHERE status = 'private';";
        $template->assign(
            [
                'L_SECTION' => l10n('Manage authorizations for selected albums'),
                'L_CAT_OPTIONS_TRUE' => l10n('Public'),
                'L_CAT_OPTIONS_FALSE' => l10n('Private'),
            ]
        );
        break;

    case 'representative':

        $query_true = 'SELECT id, name, uppercats, global_rank FROM categories WHERE representative_picture_id IS NOT NULL;';
        $query_false = 'SELECT DISTINCT id, name, uppercats, global_rank FROM categories INNER JOIN image_category ON id = category_id WHERE representative_picture_id IS NULL;';
        $template->assign(
            [
                'L_SECTION' => l10n('Representative'),
                'L_CAT_OPTIONS_TRUE' => l10n('singly represented'),
                'L_CAT_OPTIONS_FALSE' => l10n('randomly represented'),
            ]
        );
        break;

}

display_select_cat_wrapper($query_true, [], 'category_option_true');
display_select_cat_wrapper($query_false, [], 'category_option_false');
$template->assign('PWG_TOKEN', get_pwg_token());
$template->assign('ADMIN_PAGE_TITLE', l10n('Properties of abums'));

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('DOUBLE_SELECT', 'double_select');
$template->assign_var_from_handle('ADMIN_CONTENT', 'cat_options');
