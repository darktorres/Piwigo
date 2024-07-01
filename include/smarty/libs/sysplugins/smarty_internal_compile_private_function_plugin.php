<?php
/**
 * Smarty Internal Plugin Compile Function Plugin
 * Compiles code for the execution of function plugin
 *
 * @subpackage Compiler
 */

/**
 * Smarty Internal Plugin Compile Function Plugin Class
 *
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Private_Function_Plugin extends Smarty_Internal_CompileBase
{
    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $required_attributes = [];

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
     * Compiles code for the execution of function plugin
     *
     * @param array                                 $args      array with attributes from parser
     * @param \Smarty_Internal_TemplateCompilerBase $compiler  compiler object
     * @param array                                 $parameter array with compilation parameter
     * @param string                                $tag       name of function plugin
     * @param string                                $function  PHP function name
     *
     * @return string compiled code
     */
    public function compile(
        $args,
        Smarty_Internal_TemplateCompilerBase $compiler,
        $parameter,
        $tag,
        $function
    ) {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        unset($_attr['nocache']);
        // convert attributes into parameter array string
        $_paramsArray = [];
        foreach ($_attr as $_key => $_value) {
            $_paramsArray[] = is_int($_key) ? sprintf('%d=>%s', $_key, $_value) : sprintf("'%s'=>%s", $_key, $_value);
        }

        $_params = 'array(' . implode(',', $_paramsArray) . ')';
        // compile code
        $output = sprintf('%s(%s,$_smarty_tpl)', $function, $_params);
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

        return "<?php echo {$output};?>\n";
    }
}
