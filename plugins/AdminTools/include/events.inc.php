<?php

declare(strict_types=1);

defined('ADMINTOOLS_PATH') || die('Hacking attempt!');

/**
 * Add main toolbar to current page
 * @trigger loc_after_page_header
 */
function admintools_add_public_controller(): void
{
    global $MultiView, $conf, $template, $page, $user, $picture;

    if (script_basename() === 'picture' && empty($picture['current'])) {
        return;
    }

    $url_root = get_root_url();
    $tpl_vars = [];

    if ($MultiView->is_admin()) { // full options for admin
        $tpl_vars['U_SITE_ADMIN'] = $url_root . 'admin.php?page=';
        $tpl_vars['MULTIVIEW'] = $MultiView->get_data();
        $tpl_vars['USER'] = $MultiView->get_user();
        $tpl_vars['CURRENT_USERNAME'] = $user['id'] == $conf['guest_id'] ? l10n('guest') : $user['username'];
        $tpl_vars['DELETE_CACHE'] = isset($conf['multiview_invalidate_cache']);

        if (($admin_lang = $MultiView->get_user_language()) !== false) {
            require_once PHPWG_ROOT_PATH . 'include/functions_mail.inc.php';
            switch_lang_to($admin_lang);
        }
    } elseif ($conf['AdminTools']['public_quick_edit'] && script_basename() === 'picture' && $picture['current']['added_by'] == $user['id'] && ! is_a_guest()
    ) { // only "edit" button for photo owner
    } else {
        return;
    }

    $tpl_vars['POSITION'] = $conf['AdminTools']['closed_position'];
    $tpl_vars['DEFAULT_OPEN'] = $conf['AdminTools']['default_open'];
    $tpl_vars['U_SELF'] = $MultiView->get_clean_url(true);

    // photo page
    if (script_basename() === 'picture') {
        $url_self = duplicate_picture_url();
        $tpl_vars['IS_PICTURE'] = true;

        // admin can add to caddie and set representattive
        if ($MultiView->is_admin()) {
            $template->clear_assign([
                'U_SET_AS_REPRESENTATIVE',
                'U_PHOTO_ADMIN',
                'U_CADDIE',
            ]);

            $template->set_prefilter('picture', admintools_remove_privacy(...));

            $tpl_vars['U_CADDIE'] = add_url_params(
                $url_self,
                [
                    'action' => 'add_to_caddie',
                ]
            );

            $query = <<<SQL
                SELECT element_id FROM caddie
                WHERE element_id = {$page['image_id']};
                SQL;
            $tpl_vars['IS_IN_CADDIE'] = pwg_db_num_rows(pwg_query($query)) > 0;

            if (isset($page['category'])) {
                $tpl_vars['CATEGORY_ID'] = $page['category']['id'];

                $tpl_vars['U_SET_REPRESENTATIVE'] = add_url_params(
                    $url_self,
                    [
                        'action' => 'set_as_representative',
                    ]
                );

                $tpl_vars['IS_REPRESENTATIVE'] = $page['category']['representative_picture_id'] == $page['image_id'];
            }

            $tpl_vars['U_ADMIN_EDIT'] = $url_root . 'admin.php?page=photo-' . $page['image_id']
              . (isset($page['category']) ? '&amp;cat_id=' . $page['category']['id'] : '');
        }

        $tpl_vars['U_DELETE'] = add_url_params(
            $url_self,
            [
                'delete' => '',
                'pwg_token' => get_pwg_token(),
            ]
        );

        // gets tags (full available list is loaded in ajax)
        require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';

        $query = <<<SQL
            SELECT id, name
            FROM image_tag AS it
            JOIN tags AS t ON t.id = it.tag_id
            WHERE image_id = {$page['image_id']};
            SQL;
        $tag_selection = get_taglist($query);

        if (! isset($picture['current']['date_creation'])) {
            $picture['current']['date_creation'] = '';
        }

        $tpl_vars['QUICK_EDIT'] = [
            'img' => $picture['current']['derivatives']['square']->get_url(),
            'name' => $picture['current']['name'],
            'comment' => $picture['current']['comment'],
            'author' => $picture['current']['author'],
            'level' => $picture['current']['level'],
            'date_creation' => substr((string) $picture['current']['date_creation'], 0, 10),
            'date_creation_time' => substr((string) $picture['current']['date_creation'], 11, 8),
            'tag_selection' => $tag_selection,
        ];
    }
    // album page (admin only)
    elseif ($MultiView->is_admin() && ($page['section'] ?? null) == 'categories' && isset($page['category'])) {
        $url_self = duplicate_index_url();

        $tpl_vars['IS_CATEGORY'] = true;
        $tpl_vars['CATEGORY_ID'] = $page['category']['id'];

        $template->clear_assign([
            'U_EDIT',
            'U_CADDIE',
        ]);

        $tpl_vars['U_ADMIN_EDIT'] = $url_root . 'admin.php?page=album-' . $page['category']['id'];

        if (! empty($page['items'])) {
            $tpl_vars['U_CADDIE'] = add_url_params(
                $url_self,
                [
                    'caddie' => 1,
                ]
            );
        }

        $tpl_vars['QUICK_EDIT'] = [
            'img' => null,
            'name' => $page['category']['name'],
            'comment' => $page['category']['comment'],
        ];

        if (! empty($page['category']['representative_picture_id'])) {
            $query = <<<SQL
                SELECT * FROM images
                WHERE id = {$page['category']['representative_picture_id']};
                SQL;
            $image_infos = pwg_db_fetch_assoc(pwg_query($query));

            $tpl_vars['QUICK_EDIT']['img'] = DerivativeImage::get_one(IMG_SQUARE, $image_infos)->get_url();
        }
    }

    $template->assign([
        'ADMINTOOLS_PATH' => './plugins/' . ADMINTOOLS_ID . '/',
        'ato' => $tpl_vars,
        'PWG_TOKEN' => get_pwg_token(),
    ]);

    $template->set_filename('ato_public_controller', realpath(ADMINTOOLS_PATH . 'template/public_controller.tpl'));
    $template->parse('ato_public_controller');

    if ($MultiView->is_admin() && $admin_lang !== false) {
        switch_lang_back();
    }
}

