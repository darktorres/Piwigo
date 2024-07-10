<?php
/**
 * Smarty plugin
 *
 * @subpackage PluginsModifierCompiler
 */
/**
 * Smarty string_format modifier plugin
 * Type:     modifier
 * Name:     string_format
 * Purpose:  format strings via sprintf
 *
 * @link   https://www.smarty.net/manual/en/language.modifier.string.format.php string_format (Smarty online manual)
 *
 * @param array $params parameters
 *
 * @return string with compiled code
 */
function smarty_modifiercompiler_string_format(
    array $params
): string {
    return 'sprintf(' . $params[1] . ',' . $params[0] . ')';
}
