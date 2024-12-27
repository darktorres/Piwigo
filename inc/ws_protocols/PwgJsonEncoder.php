<?php

declare(strict_types=1);

namespace Piwigo\inc\ws_protocols;

use Piwigo\inc\PwgError;
use Piwigo\inc\PwgResponseEncoder;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

class PwgJsonEncoder extends PwgResponseEncoder
{
    #[\Override]
    public function encodeResponse(
        array|bool|string|PwgError|null $response
    ): bool|string {
        if ($response instanceof PwgError) {
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
