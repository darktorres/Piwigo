<?php

/**
 * Smarty Method MustCompile
 *
 * Smarty_Internal_Template::mustCompile() method
 *
 * @subpackage PluginsInternal
 */
class Smarty_Internal_Method_MustCompile
{
    /**
     * Valid for template object
     *
     * @var int
     */
    public $objMap = 2;

    /**
     * Returns if the current template must be compiled by the Smarty compiler
     * It does compare the timestamps of template source and the compiled templates and checks the force compile
     * configuration
     *
     * @return bool
     */
    public function mustCompile(
        Smarty_Internal_Template $_template
    ) {
        if (! $_template->source->exists) {
            $parent_resource = $_template->_isSubTpl() ? sprintf(
                " in '%s'",
                $_template->parent->template_resource
            ) : '';
            throw new SmartyException(
                sprintf(
                    "Unable to load template %s '%s'%s",
                    $_template->source->type,
                    $_template->source->name,
                    $parent_resource
                )
            );
        }

        if ($_template->mustCompile === null) {
            $_template->mustCompile = (! $_template->source->handler->uncompiled &&
                                       ($_template->smarty->force_compile || $_template->source->handler->recompiled ||
                                        ! $_template->compiled->exists || ($_template->compile_check &&
                                                                          $_template->compiled->getTimeStamp() <
                                                                          $_template->source->getTimeStamp())));
        }

        return $_template->mustCompile;
    }
}
