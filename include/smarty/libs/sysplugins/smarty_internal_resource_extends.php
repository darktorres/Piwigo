<?php
/**
 * Smarty Internal Plugin Resource Extends
 *
 * @subpackage TemplateResources
 */

/**
 * Smarty Internal Plugin Resource Extends
 * Implements the file system as resource for Smarty which {extend}s a chain of template files templates
 *
 * @subpackage TemplateResources
 */
class Smarty_Internal_Resource_Extends extends Smarty_Resource
{
    /**
     * mbstring.overload flag
     *
     * @var int
     */
    public $mbstring_overload = 0;

    /**
     * populate Source Object with meta data from Resource
     *
     * @param Smarty_Template_Source   $source    source object
     * @param Smarty_Internal_Template $_template template object
     */
    #[\Override]
    public function populate(
        Smarty_Template_Source $source,
        Smarty_Internal_Template $_template = null
    ) {
        $uid = '';
        $sources = [];
        $components = explode('|', $source->name);
        $smarty = &$source->smarty;
        $exists = true;
        foreach ($components as $component) {
            /** @var \Smarty_Template_Source $_s */
            $_s = Smarty_Template_Source::load(null, $smarty, $component);
            if ($_s->type === 'php') {
                throw new SmartyException(sprintf(
                    'Resource type %s cannot be used with the extends resource type',
                    $_s->type
                ));
            }

            $sources[$_s->uid] = $_s;
            $uid .= $_s->filepath;
            if ($_template !== null) {
                $exists = $exists && $_s->exists;
            }
        }

        $source->components = $sources;
        $source->filepath = $_s->filepath;
        $source->uid = sha1($uid . $source->smarty->_joined_template_dir);
        $source->exists = $exists;
        if ($_template !== null) {
            $source->timestamp = $_s->timestamp;
        }
    }

    /**
     * populate Source Object with timestamp and exists from Resource
     *
     * @param Smarty_Template_Source $source source object
     */
    #[\Override]
    public function populateTimestamp(
        Smarty_Template_Source $source
    ) {
        $source->exists = true;
        /** @var \Smarty_Template_Source $_s */
        foreach ($source->components as $_s) {
            $source->exists = $source->exists && $_s->exists;
        }

        $source->timestamp = $source->exists ? $_s->getTimeStamp() : false;
    }

    /**
     * Load template's source from files into current template object
     *
     * @param Smarty_Template_Source $source source object
     *
     * @return string template source
     */
    #[\Override]
    public function getContent(
        Smarty_Template_Source $source
    ) {
        if (! $source->exists) {
            throw new SmartyException(sprintf("Unable to load template '%s:%s'", $source->type, $source->name));
        }

        $_components = array_reverse($source->components);
        $_content = '';
        /** @var \Smarty_Template_Source $_s */
        foreach ($_components as $_s) {
            // read content
            $_content .= $_s->getContent();
        }

        return $_content;
    }

    /**
     * Determine basename for compiled filename
     *
     * @param Smarty_Template_Source $source source object
     *
     * @return string resource's basename
     */
    #[\Override]
    public function getBasename(
        Smarty_Template_Source $source
    ) {
        return str_replace(':', '.', basename($source->filepath));
    }

    /*
      * Disable timestamp checks for extends resource.
      * The individual source components will be checked.
      *
      * @return bool
      */
    /**
     * @return bool
     */
    #[\Override]
    public function checkTimestamps()
    {
        return false;
    }
}
