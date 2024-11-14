<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

if (isset($conf['session_save_handler'])
  and ($conf['session_save_handler'] == 'db')
  and defined('PHPWG_INSTALLED')) {
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
    register_shutdown_function('session_write_close');
}

/**
 * Generates a pseudo random string.
 * Characters used are a-z A-Z and numerical values.
 *
 * @param int $size
 * @return string
 */
function generate_key($size)
{

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
 *
 * @param string $path
 * @param sring $name
 * @return true
 */
function pwg_session_open($path, $name)
{
    return true;
}

/**
 * Called by PHP session manager, always return true.
 *
 * @return true
 */
function pwg_session_close()
{
    return true;
}

/**
 * Returns a hash from current user IP
 *
 * @return string
 */
function get_remote_addr_session_hash()
{
    global $conf;

    if (! $conf['session_use_ip_address']) {
        return '';
    }

    if (strpos($_SERVER['REMOTE_ADDR'], ':') === false) {//ipv4
        return vsprintf(
            '%02X%02X',
            explode('.', $_SERVER['REMOTE_ADDR'])
        );
    }
    return ''; //ipv6 not yet
}

/**
 * Called by PHP session manager, retrieves data stored in the sessions table.
 *
 * @param string $session_id
 * @return string
 */
function pwg_session_read($session_id)
{
    $query = '
SELECT data
  FROM sessions
  WHERE id = \'' . get_remote_addr_session_hash() . $session_id . '\'
;';
    $result = pwg_query($query);
    $row = pwg_db_fetch_assoc($result);
    return $row ? $row['data'] : '';
}

/**
 * Called by PHP session manager, writes data in the sessions table.
 *
 * @param string $session_id
 * @param sring $data
 * @return true
 */
function pwg_session_write($session_id, $data)
{
    $query = '
REPLACE INTO sessions
  (id,data,expiration)
  VALUES(\'' . get_remote_addr_session_hash() . $session_id . '\',\'' . pwg_db_real_escape_string($data) . '\',now())
;';
    pwg_query($query);
    return true;
}

/**
 * Called by PHP session manager, deletes data in the sessions table.
 *
 * @param string $session_id
 * @return true
 */
function pwg_session_destroy($session_id)
{
    $query = '
DELETE
  FROM sessions
  WHERE id = \'' . get_remote_addr_session_hash() . $session_id . '\'
;';
    pwg_query($query);
    return true;
}

/**
 * Called by PHP session manager, garbage collector for expired sessions.
 *
 * @return int|false
 */
function pwg_session_gc()
{
    global $conf;

    $query = '
DELETE
  FROM sessions
  WHERE ' . pwg_db_date_to_ts('NOW()') . ' - ' . pwg_db_date_to_ts('expiration') . ' > '
    . $conf['session_length'] . '
;';
    $result = pwg_query($query);

    if ($result === false) {
        return false;
    }

    $affected_rows = pwg_db_changes();

    return $affected_rows;
}

/**
 * Persistently stores a variable for the current session.
 *
 * @param string $var
 * @param mixed $value
 * @return bool
 */
function pwg_set_session_var($var, $value)
{
    if (! isset($_SESSION)) {
        return false;
    }
    $_SESSION['pwg_' . $var] = $value;
    return true;
}

/**
 * Retrieves the value of a persistent variable for the current session.
 *
 * @param string $var
 * @param mixed $default
 * @return mixed
 */
function pwg_get_session_var($var, $default = null)
{
    if (isset($_SESSION['pwg_' . $var])) {
        return $_SESSION['pwg_' . $var];
    }
    return $default;
}

/**
 * Deletes a persistent variable for the current session.
 *
 * @param string $var
 * @return bool
 */
function pwg_unset_session_var($var)
{
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
function delete_user_sessions($user_id)
{
    $query = '
DELETE
  FROM sessions
  WHERE data LIKE \'%pwg_uid|i:' . (int) $user_id . ';%\'
;';
    pwg_query($query);
}
