<?php

namespace Piwigo\inc;

use Piwigo\inc\ws_protocols\JsonEncoder;
use Piwigo\inc\ws_protocols\RestEncoder;
use Piwigo\inc\ws_protocols\RestRequestHandler;
use Piwigo\inc\ws_protocols\SerialPhpEncoder;
use Piwigo\inc\ws_protocols\XmlRpcEncoder;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

defined(
    'PHPWG_ROOT_PATH'
) || trigger_error(
    'Hacking attempt!',
    E_USER_ERROR
);

include_once(PHPWG_ROOT_PATH . 'inc/ws_core.inc.php');

add_event_handler('ws_add_methods', '\Piwigo\ws_addDefaultMethods');
add_event_handler('ws_invoke_allowed', '\Piwigo\inc\ws_isInvokeAllowed', EVENT_HANDLER_PRIORITY_NEUTRAL, 3);

$requestFormat = 'rest';
$responseFormat = null;

if (isset($_GET['format'])) {
    $responseFormat = $_GET['format'];
}

if (! isset($responseFormat) && isset($requestFormat)) {
    $responseFormat = $requestFormat;
}

$service = new Server();

if ($requestFormat !== null) {
    $handler = null;
    if ($requestFormat === 'rest') {
        $handler = new RestRequestHandler();
    }

    $service->setHandler($requestFormat, $handler);
}

if ($responseFormat !== null) {
    $encoder = null;
    switch ($responseFormat) {
        case 'rest':
            $encoder = new RestEncoder();
            break;
        case 'php':
            $encoder = new SerialPhpEncoder();
            break;
        case 'json':
            $encoder = new JsonEncoder();
            break;
        case 'xmlrpc':
            $encoder = new XmlRpcEncoder();
            break;
    }

    $service->setEncoder($responseFormat, $encoder);
}

set_make_full_url();
