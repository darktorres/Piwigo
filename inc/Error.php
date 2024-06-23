<?php

namespace Piwigo\inc;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

require_once __DIR__ . '/ws_core.inc.php';

/**
 * Error object can be returned from any web service function implementation.
 */
class Error
{
    private $_code;

    private $_codeText;

    public function __construct($code, $codeText)
    {
        if ($code >= 400 && $code < 600) {
            set_status_header($code, $codeText);
        }

        $this->_code = $code;
        $this->_codeText = $codeText;
    }

    public function code()
    {
        return $this->_code;
    }

    public function message()
    {
        return $this->_codeText;
    }
}
