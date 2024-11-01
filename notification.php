<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

// +-----------------------------------------------------------------------+
// |                           initialization                              |
// +-----------------------------------------------------------------------+

define('PHPWG_ROOT_PATH', './');
require_once PHPWG_ROOT_PATH . 'include/common.inc.php';

/**
 * search an available feed_id
 *
 * @return string feed identifier
 */
function find_available_feed_id(): string
{
    while (true) {
        $key = generate_key(50);
        $query = "SELECT COUNT(*) FROM user_feed WHERE id = '{$key}';";
        [$count] = pwg_db_fetch_row(pwg_query($query));
        if ($count == 0) {
            return $key;
        }
    }
}

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_GUEST);

trigger_notify('loc_begin_notification');

// +-----------------------------------------------------------------------+
// |                          new feed creation                            |
// +-----------------------------------------------------------------------+

$page['feed'] = find_available_feed_id();

$query = "INSERT INTO user_feed (id, user_id, last_check) VALUES ('{$page['feed']}', {$user['id']}, NULL);";
pwg_query($query);

$feed_url = PHPWG_ROOT_PATH . 'feed.php';
if (is_a_guest()) {
    $feed_image_only_url = $feed_url;
    $feed_url .= '?feed=' . $page['feed'];
} else {
    $feed_url .= '?feed=' . $page['feed'];
    $feed_image_only_url = $feed_url . '&amp;image_only';
}

// +-----------------------------------------------------------------------+
// |                        template initialization                        |
// +-----------------------------------------------------------------------+

$title = l10n('Notification');
$page['body_id'] = 'theNotificationPage';
$page['meta_robots'] = [
    'noindex' => 1,
    'nofollow' => 1,
];

$template->set_filenames([
    'notification' => 'notification.tpl',
]);

$template->assign(
    [
        'U_FEED' => $feed_url,
        'U_FEED_IMAGE_ONLY' => $feed_image_only_url,
    ]
);

// include menubar
$themeconf = $template->get_template_vars('themeconf');
if (! isset($themeconf['hide_menu_on']) || ! in_array('theNotificationPage', $themeconf['hide_menu_on'])) {
    require PHPWG_ROOT_PATH . 'include/menubar.inc.php';
}

// +-----------------------------------------------------------------------+
// |                           html code display                           |
// +-----------------------------------------------------------------------+
require PHPWG_ROOT_PATH . 'include/page_header.php';
trigger_notify('loc_end_notification');
flush_page_messages();
$template->pparse('notification');
require PHPWG_ROOT_PATH . 'include/page_tail.php';
