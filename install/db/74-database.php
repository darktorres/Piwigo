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

$upgrade_description = 'Add blk_menubar config';

include_once(PHPWG_ROOT_PATH . 'inc/constants.php');

// +-----------------------------------------------------------------------+
// |                            Upgrade content                            |
// +-----------------------------------------------------------------------+

$query = '
INSERT INTO ' . CONFIG_TABLE . " (param,value,comment) VALUES ('blk_menubar','','Menubar options');
";
pwg_query($query);

echo "\n"
. '"' . $upgrade_description . '"' . ' ended'
. "\n"
;
