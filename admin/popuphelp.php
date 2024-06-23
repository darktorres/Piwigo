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

const PHPWG_ROOT_PATH = '../';
const PWG_HELP = true;
const IN_ADMIN = true;
include_once(PHPWG_ROOT_PATH . 'inc/common.inc.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_ADMINISTRATOR);

$page['body_id'] = 'thePopuphelpPage';
$title = l10n('Piwigo Help');
$page['page_banner'] = '<h1>' . $title . '</h1>';
$page['meta_robots'] = [
    'noindex' => 1,
    'nofollow' => 1,
];

if (
    isset($_GET['page'])
    && preg_match('/^[a-z_]*$/', (string) $_GET['page'])
) {
    $help_content = load_language(
        'help/' . $_GET['page'] . '.html',
        '',
        [
            'force_fallback' => 'en_UK',
            'return' => true,
        ]
    );

    if (! $help_content) {
        $help_content = '';
    }

    $help_content = trigger_change('get_popup_help_content', $help_content, $_GET['page']);
} else {
    die('Hacking attempt!');
}

$template->set_filename('popuphelp', 'popuphelp.tpl');

$template->assign(
    [
        'HELP_CONTENT' => $help_content,
    ]
);

if (isset($_GET['output']) && $_GET['output'] == 'content_only') {
    echo $help_content;
    exit();
}

// +-----------------------------------------------------------------------+
// |                           html code display                           |
// +-----------------------------------------------------------------------+

$template->pparse('popuphelp');

include(PHPWG_ROOT_PATH . 'inc/page_tail.php');
