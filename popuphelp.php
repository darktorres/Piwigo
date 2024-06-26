<?php

declare(strict_types=1);

namespace Piwigo;

use function Piwigo\inc\check_status;
use function Piwigo\inc\l10n;
use function Piwigo\inc\load_language;
use function Piwigo\inc\trigger_change;
use const Piwigo\inc\ACCESS_GUEST;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

// +-----------------------------------------------------------------------+
// |                           initialization                              |
// +-----------------------------------------------------------------------+

const PHPWG_ROOT_PATH = './';
const PWG_HELP = true;
include_once(PHPWG_ROOT_PATH . 'inc/common.inc.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_GUEST);

$page['body_id'] = 'thePopuphelpPage';
$title = l10n('Piwigo Help');
$page['page_banner'] = '';
$page['meta_robots'] = [
    'noindex' => 1,
    'nofollow' => 1,
];
include(PHPWG_ROOT_PATH . 'inc/page_header.php');

if (
    isset($_GET['page'])
    && preg_match('/^[a-z_]*$/', (string) $_GET['page'])
) {
    $help_content =
      load_language('help/' . $_GET['page'] . '.html', '', [
          'return' => true,
      ]);

    if (! $help_content) {
        $help_content = '';
    }

    $help_content = trigger_change(
        'get_popup_help_content',
        $help_content,
        $_GET['page']
    );
} else {
    die('Hacking attempt!');
}

$template->set_filename('popuphelp', 'popuphelp.tpl');

$template->assign(
    [
        'HELP_CONTENT' => $help_content,
    ]
);

// +-----------------------------------------------------------------------+
// |                           html code display                           |
// +-----------------------------------------------------------------------+

$template->pparse('popuphelp');

include(PHPWG_ROOT_PATH . 'inc/page_tail.php');
