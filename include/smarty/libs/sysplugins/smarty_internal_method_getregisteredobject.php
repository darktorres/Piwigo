<?php

/**
 * Smarty Method GetRegisteredObject
 *
 * Smarty::getRegisteredObject() method
 *
 * @subpackage PluginsInternal
 */
class Smarty_Internal_Method_GetRegisteredObject
{
    /**
     * Valid for Smarty and template object
     *
     * @var int
     */
    public $objMap = 3;

    /**
     * return a reference to a registered object
     *
     * @api  Smarty::getRegisteredObject()
     * @link https://www.smarty.net/docs/en/api.get.registered.object.tpl
     *
     * @param \Smarty_Internal_TemplateBase|\Smarty_Internal_Template|\Smarty $obj
     * @param string                                                          $object_name object name
     *
     * @return object
     */
    public function getRegisteredObject(
        Smarty_Internal_TemplateBase $obj,
        $object_name
    ) {
        $smarty = $obj->_getSmartyObj();
        if (! isset($smarty->registered_objects[$object_name])) {
            throw new SmartyException(sprintf("'%s' is not a registered object", $object_name));
        }

        if (! is_object($smarty->registered_objects[$object_name][0])) {
            throw new SmartyException(sprintf("registered '%s' is not an object", $object_name));
        }

        return $smarty->registered_objects[$object_name][0];
    }
}
