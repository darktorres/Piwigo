<?php

declare(strict_types=1);

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
    public function encodeResponse(mixed $response): false|string
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
    public function getContentType(): string
    {
        return 'text/plain';
    }
}
