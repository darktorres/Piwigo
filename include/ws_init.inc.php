<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

defined('PHPWG_ROOT_PATH') || trigger_error('Hacking attempt!', E_USER_ERROR);

include_once(PHPWG_ROOT_PATH . 'include/ws_core.inc.php');
include_once(PHPWG_ROOT_PATH . 'include/ws_functions.inc.php');

add_event_handler('ws_add_methods', ws_addDefaultMethods(...));
add_event_handler('ws_invoke_allowed', ws_isInvokeAllowed(...), EVENT_HANDLER_PRIORITY_NEUTRAL, 3);

$requestFormat = 'rest';
$responseFormat = null;

if (isset($_GET['format'])) {
    $responseFormat = $_GET['format'];
}

if (! isset($responseFormat) && isset($requestFormat)) {
    $responseFormat = $requestFormat;
}

$service = new PwgServer();

if ($requestFormat !== null) {
    $handler = null;
    if ($requestFormat === 'rest') {
        include_once(PHPWG_ROOT_PATH . 'include/ws_protocols/rest_handler.php');
        $handler = new PwgRestRequestHandler();
    }

    $service->setHandler($requestFormat, $handler);
}

if ($responseFormat !== null) {
    $encoder = null;
    switch ($responseFormat) {
        case 'rest':
            include_once(PHPWG_ROOT_PATH . 'include/ws_protocols/rest_encoder.php');
            $encoder = new PwgRestEncoder();
            break;
        case 'php':
            include_once(PHPWG_ROOT_PATH . 'include/ws_protocols/php_encoder.php');
            $encoder = new PwgSerialPhpEncoder();
            break;
        case 'json':
            include_once(PHPWG_ROOT_PATH . 'include/ws_protocols/json_encoder.php');
            $encoder = new PwgJsonEncoder();
            break;
        case 'xmlrpc':
            include_once(PHPWG_ROOT_PATH . 'include/ws_protocols/xmlrpc_encoder.php');
            $encoder = new PwgXmlRpcEncoder();
            break;
    }

    $service->setEncoder($responseFormat, $encoder);
}

set_make_full_url();
