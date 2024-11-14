<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

define('DB_ENGINE', 'MySQL');
define('REQUIRED_MYSQL_VERSION', '8.0.39');

define('DB_REGEX_OPERATOR', 'REGEXP');
define('DB_RANDOM_FUNCTION', 'RAND()');

/**
 * Connect to database and store MySQLi resource in __$mysqli__ global variable.
 *
 * @param string $host
 *    - localhost
 *    - 1.2.3.4:3405
 *    - /path/to/socket
 */
function pwg_db_connect(
    string $host,
    string $user,
    string $password,
    string $database
): void {
    global $mysqli;

    $port = null;
    $socket = null;

    if (str_starts_with($host, '/')) {
        $socket = $host;
        $host = null;
    } elseif (str_contains($host, ':')) {
        [$host, $port] = explode(':', $host);
    }

    $mysqli = new mysqli($host, $user, $password, '', (int) $port, $socket);
    if (mysqli_connect_error()) {
        throw new Exception("Can't connect to server");
    }

    if ($database !== '' && $database !== '0' && ! $mysqli->select_db($database)) {
        throw new Exception('Connection to server succeed, but it was impossible to connect to database');
    }

    // mysqli_options($mysqli, MYSQLI_OPT_INT_AND_FLOAT_NATIVE, true);

    pwg_query("SET SESSION sql_mode = 'ANSI,TRADITIONAL,NO_ENGINE_SUBSTITUTION'");
}

/**
 * Check MySQL version. Can call fatal_error().
 */
function pwg_db_check_version(): void
{
    $current_mysql = pwg_get_db_version();
    if (version_compare($current_mysql, REQUIRED_MYSQL_VERSION, '<')) {
        fatal_error(
            sprintf(
                'your MySQL version is too old, you have "%s" and you need at least "%s"',
                $current_mysql,
                REQUIRED_MYSQL_VERSION
            )
        );
    }
}

/**
 * Get Mysql Version.
 */
function pwg_get_db_version(): string
{
    global $mysqli;

    return $mysqli->server_info;
}

/**
 * Execute a query
 */
function pwg_query(
    string $query
): bool|mysqli_result|null {
    global $mysqli, $conf, $page, $debug, $t2;

    $start = microtime(true);

    try {
        $result = $mysqli->query($query);
    } catch (Throwable) {
        $error = '[mysql error ' . $mysqli->errno . '] ' . $mysqli->error . "\n";
        $error .= $query;

        if ($conf['die_on_sql_error']) {
            fatal_error($error);
        }

        echo '<pre>';
        trigger_error($error, E_USER_WARNING);
        echo '</pre>';
    }

    $time = microtime(true) - $start;

    if (! isset($page['count_queries'])) {
        $page['count_queries'] = 0;
        $page['queries_time'] = 0;
    }

    $page['count_queries']++;
    $page['queries_time'] += $time;

    if ($conf['show_queries']) {
        $output = '';
        $output .= '<pre>[' . $page['count_queries'] . '] ';
        $output .= "\n" . $query;
        $output .= "\n" . '(this query time : ';
        $output .= '<b>' . number_format($time, 3, '.', ' ') . ' s)</b>';
        $output .= "\n" . '(total SQL time  : ';
        $output .= number_format($page['queries_time'], 3, '.', ' ') . ' s)';
        $output .= "\n" . '(total time      : ';
        $output .= number_format(($time + $start - $t2), 3, '.', ' ') . ' s)';
        if ($result != null && preg_match('/\s*SELECT\s+/i', $query)) {
            $output .= "\n" . '(num rows        : ';
            $output .= pwg_db_num_rows($result) . ' )';
        } elseif ($result != null && preg_match('/\s*INSERT|UPDATE|REPLACE|DELETE\s+/i', $query)) {
            $output .= "\n" . '(affected rows   : ';
            $output .= pwg_db_changes() . ' )';
        }

        $output .= "</pre>\n";

        $debug .= $output;
    }

    return $result;
}

/**
 * Get max value plus one of a particular column.
 */
