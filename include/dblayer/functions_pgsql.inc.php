<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

define('DB_ENGINE', 'PostgreSQL');
define('REQUIRED_POSTGRESQL_VERSION', '17.2');

define('DB_REGEX_OPERATOR', '~');
define('DB_RANDOM_FUNCTION', 'CAST(random() AS text)');

/**
 * Connect to database and store PostgreSQL resource in __$pg__ global variable.
 *
 * @param string $host
 *    - localhost
 *    - 1.2.3.4:5432
 *    - /path/to/socket
 */
function pwg_db_connect(
    string $host,
    string $user,
    string $password,
    ?string $database = null
): void {
    global $pg;

    $port = null;

    if (str_contains($host, ':')) {
        [$host, $port] = explode(':', $host);
    }

    $database ??= 'postgres';
    $database = "dbname={$database}";

    if ($password !== '') {
        $password = "password={$password}";
    }

    if ($port !== null && $port !== '') {
        $port = "port={$port}";
    }

    $connection_string = "host={$host} {$port} {$database} user={$user} {$password}";
    $pg = pg_connect($connection_string);

    if ($pg === false) {
        throw new Exception("Can't connect to server.");
    }

    // PostgreSQL doesn't require a specific session setting for group by
}

/**
 * Check PostgreSQL version. Can call fatal_error().
 */
function pwg_db_check_version(): void
{
    $current_pg = pwg_get_db_version();
    if (version_compare($current_pg, REQUIRED_POSTGRESQL_VERSION, '<')) {
        fatal_error(
            sprintf(
                'Your PostgreSQL version is too old, you have "%s" and you need at least "%s"',
                $current_pg,
                REQUIRED_POSTGRESQL_VERSION
            )
        );
    }
}

/**
 * Get PostgreSQL Version.
 */
function pwg_get_db_version(): string
{
    global $pg;

    $result = pg_query($pg, "SELECT trim(split_part(version(), ',', 1)) AS postgres_version");
    if ($result === false) {
        throw new Exception('Failed to get PostgreSQL version');
    }

    $row = pg_fetch_row($result);

    // Extract the version number using regex
    if (preg_match('/PostgreSQL (\d+\.\d+(\.\d+)?)/', (string) $row[0], $matches)) {
        return $matches[1]; // The extracted version number (e.g., 17.2)
    }

    // Handle error: Unable to parse the version string
    fatal_error('Unable to extract PostgreSQL version.');
    return '';
}

/**
 * Execute a query
 */
