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

$upgrade_description = 'remove doubled activities on tag addition';

$tag_ids_added = [];
$to_delete_activities = [];

$query = '
SELECT
    *
  FROM ' . PREFIX_TABLE . 'activity
  WHERE object = "tag"
    AND action = "add"
  ORDER BY activity_id ASC
;';

$result = pwg_query($query);
while ($row = pwg_db_fetch_assoc($result)) {
    if (isset($tag_ids_added[$row['object_id']])) {
        $to_delete_activities[] = $row['activity_id'];
    } else {
        $tag_ids_added[$row['object_id']] = 1;
    }
}

if ($to_delete_activities !== []) {
    $query = '
DELETE 
  FROM ' . PREFIX_TABLE . 'activity 
  WHERE activity_id IN (' . implode(',', $to_delete_activities) . ')
;';
    pwg_query($query);
}

echo "\n" . $upgrade_description . "\n";
