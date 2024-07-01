<?php
/**
 * Smarty Internal Plugin Compile Registered Function
 * Compiles code for the execution of a registered function
 *
 * @subpackage Compiler
 */

/**
 * Smarty Internal Plugin Compile Registered Function Class
 *
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Private_Registered_Function extends Smarty_Internal_CompileBase
{
    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $optional_attributes = [
        '_any',
    ];

    /**
     * Compiles code for the execution of a registered function
     *
     * @param array                                 $args      array with attributes from parser
     * @param \Smarty_Internal_TemplateCompilerBase $compiler  compiler object
     * @param array                                 $parameter array with compilation parameter
     * @param string                                $tag       name of function
     *
     * @return string compiled code
     */
    public function compile(
        $args,
        Smarty_Internal_TemplateCompilerBase $compiler,
        $parameter,
        $tag
    ) {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        unset($_attr['nocache']);
        if (isset($compiler->smarty->registered_plugins[Smarty::PLUGIN_FUNCTION][$tag])) {
            $tag_info = $compiler->smarty->registered_plugins[Smarty::PLUGIN_FUNCTION][$tag];
            $is_registered = true;
        } else {
            $tag_info = $compiler->default_handler_plugins[Smarty::PLUGIN_FUNCTION][$tag];
            $is_registered = false;
        }

        // not cacheable?
        $compiler->tag_nocache = $compiler->tag_nocache || ! $tag_info[1];
        // convert attributes into parameter array string
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

        $_params = 'array(' . implode(',', $_paramsArray) . ')';
        // compile code
        if ($is_registered) {
            $output =
                sprintf(
                    'call_user_func_array( $_smarty_tpl->smarty->registered_plugins[Smarty::PLUGIN_FUNCTION][\'%s\'][0], array( %s,$_smarty_tpl ) )',
                    $tag,
                    $_params
                );
        } else {
            $function = $tag_info[0];
            if (! is_array($function)) {
                $output = sprintf('%s(%s,$_smarty_tpl)', $function, $_params);
            } else {
                $output = sprintf('%s::%s(%s,$_smarty_tpl)', $function[0], $function[1], $_params);
            }
        }

        if (! empty($parameter['modifierlist'])) {
            $output = $compiler->compileTag(
                'private_modifier',
                [],
                [
                    'modifierlist' => $parameter['modifierlist'],
                    'value' => $output,
                ]
            );
        }

        $output = "<?php echo {$output};?>\n";
        return $output;
    }
}
