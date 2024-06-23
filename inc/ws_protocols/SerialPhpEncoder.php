<?php

namespace Piwigo\inc\ws_protocols;

use Piwigo\inc\Error;
use Piwigo\inc\ResponseEncoder;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

class SerialPhpEncoder extends ResponseEncoder
{
    #[\Override]
    public function encodeResponse($response)
    {
        if ($response instanceof Error) {
            return serialize(
                [
                    'stat' => 'fail',
                    'err' => $response->code(),
                    'message' => $response->message(),
                ]
            );
        }

        parent::flattenResponse($response);
        return serialize(
            [
                'stat' => 'ok',
                'result' => $response,
            ]
        );
    }

    #[\Override]
    public function getContentType()
    {
        return 'text/plain';
    }
}
