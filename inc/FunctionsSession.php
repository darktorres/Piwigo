<?php

namespace Piwigo\inc;

use Piwigo\inc\dbLayer\Mysqli;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

if (isset($conf['session_save_handler']) && $conf['session_save_handler'] == 'db' && defined(
    'PHPWG_INSTALLED'
)) {
    session_set_save_handler(
        \Piwigo\inc\FunctionsSession::pwg_session_open(...),
        \Piwigo\inc\FunctionsSession::pwg_session_close(...),
        \Piwigo\inc\FunctionsSession::pwg_session_read(...),
        \Piwigo\inc\FunctionsSession::pwg_session_write(...),
        \Piwigo\inc\FunctionsSession::pwg_session_destroy(...),
        \Piwigo\inc\FunctionsSession::pwg_session_gc(...)
    );

    if (function_exists('ini_set')) {
        ini_set('session.use_cookies', $conf['session_use_cookies']);
        ini_set('session.use_only_cookies', $conf['session_use_only_cookies']);
        ini_set('session.use_trans_sid', intval($conf['session_use_trans_sid']));
        ini_set('session.cookie_httponly', 1);
    }

    session_name($conf['session_name']);
    session_set_cookie_params(0, FunctionsCookie::cookie_path());
    register_shutdown_function(session_write_close(...));
}

class FunctionsSession
{
    /**
     * Generates a pseudo random string.
     * Characters used are a-z A-Z and numerical values.
     *
     * @param int $size
     */
    public static function generate_key(
        $size
    ): string {
        $bytes = random_bytes($size + 10);

        return substr(
            str_replace(
                ['+', '/'],
                '',
                base64_encode((string) $bytes)
            ),
            0,
            $size
        );
    }

    /**
     * Called by PHP session manager, always return true.
     *
     * @param string $path
     * @param string $name
     * @return true
     */
    public static function pwg_session_open(
        $path,
        $name
    ): bool {
        return true;
    }

    /**
     * Called by PHP session manager, always return true.
     *
     * @return true
     */
    public static function pwg_session_close(): bool
    {
        return true;
    }

    /**
     * Returns a hash from current user IP
     */
    public static function get_remote_addr_session_hash(): string
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
     *
     * @return string
     */
    public static function pwg_session_read(
        string $session_id
    ) {
        $query = '
SELECT data
  FROM ' . SESSIONS_TABLE . '
  WHERE id = \'' . self::get_remote_addr_session_hash() . $session_id . '\'
;';
        $result = Mysqli::pwg_query($query);
        if (($row = Mysqli::pwg_db_fetch_assoc($result))) {
            return $row['data'];
        }

        return '';
    }

    /**
     * Called by PHP session manager, writes data in the sessions table.
     *
     * @param string $data
     * @return true
     */
    public static function pwg_session_write(
        string $session_id,
        $data
    ): bool {
        $query = '
REPLACE INTO ' . SESSIONS_TABLE . '
  (id,data,expiration)
  VALUES(\'' . self::get_remote_addr_session_hash() . $session_id . "','" . Mysqli::pwg_db_real_escape_string(
            $data
        ) . '\',now())
;';
        Mysqli::pwg_query($query);
        return true;
    }

    /**
     * Called by PHP session manager, deletes data in the sessions table.
     *
     * @return true
     */
    public static function pwg_session_destroy(
        string $session_id
    ): bool {
        $query = '
DELETE
  FROM ' . SESSIONS_TABLE . '
  WHERE id = \'' . self::get_remote_addr_session_hash() . $session_id . '\'
;';
        Mysqli::pwg_query($query);
        return true;
    }

    /**
     * Called by PHP session manager, garbage collector for expired sessions.
     *
     * @return true
     */
    public static function pwg_session_gc(): bool
    {
        global $conf;

        $query = '
DELETE
  FROM ' . SESSIONS_TABLE . '
  WHERE ' . Mysqli::pwg_db_date_to_ts('NOW()') . ' - ' . Mysqli::pwg_db_date_to_ts('expiration') . ' > '
        . $conf['session_length'] . '
;';
        Mysqli::pwg_query($query);
        return true;
    }

    /**
     * Persistently stores a variable for the current session.
     */
    public static function pwg_set_session_var(
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
     *
     * @return mixed
     */
    public static function pwg_get_session_var(
        string $var,
        mixed $default = null
    ) {
        return $_SESSION['pwg_' . $var] ?? $default;
    }

    /**
     * Deletes a persistent variable for the current session.
     */
    public static function pwg_unset_session_var(
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
     * @param int $user_id
     */
    public static function delete_user_sessions(
        $user_id
    ): void {
        $query = '
DELETE
  FROM ' . SESSIONS_TABLE . '
  WHERE data LIKE \'%pwg_uid|i:' . (int) $user_id . ';%\'
;';
        Mysqli::pwg_query($query);
    }
}
