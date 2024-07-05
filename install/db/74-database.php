<?php

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

$upgrade_description = 'Add blk_menubar config';

require_once(__DIR__ . '/../../inc/constants.php');

// +-----------------------------------------------------------------------+
// |                            Upgrade content                            |
// +-----------------------------------------------------------------------+

$query = '
INSERT INTO ' . CONFIG_TABLE . " (param,value,comment) VALUES ('blk_menubar','','Menubar options');
";
Mysqli::pwg_query($query);

echo "\n"
. '"' . $upgrade_description . '"' . ' ended'
. "\n"
;
