<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

function customErrorHandler(
    int $errno,
    string $errstr,
    string $errfile,
    int $errline
): bool {
    // Define error types and corresponding prefixes
    $error_types = [
        E_ERROR => 'error',
        E_WARNING => 'warn',
        E_PARSE => 'error',
        E_NOTICE => 'info',
        E_CORE_ERROR => 'error',
        E_CORE_WARNING => 'warn',
        E_COMPILE_ERROR => 'error',
        E_COMPILE_WARNING => 'warn',
        E_USER_ERROR => 'error',
        E_USER_WARNING => 'warn',
        E_USER_NOTICE => 'info',
        E_RECOVERABLE_ERROR => 'error',
        E_DEPRECATED => 'warn',
        E_USER_DEPRECATED => 'warn',
    ];

    // Determine the error type
    $error_type = $error_types[$errno] ?? 'Unknown Error';

    // Construct the error message
    $errorMessage = "PHP: {$errstr} in {$errfile} on line {$errline}";

    // Store in global var
    global $custom_error_log;
    $custom_error_log .= '<script>console.' . $error_type . '(' . json_encode($errorMessage) . ');</script>';

    // Ensure PHP's internal error handler is not bypassed
    return false;
}

set_error_handler(customErrorHandler(...));

/** default rank for buttons */
define('BUTTONS_RANK_NEUTRAL', 50);
