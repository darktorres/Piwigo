<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Provides a persistent cache mechanism across multiple page loads/sessions etc...
 */
abstract class PersistentCache
{
    public int $default_lifetime = 86400;

    protected string $instance_key = PHPWG_VERSION;

    /**
     * @return string a key that can be safely be used with get/set methods
     */
    public function make_key(
        array|string $key
    ): string {
        if (is_array($key)) {
            $key = implode('&', $key);
        }

        $key .= $this->instance_key;
        return md5($key);
    }

    /**
     * Searches for a key in the persistent cache and fills corresponding value.
     * @param mixed $value out
     * @return false if the $key is not found in cache ($value is not modified in this case)
     */
    abstract public function get(
        string $key,
        mixed &$value
    );

    /**
     * Sets a key/value pair in the persistent cache.
     * @param string $key - it should be the return value of make_key function
     * @return false on error
     */
    abstract public function set(
        string $key,
        mixed $value,
        int $lifetime = null
    );

    /**
     * Purge the persistent cache.
     * @param bool $all - if false only expired items will be purged
     */
    abstract public function purge(
        bool $all
    );
}

/**
 * Implementation of a persistent cache using files.
 */
class PersistentFileCache extends PersistentCache
{
    private readonly string $dir;

    public function __construct()
    {
        global $conf;
        $this->dir = PHPWG_ROOT_PATH . $conf['data_location'] . 'cache/';
    }

    #[\Override]
    public function get(
        string $key,
        mixed &$value
    ): bool {
        $loaded = file_exists($this->dir . $key . '.cache') ? file_get_contents($this->dir . $key . '.cache') : false;
        if ($loaded !== false && ($loaded = unserialize($loaded)) !== false && $loaded['expire'] > time()) {
            $value = $loaded['data'];
            return true;
        }

        return false;
    }

    #[\Override]
    public function set(
        string $key,
        mixed $value,
        int $lifetime = null
    ): bool {
        if ($lifetime === null) {
            $lifetime = $this->default_lifetime;
        }

        if (mt_rand() % 97 == 0) {
            $this->purge(false);
        }

        $serialized = serialize([
            'expire' => time() + $lifetime,
            'data' => $value,
        ]);

        if (! file_exists(dirname($this->dir . $key . '.cache'))) {
            mkgetdir(dirname($this->dir . $key . '.cache'));
        }

        if (file_put_contents($this->dir . $key . '.cache', $serialized) === false) {
            mkgetdir($this->dir, MKGETDIR_DEFAULT & ~MKGETDIR_DIE_ON_ERROR);
            if (file_put_contents($this->dir . $key . '.cache', $serialized) === false) {
                return false;
            }
        }

        return true;
    }

    #[\Override]
    public function purge(
        bool $all
    ): void {
        $files = glob($this->dir . '*.cache');
        if ($files === [] || $files === false) {
            return;
        }

        $limit = time() - $this->default_lifetime;
        foreach ($files as $file) {
            $mtime = file_exists($file) ? filemtime($file) : false;

            if ($all || $mtime < $limit) {
                unlink($file);
            }
        }
    }
}
