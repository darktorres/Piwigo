<?php declare(strict_types=1);
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 *  Provides a persistent cache mechanism across multiple page loads/sessions etc...
 */
abstract class PersistentCache
{
  public int $default_lifetime = 86400;
  protected string $instance_key = PHPWG_VERSION;

  /**
   * @return string a key that can be safely be used with get/set methods
   */
  public function make_key($key): string
  {
    if ( is_array($key) )
    {
      $key = implode('&', $key);
    }
    $key .= $this->instance_key;
    return md5($key);
  }

  /**
   * Searches for a key in the persistent cache and fills corresponding value.
   * @param string $key
   * @param mixed $value
   * @return false if the $key is not found in cache ($value is not modified in this case)
   */
  abstract public function get(string $key, mixed &$value): bool;

  /**
   * Sets a key/value pair in the persistent cache.
   * @param string $key - it should be the return value of make_key function
   * @param mixed $value
   * @param int|null $lifetime
   * @return false on error
   */
  abstract public function set(string $key, mixed $value, int $lifetime = null): bool;

  /**
   * Purge the persistent cache.
   * @param bool $all - if false only expired items will be purged
   */
  abstract public function purge(bool $all);
}


/**
 *  Implementation of a persistent cache using files.
 */
class PersistentFileCache extends PersistentCache
{
  private string $dir;

  public function __construct()
  {
    global $conf;
    $this->dir = PHPWG_ROOT_PATH.$conf['data_location'].'cache/';
  }

  /**
   * @param string $key
   * @param mixed $value
   * @return bool
   */
  public function get(string $key, mixed &$value): bool
  {
    $loaded = file_exists($this->dir.$key.'.cache') ? file_get_contents($this->dir.$key.'.cache') : false;
    if ($loaded !== false && ($loaded=unserialize($loaded)) !== false)
    {
      if ($loaded['expire'] > time())
      {
        $value = $loaded['data'];
        return true;
      }
    }
    return false;
  }

  /**
   * @param string $key
   * @param mixed $value
   * @param int|null $lifetime
   * @return bool
   */
  public function set(string $key, mixed $value, int $lifetime = null): bool
  {
    if ($lifetime === null)
    {
      $lifetime = $this->default_lifetime;
    }

    if (rand() % 97 == 0)
    {
      $this->purge(false);
    }

    $serialized = serialize( array(
        'expire' => time() + $lifetime,
        'data' => $value
      ));

    if (!file_exists($this->dir)) {
        mkgetdir($this->dir, MKGETDIR_DEFAULT & ~MKGETDIR_DIE_ON_ERROR);
    }

    if (false === file_put_contents($this->dir.$key.'.cache', $serialized))
    {
      return false;
    }
    return true;
  }

  /**
   * @param bool $all
   * @return void
   */
  public function purge(bool $all): void
  {
    $files = glob($this->dir.'*.cache');
    if (empty($files))
    {
      return;
    }

    $limit = time() - $this->default_lifetime;
    foreach ($files as $file)
    {
      if ($all || filemtime($file) < $limit)
        unlink($file);
    }
  }

}

