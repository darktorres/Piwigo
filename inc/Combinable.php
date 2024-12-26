<?php

declare(strict_types=1);

namespace Piwigo\inc;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * A Combinable represents a JS or CSS file ready for cobination and minification.
 */
class Combinable
{
    public string $path = '';

    public ?bool $is_template = false;

    public function __construct(
        public string $id,
        ?string $path,
        public int|string $version = 0
    ) {
        $this->set_path($path);
    }

    public function set_path(
        ?string $path
    ): void {
        if ($path !== null && $path !== '' && $path !== '0') {
            $this->path = $path;
        }
    }

    public function is_remote(): bool
    {
        return url_is_remote($this->path) || str_starts_with($this->path, '//');
    }
}