/**
 * Add main toolbar to current page
 * @trigger loc_after_page_header
 */
function admintools_add_admin_controller(): void
{
    global $MultiView, $conf, $template, $page, $user;

    $url_root = get_root_url();
    $tpl_vars = [];

    $tpl_vars['MULTIVIEW'] = $MultiView->get_data();
    $tpl_vars['DELETE_CACHE'] = isset($conf['multiview_invalidate_cache']);
    $tpl_vars['U_SELF'] = $MultiView->get_clean_admin_url(true);

    if (($admin_lang = $MultiView->get_user_language()) !== false) {
        require_once PHPWG_ROOT_PATH . 'include/functions_mail.inc.php';
        switch_lang_to($admin_lang);
    }

    $template->assign([
        'ADMINTOOLS_PATH' => './plugins/' . ADMINTOOLS_ID . '/',
        'ato' => $tpl_vars,
    ]);

    $template->set_filename('ato_admin_controller', realpath(ADMINTOOLS_PATH . 'template/admin_controller.tpl'));
    $template->parse('ato_admin_controller');

    if ($MultiView->is_admin() && $admin_lang !== false) {
        switch_lang_back();
    }
}

function admintools_add_admin_controller_setprefilter(): void
{
    global $template;
    $template->set_prefilter('header', admintools_admin_prefilter(...));
}

