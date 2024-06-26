<?php

declare(strict_types=1);

namespace Piwigo\admin;

use Piwigo\admin\inc\Themes;
use function Piwigo\inc\check_status;
use const Piwigo\inc\ACCESS_ADMINISTRATOR;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

include_once(PHPWG_ROOT_PATH . 'admin/inc/functions.php');
check_status(ACCESS_ADMINISTRATOR);

if (empty($_GET['theme'])) {
    die('Invalid theme URL');
}

$themes = new Themes();
if (! in_array($_GET['theme'], array_keys($themes->fs_themes))) {
    die('Invalid theme');
}

$filename = PHPWG_THEMES_PATH . $_GET['theme'] . '/admin/admin.inc.php';
if (is_file($filename)) {
    include_once($filename);
} else {
    die('Missing file ' . $filename);
}
