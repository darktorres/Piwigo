<?php

/**
 * Smarty Method UnloadFilter
 *
 * Smarty::unloadFilter() method
 *
 * @subpackage PluginsInternal
 */
class Smarty_Internal_Method_UnloadFilter extends Smarty_Internal_Method_LoadFilter
{
    /**
     * load a filter of specified type and name
     *
     * @api  Smarty::unloadFilter()
     *
     * @link https://www.smarty.net/docs/en/api.unload.filter.tpl
     *
     * @param \Smarty_Internal_TemplateBase|\Smarty_Internal_Template|\Smarty $obj
     * @param string                                                          $type filter type
     * @param string                                                          $name filter name
     */
    public function unloadFilter(
        Smarty_Internal_TemplateBase $obj,
        $type,
        $name
    ): Smarty_Internal_TemplateBase {
        $smarty = $obj->_getSmartyObj();
        $this->_checkFilterType($type);
        if (isset($smarty->registered_filters[$type])) {
            $_filter_name = sprintf('smarty_%sfilter_%s', $type, $name);
            if (isset($smarty->registered_filters[$type][$_filter_name])) {
                unset($smarty->registered_filters[$type][$_filter_name]);
                if (empty($smarty->registered_filters[$type])) {
                    unset($smarty->registered_filters[$type]);
                }
            }
        }

        return $obj;
    }
}
