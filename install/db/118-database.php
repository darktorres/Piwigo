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

$upgrade_description = 'Automatically activate mobile theme.';

include_once(PHPWG_ROOT_PATH . 'inc/constants.php');
$themes = new Themes();
$themes->perform_action('activate', 'smartpocket');

echo "\n"
. $upgrade_description
. "\n"
;
