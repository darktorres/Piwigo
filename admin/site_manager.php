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

require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

if (! $conf['enable_synchronization']) {
    die('synchronization is disabled');
}

check_status(ACCESS_ADMINISTRATOR);

if ($_POST !== [] || isset($_GET['action'])) {
    check_pwg_token();
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+
$template->set_filenames([
    'site_manager' => 'site_manager.tpl',
]);

// +-----------------------------------------------------------------------+
// | tabs                                                                  |
// +-----------------------------------------------------------------------+

require_once PHPWG_ROOT_PATH . 'admin/include/tabsheet.class.php';
$my_base_url = get_root_url() . 'admin.php?page=';

$tabsheet = new tabsheet();
$tabsheet->set_id('site_update');
$tabsheet->select('site_manager');
$tabsheet->assign();

// +-----------------------------------------------------------------------+
// |                        new site creation form                         |
// +-----------------------------------------------------------------------+
if (isset($_POST['submit']) && ! empty($_POST['galleries_url'])) {
    $is_remote = url_is_remote($_POST['galleries_url']);
    if ($is_remote) {
        fatal_error('remote sites not supported');
    }

    $url = preg_replace('/[\/]*$/', '', (string) $_POST['galleries_url']);
    $url .= '/';
    if (! str_starts_with($url, '.')) {
        $url = './' . $url;
    }

    // site must not exist
    $query = <<<SQL
        SELECT COUNT(id) AS count
        FROM sites
        WHERE galleries_url = '{$url}';
        SQL;
    $row = pwg_db_fetch_assoc(pwg_query($query));
    if ($row['count'] > 0) {
        $page['errors'][] = l10n('This site already exists') . ' [' . $url . ']';
    }

    if (count($page['errors']) == 0 && ! file_exists($url)) {
        $page['errors'][] = l10n('Directory does not exist') . ' [' . $url . ']';
    }

    if (count($page['errors']) == 0) {
        $query = <<<SQL
            INSERT INTO sites
                (galleries_url)
            VALUES
                ('{$url}');
            SQL;
        pwg_query($query);
        $page['infos'][] = $url . ' ' . l10n('created');
    }
}

// +-----------------------------------------------------------------------+
// |                            actions on site                            |
// +-----------------------------------------------------------------------+
if (isset($_GET['site']) && is_numeric($_GET['site'])) {
    $page['site'] = $_GET['site'];
}

if (isset($_GET['action']) && isset($page['site'])) {
    $query = <<<SQL
        SELECT galleries_url
        FROM sites
        WHERE id = {$page['site']};
        SQL;
    [$galleries_url] = pwg_db_fetch_row(pwg_query($query));
    if ($_GET['action'] === 'delete') {
        delete_site($page['site']);
        $page['infos'][] = $galleries_url . ' ' . l10n('deleted');
    }
}

$template->assign(
    [
        'F_ACTION' => get_root_url() . 'admin.php' . get_query_string_diff(['action', 'site', 'pwg_token']),
        'PWG_TOKEN' => get_pwg_token(),
        'ADMIN_PAGE_TITLE' => l10n('Synchronize'),
    ]
);

$query = <<<SQL
    SELECT c.site_id, COUNT(DISTINCT c.id) AS nb_categories, COUNT(i.id) AS nb_images
    FROM categories AS c
    LEFT JOIN images AS i ON c.id = i.storage_category_id
    WHERE c.site_id IS NOT NULL
    GROUP BY c.site_id;
    SQL;
$sites_detail = query2array($query, 'site_id');

$query = <<<SQL
    SELECT *
    FROM sites;
    SQL;
$result = pwg_query($query);

while ($row = pwg_db_fetch_assoc($result)) {
    $is_remote = url_is_remote($row['galleries_url']);
    $base_url = PHPWG_ROOT_PATH . 'admin.php';
    $base_url .= '?page=site_manager';
    $base_url .= '&amp;site=' . $row['id'];
    $base_url .= '&amp;pwg_token=' . get_pwg_token();
    $base_url .= '&amp;action=';

    $update_url = PHPWG_ROOT_PATH . 'admin.php';
    $update_url .= '?page=site_update';
    $update_url .= '&amp;site=' . $row['id'];

    $tpl_var =
      [
          'NAME' => $row['galleries_url'],
          'TYPE' => l10n($is_remote ? 'Remote' : 'Local'),
          'CATEGORIES' => $sites_detail[$row['id']]['nb_categories'] ?? 0,
          'IMAGES' => $sites_detail[$row['id']]['nb_images'] ?? 0,
          'U_SYNCHRONIZE' => $update_url,
      ];

    if ($row['id'] != 1) {
        $tpl_var['U_DELETE'] = $base_url . 'delete';
    }

    $plugin_links = [];
    //$plugin_links is array of array composed of U_HREF, U_HINT & U_CAPTION
    $plugin_links =
      trigger_change(
          'get_admins_site_links',
          $plugin_links,
          $row['id'],
          $is_remote
      );
    $tpl_var['plugin_links'] = $plugin_links;

    $template->append('sites', $tpl_var);
}

$template->assign_var_from_handle('ADMIN_CONTENT', 'site_manager');