function pwg_db_nextval(
    string $column,
    string $table
): string {
    $query = <<<SQL
        SELECT IF(MAX({$column}) + 1 IS NULL, 1, MAX({$column}) + 1)
        FROM {$table};
        SQL;
    [$next] = pwg_db_fetch_row(pwg_query($query));

    return $next;
}

function pwg_db_changes(): int|string
{
    global $mysqli;

    return $mysqli->affected_rows;
}

function pwg_db_num_rows(
    mysqli_result $result
): int|string {
    return $result->num_rows;
}

function pwg_db_fetch_assoc(
    mysqli_result $result
): array|bool|null {
    return $result->fetch_assoc();
}

function pwg_db_fetch_row(
    mysqli_result $result
): array|bool|null {
    return $result->fetch_row();
}

function pwg_db_real_escape_string(
    ?string $s
): ?string {
    global $mysqli;

    return isset($s) ? $mysqli->real_escape_string($s) : null;
}

function pwg_db_insert_id(): int|string
{
    global $mysqli;

    return $mysqli->insert_id;
}

function pwg_db_close(): bool
{
    global $mysqli;

    return $mysqli->close();
}

define('MASS_UPDATES_SKIP_EMPTY', 1);

/**
 * Updates multiple lines in a table.
 *
 * @param array $dbfields - contains 'primary' and 'update' arrays
 * @param array $datas - indexed by column names
 * @param int $flags - if MASS_UPDATES_SKIP_EMPTY, empty values do not overwrite existing ones
 */
function mass_updates(
    string $tablename,
    array $dbfields,
    array $datas,
    int $flags = 0
): void {
    if (count($datas) == 0) {
        return;
    }

    // we use the multi table update or N update queries
    if (count($datas) < 10) {
        foreach ($datas as $data) {
            $is_first = true;

            $query = <<<SQL
                UPDATE {$tablename}
                SET

                SQL;

            foreach ($dbfields['update'] as $key) {
                $separator = $is_first ? '' : ",\n    ";

                if (isset($data[$key]) && $data[$key] != '') {
                    $query .= "{$separator}{$key} = '{$data[$key]}'\n";
                } else {
                    if (($flags & MASS_UPDATES_SKIP_EMPTY) !== 0) {
                        continue; // next field
                    }

                    $query .= "{$separator}{$key} = NULL\n";
                }

                $is_first = false;
            }

            if (! $is_first) {// only if one field at least updated
                $is_first = true;

                $query .= " WHERE\n";
                foreach ($dbfields['primary'] as $key) {
                    if (! $is_first) {
                        $query .= ' AND ';
                    }

                    if (isset($data[$key])) {
                        $query .= "{$key} = '{$data[$key]}'\n";
                    } else {
                        $query .= "{$key} IS NULL\n";
                    }

                    $is_first = false;
                }

                pwg_query($query);
            }
        } // foreach update
    } // if count<X
    else {
        // creation of the temporary table
        $result = pwg_query("SHOW FULL COLUMNS FROM {$tablename};");
        $columns = [];
        $all_fields = array_merge($dbfields['primary'], $dbfields['update']);

        while ($row = pwg_db_fetch_assoc($result)) {
            if (in_array($row['Field'], $all_fields)) {
                $column = $row['Field'];
                $column .= " {$row['Type']}";

                $nullable = true;
                if (! isset($row['Null']) || $row['Null'] == '' || $row['Null'] == 'NO') {
                    $column .= ' NOT NULL';
                    $nullable = false;
                }

                if (isset($row['Default'])) {
                    $column .= " default '{$row['Default']}'";
                } elseif ($nullable) {
                    $column .= ' default NULL';
                }

                if (isset($row['Collation']) && $row['Collation'] != 'NULL') {
                    $column .= " collate '{$row['Collation']}'";
                }

                $columns[] = $column;
            }
        }

        $temporary_tablename = $tablename . '_' . micro_seconds();

        $columnsList = implode(",\n  ", $columns);
        $primaryKeys = implode(',', $dbfields['primary']);
        $query = <<<SQL
            CREATE TABLE {$temporary_tablename}
                (
                    {$columnsList},
                    UNIQUE KEY the_key ({$primaryKeys})
                );
            SQL;

        pwg_query($query);
        mass_inserts($temporary_tablename, $all_fields, $datas);

        if (($flags & MASS_UPDATES_SKIP_EMPTY) !== 0) {
            $func_set = fn (string $s): string => "t1.{$s} = IFNULL(t2.{$s}, t1.{$s})";
        } else {
            $func_set = fn (string $s): string => "t1.{$s} = t2.{$s}";
        }

        // update of table by joining with temporary table
        $updateFields = implode(
            "\n    , ",
            array_map($func_set, $dbfields['update'])
        );

        $primaryConditions = implode(
            "\n    AND ",
            array_map(
                fn (string $s): string => "t1.{$s} = t2.{$s}",
                $dbfields['primary']
            )
        );

        $query = <<<SQL
            UPDATE {$tablename} AS t1, {$temporary_tablename} AS t2
            SET {$updateFields}
            WHERE {$primaryConditions};
            SQL;
        pwg_query($query);

        pwg_query("DROP TABLE {$temporary_tablename};");
    }
}

