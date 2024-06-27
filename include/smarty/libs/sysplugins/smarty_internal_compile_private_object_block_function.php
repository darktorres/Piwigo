<?php
/**
 * Smarty Internal Plugin Compile Object Block Function
 * Compiles code for registered objects as block function
 *
 * @subpackage Compiler
 */

/**
 * Smarty Internal Plugin Compile Object Block Function Class
 *
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Private_Object_Block_Function extends Smarty_Internal_Compile_Private_Block_Plugin
{
    /**
     * Setup callback and parameter array
     *
     * @param array                                 $_attr attributes
     * @param string                                $tag
     * @param string                                $method
     *
     * @return array
     */
    #[\Override]
    public function setup(
        Smarty_Internal_TemplateCompilerBase $compiler,
        $_attr,
        $tag,
        $method
    ) {
        $_paramsArray = [];
        foreach ($_attr as $_key => $_value) {
            if (is_int($_key)) {
                $_paramsArray[] = sprintf('%d=>%s', $_key, $_value);
            } else {
                $_paramsArray[] = sprintf("'%s'=>%s", $_key, $_value);
            }
        }

        $callback = [sprintf('$_smarty_tpl->smarty->registered_objects[\'%s\'][0]', $tag), '->' . $method];
        return [$callback, $_paramsArray, sprintf('array($_block_plugin%d, \'%s\')', $this->nesting, $method)];
    }
}
