<?php

declare(strict_types=1);

namespace Piwigo;

use function Piwigo\inc\check_status;
use function Piwigo\inc\flush_page_messages;
use function Piwigo\inc\l10n;
use function Piwigo\inc\load_language;
use function Piwigo\inc\trigger_notify;
use const Piwigo\inc\ACCESS_GUEST;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

//----------------------------------------------------------- include
const PHPWG_ROOT_PATH = './';
include_once(PHPWG_ROOT_PATH . 'inc/common.inc.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_GUEST);

//----------------------------------------------------- template initialization
//
// Start output of page
//
$title = l10n('About Piwigo');
$page['body_id'] = 'theAboutPage';

trigger_notify('loc_begin_about');

$template->set_filename('about', 'about.tpl');

$template->assign('ABOUT_MESSAGE', load_language('about.html', '', [
    'return' => true,
]));

$theme_about = load_language('about.html', PHPWG_THEMES_PATH . $user['theme'] . '/', [
    'return' => true,
]);
if ($theme_about !== false) {
    $template->assign('THEME_ABOUT', $theme_about);
}

// include menubar
$themeconf = $template->get_template_vars('themeconf');
if (! isset($themeconf['hide_menu_on']) || ! in_array('theAboutPage', $themeconf['hide_menu_on'])) {
    include(PHPWG_ROOT_PATH . 'inc/menubar.inc.php');
}

include(PHPWG_ROOT_PATH . 'inc/page_header.php');
flush_page_messages();
$template->pparse('about');
include(PHPWG_ROOT_PATH . 'inc/page_tail.php');
