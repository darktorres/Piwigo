<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Upgrade from 1.3.0 to 1.3.1
 */

if (! defined('PHPWG_ROOT_PATH')) {
    die('This page cannot be loaded directly, load upgrade.php');
}

if (! defined('PHPWG_IN_UPGRADE') || ! PHPWG_IN_UPGRADE) {
    die('Hacking attempt!');
}

$queries = [
    "
ALTER TABLE phpwebgallery_categories
  ADD COLUMN uppercats varchar(255) NOT NULL default ''
;",

    "
CREATE TABLE phpwebgallery_user_category (
  user_id smallint(5) unsigned NOT NULL default '0'
)
;",

    '
ALTER TABLE phpwebgallery_categories
  ADD INDEX id (id)
;',

    '
ALTER TABLE phpwebgallery_categories
  ADD INDEX id_uppercat (id_uppercat)
;',

    '
ALTER TABLE phpwebgallery_image_category
  ADD INDEX category_id (category_id)
;',

    '
ALTER TABLE phpwebgallery_image_category
  ADD INDEX image_id (image_id)
;',
];

foreach ($queries as $query) {
    $query = str_replace('phpwebgallery_', PREFIX_TABLE, $query);
    pwg_query($query);
}

// filling the new column categories.uppercats
$id_uppercats = [];

$query = '
SELECT id, id_uppercat
  FROM ' . CATEGORIES_TABLE . '
;';
$result = pwg_query($query);
while ($row = pwg_db_fetch_assoc($result)) {
    if (! isset($row['id_uppercat']) || $row['id_uppercat'] == '') {
        $row['id_uppercat'] = 'NULL';
    }

    $id_uppercats[$row['id']] = $row['id_uppercat'];
}

$datas = [];

foreach (array_keys($id_uppercats) as $id) {
    $data = [];
    $data['id'] = $id;
    $uppercats = [];

    $uppercats[] = $id;
    while (isset($id_uppercats[$id]) && $id_uppercats[$id] != 'NULL') {
        $uppercats[] = $id_uppercats[$id];
        $id = $id_uppercats[$id];
    }

    $data['uppercats'] = implode(',', array_reverse($uppercats));

    $datas[] = $data;
}

mass_updates(
    CATEGORIES_TABLE,
    [
        'primary' => ['id'],
        'update' => ['uppercats'],
    ],
    $datas
);

// now we upgrade from 1.3.1 to 1.6.0
include_once(PHPWG_ROOT_PATH . 'install/upgrade_1.3.1.php');
