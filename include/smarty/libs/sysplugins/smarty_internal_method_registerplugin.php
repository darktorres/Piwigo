<?php

/**
 * Smarty Method RegisterPlugin
 *
 * Smarty::registerPlugin() method
 *
 * @subpackage PluginsInternal
 */
class Smarty_Internal_Method_RegisterPlugin
{
    /**
     * Valid for Smarty and template object
     *
     * @var int
     */
    public $objMap = 3;

    /**
     * Registers plugin to be used in templates
     *
     * @api  Smarty::registerPlugin()
     * @link https://www.smarty.net/docs/en/api.register.plugin.tpl
     *
     * @param \Smarty_Internal_TemplateBase|\Smarty_Internal_Template|\Smarty $obj
     * @param string                                                          $type       plugin type
     * @param string                                                          $name       name of template tag
     * @param callback                                                        $callback   PHP callback to register
     * @param bool                                                            $cacheable  if true (default) this
     *                                                                                    function is cache able
     * @param mixed                                                           $cache_attr caching attributes if any
     *
     * @return \Smarty|\Smarty_Internal_Template
     */
    public function registerPlugin(
        Smarty_Internal_TemplateBase $obj,
        $type,
        $name,
        $callback,
        $cacheable = true,
        mixed $cache_attr = null
    ) {
        $smarty = $obj->_getSmartyObj();
        if (isset($smarty->registered_plugins[$type][$name])) {
            throw new SmartyException(sprintf("Plugin tag '%s' already registered", $name));
        } elseif (! is_callable($callback)) {
            throw new SmartyException(sprintf("Plugin '%s' not callable", $name));
        } elseif ($cacheable && $cache_attr) {
            throw new SmartyException(sprintf(
                "Cannot set caching attributes for plugin '%s' when it is cacheable.",
                $name
            ));
        }

        $smarty->registered_plugins[$type][$name] = [$callback, (bool) $cacheable, (array) $cache_attr];

        return $obj;
    }
}
