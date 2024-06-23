<?php

namespace Piwigo\admin;

use Piwigo\admin\inc\Tabsheet;
use function Piwigo\inc\check_status;
use function Piwigo\inc\get_root_url;
use function Piwigo\inc\load_language;
use function Piwigo\inc\trigger_notify;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

include_once(PHPWG_ROOT_PATH . 'admin/inc/functions.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_ADMINISTRATOR);

$help_link = get_root_url() . 'admin.php?page=help&section=';
$selected = null;

$selected = $_GET['section'] ?? 'add_photos';

$tabsheet = new Tabsheet();
$tabsheet->set_id('help');
$tabsheet->select($selected);
$tabsheet->assign();

trigger_notify('loc_end_help');

$template->set_filenames([
    'help' => 'help.tpl',
]);

$template->assign(
    [
        'HELP_CONTENT' => load_language(
            'help/help_' . $tabsheet->selected . '.html',
            '',
            [
                'return' => true,
            ]
        ),
        'HELP_SECTION_TITLE' => $tabsheet->sheets[$tabsheet->selected]['caption'],
    ]
);

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle(
    'ADMIN_CONTENT',
    'help'
);
