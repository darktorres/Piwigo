<?php

namespace Piwigo\inc;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Implementation of a persistent cache using files.
 */
class PersistentFileCache extends PersistentCache
{
    private $dir;

    public function __construct()
    {
        global $conf;
        $this->dir = PHPWG_ROOT_PATH . $conf['data_location'] . 'cache/';
    }

    #[\Override]
    public function get($key, &$value)
    {
        $loaded = @file_get_contents($this->dir . $key . '.cache');
        if ($loaded !== false && ($loaded = unserialize($loaded)) !== false && $loaded['expire'] > time()) {
            $value = $loaded['data'];
            return true;
        }

        return false;
    }

    #[\Override]
    public function set($key, $value, $lifetime = null)
    {
        if ($lifetime === null) {
            $lifetime = $this->default_lifetime;
        }

        if (random_int(0, mt_getrandmax()) % 97 == 0) {
            $this->purge(false);
        }

        $serialized = serialize([
            'expire' => time() + $lifetime,
            'data' => $value,
        ]);

        if (@file_put_contents($this->dir . $key . '.cache', $serialized) === false) {
            mkgetdir($this->dir, MKGETDIR_DEFAULT & ~MKGETDIR_DIE_ON_ERROR);
            if (@file_put_contents($this->dir . $key . '.cache', $serialized) === false) {
                return false;
            }
        }

        return true;
    }

    #[\Override]
    public function purge($all)
    {
        $files = glob($this->dir . '*.cache');
        if ($files === [] || $files === false) {
            return;
        }

        $limit = time() - $this->default_lifetime;
        foreach ($files as $file) {
            $mtime = file_exists($file) ? filemtime($file) : false;

            if ($all || $mtime < $limit) {
                @unlink($file);
            }
        }
    }
}