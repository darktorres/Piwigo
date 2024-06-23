<?php

namespace Piwigo\inc;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

require_once __DIR__ . '/template.inc.php';

/**
 * A Combinable represents a JS or CSS file ready for cobination and minification.
 */
class Combinable
{
    /**
     * @var string
     */
    public $path;

    /**
     * @var bool
     */
    public $is_template = false;

    /**
     * @param string $id
     * @param string $path
     * @param string $version
     */
    public function __construct(
        public $id,
        $path,
        public $version = 0
    ) {
        $this->set_path($path);
    }

    /**
     * @param string $path
     */
    public function set_path($path)
    {
        if (! empty($path)) {
            $this->path = $path;
        }
    }

    /**
     * @return bool
     */
    public function is_remote()
    {
        return url_is_remote($this->path) || str_starts_with($this->path, '//');
    }
}