function pwg_query(
    string $query
): PgSql\Result|bool {
    global $pg, $conf, $page, $debug, $t2;

    $start = microtime(true);

    $result = pg_query($pg, $query);

    if ($result === false) {
        $error = '[pgsql error] ' . pg_last_error($pg) . "\n";
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
    $query = "SELECT COALESCE(MAX({$column}) + 1, 1) FROM {$table}";
    $result = pwg_query($query);
    $row = pwg_db_fetch_row($result);

    return $row[0];
}

function pwg_db_changes(): int|string
{
    global $pg;

    return pg_affected_rows($pg);
}

function pwg_db_num_rows(
    PgSql\Result $result
): int {
    return pg_num_rows($result);
}

function pwg_db_fetch_assoc(
    PgSql\Result $result
): array|bool {
    return pg_fetch_assoc($result);
}

function pwg_db_fetch_row(
    PgSql\Result $result
): array|bool {
    return pg_fetch_row($result);
}

function pwg_db_real_escape_string(
    string|int|float|null $s
): string|null {
    global $pg;

    if (is_int($s) || is_float($s)) {
        return (string) $s;
    }

    return isset($s) ? pg_escape_string($pg, $s) : null;
}

function pwg_db_insert_id(): int|string
{
    global $pg;

    $result = pg_query($pg, 'SELECT lastval()');
    if ($result === false) {
        throw new Exception('Failed to get last insert id');
    }

    $row = pg_fetch_row($result);
    return $row[0];
}

function pwg_db_close(): bool
{
    global $pg;

    return pg_close($pg);
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

    // If there are less than 10 records, we update individually
    if (count($datas) < 10) {
        foreach ($datas as $data) {
            $is_first = true;
            $query = "UPDATE {$tablename} SET ";

            foreach ($dbfields['update'] as $key) {
                $separator = $is_first ? '' : ",\n    ";

                if (isset($data[$key]) && $data[$key] != '') {
                    $query .= "{$separator}{$key} = '{$data[$key]}'";
                } else {
                    if (($flags & MASS_UPDATES_SKIP_EMPTY) !== 0) {
                        continue; // Skip if empty values are not supposed to overwrite
                    }

                    $query .= "{$separator}{$key} = NULL";
                }

                $is_first = false;
            }

            if (! $is_first) { // Only update if at least one field is set
                $is_first = true;
                $query .= ' WHERE ';

                foreach ($dbfields['primary'] as $key) {
                    if (! $is_first) {
                        $query .= ' AND ';
                    }

                    if (isset($data[$key])) {
                        $query .= "{$key} = '{$data[$key]}'";
                    } else {
                        $query .= "{$key} IS NULL";
                    }

                    $is_first = false;
                }

                pwg_query($query);
            }
        } // End foreach update
    } else {
        // Creation of a temporary table for bulk update
        $result = pwg_query("SELECT column_name, data_type, is_nullable, column_default FROM information_schema.columns WHERE table_name = '{$tablename}'");

        $columns = [];
        $all_fields = array_merge($dbfields['primary'], $dbfields['update']);

        while ($row = pwg_db_fetch_assoc($result)) {
            if (in_array($row['column_name'], $all_fields)) {
                $column = $row['column_name'] . ' ' . $row['data_type'];
                if ($row['is_nullable'] == 'NO') {
                    $column .= ' NOT NULL';
                }

                if ($row['column_default'] !== null) {
                    $column .= " DEFAULT {$row['column_default']}";
                }

                $columns[] = $column;
            }
        }

        $temporary_tablename = $tablename . '_' . micro_seconds();
        $columns_ = implode(",\n  ", $columns);
        $dbfields_ = implode(',', $dbfields['primary']);
        $query = "CREATE TEMPORARY TABLE {$temporary_tablename} ({$columns_}, UNIQUE ({$dbfields_}))";
        pwg_query($query);

        // Insert data into the temporary table
        mass_inserts($temporary_tablename, $all_fields, $datas);

        // Determine the update set based on flags
        if (($flags & MASS_UPDATES_SKIP_EMPTY) !== 0) {
            $func_set = fn (string $s): string => "{$s} = COALESCE(t2.{$s}, {$tablename}.{$s})";
        } else {
            $func_set = fn (string $s): string => "{$s} = t2.{$s}";
        }

        // Construct the update query with a join
        $dbfields_update_ = implode(
            "\n    , ",
            array_map($func_set, $dbfields['update'])
        );
        $dbfields_primary_ = implode(
            "\n    AND ",
            array_map(
                fn (string $s): string => "{$tablename}.{$s} = t2.{$s}",
                $dbfields['primary']
            )
        );

        $query = "UPDATE {$tablename} SET {$dbfields_update_} FROM {$temporary_tablename} t2 WHERE {$dbfields_primary_};";
        pwg_query($query);

        // Drop the temporary table after the update
        pwg_query("DROP TABLE {$temporary_tablename}");
    }
}

/**
 * Updates one row in a table.
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

    $query = "UPDATE {$tablename} SET ";

    foreach ($datas as $key => $value) {
        $separator = $is_first ? '' : ",\n    ";

        if (isset($value) && $value !== '') {
            $query .= "{$separator}{$key} = '{$value}'";
        } else {
            if (($flags & MASS_UPDATES_SKIP_EMPTY) !== 0) {
                continue; // next field
            }

            $query .= "{$separator}{$key} = NULL";
        }

        $is_first = false;
    }

    if (! $is_first) { // only if at least one field is updated
        $is_first = true;

        $query .= ' WHERE ';

        foreach ($where as $key => $value) {
            if (! $is_first) {
                $query .= ' AND ';
            }

            if (isset($value)) {
                $query .= "{$key} = '{$value}'";
            } else {
                $query .= "{$key} IS NULL";
            }

            $is_first = false;
        }

        pwg_query($query);
    }
}

/**
 * Insert multiple lines into a table.
 */
function mass_inserts(
    string $tablename,
    array $dbfields,
    array $datas
): void {
    if (count($datas) == 0) {
        return;
    }

    $query = "INSERT INTO {$tablename} (" . implode(', ', $dbfields) . ') VALUES ';

    $values = [];
    foreach ($datas as $data) {
        $row_values = [];

        foreach ($dbfields as $key) {
            $row_values[] = isset($data[$key]) ? "'" . pwg_db_real_escape_string($data[$key]) . "'" : 'NULL';
        }

        $values[] = '(' . implode(', ', $row_values) . ')';
    }

    $query .= implode(', ', $values);

    pwg_query($query);
}

/**
 * Inserts one row into a table.
 *
 * @param array $options - bool ignore - use "ON CONFLICT DO NOTHING" for PostgreSQL
 */
function single_insert(
    string $table_name,
    array $data,
    array $options = []
): void {
    $on_conflict = '';
    if (isset($options['ignore']) && $options['ignore']) {
        $on_conflict = 'ON CONFLICT DO NOTHING';
    }

    if (count($data) != 0) {
        $columns = implode(', ', array_keys($data));
        $values = implode(', ', array_values($data));

        $query = "INSERT INTO {$table_name} ({$columns}) VALUES ({$values}) {$on_conflict};";
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
    $query = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public';";
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result)) {
        $all_tables[] = '"' . $row['table_name'] . '"';
    }

    // PostgreSQL does not have a direct REPAIR TABLE equivalent, so this step is skipped.

    // Re-Order all tables
    foreach ($all_tables as $table_name) {
        $all_primary_key = [];

        $query = "SELECT column_name FROM information_schema.key_column_usage WHERE table_name = {$table_name} AND constraint_name LIKE '%pkey%';";
        $result = pwg_query($query);
        while ($row = pwg_db_fetch_assoc($result)) {
            $all_primary_key[] = '"' . $row['column_name'] . '"';
        }

        if (count($all_primary_key) != 0) {
            // Re-ordering tables is not a direct feature in PostgreSQL
            // This might be application-specific and may not be applicable as is.
        }
    }

    // Optimize all tables
    // PostgreSQL does not have an OPTIMIZE TABLE command.
    // You may use VACUUM ANALYZE to optimize tables.
    foreach ($all_tables as $table_name) {
        $query = "VACUUM ANALYZE {$table_name};";
        $pgsql_rc = pwg_query($query);
    }

    if ($pgsql_rc) {
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
    $options = [];
    $query = "SELECT e.enumlabel FROM pg_enum e JOIN pg_type t ON e.enumtypid = t.oid WHERE t.typname = 'status_enum' ORDER BY e.enumsortorder";
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result)) {
        $options[] = $row['enumlabel'];
    }

    return $options;
}

