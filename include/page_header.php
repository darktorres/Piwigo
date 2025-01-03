<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

//
// Start output of page
//
$template->set_filenames([
    'header' => 'header.tpl',
]);

trigger_notify('loc_begin_page_header');

$show_mobile_app_banner = conf_get_param('show_mobile_app_banner_in_gallery', false);
if (defined('IN_ADMIN') && IN_ADMIN) {
    $show_mobile_app_banner = conf_get_param('show_mobile_app_banner_in_admin', true);
}

$template->assign(
    [
        'GALLERY_TITLE' =>
          $page['gallery_title'] ?? $conf['gallery_title'],

        'PAGE_BANNER' =>
          trigger_change(
              'render_page_banner',
              str_replace(
                  '%gallery_title%',
                  $conf['gallery_title'],
                  $page['page_banner'] ?? $conf['page_banner']
              )
          ),

        'BODY_ID' =>
          $page['body_id'] ?? '',

        'CONTENT_ENCODING' => 'utf-8',
        'PAGE_TITLE' => strip_tags((string) $title),

        'U_HOME' => get_gallery_home_url(),

        'LEVEL_SEPARATOR' => $conf['level_separator'],

        'SHOW_MOBILE_APP_BANNER' => $show_mobile_app_banner,

        'BODY_CLASSES' => $page['body_classes'],

        'BODY_DATA' => json_encode($page['body_data']),
    ]
);

// Header notes
if (! empty($header_notes)) {
    $template->assign('header_notes', $header_notes);
}

// No referencing is required
if (! $conf['meta_ref']) {
    $page['meta_robots']['noindex'] = 1;
    $page['meta_robots']['nofollow'] = 1;
}

if (! empty($page['meta_robots'])) {
    $template->append(
        'head_elements',
        '<meta name="robots" content="'
          . implode(',', array_keys($page['meta_robots']))
          . '">'
    );
}

if (! isset($page['meta_robots']['noindex'])) {
    $template->assign('meta_ref', 1);
}

// refresh
if (isset($refresh) && intval($refresh) >= 0 && isset($url_link)) {
    $template->assign(
        [
            'page_refresh' => [
                'TIME' => $refresh,
                'U_REFRESH' => $url_link,
            ],
        ]
    );
}

trigger_notify('loc_end_page_header');

header('Content-Type: text/html; charset=utf-8');
$template->parse('header');

trigger_notify('loc_after_page_header');
