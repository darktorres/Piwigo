<?php declare(strict_types=1);
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 *
 */
class PwgSerialPhpEncoder extends PwgResponseEncoder
{
  /**
   * @param $response
   * @return string
   */
  public function encodeResponse($response): string
  {
    if ($response instanceof PwgError)
    {
      return serialize(
        array(
          'stat' => 'fail',
          'err' => $response->code(),
          'message' => $response->message(),
          )
      );
    }
    parent::flattenResponse($response);
    return serialize(
        array(
          'stat' => 'ok',
          'result' => $response
      )
    );
  }

  /**
   * @return string
   */
  public function getContentType(): string
  {
    return 'text/plain';
  }
}


