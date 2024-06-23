<?php

namespace Piwigo\inc;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

require_once __DIR__ . '/ws_core.inc.php';

/**
 * Abstract base class for request handlers.
 */
abstract class RequestHandler
{
    /** Virtual abstract method. Decodes the request (GET or POST) handles the
     * method invocation as well as response sending.
     */
    abstract public function handleRequest(
        &$service
    );
}
