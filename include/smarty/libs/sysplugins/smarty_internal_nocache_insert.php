<?php
/**
 * Smarty Internal Plugin Nocache Insert
 * Compiles the {insert} tag into the cache file
 *
 * @subpackage Compiler
 */

/**
 * Smarty Internal Plugin Compile Insert Class
 *
 * @subpackage Compiler
 */
class Smarty_Internal_Nocache_Insert
{
    /**
     * Compiles code for the {insert} tag into cache file
     *
     * @param string                   $_function insert function name
     * @param array                    $_attr     array with parameter
     * @param Smarty_Internal_Template $_template template object
     * @param string                   $_script   script name to load or 'null'
     * @param string                   $_assign   optional variable name
     *
     * @return string                   compiled code
     */
    public static function compile(
        $_function,
        $_attr,
        $_template,
        $_script,
        $_assign = null
    ) {
        $_output = '<?php ';
        if ($_script !== 'null') {
            // script which must be included
            // code for script file loading
            $_output .= sprintf("require_once '%s';", $_script);
        }

        // call insert
        if (isset($_assign)) {
            $_output .= sprintf('$_smarty_tpl->assign(\'%s\' , %s (', $_assign, $_function) . var_export(
                $_attr,
                true
            ) .
                        ',\$_smarty_tpl), true);?>';
        } else {
            $_output .= sprintf('echo %s(', $_function) . var_export($_attr, true) . ',$_smarty_tpl);?>';
        }

        $_tpl = $_template;
        while ($_tpl->_isSubTpl()) {
            $_tpl = $_tpl->parent;
        }

        return sprintf(
            '/*%%%%SmartyNocache:%s%%%%*/%s/*/%%%%SmartyNocache:%s%%%%*/',
            $_tpl->compiled->nocache_hash,
            $_output,
            $_tpl->compiled->nocache_hash
        );
    }
}
