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

$upgrade_description = 'fill empty images name with filename';

include_once(PHPWG_ROOT_PATH . 'inc/constants.php');

// +-----------------------------------------------------------------------+
// |                            Upgrade content                            |
// +-----------------------------------------------------------------------+

$query = 'SELECT id, file FROM ' . IMAGES_TABLE . ' WHERE name IS NULL;';
$images = pwg_query($query);

$updates = [];
while ($row = pwg_db_fetch_assoc($images)) {
    $updates[] = [
        'id' => $row['id'],
        'name' => get_name_from_file($row['file']),
    ];
}

mass_updates(
    IMAGES_TABLE,
    [
        'primary' => ['id'],
        'update' => ['name'],
    ],
    $updates
);

echo "\n"
. '"' . $upgrade_description . '"' . ' ended'
. "\n"
;