/**
 * Updates one line in a table.
 *
 * @param int $flags - if MASS_UPDATES_SKIP_EMPTY, empty values do not overwrite existing ones
 */
function single_update(
    string $tablename,
    array $datas,
    array $where,
    int $flags = 0
): void {
    if (count($datas) == 0) {
        return;
    }

    $is_first = true;

    $query = <<<SQL
        UPDATE {$tablename}
        SET

        SQL;

    foreach ($datas as $key => $value) {
        $separator = $is_first ? '' : ",\n    ";

        if (isset($value) && $value !== '') {
            $query .= "{$separator}{$key} = '{$value}'\n";
        } else {
            if (($flags & MASS_UPDATES_SKIP_EMPTY) !== 0) {
                continue; // next field
            }

            $query .= "{$separator}{$key} = NULL\n";
        }

        $is_first = false;
    }

    if (! $is_first) {// only if one field at least updated
        $is_first = true;

        $query .= " WHERE\n";

        foreach ($where as $key => $value) {
            if (! $is_first) {
                $query .= ' AND ';
            }

            if (isset($value)) {
                $query .= "{$key} = '{$value}'\n";
            } else {
                $query .= "{$key} IS NULL\n";
            }

            $is_first = false;
        }

        $query .= ';';
        pwg_query($query);
    }
}

/**
 * Inserts multiple lines in a table.
 *
 * @param array $dbfields - fields from $datas which will be used
 * @param array $options
 *    - bool ignore - use "INSERT IGNORE"
 */
function mass_inserts(
    string $table_name,
    array $dbfields,
    array $datas,
    array $options = []
): void {
    $ignore = '';
    if (isset($options['ignore']) && $options['ignore']) {
        $ignore = 'IGNORE';
    }

    if (count($datas) != 0) {
        $query = <<<SQL
            SHOW VARIABLES LIKE 'max_allowed_packet';
            SQL;
        [, $packet_size] = pwg_db_fetch_row(pwg_query($query));
        $dbfields_ = implode(',', $dbfields);
        $queryBase = "INSERT {$ignore} INTO {$table_name} ({$dbfields_}) VALUES ";
        $query = '';

        foreach ($datas as $insert) {
            $queryTemp = '(';

            foreach ($dbfields as $field_id => $dbfield) {
                if ($field_id > 0) {
                    $queryTemp .= ',';
                }

                if (! isset($insert[$dbfield]) || $insert[$dbfield] === '') {
                    $queryTemp .= 'NULL';
                } else {
                    $queryTemp .= "'{$insert[$dbfield]}'";
                }
            }

            $queryTemp .= ')';

            $len = strlen($queryBase . $query . ', ' . $queryTemp);

            if ($len >= $packet_size) { // delay $insert to next query
                pwg_query($queryBase . $query);
                $query = $queryTemp;
            } else {
                if ($query !== '' && $query !== '0') {
                    $query .= ', ';
                }

                $query .= $queryTemp;
            }
        }

        pwg_query($queryBase . $query);
    }
}

