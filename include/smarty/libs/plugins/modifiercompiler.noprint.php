<?php
/**
 * Smarty plugin
 *
 * @subpackage PluginsModifierCompiler
 */
/**
 * Smarty noprint modifier plugin
 * Type:     modifier
 * Name:     noprint
 * Purpose:  return an empty string
 *
 * @return string with compiled code
 */
function smarty_modifiercompiler_noprint()
{
    return "''";
}
