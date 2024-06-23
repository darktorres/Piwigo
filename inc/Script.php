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
 * Implementation of Combinable for JS files.
 */
final class Script extends Combinable
{
    /**
     * @var array
     */
    public $extra = [];

    /**
     * @param int $load_mode 0,1,2
     * @param string $id
     * @param string $path
     * @param string $version
     * @param array $precedents
     */
    public function __construct(
        public $load_mode,
        $id,
        $path,
        $version = 0,
        public $precedents = []
    ) {
        parent::__construct($id, $path, $version);
    }
}
