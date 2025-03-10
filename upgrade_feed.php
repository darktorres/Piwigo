<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

//check php version
if (version_compare(PHP_VERSION, REQUIRED_PHP_VERSION, '<')) {
    die('Piwigo requires PHP ' . REQUIRED_PHP_VERSION . ' or above.');
}

define('PHPWG_ROOT_PATH', './');

require PHPWG_ROOT_PATH . 'include/config_default.inc.php';
if (file_exists(PHPWG_ROOT_PATH . 'local/config/config.inc.php')) {
    require PHPWG_ROOT_PATH . 'local/config/config.inc.php';
}

require PHPWG_ROOT_PATH . 'local/config/database.inc.php';
require PHPWG_ROOT_PATH . 'include/dblayer/functions_' . $conf['dblayer'] . '.inc.php';

require_once PHPWG_ROOT_PATH . 'include/functions.inc.php';
require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';
require_once PHPWG_ROOT_PATH . 'admin/include/functions_upgrade.php';

// +-----------------------------------------------------------------------+
// | Check Access and exit when it is not ok                               |
// +-----------------------------------------------------------------------+

if (! $conf['check_upgrade_feed']) {
    die('upgrade feed is not active');
}

define('UPGRADES_PATH', PHPWG_ROOT_PATH . 'install/db');

// +-----------------------------------------------------------------------+
// |                         Database connection                           |
// +-----------------------------------------------------------------------+
pwg_db_connect(
    $conf['db_host'],
    $conf['db_user'],
    $conf['db_password'],
    $conf['db_base']
);

// +-----------------------------------------------------------------------+
// |                              Upgrades                                 |
// +-----------------------------------------------------------------------+

// retrieve already applied upgrades
$query = <<<SQL
    SELECT id
    FROM upgrade;
    SQL;
$applied = query2array($query, null, 'id');

// retrieve existing upgrades
$existing = get_available_upgrade_ids();

// which upgrades need to be applied?
$to_apply = array_diff($existing, $applied);

echo '<pre>';
echo count($to_apply) . ' upgrades to apply';

foreach ($to_apply as $upgrade_id) {
    unset($upgrade_description);

    echo "\n\n";
    echo '=== upgrade ' . $upgrade_id . "\n";

    // include & execute upgrade script. Each upgrade script must contain
    // $upgrade_description variable which describe briefly what the upgrade
    // script does.
    require UPGRADES_PATH . '/' . $upgrade_id . '-database.php';

    // notify upgrade
    $query = <<<SQL
        INSERT INTO upgrade
            (id, applied, description)
        VALUES
            ('{$upgrade_id}', NOW(), '{$upgrade_description}');
        SQL;
    pwg_query($query);
}

echo '</pre>';
