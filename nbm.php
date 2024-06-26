<?php

declare(strict_types=1);

namespace Piwigo;

use function Piwigo\admin\inc\subscribe_notification_by_mail;
use function Piwigo\admin\inc\unsubscribe_notification_by_mail;
use function Piwigo\inc\check_status;
use function Piwigo\inc\flush_page_messages;
use function Piwigo\inc\l10n;
use function Piwigo\inc\load_language;
use function Piwigo\inc\trigger_notify;
use const Piwigo\inc\ACCESS_FREE;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

//--------------------------------------------------------------------- include
const PHPWG_ROOT_PATH = './';
include_once(PHPWG_ROOT_PATH . 'inc/common.inc.php');
check_status(ACCESS_FREE);
include_once(PHPWG_ROOT_PATH . 'inc/functions_notification.inc.php');
include_once(PHPWG_ROOT_PATH . 'inc/functions_mail.inc.php');
include_once(PHPWG_ROOT_PATH . 'admin/inc/functions.php');
include_once(PHPWG_ROOT_PATH . 'admin/inc/functions_notification_by_mail.inc.php');
// Translations are in admin file too
load_language('admin.lang');
// Need to update a second time
trigger_notify('loading_lang');
load_language('lang', PHPWG_ROOT_PATH . PWG_LOCAL_DIR, [
    'no_fallback' => true,
    'local' => true,
]);

// +-----------------------------------------------------------------------+
// | Main                                                                  |
// +-----------------------------------------------------------------------+
if (isset($_GET['subscribe'])
    && preg_match('/^[A-Za-z0-9]{16}$/', (string) $_GET['subscribe'])) {
    subscribe_notification_by_mail(false, [$_GET['subscribe']]);
} elseif (isset($_GET['unsubscribe'])
    && preg_match('/^[A-Za-z0-9]{16}$/', (string) $_GET['unsubscribe'])) {
    unsubscribe_notification_by_mail(false, [$_GET['unsubscribe']]);
} else {
    $page['errors'][] = l10n('Unknown identifier');
}

// +-----------------------------------------------------------------------+
// | template initialization                                               |
// +-----------------------------------------------------------------------+
$title = l10n('Notification');
$page['body_id'] = 'theNBMPage';

$template->set_filenames([
    'nbm' => 'nbm.tpl',
]);

// include menubar
$themeconf = $template->get_template_vars('themeconf');
if (! isset($themeconf['hide_menu_on']) || ! in_array('theNBMPage', $themeconf['hide_menu_on'])) {
    include(PHPWG_ROOT_PATH . 'inc/menubar.inc.php');
}

// +-----------------------------------------------------------------------+
// | html code display                                                     |
// +-----------------------------------------------------------------------+
include(PHPWG_ROOT_PATH . 'inc/page_header.php');
flush_page_messages();
$template->parse('nbm');
include(PHPWG_ROOT_PATH . 'inc/page_tail.php');
