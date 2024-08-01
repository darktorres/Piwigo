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

if (! is_webmaster()) {
    $page['warnings'][] = str_replace('%s', l10n('user_status_webmaster'), l10n('%s status is required to edit parameters.'));
}

include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
include_once(PHPWG_ROOT_PATH . 'admin/include/functions_upload.inc.php');
include_once(PHPWG_ROOT_PATH . 'admin/include/tabsheet.class.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_ADMINISTRATOR);

//-------------------------------------------------------- sections definitions

check_input_parameter('section', $_GET, false, '/^[a-z]+$/i');

$page['section'] = $_GET['section'] ?? 'main';

$main_checkboxes = [
    'allow_user_registration',
    'obligatory_user_mail_address',
    'rate',
    'rate_anonymous',
    'allow_user_customization',
    'log',
    'history_admin',
    'history_guest',
    'show_mobile_app_banner_in_gallery',
    'show_mobile_app_banner_in_admin',
    'upload_detect_duplicate',
];

$sizes_checkboxes = [
    'original_resize',
];

$comments_checkboxes = [
    'activate_comments',
    'comments_forall',
    'comments_validation',
    'email_admin_on_comment',
    'email_admin_on_comment_validation',
    'user_can_delete_comment',
    'user_can_edit_comment',
    'email_admin_on_comment_edition',
    'email_admin_on_comment_deletion',
    'comments_author_mandatory',
    'comments_email_mandatory',
    'comments_enable_website',
];

$display_checkboxes = [
    'menubar_filter_icon',
    'index_search_in_set_button',
    'index_search_in_set_action',
    'index_sort_order_input',
    'index_flat_icon',
    'index_posted_date_icon',
    'index_created_date_icon',
    'index_slideshow_icon',
    'index_sizes_icon',
    'index_new_icon',
    'index_edit_icon',
    'index_caddie_icon',
    'display_fromto',
    'picture_metadata_icon',
    'picture_slideshow_icon',
    'picture_favorite_icon',
    'picture_sizes_icon',
    'picture_download_icon',
    'picture_edit_icon',
    'picture_caddie_icon',
    'picture_representative_icon',
    'picture_navigation_icons',
    'picture_navigation_thumb',
    'picture_menu',
];

$display_info_checkboxes = [
    'author',
    'created_on',
    'posted_on',
    'dimensions',
    'file',
    'filesize',
    'tags',
    'categories',
    'visits',
    'rating_score',
    'privacy_level',
];

// image order management
$sort_fields = [
    '' => '',
    'file ASC' => l10n('File name, A &rarr; Z'),
    'file DESC' => l10n('File name, Z &rarr; A'),
    'name ASC' => l10n('Photo title, A &rarr; Z'),
    'name DESC' => l10n('Photo title, Z &rarr; A'),
    'date_creation DESC' => l10n('Date created, new &rarr; old'),
    'date_creation ASC' => l10n('Date created, old &rarr; new'),
    'date_available DESC' => l10n('Date posted, new &rarr; old'),
    'date_available ASC' => l10n('Date posted, old &rarr; new'),
    'rating_score DESC' => l10n('Rating score, high &rarr; low'),
    'rating_score ASC' => l10n('Rating score, low &rarr; high'),
    'hit DESC' => l10n('Visits, high &rarr; low'),
    'hit ASC' => l10n('Visits, low &rarr; high'),
    'id ASC' => l10n('Numeric identifier, 1 &rarr; 9'),
    'id DESC' => l10n('Numeric identifier, 9 &rarr; 1'),
    'rank_column ASC' => l10n('Manual sort order'),
];

$comments_order = [
    'ASC' => l10n('Show oldest comments first'),
    'DESC' => l10n('Show latest comments first'),
];

$mail_themes = [
    'clear' => 'Clear',
    'dark' => 'Dark',
];

//------------------------------ verification and registration of modifications
if (isset($_POST['submit'])) {
    check_pwg_token();
    $int_pattern = '/^\d+$/';

    switch ($page['section']) {
        case 'main':

            if (! isset($conf['order_by_custom']) && ! isset($conf['order_by_inside_category_custom'])) {
                if (! empty($_POST['order_by'])) {
                    check_input_parameter('order_by', $_POST, true, '/^(' . implode('|', array_keys($sort_fields)) . ')$/');

                    $used = [];
                    foreach ($_POST['order_by'] as $i => $val) {
                        if (empty($val) || isset($used[$val])) {
                            unset($_POST['order_by'][$i]);
                        } else {
                            $used[$val] = true;
                        }
                    }

                    if (count($_POST['order_by']) === 0) {
                        $page['errors'][] = l10n('No order field selected');
                    } else {
                        // limit to the number of available parameters
                        $order_by = array_slice($_POST['order_by'], 0, ceil(count($sort_fields) / 2));
                        $order_by_inside_category = $order_by;
                        // there is no rank outside categories
                        if (($i = array_search('rank_column ASC', $order_by, true)) !== false) {
                            unset($order_by[$i]);
                        }

                        // must define a default order_by if user want to order by rank only
                        if (count($order_by) == 0) {
                            $order_by = ['id ASC'];
                        }

                        $_POST['order_by'] = 'ORDER BY ' . implode(', ', $order_by);
                        $_POST['order_by_inside_category'] = 'ORDER BY ' . implode(', ', $order_by_inside_category);
                    }
                } else {
                    $page['errors'][] = l10n('No order field selected');
                }
            }

            if (empty($_POST['email_admin_on_new_user'])) {
                $_POST['email_admin_on_new_user'] = 'none';
            } elseif ($_POST['email_admin_on_new_user_filter'] == 'all') {
                $_POST['email_admin_on_new_user'] = 'all';
            } elseif (empty($_POST['email_admin_on_new_user_filter_group'])) {
                $_POST['email_admin_on_new_user'] = 'all';
            } else {
                $_POST['email_admin_on_new_user'] = 'group:' . $_POST['email_admin_on_new_user_filter_group'];
            }

            foreach ($main_checkboxes as $checkbox) {
                $_POST[$checkbox] = empty($_POST[$checkbox]) ? 'false' : 'true';
            }

            break;

        case 'watermark':

            include(PHPWG_ROOT_PATH . 'admin/include/configuration_watermark_process.inc.php');
            break;

        case 'sizes':

            include(PHPWG_ROOT_PATH . 'admin/include/configuration_sizes_process.inc.php');
            break;

        case 'comments':

            // the number of comments per page must be an integer between 5 and 50
            // included
            if (! preg_match($int_pattern, (string) $_POST['nb_comment_page']) || $_POST['nb_comment_page'] < 5 || $_POST['nb_comment_page'] > 50) {
                $page['errors'][] = l10n('The number of comments a page must be between 5 and 50 included.');
            }

            foreach ($comments_checkboxes as $checkbox) {
                $_POST[$checkbox] = empty($_POST[$checkbox]) ? 'false' : 'true';
            }

            break;

        case 'default':

            // Never go here
            break;

        case 'display':

            if (! preg_match($int_pattern, (string) $_POST['nb_categories_page']) || $_POST['nb_categories_page'] < 4) {
                $page['errors'][] = l10n('The number of albums a page must be above 4.');
            }

            foreach ($display_checkboxes as $checkbox) {
                $_POST[$checkbox] = empty($_POST[$checkbox]) ? 'false' : 'true';
            }

            foreach ($display_info_checkboxes as $checkbox) {
                $_POST['picture_informations'][$checkbox] =
                  ! empty($_POST['picture_informations'][$checkbox]);
            }

            $_POST['picture_informations'] = addslashes(serialize($_POST['picture_informations']));
            break;

    }

    // updating configuration if no error found
    if (! in_array($page['section'], ['sizes', 'watermark']) && count($page['errors']) == 0 && is_webmaster()) {
        //echo '<pre>'; print_r($_POST); echo '</pre>';
        $result = pwg_query('SELECT param FROM config;');
        while ($row = pwg_db_fetch_assoc($result)) {
            if (isset($_POST[$row['param']])) {
                $value = $_POST[$row['param']];

                if ($row['param'] == 'gallery_title' && ! $conf['allow_html_descriptions']) {
                    $value = strip_tags((string) $value);
                }

                $value_ = str_replace("\'", "''", $value);
                $query = "UPDATE config SET value = '{$value_}' WHERE param = '{$row['param']}';";
                pwg_query($query);
            }
        }

        $page['infos'][] = l10n('Your configuration settings are saved');
        pwg_activity('system', ACTIVITY_SYSTEM_CORE, 'config', [
            'config_section' => $page['section'],
        ]);
    }

    //------------------------------------------------------ $conf reinitialization
    load_conf_from_db();
}

// restore default derivatives settings
if ($page['section'] == 'sizes' && isset($_GET['action']) && $_GET['action'] == 'restore_settings') {
    ImageStdParams::set_and_save(ImageStdParams::get_default_sizes());
    pwg_query("DELETE FROM config WHERE param = 'disabled_derivatives';");
    clear_derivative_cache();

    $page['infos'][] = l10n('Your configuration settings are saved');
    pwg_activity('system', ACTIVITY_SYSTEM_CORE, 'config', [
        'config_section' => $page['section'],
        'config_action' => $_GET['action'],
    ]);
}

//----------------------------------------------------- template initialization
$template->set_filename('config', 'configuration_' . $page['section'] . '.tpl');

// TabSheet
$tabsheet = new tabsheet();
$tabsheet->set_id('configuration');
$tabsheet->select($page['section']);
$tabsheet->assign();

$action = get_root_url() . 'admin.php?page=configuration';
$action .= '&amp;section=' . $page['section'];

$template->assign(
    [
        'U_HELP' => get_root_url() . 'admin/popuphelp.php?page=configuration',
        'PWG_TOKEN' => get_pwg_token(),
        'F_ACTION' => $action,
    ]
);

switch ($page['section']) {
    case 'main':

        function order_by_is_local(): bool
        {
            $conf = [];
            include(PHPWG_ROOT_PATH . 'include/config_default.inc.php');
            if (file_exists(PHPWG_ROOT_PATH . 'local/config/config.inc.php')) {
                include(PHPWG_ROOT_PATH . 'local/config/config.inc.php');
            }

            if (isset($conf['local_dir_site'])) {
                @include(PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'config/config.inc.php');
            }

            return isset($conf['order_by']) || isset($conf['order_by_inside_category']);
        }

        if (order_by_is_local()) {
            $page['warnings'][] = l10n('You have specified <i>$conf[\'order_by\']</i> in your local configuration file, this parameter in deprecated, please remove it or rename it into <i>$conf[\'order_by_custom\']</i> !');
        }

        if (isset($conf['order_by_custom']) || isset($conf['order_by_inside_category_custom'])) {
            $order_by = [''];
            $template->assign('ORDER_BY_IS_CUSTOM', true);
        } else {
            $out = [];
            $order_by = trim((string) $conf['order_by_inside_category']);
            $order_by = str_replace('ORDER BY ', '', $order_by);
            $order_by = explode(', ', $order_by);
        }

        $template->assign(
            'main',
            [
                'CONF_GALLERY_TITLE' => htmlspecialchars((string) $conf['gallery_title']),
                'CONF_PAGE_BANNER' => htmlspecialchars((string) $conf['page_banner']),
                'week_starts_on_options' => [
                    'sunday' => $lang['day'][0],
                    'monday' => $lang['day'][1],
                ],
                'week_starts_on_options_selected' => $conf['week_starts_on'],
                'mail_theme' => $conf['mail_theme'],
                'mail_theme_options' => $mail_themes,
                'order_by' => $order_by,
                'order_by_options' => $sort_fields,
                'email_admin_on_new_user' => $conf['email_admin_on_new_user'] != 'none',
                'email_admin_on_new_user_filter' => in_array($conf['email_admin_on_new_user'], ['none', 'all']) ? 'all' : 'group',
                'email_admin_on_new_user_filter_group' => preg_match('/^group:(\d+)$/', (string) $conf['email_admin_on_new_user'], $matches) ? $matches[1] : -1,
            ]
        );

        // list of groups
        $query = 'SELECT id, name FROM groups_table;';
        $groups = query2array($query, 'id', 'name');
        natcasesort($groups);

        $template->assign(
            [
                'group_options' => $groups,
            ]
        );

        foreach ($main_checkboxes as $checkbox) {
            $template->append(
                'main',
                [
                    $checkbox => $conf[$checkbox],
                ],
                true
            );
        }

        break;

    case 'comments':

        $template->assign(
            'comments',
            [
                'NB_COMMENTS_PAGE' => $conf['nb_comment_page'],
                'comments_order' => $conf['comments_order'],
                'comments_order_options' => $comments_order,
            ]
        );

        foreach ($comments_checkboxes as $checkbox) {
            $template->append(
                'comments',
                [
                    $checkbox => $conf[$checkbox],
                ],
                true
            );
        }

        break;

    case 'default':

        $edit_user = build_user($conf['guest_id'], false);
        include_once(PHPWG_ROOT_PATH . 'profile.php');

        $errors = [];
        if (save_profile_from_post($edit_user, $errors)) {
            // Reload user
            $edit_user = build_user($conf['guest_id'], false);
            $page['infos'][] = l10n('Information data registered in database');
        }

        $page['errors'] = array_merge($page['errors'], $errors);

        load_profile_in_template(
            $action,
            '',
            $edit_user,
            'GUEST_'
        );
        $template->assign('default', []);
        break;

    case 'display':

        foreach ($display_checkboxes as $checkbox) {
            $template->append(
                'display',
                [
                    $checkbox => $conf[$checkbox],
                ],
                true
            );
        }

        $template->append(
            'display',
            [
                'picture_informations' => unserialize($conf['picture_informations']),
                'NB_CATEGORIES_PAGE' => $conf['nb_categories_page'],
            ],
            true
        );
        break;

    case 'sizes':

        // we only load the derivatives if it was not already loaded: it occurs
        // when submitting the form and an error remains
        if (! isset($page['sizes_loaded_in_tpl'])) {
            $template->assign(
                'sizes',
                [
                    'original_resize_maxwidth' => $conf['original_resize_maxwidth'],
                    'original_resize_maxheight' => $conf['original_resize_maxheight'],
                    'original_resize_quality' => $conf['original_resize_quality'],
                ]
            );

            foreach ($sizes_checkboxes as $checkbox) {
                $template->append(
                    'sizes',
                    [
                        $checkbox => $conf[$checkbox],
                    ],
                    true
                );
            }

            // derivatives = multiple size
            $enabled = ImageStdParams::get_defined_type_map();
            $disabled = @unserialize(@$conf['disabled_derivatives']);
            if ($disabled === false) {
                $disabled = [];
            }

            $tpl_vars = [];
            foreach (ImageStdParams::get_all_types() as $type) {
                $tpl_var = [];

                $tpl_var['must_square'] = ($type == IMG_SQUARE);
                $tpl_var['must_enable'] = $type == IMG_SQUARE || $type == IMG_THUMB || $type == $conf['derivative_default_size'];

                if ($params = @$enabled[$type]) {
                    $tpl_var['enabled'] = true;
                } else {
                    $tpl_var['enabled'] = false;
                    $params = @$disabled[$type];
                }

                if ($params) {
                    [$tpl_var['w'], $tpl_var['h']] = $params->sizing->ideal_size;
                    if (($tpl_var['crop'] = round(100 * $params->sizing->max_crop)) > 0) {
                        [$tpl_var['minw'], $tpl_var['minh']] = $params->sizing->min_size;
                    } else {
                        $tpl_var['minw'] = $tpl_var['minh'] = '';
                    }

                    $tpl_var['sharpen'] = $params->sharpen;
                }

                $tpl_vars[$type] = $tpl_var;
            }

            $template->assign('derivatives', $tpl_vars);
            $template->assign('resize_quality', ImageStdParams::$quality);

            $tpl_vars = [];
            $now = time();
            foreach (ImageStdParams::$custom as $custom => $time) {
                $tpl_vars[$custom] = ($now - $time <= 24 * 3600) ? l10n('today') : time_since($time, 'day');
            }

            $template->assign('custom_derivatives', $tpl_vars);
        }

        break;

    case 'watermark':

        $watermark_files = [];
        foreach (glob(PHPWG_ROOT_PATH . 'themes/default/watermarks/*.png') as $file) {
            $watermark_files[] = substr($file, strlen(PHPWG_ROOT_PATH));
        }

        if (($glob = glob(PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'watermarks/*.png')) !== false) {
            foreach ($glob as $file) {
                $watermark_files[] = substr($file, strlen(PHPWG_ROOT_PATH));
            }
        }

        $watermark_filemap = [
            '' => '---',
        ];
        foreach ($watermark_files as $file) {
            $display = basename($file);
            $watermark_filemap[$file] = $display;
        }

        $template->assign('watermark_files', $watermark_filemap);

        if ($template->get_template_vars('watermark') === null) {
            $wm = ImageStdParams::get_watermark();

            $position = 'custom';
            if ($wm->xpos == 0 && $wm->ypos == 0) {
                $position = 'topleft';
            }

            if ($wm->xpos == 100 && $wm->ypos == 0) {
                $position = 'topright';
            }

            if ($wm->xpos == 50 && $wm->ypos == 50) {
                $position = 'middle';
            }

            if ($wm->xpos == 0 && $wm->ypos == 100) {
                $position = 'bottomleft';
            }

            if ($wm->xpos == 100 && $wm->ypos == 100) {
                $position = 'bottomright';
            }

            if ($wm->xrepeat != 0 || $wm->yrepeat != 0) {
                $position = 'custom';
            }

            $template->assign(
                'watermark',
                [
                    'file' => $wm->file,
                    'minw' => $wm->min_size[0],
                    'minh' => $wm->min_size[1],
                    'xpos' => $wm->xpos,
                    'ypos' => $wm->ypos,
                    'xrepeat' => $wm->xrepeat,
                    'yrepeat' => $wm->yrepeat,
                    'opacity' => $wm->opacity,
                    'position' => $position,
                ]
            );
        }

        break;

}

$template->assign('isWebmaster', (is_webmaster()) ? 1 : 0);
$template->assign('ADMIN_PAGE_TITLE', l10n('Configuration'));

//----------------------------------------------------------- sending html code
$template->assign_var_from_handle('ADMIN_CONTENT', 'config');
