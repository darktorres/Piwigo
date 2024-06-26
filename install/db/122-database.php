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

$upgrade_description = 'derivatives: new organization of "upload" and "galleries" directories';

$query = '
SELECT
    id,
    path,
    tn_ext,
    has_high,
    high_filesize,
    high_width,
    high_height
  FROM ' . IMAGES_TABLE . '
;';
$result = pwg_query($query);
$starttime = get_moment();

$updates = [];

while ($row = pwg_db_fetch_assoc($result)) {
    if ($row['has_high'] == 'true') {
        $high_path = dirname((string) $row['path']) . '/pwg_high/' . basename((string) $row['path']);
        rename($high_path, $row['path']);

        $updates[] = [
            'id' => $row['id'],
            'width' => $row['high_width'],
            'height' => $row['high_height'],
            'filesize' => $row['high_filesize'],
        ];
    }
}

if ($updates !== []) {
    mass_updates(
        IMAGES_TABLE,
        [
            'primary' => ['id'],
            'update' => ['width', 'height', 'filesize'],
        ],
        $updates
    );
}

echo "\n"
. $upgrade_description . sprintf(' (execution in %.3fs)', (get_moment() - $starttime))
. "\n"
;