function admintools_admin_prefilter(
    string $content
): string|array {
    if (version_compare(PHPWG_VERSION, '2.9', '>=')) {
        $search = '<a href="{$U_LOGOUT}">';
        $replace = '<span id="ato_container"><a href="#"><i class="icon-cog-alt"></i><span>{\'Tools\'|translate}</span></a></span>' . $search;
    } else {
        $search = '<a class="icon-brush tiptip" href="{$U_CHANGE_THEME}" title="{\'Switch to clear or dark colors for administration\'|translate}">{\'Change Admin Colors\'|translate}</a>';
        $replace = '<span id="ato_container"><a class="icon-cog-alt" href="#">{\'Tools\'|translate}</a></span>';
    }

    return str_replace($search, $replace, $content);
}

/**
 * Disable privacy level switchbox
 */
function admintools_remove_privacy(
    string $content
): string|array {
    $search = '{if $display_info.privacy_level and isset($available_permission_levels)}';
    $replace = '{if false}';
    return str_replace($search, $replace, $content);
}

/**
 * Save picture form
 * @trigger loc_begin_picture
 */
function admintools_save_picture(): void
{
    global $page, $conf, $MultiView, $user, $picture;

    if (! isset($_GET['delete']) && ! isset($_POST['action']) || $_POST['action'] != 'quick_edit') {
        return;
    }

    if (is_a_guest()) {
        return;
    }

    $query = <<<SQL
        SELECT added_by
        FROM images
        WHERE id = {$page['image_id']};
        SQL;
    [$added_by] = pwg_db_fetch_row(pwg_query($query));

    if (! $MultiView->is_admin() && $user['id'] != $added_by) {
        return;
    }

    if (isset($_GET['delete']) && get_pwg_token() == $_GET['pwg_token']) {
        require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';

        delete_elements([$page['image_id']], true);
        invalidate_user_cache();

        if (isset($page['rank_of'][$page['image_id']])) {
            redirect(
                duplicate_index_url(
                    [
                        'start' =>
                          floor($page['rank_of'][$page['image_id']] / $page['nb_image_page'])
                          * $page['nb_image_page'],
                    ]
                )
            );
        } else {
            redirect(make_index_url());
        }
    }

    if ($_POST['action'] == 'quick_edit') {
        require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';

        check_pwg_token();

        $data = [
            'name' => (is_admin() && $conf['allow_html_descriptions']) ? $_POST['name'] : strip_tags((string) $_POST['name']),
            'author' => (is_admin() && $conf['allow_html_descriptions']) ? $_POST['author'] : strip_tags((string) $_POST['author']),
        ];

        if ($MultiView->is_admin()) {
            $data['level'] = $_POST['level'];
        }

        if (is_admin() && $conf['allow_html_descriptions']) {
            $data['comment'] = $_POST['comment'];
        } else {
            $data['comment'] = strip_tags((string) $_POST['comment']);
        }

        if (! empty($_POST['date_creation']) && strtotime((string) $_POST['date_creation']) !== false) {
            $data['date_creation'] = $_POST['date_creation'] . ' ' . $_POST['date_creation_time'];
        }

        single_update(
            'images',
            $data,
            [
                'id' => $page['image_id'],
            ]
        );

        $tag_ids = [];
        if (! empty($_POST['tags'])) {
            $tag_ids = get_tag_ids($_POST['tags']);
        }

        set_tags($tag_ids, $page['image_id']);
    }
}

/**
 * Save category form
 * @trigger loc_begin_index
 */
function admintools_save_category(): void
{
    global $page, $conf, $MultiView;

    if (! $MultiView->is_admin()) {
        return;
    }

    if (($_POST['action'] ?? null) == 'quick_edit') {
        check_pwg_token();

        $data = [
            'name' => (is_admin() && $conf['allow_html_descriptions']) ? $_POST['name'] : strip_tags((string) $_POST['name']),
        ];

        if (is_admin() && $conf['allow_html_descriptions']) {
            $data['comment'] = $_POST['comment'];
        } else {
            $data['comment'] = strip_tags((string) $_POST['comment']);
        }

        single_update(
            'categories',
            $data,
            [
                'id' => $page['category']['id'],
            ]
        );

        redirect(duplicate_index_url());
    }
}
