<?php

/**
 * {make_nocache} Runtime Methods save(), store()
 *
 * @subpackage PluginsInternal
 */
class Smarty_Internal_Runtime_Make_Nocache
{
    /**
     * Save current variable value while rendering compiled template and inject nocache code to
     * assign variable value in cahed template
     *
     * @param string                    $var variable name
     */
    public function save(
        Smarty_Internal_Template $tpl,
        $var
    ) {
        if (isset($tpl->tpl_vars[$var])) {
            $export =
                preg_replace(
                    '/^\\\\?Smarty_Variable::__set_state[(]|[)]$/',
                    '',
                    var_export($tpl->tpl_vars[$var], true)
                );
            if (preg_match('/(\w+)::__set_state/', (string) $export, $match)) {
                throw new SmartyException(
                    sprintf(
                        '{make_nocache $%s} in template \'%s\': variable does contain object \'%s\' not implementing method \'__set_state\'',
                        $var,
                        $tpl->source->name,
                        $match[1]
                    )
                );
            }

            echo sprintf('/*%%%%SmartyNocache:%s%%%%*/<?php ', $tpl->compiled->nocache_hash) .
                 addcslashes(
                     sprintf('$_smarty_tpl->smarty->ext->_make_nocache->store($_smarty_tpl, \'%s\', ', $var),
                     '\\'
                 ) .
                 $export . ");?>\n/*/%%SmartyNocache:{$tpl->compiled->nocache_hash}%%*/";
        }
    }

    /**
     * Store variable value saved while rendering compiled template in cached template context
     *
     * @param string                    $var variable name
     * @param array                     $properties
     */
    public function store(
        Smarty_Internal_Template $tpl,
        $var,
        $properties
    ) {
        // do not overwrite existing nocache variables
        if (! isset($tpl->tpl_vars[$var]) || ! $tpl->tpl_vars[$var]->nocache) {
            $newVar = new Smarty_Variable();
            unset($properties['nocache']);
            foreach ($properties as $k => $v) {
                $newVar->{$k} = $v;
            }

            $tpl->tpl_vars[$var] = $newVar;
        }
    }
}
