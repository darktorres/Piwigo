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

function upgrade65_change_table_to_blob($table, $field_definitions): void
{
    $types = [
        'varchar' => 'varbinary',
        'text' => 'blob',
        'mediumtext' => 'mediumblob',
        'longtext' => 'longblob',
    ];

    $changes = [];
    foreach ($field_definitions as $row) {
        if (! isset($row['Collation']) || $row['Collation'] == 'NULL') {
            continue;
        }

        [$type] = explode('(', (string) $row['Type']);
        if (! isset($types[$type])) {
            continue;
        }

        // no need
        $binaryType = preg_replace('/' . $type . '/i', $types[$type], $row['Type']);
        $changes[] = 'MODIFY COLUMN ' . $row['Field'] . ' ' . $binaryType;
    }

    if ($changes !== []) {
        $query = 'ALTER TABLE ' . $table . ' ' . implode(', ', $changes);
        pwg_query($query);
    }
}

function upgrade65_change_table_to_charset($table, $field_definitions, $db_charset): void
{
    $changes = [];
    foreach ($field_definitions as $row) {
        if (! isset($row['Collation']) || $row['Collation'] == 'NULL') {
            continue;
        }

        $query = $row['Field'] . ' ' . $row['Type'];
        $query .= ' CHARACTER SET ' . $db_charset;
        if (str_contains((string) $row['Collation'], '_bin')) {
            $query .= ' BINARY';
        }

        if ($row['Null'] != 'YES') {
            $query .= ' NOT NULL';
            if (isset($row['Default'])) {
                $query .= ' DEFAULT "' . addslashes((string) $row['Default']) . '"';
            }
        } elseif (! isset($row['Default'])) {
            $query .= ' DEFAULT NULL';
        } else {
            $query .= ' DEFAULT "' . addslashes((string) $row['Default']) . '"';
        }

        if ($row['Extra'] == 'auto_increment') {
            $query .= ' auto_increment';
        }

        $changes[] = 'MODIFY COLUMN ' . $query;
    }

    if ($changes !== []) {
        $query = 'ALTER TABLE `' . $table . '` ' . implode(', ', $changes);
        pwg_query($query);
    }
}

$upgrade_description = 'PWG charset migration';
// +-----------------------------------------------------------------------+
// |                            Upgrade content                            |
// +-----------------------------------------------------------------------+
if (! defined('PWG_CHARSET')) {
    $upgrade_log = '';

    // +-----------------------------------------------------------------------+
    // load all the user languages
    $all_langs = [];
    $query = '
SELECT language, COUNT(user_id) AS count FROM ' . USER_INFOS_TABLE . '
  GROUP BY language';
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result)) {
        $language = $row['language'];
        $lang_def = explode('.', (string) $language);
        if (count($lang_def) == 2) {
            $new_lang = $lang_def[0];
            $charset = strtolower($lang_def[1]);
        } else {
            $new_lang = 'en_UK';
            $charset = 'iso-8859-1';
        }

        $all_langs[$language] = [
            'count' => $row['count'],
            'new_lang' => $new_lang,
            'charset' => $charset,
        ];
        $upgrade_log .= ">>user_lang\t" . $language . "\t" . $row['count'] . "\n";
    }

    $upgrade_log .= "\n";

    // +-----------------------------------------------------------------------+
    // get admin charset
    include(PHPWG_ROOT_PATH . 'inc/config_default.inc.php');
    include(PHPWG_ROOT_PATH . 'local/config/config.inc.php');
    $admin_charset = 'iso-8859-1';
    $query = '
