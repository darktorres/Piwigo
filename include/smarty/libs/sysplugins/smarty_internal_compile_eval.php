<?php
/**
 * Smarty Internal Plugin Compile Eval
 * Compiles the {eval} tag.
 *
 * @subpackage Compiler
 */

/**
 * Smarty Internal Plugin Compile Eval Class
 *
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Eval extends Smarty_Internal_CompileBase
{
    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $required_attributes = [
        'var',
    ];

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $optional_attributes = [
        'assign',
    ];

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $shorttag_order = [
        'var',
        'assign',
    ];

    /**
     * Compiles code for the {eval} tag
     *
     * @param array  $args     array with attributes from parser
     * @param object $compiler compiler object
     *
     * @return string compiled code
     */
    public function compile(
        $args,
        $compiler
    ) {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        if (isset($_attr['assign'])) {
            // output will be stored in a smarty variable instead of being displayed
            $_assign = $_attr['assign'];
        }

        // create template object
        $_output =
            sprintf(
                '$_template = new %s(\'eval:\'.%s, $_smarty_tpl->smarty, $_smarty_tpl);',
                $compiler->smarty->template_class,
                $_attr['var']
            );
        //was there an assign attribute?
        if (isset($_assign)) {
            $_output .= sprintf('$_smarty_tpl->assign(%s,$_template->fetch());', $_assign);
        } else {
            $_output .= 'echo $_template->fetch();';
        }

        return sprintf('<?php %s ?>', $_output);
    }
}