/**
 * Checks if a variable is equivalent to true or false.
 */
function get_boolean(
    string $input
): bool {
    return strtolower($input) === 'false' ? false : (bool) $input;
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
        $date = "'" . $date . "'";
    }

    return "CURRENT_DATE - INTERVAL '" . $period . " days'";
}

function pwg_db_get_recent_period(
    int|string $period,
    string $date = 'CURRENT_DATE'
): string {
    $query = 'SELECT ' . pwg_db_get_recent_period_expression($period) . ' AS date';
    [$d] = pwg_db_fetch_row(pwg_query($query));

    return $d;
}

function pwg_db_get_flood_period_expression(
    string $seconds
): string {
    return "CURRENT TIMESTAMP - INTERVAL '{$seconds} seconds'";
}

function pwg_db_get_hour(
    string $date
): string {
    return "EXTRACT(HOUR FROM {$date})";
}

function pwg_db_get_date_YYYYMM(
    string $date
): string {
    return "TO_CHAR({$date}, 'YYYYMM')";
}

function pwg_db_get_date_MMDD(
    string $date
): string {
    return "TO_CHAR({$date}, 'MMDD')";
}

function pwg_db_get_year(
    string $date
): string {
    return "EXTRACT(YEAR FROM {$date})";
}

function pwg_db_get_month(
    string $date
): string {
    return "EXTRACT(MONTH FROM {$date})";
}

function pwg_db_get_week(
    string $date,
    ?int $mode = null
): string {
    if ($mode) {
        return "EXTRACT(WEEK FROM {$date})";
    }

    return "EXTRACT(WEEK FROM {$date})";
}

function pwg_db_get_dayofmonth(
    string $date
): string {
    return "EXTRACT(DAY FROM {$date})";
}

function pwg_db_get_dayofweek(
    string $date
): string {
    return "EXTRACT(DOW FROM {$date})";
}

function pwg_db_get_weekday(
    string $date
): string {
    return "EXTRACT(DOW FROM {$date})";
}

function pwg_db_date_to_ts(
    string $date
): string {
    return "EXTRACT(EPOCH FROM {$date})";
}

/**
 * Builds an data array from a SQL query.
 * Depending on $key_name and $value_name it can return :
 * ...
 * @since 2.6
 */
function query2array(
    string $query,
    ?string $key_name = null,
    ?string $value_name = null
): array {
    $result = pwg_query($query);
    $data = [];

    while ($row = pwg_db_fetch_assoc($result)) {
        if (isset($key_name)) {
            $data[$row[$key_name]] = isset($value_name) ? $row[$value_name] : $row;
        } elseif (isset($value_name)) {
            $data[] = $row[$value_name];
        } else {
            $data[] = $row;
        }
    }

    return $data;
}