SELECT language FROM ' . USER_INFOS_TABLE . '
  WHERE user_id=' . $conf['webmaster_id'];
    $result = pwg_query($query);
    if (pwg_db_num_rows($result) == 0) {
        $query = '
SELECT language FROM ' . USER_INFOS_TABLE . '
  WHERE status="webmaster" and adviser="false"
  LIMIT 1';
        $result = pwg_query($query);
    }

    if ($row = pwg_db_fetch_assoc($result)) {
        $admin_charset = $all_langs[$row['language']]['charset'];
    }

    $upgrade_log .= ">>admin_charset\t" . $admin_charset . "\n";

    // +-----------------------------------------------------------------------+
    // get mysql version and structure of tables
    $mysql_version = mysql_get_server_info();
    $upgrade_log .= ">>mysql_ver\t" . $mysql_version . "\n";

    $all_tables = [];
    $query = 'SHOW TABLES LIKE "' . $prefixeTable . '%"';
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_row($result)) {
        $all_tables[] = $row[0];
    }

    $all_tables_definition = [];
    foreach ($all_tables as $table) {
        $query = 'SHOW FULL COLUMNS FROM ' . $table;
        $result = pwg_query($query);
        $field_definitions = [];
        while ($row = pwg_db_fetch_assoc($result)) {
            if (! isset($row['Collation']) || $row['Collation'] == 'NULL') {
                continue;
            }

            $field_definitions[] = $row;
        }

        $all_tables_definition[$table] = $field_definitions;
    }

    // +-----------------------------------------------------------------------+
    // calculate the result and convert the tables

    //tables that can be converted without going through binary (they contain only ascii data)
    $safe_tables = [
        'history',
        'history_backup',
        'history_summary',
        'old_permalinks',
        'plugins',
        'rate',
        'upgrade',
        'user_cache',
        'user_feed',
        'user_infos',
        'user_mail_notification',
        'users',
        'waiting',
        'ws_access',
    ];
    $safe_tables = array_flip($safe_tables);

    $pwg_charset = 'iso-8859-1';
    $db_charset = 'latin1';
    $db_collate = '';
    if (version_compare($mysql_version, '4.1', '<')) { // below 4.1 no charset support
        $upgrade_log .= "< conversion\tnothing\n";
    } elseif ($admin_charset === 'iso-8859-1') {
        $pwg_charset = 'utf-8';
        $db_charset = 'utf8';
        foreach ($all_tables as $table) {
            upgrade65_change_table_to_charset($table, $all_tables_definition[$table], 'utf8');
            $query = 'ALTER TABLE ' . $table . ' DEFAULT CHARACTER SET utf8';
            pwg_query($query);
        }

        $upgrade_log .= "< conversion\tchange utf8\n";
    }
    /*ALTER TABLE tbl_name CONVERT TO CHARACTER SET charset_name; (or change column character set)

    Warning: The preceding operation converts column values between the character sets. This is not what you want if you have a column in one character set (like latin1) but the stored values actually use some other, incompatible character set (like utf8). In this case, you have to do the following for each such column:

    ALTER TABLE t1 CHANGE c1 c1 BLOB;
    ALTER TABLE t1 CHANGE c1 c1 TEXT CHARACTER SET utf8;
    */
    elseif ($admin_charset === 'utf-8') {
        $pwg_charset = 'utf-8';
        $db_charset = 'utf8';
        foreach ($all_tables as $table) {
            if (! isset($safe_tables[substr((string) $table, strlen((string) $prefixeTable))])) {
                upgrade65_change_table_to_blob($table, $all_tables_definition[$table]);
            }

            upgrade65_change_table_to_charset($table, $all_tables_definition[$table], 'utf8');
            $query = 'ALTER TABLE ' . $table . ' DEFAULT CHARACTER SET utf8';
            pwg_query($query);
        }

        $upgrade_log .= "< conversion\tchange binary\n";
        $upgrade_log .= "< conversion\tchange utf8\n";
    } elseif ($admin_charset === 'iso-8859-2'/*Central European*/) {
        $pwg_charset = 'utf-8';
        $db_charset = 'utf8';
        foreach ($all_tables as $table) {
            if (! isset($safe_tables[substr((string) $table, strlen((string) $prefixeTable))])) {
                upgrade65_change_table_to_blob($table, $all_tables_definition[$table]);
                upgrade65_change_table_to_charset($table, $all_tables_definition[$table], 'latin2');
            }

            upgrade65_change_table_to_charset($table, $all_tables_definition[$table], 'utf8');
            $query = 'ALTER TABLE ' . $table . ' DEFAULT CHARACTER SET utf8';
            pwg_query($query);
        }

        $upgrade_log .= "< conversion\tchange binary\n";
        $upgrade_log .= "< conversion\tchange latin2\n";
        $upgrade_log .= "< conversion\tchange utf8\n";
    }

    // +-----------------------------------------------------------------------+
    // changes to write in database.inc.php
    $mysql_changes[] =
"define('PWG_CHARSET', '" . $pwg_charset . '\');
define(\'DB_CHARSET\',  \'' . $db_charset . '\');
define(\'DB_COLLATE\',  \'\');';

    foreach ($all_langs as $old_lang => $lang_data) {
        $query = '
  UPDATE ' . USER_INFOS_TABLE . ' SET language="' . $lang_data['new_lang'] . '"
    WHERE language="' . $old_lang . '"';
        pwg_query($query);
    }

    define('PWG_CHARSET', $pwg_charset);
    define('DB_CHARSET', $db_charset);
    define('DB_COLLATE', '');

    if (version_compare(mysql_get_server_info(), '4.1.0', '>=') && DB_CHARSET !== '') {
        pwg_query('SET NAMES "' . DB_CHARSET . '"');
    }

    echo $upgrade_log;
    $fp = fopen(PHPWG_ROOT_PATH . 'upgrade65.log', 'w');
    if ($fp) {
        fwrite($fp, $upgrade_log, strlen($upgrade_log));
        fclose($fp);
    }

    echo "\n"
    . '"' . $upgrade_description . '"' . ' ended'
    . "\n"
    ;
} else {
    echo 'PWG_CHARSET already defined - nada';
}
