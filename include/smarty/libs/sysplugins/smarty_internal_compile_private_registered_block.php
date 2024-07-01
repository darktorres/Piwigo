<?php
/**
 * Smarty Internal Plugin Compile Registered Block
 * Compiles code for the execution of a registered block function
 *
 * @subpackage Compiler
 */

/**
 * Smarty Internal Plugin Compile Registered Block Class
 *
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Private_Registered_Block extends Smarty_Internal_Compile_Private_Block_Plugin
{
    /**
     * Setup callback, parameter array and nocache mode
     *
     * @param array                                 $_attr attributes
     * @param string                                $tag
     * @param null                                  $function
     *
     * @return array
     */
    public function setup(
        Smarty_Internal_TemplateCompilerBase $compiler,
        $_attr,
        $tag,
        $function
    ) {
        if (isset($compiler->smarty->registered_plugins[Smarty::PLUGIN_BLOCK][$tag])) {
            $tag_info = $compiler->smarty->registered_plugins[Smarty::PLUGIN_BLOCK][$tag];
            $callback = $tag_info[0];
            if (is_array($callback)) {
                if (is_object($callback[0])) {
                    $callable = sprintf('array($_block_plugin%d, \'%s\')', $this->nesting, $callback[1]);
                    $callback =
                        [
                            sprintf('$_smarty_tpl->smarty->registered_plugins[\'block\'][\'%s\'][0][0]', $tag),
                            '->' . $callback[1],
                        ];
                } else {
                    $callable = sprintf('array($_block_plugin%d, \'%s\')', $this->nesting, $callback[1]);
                    $callback =
                        [
                            sprintf('$_smarty_tpl->smarty->registered_plugins[\'block\'][\'%s\'][0][0]', $tag),
                            '::' . $callback[1],
                        ];
                }
            } else {
                $callable = '$_block_plugin' . $this->nesting;
                $callback = [sprintf('$_smarty_tpl->smarty->registered_plugins[\'block\'][\'%s\'][0]', $tag), ''];
            }
        } else {
            $tag_info = $compiler->default_handler_plugins[Smarty::PLUGIN_BLOCK][$tag];
            $callback = $tag_info[0];
            if (is_array($callback)) {
                $callable = sprintf("array('%s', '%s')", $callback[0], $callback[1]);
                $callback = sprintf('%s::%s', $callback[1], $callback[1]);
            } else {
                $callable = null;
            }
        }

        $compiler->tag_nocache = ! $tag_info[1] | $compiler->tag_nocache;
        $_paramsArray = [];
        foreach ($_attr as $_key => $_value) {
            if (is_int($_key)) {
                $_paramsArray[] = sprintf('%d=>%s', $_key, $_value);
            } elseif ($compiler->template->caching && in_array($_key, $tag_info[2])) {
                $_value = str_replace("'", '^#^', $_value);
                $_paramsArray[] = sprintf("'%s'=>^#^.var_export(%s,true).^#^", $_key, $_value);
            } else {
                $_paramsArray[] = sprintf("'%s'=>%s", $_key, $_value);
            }
        }

        return [$callback, $_paramsArray, $callable];
    }
}
