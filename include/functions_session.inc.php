<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

if (isset($conf['session_save_handler']) && $conf['session_save_handler'] == 'db' && defined('PHPWG_INSTALLED')) {
    require_once PHPWG_ROOT_PATH . 'include/PwgSessionHandler.php';
    $handler = new PwgSessionHandler();
    session_set_save_handler($handler, true);

    if (function_exists('ini_set')) {
        ini_set('session.use_cookies', $conf['session_use_cookies']);
        ini_set('session.use_only_cookies', $conf['session_use_only_cookies']);
        ini_set('session.use_trans_sid', intval($conf['session_use_trans_sid']));
        ini_set('session.cookie_httponly', 1);
    }

    session_name($conf['session_name']);
    session_set_cookie_params(0, cookie_path());
    register_shutdown_function(session_write_close(...));
}

/**
 * Generates a pseudo random string.
 * Characters used are a-z A-Z and numerical values.
 */
function generate_key(
    int $size
): string {

    $bytes = random_bytes($size + 10);

    return substr(
        str_replace(
            ['+', '/'],
            '',
            base64_encode($bytes)
        ),
        0,
        $size
    );
}

/**
 * Called by PHP session manager, always return true.
 */
function pwg_session_open(
    string $path,
    string $name
): bool {
    return true;
}

/**
 * Called by PHP session manager, always return true.
 */
function pwg_session_close(): bool
{
    return true;
}

/**
 * Returns a hash from current user IP
 */
function get_remote_addr_session_hash(): string
{
    global $conf;

    if (! $conf['session_use_ip_address']) {
        return '';
    }

    if (! str_contains((string) $_SERVER['REMOTE_ADDR'], ':')) {//ipv4
        return vsprintf(
            '%02X%02X',
            explode('.', (string) $_SERVER['REMOTE_ADDR'])
        );
    }

    return ''; //ipv6 not yet
}

/**
 * Called by PHP session manager, retrieves data stored in the sessions table.
 */
function pwg_session_read(
    string $session_id
): string {
    $session_hash = get_remote_addr_session_hash() . $session_id;
    $query = <<<SQL
        SELECT data
        FROM sessions
        WHERE id = '{$session_hash}';
        SQL;

    $result = pwg_query($query);
    $row = pwg_db_fetch_assoc($result);
    return $row ? $row['data'] : '';
}

/**
 * Called by PHP session manager, writes data in the sessions table.
 */
function pwg_session_write(
    string $session_id,
    string $data
): bool {
    if (DB_ENGINE === 'MySQL') {
        $query = "REPLACE INTO sessions\n";
    }

    if (DB_ENGINE === 'PostgreSQL') {
        $query = "INSERT INTO sessions\n";
    }

    $session_hash = get_remote_addr_session_hash() . $session_id;
    $escaped_data = pwg_db_real_escape_string($data);
    $query .= <<<SQL
        (id, data, expiration)
        VALUES ('{$session_hash}', '{$escaped_data}', NOW())
        SQL;

    if (DB_ENGINE === 'PostgreSQL') {
        $query .= "\nON CONFLICT (id) DO UPDATE SET data = EXCLUDED.data, expiration = EXCLUDED.expiration\n";
    }

    $query .= ';';
    pwg_query($query);
    return true;
}

/**
 * Called by PHP session manager, deletes data in the sessions table.
 */
function pwg_session_destroy(
    string $session_id
): bool {
    $session_id_hash = get_remote_addr_session_hash() . $session_id;
    $query = <<<SQL
        DELETE FROM sessions
        WHERE id = '{$session_id_hash}';
        SQL;
    pwg_query($query);
    return true;
}

/**
 * Called by PHP session manager, garbage collector for expired sessions.
 */
function pwg_session_gc(): int|false
{
    global $conf;

    $now_ts = pwg_db_date_to_ts('NOW()');
    $expiration_ts = pwg_db_date_to_ts('expiration');
    $query = <<<SQL
        DELETE FROM sessions
        WHERE {$now_ts} - {$expiration_ts} > {$conf['session_length']};
        SQL;

    $result = pwg_query($query);

    if ($result === false) {
        return false;
    }

    $affected_rows = pwg_db_changes();

    return $affected_rows;
}

/**
 * Persistently stores a variable for the current session.
 */
function pwg_set_session_var(
    string $var,
    mixed $value
): bool {
    if (! isset($_SESSION)) {
        return false;
    }

    $_SESSION['pwg_' . $var] = $value;
    return true;
}

/**
 * Retrieves the value of a persistent variable for the current session.
 */
function pwg_get_session_var(
    string $var,
    mixed $default = null
): mixed {
    return $_SESSION['pwg_' . $var] ?? $default;
}

/**
 * Deletes a persistent variable for the current session.
 */
function pwg_unset_session_var(
    string $var
): bool {
    if (! isset($_SESSION)) {
        return false;
    }

    unset($_SESSION['pwg_' . $var]);
    return true;
}

/**
 * delete all sessions for a given user (certainly deleted)
 *
 * @since 2.8
 */
function delete_user_sessions(
    int $user_id
): void {
    $query = <<<SQL
        DELETE FROM sessions
        WHERE data LIKE '%pwg_uid|i:{$user_id};%';
        SQL;

    pwg_query($query);
}
