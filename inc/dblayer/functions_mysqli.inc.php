<?php

declare(strict_types=1);

namespace Piwigo\inc\DbLayer;

use function Piwigo\inc\fatal_error;
use function Piwigo\inc\l10n;
use function Piwigo\inc\micro_seconds;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

const DB_ENGINE = 'MySQL';
const REQUIRED_MYSQL_VERSION = '8.0.37';

const DB_REGEX_OPERATOR = 'REGEXP';
const DB_RANDOM_FUNCTION = 'RAND';

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

    $mysqli = new \mysqli($host, $user, $password, '', $port, $socket);
    if (mysqli_connect_error()) {
        throw new \Exception("Can't connect to server");
    }

    if (! empty($database) && ! $mysqli->select_db($database)) {
        throw new \Exception('Connection to server succeed, but it was impossible to connect to database');
    }

    // MySQL 5.7 default settings forbid to select a colum that is not in the
    // group by. We've used that in Piwigo, for years. As an immediate solution
    // we can remove this constraint in the current MySQL session.
    [$sql_mode_current] = pwg_db_fetch_row(
        pwg_query('SELECT @@SESSION.sql_mode')
    );

    // remove ONLY_FULL_GROUP_BY from the list
    $sql_mode_altered = implode(
        ',',
        array_diff(explode(',', (string) $sql_mode_current), ['ONLY_FULL_GROUP_BY'])
    );

    if ($sql_mode_altered != $sql_mode_current) {
        pwg_query("SET SESSION sql_mode='" . $sql_mode_altered . "'");
    }
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
function pwg_query(string $query): \mysqli_result|bool
{
    global $mysqli, $conf, $page, $debug, $t2;

    $start = microtime(true);

    try {
        $result = $mysqli->query($query);
    } catch (Throwable) {
        my_error($query, $conf['die_on_sql_error']);
    }

    $time = microtime(true) - $start;

    if (! isset($page['count_queries'])) {
        $page['count_queries'] = 0;
        $page['queries_time'] = 0;
    }

    $page['count_queries']++;
    $page['queries_time'] += $time;

    if ($conf['show_queries']) {
        $output = '<pre>[' . $page['count_queries'] . '] ';
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
        } elseif ($result != null
          && preg_match('/\s*INSERT|UPDATE|REPLACE|DELETE\s+/i', $query)) {
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
): mixed {
    $query = '
SELECT IF(MAX(' . $column . ')+1 IS NULL, 1, MAX(' . $column . ')+1)
  FROM ' . $table;
    [$next] = pwg_db_fetch_row(pwg_query($query));

    return $next;
}

function pwg_db_changes(): int|string
{
    global $mysqli;

    return $mysqli->affected_rows;
}

function pwg_db_num_rows($result): mixed
{
    return $result->num_rows;
}

function pwg_db_fetch_array($result): mixed
{
    return $result->fetch_array();
}

function pwg_db_fetch_assoc($result): mixed
{
    return $result->fetch_assoc();
}

function pwg_db_fetch_row($result): mixed
{
    return $result->fetch_row();
}

function pwg_db_fetch_object($result): mixed
{
    return $result->fetch_object();
}

function pwg_db_free_result($result): mixed
{
    return $result->free_result();
}

function pwg_db_real_escape_string($s): ?string
{
    global $mysqli;

    return isset($s) ? $mysqli->real_escape_string($s) : null;
}

function pwg_db_insert_id(): int|string
{
    global $mysqli;

    return $mysqli->insert_id;
}

function pwg_db_errno(): int
{
    global $mysqli;

    return $mysqli->errno;
}

function pwg_db_error(): string
{
    global $mysqli;

    return $mysqli->error;
}

function pwg_db_close(): bool
{
    global $mysqli;

    return $mysqli->close();
}

const MASS_UPDATES_SKIP_EMPTY = 1;

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

            $query = '
UPDATE ' . protect_column_name($tablename) . '
  SET ';

            foreach ($dbfields['update'] as $key) {
                $separator = $is_first ? '' : ",\n    ";

                if (isset($data[$key]) && $data[$key] != '') {
                    $query .= $separator . protect_column_name($key) . " = '" . $data[$key] . "'";
                } else {
                    if (($flags & MASS_UPDATES_SKIP_EMPTY) !== 0) {
                        continue; // next field
                    }

                    $query .= $separator . protect_column_name($key) . ' = NULL';
                }

                $is_first = false;
            }

            if (! $is_first) {// only if one field at least updated
                $is_first = true;

                $query .= '
  WHERE ';
                foreach ($dbfields['primary'] as $key) {
                    if (! $is_first) {
                        $query .= ' AND ';
                    }

                    if (isset($data[$key])) {
                        $query .= protect_column_name($key) . " = '" . $data[$key] . "'";
                    } else {
                        $query .= protect_column_name($key) . ' IS NULL';
                    }

                    $is_first = false;
                }

                pwg_query($query);
            }
        } // foreach update
    } // if count<X
    else {
        // creation of the temporary table
        $result = pwg_query(
            'SHOW FULL COLUMNS FROM ' . protect_column_name($tablename)
        );
        $columns = [];
        $all_fields = array_merge($dbfields['primary'], $dbfields['update']);

        while ($row = pwg_db_fetch_assoc($result)) {
            if (in_array($row['Field'], $all_fields)) {
                $column = '`' . $row['Field'] . '`';
                $column .= ' ' . $row['Type'];

                $nullable = true;
                if (! isset($row['Null']) || $row['Null'] == '' || $row['Null'] == 'NO') {
                    $column .= ' NOT NULL';
                    $nullable = false;
                }

                if (isset($row['Default'])) {
                    $column .= " default '" . $row['Default'] . "'";
                } elseif ($nullable) {
                    $column .= ' default NULL';
                }

                if (isset($row['Collation']) && $row['Collation'] != 'NULL') {
                    $column .= " collate '" . $row['Collation'] . "'";
                }

                $columns[] = $column;
            }
        }

        $temporary_tablename = str_replace('`', '', $tablename) . '_' . micro_seconds();

        $query = '
CREATE TABLE ' . protect_column_name($temporary_tablename) . '
(
  ' . implode(",\n  ", $columns) . ',
  UNIQUE KEY the_key (' . implode(',', $dbfields['primary']) . ')
)';

        pwg_query($query);
        mass_inserts($temporary_tablename, $all_fields, $datas);

        if (($flags & MASS_UPDATES_SKIP_EMPTY) !== 0) {
            $func_set = fn ($s) => sprintf('t1.%s = IFNULL(t2.%s, t1.%s)', $s, $s, $s);
        } else {
            $func_set = fn ($s) => sprintf('t1.%s = t2.%s', $s, $s);
        }

        // update of table by joining with temporary table
        $query = '
UPDATE ' . protect_column_name($tablename) . ' AS t1, ' . $temporary_tablename . ' AS t2
  SET ' .
          implode(
              "\n    , ",
              array_map($func_set, $dbfields['update'])
          ) . '
  WHERE ' .
          implode(
              "\n    AND ",
              array_map(
                  fn ($s) => sprintf('t1.%s = t2.%s', $s, $s),
                  $dbfields['primary']
              )
          );
        pwg_query($query);

        pwg_query('DROP TABLE ' . $temporary_tablename);
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

    $query = '
UPDATE ' . protect_column_name($tablename) . '
  SET ';

    foreach ($datas as $key => $value) {
        $separator = $is_first ? '' : ",\n    ";

        if (isset($value) && $value !== '') {
            $query .= $separator . protect_column_name($key) . " = '" . $value . "'";
        } else {
            if (($flags & MASS_UPDATES_SKIP_EMPTY) !== 0) {
                continue; // next field
            }

            $query .= $separator . protect_column_name($key) . ' = NULL';
        }

        $is_first = false;
    }

    if (! $is_first) {// only if one field at least updated
        $is_first = true;

        $query .= '
  WHERE ';

        foreach ($where as $key => $value) {
            if (! $is_first) {
                $query .= ' AND ';
            }

            if (isset($value)) {
                $query .= protect_column_name($key) . " = '" . $value . "'";
            } else {
                $query .= protect_column_name($key) . ' IS NULL';
            }

            $is_first = false;
        }

        pwg_query($query);
    }
}

