<?php
/**
 * Smarty Internal Plugin Compile Insert
 * Compiles the {insert} tag
 *
 * @subpackage Compiler
 */

/**
 * Smarty Internal Plugin Compile Insert Class
 *
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Insert extends Smarty_Internal_CompileBase
{
    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $required_attributes = [
        'name',
    ];

    /**
     * Attribute definition: Overwrites base class.
     *
     * @var array
     * @see Smarty_Internal_CompileBase
     */
    public $shorttag_order = [
        'name',
    ];

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
     * Compiles code for the {insert} tag
     *
     * @param array                                 $args     array with attributes from parser
     * @param \Smarty_Internal_TemplateCompilerBase $compiler compiler object
     *
     * @return string compiled code
     */
    public function compile(
        $args,
        Smarty_Internal_TemplateCompilerBase $compiler
    ) {
        // check and get attributes
        $_attr = $this->getAttributes($compiler, $args);
        $nocacheParam = $compiler->template->caching && ($compiler->tag_nocache || $compiler->nocache);
        if (! $nocacheParam) {
            // do not compile as nocache code
            $compiler->suppressNocacheProcessing = true;
        }

        $compiler->tag_nocache = true;
        $_smarty_tpl = $compiler->template;
        $_name = null;
        $_script = null;
        $_output = '<?php ';
        // save possible attributes
        eval('$_name = @' . $_attr['name'] . ';');
        if (isset($_attr['assign'])) {
            // output will be stored in a smarty variable instead of being displayed
            $_assign = $_attr['assign'];
            // create variable to make sure that the compiler knows about its nocache status
            $var = trim(
                $_attr['assign'],
                "'"
            );
            if (isset($compiler->template->tpl_vars[$var])) {
                $compiler->template->tpl_vars[$var]->nocache = true;
            } else {
                $compiler->template->tpl_vars[$var] = new Smarty_Variable(null, true);
            }
        }

        if (isset($_attr['script'])) {
            // script which must be included
            $_function = 'smarty_insert_' . $_name;
            $_smarty_tpl = $compiler->template;
            $_filepath = false;
            eval('$_script = @' . $_attr['script'] . ';');
            if (! isset($compiler->smarty->security_policy) && file_exists($_script)) {
                $_filepath = $_script;
            } else {
                if (isset($compiler->smarty->security_policy)) {
                    $_dir = $compiler->smarty->security_policy->trusted_dir;
                } else {
                    $_dir = null;
                }

                if (! empty($_dir)) {
                    foreach ((array) $_dir as $_script_dir) {
                        $_script_dir = rtrim($_script_dir ?? '', '/\\') . DIRECTORY_SEPARATOR;
                        if (file_exists($_script_dir . $_script)) {
                            $_filepath = $_script_dir . $_script;
                            break;
                        }
                    }
                }
            }

            if ($_filepath === false) {
                $compiler->trigger_template_error(sprintf("{insert} missing script file '%s'", $_script), null, true);
            }

            // code for script file loading
            $_output .= sprintf("require_once '%s' ;", $_filepath);
            include_once $_filepath;
            if (! is_callable($_function)) {
                $compiler->trigger_template_error(
                    sprintf(" {insert} function '%s' is not callable in script file '%s'", $_function, $_script),
                    null,
                    true
                );
            }
        } else {
            $_filepath = 'null';
            $_function = 'insert_' . $_name;
            // function in PHP script ?
            if (! is_callable($_function)) {
                // try plugin
                if (! $_function = $compiler->getPlugin($_name, 'insert')) {
                    $compiler->trigger_template_error(
                        sprintf("{insert} no function or plugin found for '%s'", $_name),
                        null,
                        true
                    );
                }
            }
        }

        // delete {insert} standard attributes
        unset($_attr['name'], $_attr['assign'], $_attr['script'], $_attr['nocache']);
        // convert attributes into parameter array string
        $_paramsArray = [];
        foreach ($_attr as $_key => $_value) {
            $_paramsArray[] = sprintf("'%s' => %s", $_key, $_value);
        }

        $_params = 'array(' . implode(', ', $_paramsArray) . ')';
        // call insert
        if (isset($_assign)) {
            if ($_smarty_tpl->caching && ! $nocacheParam) {
                $_output .= sprintf(
                    'echo Smarty_Internal_Nocache_Insert::compile (\'%s\',%s, $_smarty_tpl, \'%s\',%s);?>',
                    $_function,
                    $_params,
                    $_filepath,
                    $_assign
                );
            } else {
                $_output .= sprintf(
                    '$_smarty_tpl->assign(%s , %s (%s,$_smarty_tpl), true);?>',
                    $_assign,
                    $_function,
                    $_params
                );
            }
        } else {
            if ($_smarty_tpl->caching && ! $nocacheParam) {
                $_output .= sprintf(
                    'echo Smarty_Internal_Nocache_Insert::compile (\'%s\',%s, $_smarty_tpl, \'%s\');?>',
                    $_function,
                    $_params,
                    $_filepath
                );
            } else {
                $_output .= sprintf('echo %s(%s,$_smarty_tpl);?>', $_function, $_params);
            }
        }

        $compiler->template->compiled->has_nocache_code = true;
        return $_output;
    }
}