/**
 * Inserts one line in a table.
 *
 * @param array $options
 *    - bool ignore - use "INSERT IGNORE"
 */
function single_insert(
    string $table_name,
    array $data,
    array $options = []
): void {
    $ignore = '';
    if (isset($options['ignore']) && $options['ignore']) {
        $ignore = 'IGNORE';
    }

    if (count($data) != 0) {
        $columns = implode(',', array_keys($data));
        $query = <<<SQL
            INSERT {$ignore} INTO {$table_name}
                ({$columns})
            VALUES

            SQL;

        $query .= '(';
        $is_first = true;
        foreach ($data as $value) {
            if (! $is_first) {
                $query .= ',';
            } else {
                $is_first = false;
            }

            if ($value === '' || $value === null) {
                $query .= 'NULL';
            } else {
                $query .= "'{$value}'";
            }
        }

        $query .= ');';

        pwg_query($query);
    }
}

/**
 * Do maintenance on all Piwigo tables
 */
function do_maintenance_all_tables(): void
{
    global $page;

    $all_tables = [];

    // List all tables
    $query = <<<SQL
        SHOW TABLES;
        SQL;
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_row($result)) {
        $all_tables[] = $row[0];
    }

    // Repair all tables
    $allTablesList = implode(', ', $all_tables);
    $query = <<<SQL
        REPAIR TABLE {$allTablesList};
        SQL;
    $mysqli_rc = pwg_query($query);

    // Re-Order all tables
    foreach ($all_tables as $table_name) {
        $all_primary_key = [];

        $query = <<<SQL
            DESC {$table_name};
            SQL;
        $result = pwg_query($query);
        while ($row = pwg_db_fetch_assoc($result)) {
            if ($row['Key'] == 'PRI') {
                $all_primary_key[] = $row['Field'];
            }
        }

        if (count($all_primary_key) != 0) {
            $allPrimaryKeyList = implode(', ', $all_primary_key);
            $query = <<<SQL
                ALTER TABLE {$table_name}
                ORDER BY {$allPrimaryKeyList};
                SQL;
            $mysqli_rc = $mysqli_rc && pwg_query($query);
        }
    }

    // Optimize all tables
    $allTablesList = implode(', ', $all_tables);
    $query = <<<SQL
        OPTIMIZE TABLE {$allTablesList};
        SQL;
    $mysqli_rc = $mysqli_rc && pwg_query($query);
    if ($mysqli_rc) {
        $page['infos'][] = l10n('All optimizations have been successfully completed.');
    } else {
        $page['errors'][] = l10n('Optimizations have been completed with some errors.');
    }
}

function pwg_db_concat(
    array $array
): string {
    $string = implode(',', $array);
    return "CONCAT({$string})";
}

function pwg_db_concat_ws(
    array $array,
    string $separator
): string {
    $string = implode(',', $array);
    return "CONCAT_WS('{$separator}', {$string})";
}

/**
 * Returns an array containing the possible values of an enum field.
 *
 * @return string[]
 */
function get_enums(
    string $table,
    string $field
): array|bool {
    $result = pwg_query("DESC {$table};");
    while ($row = pwg_db_fetch_assoc($result)) {
        if ($row['Field'] == $field) {
            // parse enum('blue','green','black')
            $options = explode(',', substr((string) $row['Type'], 5, -1));
            foreach ($options as $i => $option) {
                $options[$i] = str_replace("'", '', $option);
            }
        }
    }

    $result->free_result();
    return $options;
}

/**
 * Checks if a variable is equivalent to true or false.
 */
function get_boolean(
    string $input
): bool {
    if (strtolower($input) === 'false') {
        return false;
    }

    return (bool) $input;
}

/**
 * Returns string 'true' or 'false' if the given var is bool.
 * If the input is another type, it is not changed.
 */
function boolean_to_string(
    string|bool|int $var
): mixed {
    if (is_bool($var)) {
        return $var ? 'true' : 'false';
    }

    return $var;

}

