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
check_status(ACCESS_ADMINISTRATOR);

if (empty($_GET['theme'])) {
    die('Invalid theme URL');
}

require_once PHPWG_ROOT_PATH . 'admin/include/themes.class.php';
$themes = new themes();
if (! in_array($_GET['theme'], array_keys($themes->fs_themes))) {
    die('Invalid theme');
}

$filename = PHPWG_THEMES_PATH . $_GET['theme'] . '/admin/admin.inc.php';
if (is_file($filename)) {
    require_once $filename;
} else {
    die('Missing file ' . $filename);
}
