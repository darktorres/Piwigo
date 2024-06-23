<?php

namespace Piwigo\inc\ws_protocols;

use Piwigo\inc\Error;
use Piwigo\inc\RequestHandler;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

class RestRequestHandler extends RequestHandler
{
    #[\Override]
    public function handleRequest(&$service)
    {
        $params = [];

        $param_array = $service->isPost() ? $_POST : $_GET;
        foreach ($param_array as $name => $value) {
            if ($name == 'format') {
                continue;
            }

            // ignore - special keys
            if ($name == 'method') {
                $method = $value;
            } else {
                $params[$name] = $value;
            }
        }

        if (empty($method) && isset($_GET['method'])) {
            $method = $_GET['method'];
        }

        if (empty($method)) {
            $service->sendResponse(
                new Error(WS_ERR_INVALID_METHOD, 'Missing "method" name')
            );
            return;
        }

        $resp = $service->invoke($method, $params);
        $service->sendResponse($resp);
    }
}
