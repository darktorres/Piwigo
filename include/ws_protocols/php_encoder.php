<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

class PwgSerialPhpEncoder extends PwgResponseEncoder
{
    #[\Override]
    public function encodeResponse(
        array|bool|string|PwgError|null $response
    ): string {
        if ($response instanceof PwgError) {
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
    public function getContentType(): string
    {
        return 'text/plain';
    }
}