function pwg_db_get_recent_period_expression(
    int|string $period,
    string $date = 'CURRENT_DATE'
): string {
    if ($date !== 'CURRENT_DATE') {
        $date = "'{$date}'";
    }

    return <<<SQL
        SUBDATE({$date}, INTERVAL {$period} DAY)
        SQL;
}

function pwg_db_get_recent_period(
    int|string $period,
    string $date = 'CURRENT_DATE'
): string {
    $recentPeriodExpression = pwg_db_get_recent_period_expression($period);
    $query = <<<SQL
        SELECT {$recentPeriodExpression};
        SQL;
    [$d] = pwg_db_fetch_row(pwg_query($query));

    return $d;
}

function pwg_db_get_flood_period_expression(
    string $seconds
): string {
    return <<<SQL
        SUBDATE(NOW(), INTERVAL {$seconds} SECOND)
        SQL;
}

function pwg_db_get_hour(
    string $date
): string {
    return <<<SQL
        HOUR({$date})
        SQL;
}

function pwg_db_get_date_YYYYMM(
    string $date
): string {
    return <<<SQL
        DATE_FORMAT({$date}, '%Y%m')
        SQL;
}

function pwg_db_get_date_MMDD(
    string $date
): string {
    return <<<SQL
        DATE_FORMAT({$date}, '%m%d')
        SQL;
}

function pwg_db_get_year(
    string $date
): string {
    return <<<SQL
        YEAR({$date})
        SQL;
}

function pwg_db_get_month(
    string $date
): string {
    return <<<SQL
        MONTH({$date})
        SQL;
}

function pwg_db_get_week(
    string $date,
    ?int $mode = null
): string {
    if ($mode) {
        return <<<SQL
            WEEK({$date}, {$mode})
            SQL;
    }

    return <<<SQL
        WEEK({$date})
        SQL;
}

function pwg_db_get_dayofmonth(
    string $date
): string {
    return <<<SQL
        DAYOFMONTH({$date})
        SQL;
}

function pwg_db_get_dayofweek(
    string $date
): string {
    return <<<SQL
        DAYOFWEEK({$date})
        SQL;
}

function pwg_db_get_weekday(
    string $date
): string {
    return <<<SQL
        WEEKDAY({$date})
        SQL;
}

function pwg_db_date_to_ts(
    string $date
): string {
    return <<<SQL
        UNIX_TIMESTAMP({$date})
        SQL;
}

/**
 * Builds a data array from a SQL query.
 * Depending on $key_name and $value_name it can return :
 *
 *    - an array of arrays of all fields (key=null, value=null)
 *        array(
 *          array('id'=>1, 'name'=>'DSC8956', ...),
 *          array('id'=>2, 'name'=>'DSC8957', ...),
 *          ...
 *          )
 *
 *    - an array of a single field (key=null, value='...')
 *        array('DSC8956', 'DSC8957', ...)
 *
 *    - an associative array of array of all fields (key='...', value=null)
 *        array(
 *          'DSC8956' => array('id'=>1, 'name'=>'DSC8956', ...),
 *          'DSC8957' => array('id'=>2, 'name'=>'DSC8957', ...),
 *          ...
 *          )
 *
 *    - an associative array of a single field (key='...', value='...')
 *        array(
 *          'DSC8956' => 1,
 *          'DSC8957' => 2,
 *          ...
 *          )
 *
 * @since 2.6
 */
function query2array(
    string $query,
    ?string $key_name = null,
    ?string $value_name = null
): array {
    $result = pwg_query($query);
    $data = [];

    if (isset($key_name)) {
        if (isset($value_name)) {
            while ($row = $result->fetch_assoc()) {
                $data[$row[$key_name]] = $row[$value_name];
            }
        } else {
            while ($row = $result->fetch_assoc()) {
                $data[$row[$key_name]] = $row;
            }
        }
    } elseif (isset($value_name)) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row[$value_name];
        }
    } else {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }

    return $data;
}

// todo: remove '1 = 1'
