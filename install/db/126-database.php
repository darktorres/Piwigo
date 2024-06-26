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

$upgrade_description = 'rename language sl_SL into sl_SI';

include_once(PHPWG_ROOT_PATH . 'inc/constants.php');

$query = '
UPDATE ' . USER_INFOS_TABLE . '
  SET language = \'sl_SI\'
  WHERE language = \'sl_SL\'
;';
pwg_query($query);

$query = '
UPDATE ' . LANGUAGES_TABLE . '
  SET id = \'sl_SI\'
  WHERE id = \'sl_SL\'
;';
pwg_query($query);

echo "\n" . $upgrade_description . "\n";
