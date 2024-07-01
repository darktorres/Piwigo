<?php

/**
 * Smarty Method RegisterDefaultTemplateHandler
 *
 * Smarty::registerDefaultTemplateHandler() method
 *
 * @subpackage PluginsInternal
 */
class Smarty_Internal_Method_RegisterDefaultTemplateHandler
{
    /**
     * Valid for Smarty and template object
     *
     * @var int
     */
    public $objMap = 3;

    /**
     * Register template default handler
     *
     * @api Smarty::registerDefaultTemplateHandler()
     *
     * @param \Smarty_Internal_TemplateBase|\Smarty_Internal_Template|\Smarty $obj
     * @param callable                                                        $callback class/method name
     *
     * @return \Smarty|\Smarty_Internal_Template
     */
    public function registerDefaultTemplateHandler(
        Smarty_Internal_TemplateBase $obj,
        $callback
    ) {
        $smarty = $obj->_getSmartyObj();
        if (is_callable($callback)) {
            $smarty->default_template_handler_func = $callback;
        } else {
            throw new SmartyException('Default template handler not callable');
        }

        return $obj;
    }

    /**
     * get default content from template or config resource handler
     */
    public static function _getDefaultTemplate(
        Smarty_Template_Source $source
    ) {
        if ($source->isConfig) {
            $default_handler = $source->smarty->default_config_handler_func;
        } else {
            $default_handler = $source->smarty->default_template_handler_func;
        }

        $_content = null;
        $_timestamp = null;
        $_return = call_user_func_array(
            $default_handler,
            [$source->type, $source->name, &$_content, &$_timestamp, $source->smarty]
        );
        if (is_string($_return)) {
            $source->exists = is_file($_return);
            if ($source->exists) {
                $source->timestamp = filemtime($_return);
            } else {
                throw new SmartyException(
                    'Default handler: Unable to load ' .
                    ($source->isConfig ? 'config' : 'template') .
                    sprintf(" default file '%s' for '%s:%s'", $_return, $source->type, $source->name)
                );
            }

            $source->name = $_return;
            $source->filepath = $_return;
            $source->uid = sha1($source->filepath);
        } elseif ($_return === true) {
            $source->content = $_content;
            $source->exists = true;
            $source->uid = sha1($_content);
            $source->name = $source->uid;
            $source->handler = Smarty_Resource::load($source->smarty, 'eval');
        } else {
            $source->exists = false;
            throw new SmartyException(
                'Default handler: No ' . ($source->isConfig ? 'config' : 'template') .
                sprintf(" default content for '%s:%s'", $source->type, $source->name)
            );
        }
    }
}