/**
 * Inserts multiple lines in a table.
 *
 * @param array $dbfields - fields from $datas which will be used
 * @param array $options
 *    - boolean ignore - use "INSERT IGNORE"
 */
function mass_inserts(
    string $table_name,
    array $dbfields,
    array $datas,
    array $options = [
    ]
): void {
    $ignore = '';
    if (isset($options['ignore']) && $options['ignore']) {
        $ignore = 'IGNORE';
    }

    if (count($datas) != 0) {
        [, $packet_size] = pwg_db_fetch_row(pwg_query("SHOW VARIABLES LIKE 'max_allowed_packet'"));
        $queryBase = 'INSERT ' . $ignore . ' INTO ' . protect_column_name($table_name) . ' (' . implode(
            ',',
            array_map('protect_column_name', $dbfields)
        ) . ') VALUES ';
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
                    $queryTemp .= "'" . $insert[$dbfield] . "'";
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
 *    - boolean ignore - use "INSERT IGNORE"
 */
function single_insert(
    string $table_name,
    array $data,
    array $options = [
    ]
): void {
    $ignore = '';
    if (isset($options['ignore']) && $options['ignore']) {
        $ignore = 'IGNORE';
    }

    if (count($data) != 0) {
        $query = '
INSERT ' . $ignore . ' INTO ' . protect_column_name($table_name) . '
  (' . implode(',', array_map('protect_column_name', array_keys($data))) . ')
  VALUES';

        $query .= '(';
        $is_first = true;
        foreach ($data as $key => $value) {
            if (! $is_first) {
                $query .= ',';
            } else {
                $is_first = false;
            }

            if ($value === '' || $value === null) {
                $query .= 'NULL';
            } else {
                $query .= "'" . $value . "'";
            }
        }

        $query .= ')';

        pwg_query($query);
    }
}

/**
 * @return mixed|string
 */
function protect_column_name($column_name): mixed
{
    if ($column_name[0] != '`') {
        $column_name = '`' . $column_name . '`';
    }

    return $column_name;
}

/**
 * Do maintenance on all Piwigo tables
 */
function do_maintenance_all_tables(): void
{
    global $prefixeTable, $page;

    $all_tables = [];

    // List all tables
    $query = "SHOW TABLES LIKE '" . $prefixeTable . "%'";
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_row($result)) {
        $all_tables[] = '`' . $row[0] . '`';
    }

    // Repair all tables
    $query = 'REPAIR TABLE ' . implode(', ', $all_tables);
    $mysqli_rc = pwg_query($query);

    // Re-Order all tables
    foreach ($all_tables as $table_name) {
        $all_primary_key = [];

        $query = 'DESC ' . $table_name . ';';
        $result = pwg_query($query);
        while ($row = pwg_db_fetch_assoc($result)) {
            if ($row['Key'] == 'PRI') {
                $all_primary_key[] = $row['Field'];
            }
        }

        if (count($all_primary_key) != 0) {
            $query = 'ALTER TABLE ' . $table_name . ' ORDER BY ' . implode(', ', $all_primary_key) . ';';
            $mysqli_rc = $mysqli_rc && pwg_query($query);
        }
    }

    // Optimize all tables
    $query = 'OPTIMIZE TABLE ' . implode(', ', $all_tables);
    $mysqli_rc = $mysqli_rc && pwg_query($query);
    if ($mysqli_rc) {
        $page['infos'][] = l10n('All optimizations have been successfully completed.');
    } else {
        $page['errors'][] = l10n('Optimizations have been completed with some errors.');
    }
}

function pwg_db_concat($array): string
{
    $string = implode(',', $array);
    return 'CONCAT(' . $string . ')';
}

function pwg_db_concat_ws($array, $separator): string
{
    $string = implode(',', $array);
    return "CONCAT_WS('" . $separator . "'," . $string . ')';
}

function pwg_db_cast_to_text($string): mixed
{
    return $string;
}

/**
 * Returns an array containing the possible values of an enum field.
 *
 * @return string[]
 */
function get_enums(
    string $table,
    string $field
): array {
    $result = pwg_query('DESC ' . $table);
    while ($row = pwg_db_fetch_assoc($result)) {
        if ($row['Field'] == $field) {
            // parse enum('blue','green','black')
            $options = explode(',', substr((string) $row['Type'], 5, -1));
            foreach ($options as $i => $option) {
                $options[$i] = str_replace("'", '', $option);
            }
        }
    }

    pwg_db_free_result($result);
    return $options;
}

/**
 * Checks if a variable is equivalent to true or false.
 */
function get_boolean(mixed $input): bool
{
    if (strtolower((string) $input) === 'false') {
        return false;
    }

    return (bool) $input;
}

/**
 * Returns string 'true' or 'false' if the given var is boolean.
 * If the input is another type, it is not changed.
 */
function boolean_to_string(
    mixed $var
): mixed {
    if (is_bool($var)) {
        return $var ? 'true' : 'false';
    }

    return $var;

}

function pwg_db_get_recent_period_expression($period, string $date = 'CURRENT_DATE'): string
{
    if ($date !== 'CURRENT_DATE') {
        $date = "'" . $date . "'";
    }

    return 'SUBDATE(' . $date . ',INTERVAL ' . $period . ' DAY)';
}

function pwg_db_get_recent_period($period, string $date = 'CURRENT_DATE'): mixed
{
    $query = '
SELECT ' . pwg_db_get_recent_period_expression($period);
    [$d] = pwg_db_fetch_row(pwg_query($query));

    return $d;
}

function pwg_db_get_flood_period_expression($seconds): string
{
    return 'SUBDATE(NOW(), INTERVAL ' . $seconds . ' SECOND)';
}

function pwg_db_get_hour($date): string
{
    return 'HOUR(' . $date . ')';
}

function pwg_db_get_date_YYYYMM($date): string
{
    return 'DATE_FORMAT(' . $date . ", '%Y%m')";
}

function pwg_db_get_date_MMDD($date): string
{
    return 'DATE_FORMAT(' . $date . ", '%m%d')";
}

function pwg_db_get_year($date): string
{
    return 'YEAR(' . $date . ')';
}

function pwg_db_get_month($date): string
{
    return 'MONTH(' . $date . ')';
}

function pwg_db_get_week($date, $mode = null): string
{
    if ($mode) {
        return 'WEEK(' . $date . ', ' . $mode . ')';
    }

    return 'WEEK(' . $date . ')';

}

function pwg_db_get_dayofmonth($date): string
{
    return 'DAYOFMONTH(' . $date . ')';
}

function pwg_db_get_dayofweek($date): string
{
    return 'DAYOFWEEK(' . $date . ')';
}

function pwg_db_get_weekday($date): string
{
    return 'WEEKDAY(' . $date . ')';
}

function pwg_db_date_to_ts($date): string
{
    return 'UNIX_TIMESTAMP(' . $date . ')';
}

/**
 * Returns (or send to standard output) the message concerning the
 * error occured for the last mysql query.
 */
function my_error(
    $header,
    $die
): void {
    global $mysqli;

    $error = '[mysql error ' . $mysqli->errno . '] ' . $mysqli->error . "\n";
    $error .= $header;

    if ($die) {
        fatal_error($error);
    }

    echo '<pre>';
    trigger_error($error, E_USER_WARNING);
    echo '</pre>';
}

/**
 * Builds an data array from a SQL query.
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
 */
function query2array(
    string $query,
    string $key_name = null,
    string $value_name = null
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
