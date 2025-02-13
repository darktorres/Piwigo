<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

defined('PHPWG_ROOT_PATH') or trigger_error('Hacking attempt!', E_USER_ERROR);

require_once PHPWG_ROOT_PATH . 'include/ws_core.inc.php';
require_once PHPWG_ROOT_PATH . 'include/ws_functions.inc.php';

add_event_handler('ws_add_methods', ws_addDefaultMethods(...));
add_event_handler('ws_invoke_allowed', ws_isInvokeAllowed(...));

$requestFormat = 'rest';
$responseFormat = null;

if (isset($_GET['format'])) {
    $responseFormat = $_GET['format'];
}

if (! isset($responseFormat) and isset($requestFormat)) {
    $responseFormat = $requestFormat;
}

$service = new PwgServer();

if ($requestFormat !== null) {
    $handler = null;
    switch ($requestFormat) {
        case 'rest':
            require_once PHPWG_ROOT_PATH . 'include/ws_protocols/rest_handler.php';
            $handler = new PwgRestRequestHandler();
            break;
    }
    $service->setHandler($requestFormat, $handler);
}

if ($responseFormat !== null) {
    $encoder = null;
    switch ($responseFormat) {
        case 'rest':
            require_once PHPWG_ROOT_PATH . 'include/ws_protocols/rest_encoder.php';
            $encoder = new PwgRestEncoder();
            break;
        case 'php':
            require_once PHPWG_ROOT_PATH . 'include/ws_protocols/php_encoder.php';
            $encoder = new PwgSerialPhpEncoder();
            break;
        case 'json':
            require_once PHPWG_ROOT_PATH . 'include/ws_protocols/json_encoder.php';
            $encoder = new PwgJsonEncoder();
            break;
        case 'xmlrpc':
            require_once PHPWG_ROOT_PATH . 'include/ws_protocols/xmlrpc_encoder.php';
            $encoder = new PwgXmlRpcEncoder();
            break;
    }
    $service->setEncoder($responseFormat, $encoder);
}

set_make_full_url();
