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

$upgrade_description = 'Add #images.coi';

require_once(__DIR__ . '/../../inc/constants.php');

// +-----------------------------------------------------------------------+
// |                            Upgrade content                            |
// +-----------------------------------------------------------------------+

$query = '
ALTER TABLE ' . IMAGES_TABLE . ' ADD COLUMN coi CHAR(4) DEFAULT NULL COMMENT \'center of interest\' AFTER height
';
Mysqli::pwg_query($query);

echo "\n"
. '"' . $upgrade_description . '"' . ' ended'
. "\n"
;
