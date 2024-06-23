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
 * Implementation of Combinable for CSS files.
 */
final class Css extends Combinable
{
    /**
     * @param string $id
     * @param string $path
     * @param string $version
     * @param int $order
     */
    public function __construct(
        $id,
        $path,
        $version = 0,
        public $order = 0
    ) {
        parent::__construct($id, $path, $version);
    }
}
