<?php

/**
 * Smarty Internal Undefined
 *
 * Class to handle undefined method calls or calls to obsolete runtime extensions
 *
 * @subpackage PluginsInternal
 */
class Smarty_Internal_Undefined
{
    /**
     * Smarty_Internal_Undefined constructor.
     *
     * @param null|string $class name of undefined extension class
     */
    public function __construct(
        /**
         * Name of undefined extension class
         */
        public $class = null
    ) {
    }

    /**
     * Call error handler for undefined method
     *
     * @param string $name unknown method-name
     * @param array  $args argument array
     *
     * @return mixed
     */
    public function __call(
        $name,
        $args
    ) {
        if ($this->class !== null) {
            throw new SmartyException(sprintf("undefined extension class '%s'", $this->class));
        }

        throw new SmartyException($args[0]::class . sprintf('->%s() undefined method', $name));
    }

    /**
     * Wrapper for obsolete class Smarty_Internal_Runtime_ValidateCompiled
     *
     * @param array                     $properties special template properties
     * @param bool                      $cache      flag if called from cache file
     *
     * @return bool false
     */
    public function decodeProperties(
        Smarty_Internal_Template $tpl,
        $properties,
        $cache = false
    ) {
        if ($cache) {
            $tpl->cached->valid = false;
        } else {
            $tpl->mustCompile = true;
        }

        return false;
    }
}
