<?php declare(strict_types=1);
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * @package functions\cookie
 */


/**
 * Returns the path to use for the Piwigo cookie.
 * If Piwigo is installed on :
 * http://domain.org/meeting/gallery/
 * it will return : "/meeting/gallery"
 *
 * @return string
 */
function cookie_path(): string
{
  if ( isset($_SERVER['REDIRECT_SCRIPT_NAME']) and
       !empty($_SERVER['REDIRECT_SCRIPT_NAME']) )
  {
    $scr = $_SERVER['REDIRECT_SCRIPT_NAME'];
  }
  elseif ( isset($_SERVER['REDIRECT_URL']) )
  {
    // mod_rewrite is activated for upper level directories. we must set the
    // cookie to the path shown in the browser otherwise it will be discarded.
    if
      (
        isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO']) and
        ($_SERVER['REDIRECT_URL'] !== $_SERVER['PATH_INFO']) and
        (str_ends_with($_SERVER['REDIRECT_URL'], $_SERVER['PATH_INFO']))
      )
    {
      $scr = substr($_SERVER['REDIRECT_URL'], 0,
        strlen($_SERVER['REDIRECT_URL'])-strlen($_SERVER['PATH_INFO']));
    }
    else
    {
      $scr = $_SERVER['REDIRECT_URL'];
    }
  }
  else
  {
    $scr = $_SERVER['SCRIPT_NAME'];
  }

  $scr = substr($scr,0,strrpos( $scr,'/'));

  // add a trailing '/' if needed
  if ((strlen($scr) == 0) || ($scr[strlen($scr)-1] !== '/'))
  {
    $scr .= '/';
  }

  if ( str_starts_with(PHPWG_ROOT_PATH, '../') )
  { // this is maybe a plugin inside pwg directory
    // TODO - what if it is an external script outside PWG ?
    $scr = $scr.PHPWG_ROOT_PATH;
    while (1)
    {
      $new = preg_replace('#[^/]+/\.\.(/|$)#', '', $scr);
      if ($new==$scr)
      {
        break;
      }
      $scr=$new;
    }
  }
  return $scr;
}

/**
 * Persistently stores a variable in pwg cookie.
 * Set $value to null to delete the cookie.
 *
 * @param string $var
 * @param mixed $value
 * @param int|null $expire
 * @return bool
 */
function pwg_set_cookie_var(string $var, mixed $value, int $expire=null): bool
{
  if ($value==null || $expire===0)
  {
    unset($_COOKIE['pwg_'.$var]);
    return setcookie('pwg_'.$var, '', 0, cookie_path());

  }
  else
  {
    $_COOKIE['pwg_'.$var] = $value;
    $expire = is_numeric($expire) ? $expire : strtotime('+10 years');
    return setcookie('pwg_'.$var, $value, $expire, cookie_path());
  }
}

/**
 * Retrieves the value of a persistent variable in pwg cookie
 * @param string $var
 * @param mixed $default
 * @return mixed
 * @see pwg_set_cookie_var
 *
 */
function pwg_get_cookie_var(string $var, mixed $default = null): mixed
{
  return $_COOKIE['pwg_' . $var] ?? $default;
}

