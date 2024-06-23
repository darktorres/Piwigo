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

class JsonEncoder extends ResponseEncoder
{
    #[\Override]
    public function encodeResponse($response)
    {
        if ($response instanceof Error) {
            return json_encode(
                [
                    'stat' => 'fail',
                    'err' => $response->code(),
                    'message' => $response->message(),
                ]
            );
        }

        parent::flattenResponse($response);
        return json_encode(
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
