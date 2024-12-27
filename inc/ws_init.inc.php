<?php

declare(strict_types=1);

use Piwigo\inc\PwgServer;
use Piwigo\inc\ws_protocols\PwgJsonEncoder;
use Piwigo\inc\ws_protocols\PwgRestEncoder;
use Piwigo\inc\ws_protocols\PwgRestRequestHandler;
use Piwigo\inc\ws_protocols\PwgSerialPhpEncoder;
use Piwigo\inc\ws_protocols\PwgXmlRpcEncoder;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

defined('PHPWG_ROOT_PATH') || trigger_error('Hacking attempt!', E_USER_ERROR);

require_once PHPWG_ROOT_PATH . 'inc/ws_core.inc.php';
require_once PHPWG_ROOT_PATH . 'inc/ws_functions.inc.php';

add_event_handler('ws_add_methods', ws_addDefaultMethods(...));
add_event_handler('ws_invoke_allowed', ws_isInvokeAllowed(...));

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
        $handler = new PwgRestRequestHandler();
    }

    $service->setHandler($requestFormat, $handler);
}

if ($responseFormat !== null) {
    $encoder = null;
    switch ($responseFormat) {
        case 'rest':
            $encoder = new PwgRestEncoder();
            break;
        case 'php':
            $encoder = new PwgSerialPhpEncoder();
            break;
        case 'json':
            $encoder = new PwgJsonEncoder();
            break;
        case 'xmlrpc':
            $encoder = new PwgXmlRpcEncoder();
            break;
    }

    $service->setEncoder($responseFormat, $encoder);
}

set_make_full_url();
