<?php
/**
 * Smarty Internal Plugin Resource Eval
 *
 * @subpackage TemplateResources
 */

/**
 * Smarty Internal Plugin Resource Eval
 * Implements the strings as resource for Smarty template
 * {@internal unlike string-resources the compiled state of eval-resources is NOT saved for subsequent access}}
 *
 * @subpackage TemplateResources
 */
class Smarty_Internal_Resource_Eval extends Smarty_Resource_Recompiled
{
    /**
     * populate Source Object with meta data from Resource
     *
     * @param Smarty_Template_Source   $source    source object
     * @param Smarty_Internal_Template $_template template object
     */
    #[\Override]
    public function populate(Smarty_Template_Source $source, Smarty_Internal_Template $_template = null)
    {
        $source->uid = sha1($source->name);
        $source->filepath = $source->uid;
        $source->timestamp = true;
        $source->exists = true;
    }

    /**
     * Load template's source from $resource_name into current template object
     *
     * @uses decode() to decode base64 and urlencoded template_resources
     *
     * @param Smarty_Template_Source $source source object
     *
     * @return string                 template source
     */
    #[\Override]
    public function getContent(
        Smarty_Template_Source $source
    ) {
        return $this->decode($source->name);
    }

    /**
     * modify resource_name according to resource handlers specifications
     *
     * @param Smarty  $smarty        Smarty instance
     * @param string  $resource_name resource_name to make unique
     * @param boolean $isConfig      flag for config resource
     *
     * @return string unique resource name
     */
    #[\Override]
    public function buildUniqueResourceName(
        Smarty $smarty,
        $resource_name,
        $isConfig = false
    ) {
        return static::class . '#' . $this->decode($resource_name);
    }

    /**
     * Determine basename for compiled filename
     *
     * @param Smarty_Template_Source $source source object
     *
     * @return string                 resource's basename
     */
    #[\Override]
    public function getBasename(
        Smarty_Template_Source $source
    ) {
        return '';
    }

    /**
     * decode base64 and urlencode
     *
     * @param string $string template_resource to decode
     *
     * @return string decoded template_resource
     */
    protected function decode(
        $string
    ) {
        // decode if specified
        if (($pos = strpos($string, ':')) !== false) {
            if (! strncmp($string, 'base64', 6)) {
                return base64_decode(substr($string, 7));
            } elseif (! strncmp($string, 'urlencode', 9)) {
                return urldecode(substr($string, 10));
            }
        }

        return $string;
    }
}
