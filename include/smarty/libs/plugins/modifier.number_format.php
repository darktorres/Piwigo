<?php
/**
 * Smarty plugin
 *
 * @subpackage PluginsModifier
 */

/**
 * Smarty number_format modifier plugin
 * Type:     modifier
 * Name:     number_format
 * Purpose:  Format a number with grouped thousands
 *
 * @return string
 */
function smarty_modifier_number_format(
    ?float $num,
    int $decimals = 0,
    ?string $decimal_separator = '.',
    ?string $thousands_separator = ','
) {
    // provide $num default to prevent deprecation errors in PHP >=8.1
    return number_format(
        $num ?? 0.0,
        $decimals,
        $decimal_separator,
        $thousands_separator
    );
}
