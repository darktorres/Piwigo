<?php
/**
 * Smarty Internal Plugin Compile Special Smarty Variable
 * Compiles the special $smarty variables
 *
 * @subpackage Compiler
 */

/**
 * Smarty Internal Plugin Compile special Smarty Variable Class
 *
 * @subpackage Compiler
 */
class Smarty_Internal_Compile_Private_Special_Variable extends Smarty_Internal_CompileBase
{
    /**
     * Compiles code for the special $smarty variables
     *
     * @param array                                 $args     array with attributes from parser
     * @param \Smarty_Internal_TemplateCompilerBase $compiler compiler object
     *
     * @return string compiled code
     */
    public function compile(
        $args,
        Smarty_Internal_TemplateCompilerBase $compiler,
        $parameter
    ) {
        $_index = preg_split("/\]\[/", substr((string) $parameter, 1, strlen((string) $parameter) - 2));
        $variable = smarty_strtolower_ascii($compiler->getId($_index[0]));
        if ($variable === false) {
            $compiler->trigger_template_error('special $Smarty variable name index can not be variable', null, true);
        }

        if ($compiler->smarty->security_policy === null
            || $compiler->smarty->security_policy->isTrustedSpecialSmartyVar($variable, $compiler)
        ) {
            switch ($variable) {
                case 'foreach':
                case 'section':
                    if (! isset(Smarty_Internal_TemplateCompilerBase::$_tag_objects[$variable])) {
                        $class = 'Smarty_Internal_Compile_' . smarty_ucfirst_ascii($variable);
                        Smarty_Internal_TemplateCompilerBase::$_tag_objects[$variable] = new $class();
                    }

                    return Smarty_Internal_TemplateCompilerBase::$_tag_objects[$variable]->compileSpecialVariable(
                        [],
                        $compiler,
                        $_index
                    );
                case 'capture':
                    if (class_exists('Smarty_Internal_Compile_Capture')) {
                        return Smarty_Internal_Compile_Capture::compileSpecialVariable([], $compiler, $_index);
                    }

                    return '';
                case 'now':
                    return 'time()';
                case 'cookies':
                    if ($compiler->smarty->security_policy !== null
                        && ! $compiler->smarty->security_policy->allow_super_globals
                    ) {
                        $compiler->trigger_template_error('(secure mode) super globals not permitted');
                        break;
                    }

                    $compiled_ref = '$_COOKIE';
                    break;
                case 'get':
                case 'post':
                case 'env':
                case 'server':
                case 'session':
                case 'request':
                    if ($compiler->smarty->security_policy !== null
                        && ! $compiler->smarty->security_policy->allow_super_globals
                    ) {
                        $compiler->trigger_template_error('(secure mode) super globals not permitted');
                        break;
                    }

                    $compiled_ref = '$_' . smarty_strtoupper_ascii($variable);
                    break;
                case 'template':
                    return 'basename($_smarty_tpl->source->filepath)';
                case 'template_object':
                    if ($compiler->smarty->security_policy !== null) {
                        $compiler->trigger_template_error('(secure mode) template_object not permitted');
                        break;
                    }

                    return '$_smarty_tpl';
                case 'current_dir':
                    return 'dirname($_smarty_tpl->source->filepath)';
                case 'version':
                    return 'Smarty::SMARTY_VERSION';
                case 'const':
                    if ($compiler->smarty->security_policy !== null
                        && ! $compiler->smarty->security_policy->allow_constants
                    ) {
                        $compiler->trigger_template_error('(secure mode) constants not permitted');
                        break;
                    }

                    if (! str_contains($_index[1], '$') && ! str_contains($_index[1], "'")) {
                        return sprintf("(defined('%s') ? constant('%s') : null)", $_index[1], $_index[1]);
                    }

                    return sprintf('(defined(%s) ? constant(%s) : null)', $_index[1], $_index[1]);

                case 'config':
                    if (isset($_index[2])) {
                        return sprintf(
                            '(is_array($tmp = $_smarty_tpl->smarty->ext->configload->_getConfigVariable($_smarty_tpl, %s)) ? $tmp[%s] : null)',
                            $_index[1],
                            $_index[2]
                        );
                    }

                    return sprintf(
                        '$_smarty_tpl->smarty->ext->configload->_getConfigVariable($_smarty_tpl, %s)',
                        $_index[1]
                    );

                case 'ldelim':
                    return '$_smarty_tpl->smarty->left_delimiter';
                case 'rdelim':
                    return '$_smarty_tpl->smarty->right_delimiter';
                default:
                    $compiler->trigger_template_error('$smarty.' . trim($_index[0], "'") . ' is not defined');
                    break;
            }

            if (isset($_index[1])) {
                array_shift($_index);
                foreach ($_index as $_ind) {
                    $compiled_ref .= sprintf('[%s]', $_ind);
                }
            }

            return $compiled_ref;
        }

        return null;
    }
}
