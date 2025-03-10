<?php
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

namespace Piwigo\inc\ws_protocols;

use Piwigo\inc\PwgError;
use Piwigo\inc\PwgResponseEncoder;

class PwgXmlRpcEncoder extends PwgResponseEncoder
{
  static function xmlrpc_encode($data)
  {
    switch (gettype($data))
    {
      case 'boolean':
        return '<boolean>'.($data ? '1' : '0').'</boolean>';
      case 'integer':
        return '<int>'.$data.'</int>';
      case 'double':
        return '<double>'.$data.'</double>';
      case 'string':
        return '<string>'.htmlspecialchars($data).'</string>';
      case 'object':
      case 'array':
        $is_array = range(0, count($data) - 1) === array_keys($data);
        if ($is_array)
        {
          $return = '<array><data>'."\n";
          foreach ($data as $item)
          {
            $return .= '  <value>'.self::xmlrpc_encode($item)."</value>\n";
          }
          $return .= '</data></array>';
        }
        else
        {
          $return = '<struct>'."\n";
          foreach ($data as $name => $value)
          {
            $name = htmlspecialchars($name);
            $return .= "  <member><name>$name</name><value>";
            $return .= self::xmlrpc_encode($value)."</value></member>\n";
          }
          $return .= '</struct>';
        }
        return $return;
    }
  }

  static function encodeResponse($response)
  {
    if ($response instanceof PwgError)
    {
      $code = $response->code();
      $msg = htmlspecialchars($response->message());
      $ret = <<<EOD
<methodResponse>
  <fault>
    <value>
      <struct>
        <member>
          <name>faultCode</name>
          <value><int>{$code}</int></value>
        </member>
        <member>
          <name>faultString</name>
          <value><string>{$msg}</string></value>
        </member>
      </struct>
    </value>
  </fault>
</methodResponse>
EOD;
      return $ret;
    }

    parent::flattenResponse($response);
    $ret = self::xmlrpc_encode($response);
    $ret = <<<EOD
<methodResponse>
  <params>
    <param>
      <value>
        $ret
      </value>
    </param>
  </params>
</methodResponse>
EOD;
    return $ret;
  }

  static function getContentType()
  {
    return 'text/xml';
  }
}

?>
