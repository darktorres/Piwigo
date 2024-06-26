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

$upgrade_description = 'Update default template to default theme.';

$query = '
ALTER TABLE ' . USER_INFOS_TABLE . '
  CHANGE COLUMN template theme varchar(255) NOT NULL default \'Sylvia\'
;';

pwg_query($query);

$query = '
SELECT user_id, theme
  FROM ' . USER_INFOS_TABLE . '
;';

$result = pwg_query($query);

$users = [];
while ($row = pwg_db_fetch_assoc($result)) {
    [$user_template, $user_theme] = explode('/', (string) $row['theme']);

    switch ($user_template) {
        case 'yoga':
            break;

        case 'gally':
            $user_theme = 'gally-' . $user_theme;
            break;

        case 'floPure':
            $user_theme = 'Pure_' . $user_theme;
            break;

        case 'floOs':
            $user_theme = 'OS_' . $user_theme;
            break;

        case 'simple':
            $user_theme = 'simple-' . $user_theme;
            break;

        default:
            $user_theme = 'Sylvia';
    }

    $users[] = [
        'user_id' => $row['user_id'],
        'theme' => $user_theme,
    ];
}

mass_updates(
    USER_INFOS_TABLE,
    [
        'primary' => ['user_id'],
        'update' => ['theme'],
    ],
    $users
);

echo "\n"
. $upgrade_description
. "\n"
;
