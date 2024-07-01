<?php
/**
 * Smarty Resource Plugin
 *
 * @subpackage TemplateResources
 */

/**
 * Smarty Resource Plugin
 * Wrapper Implementation for custom resource plugins
 *
 * @subpackage TemplateResources
 */
abstract class Smarty_Resource_Custom extends Smarty_Resource
{
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
        $source->filepath = $source->type . ':' . $this->generateSafeName($source->name);
        $source->uid = sha1($source->type . ':' . $source->name);
        $mtime = $this->fetchTimestamp($source->name);
        if ($mtime !== null) {
            $source->timestamp = $mtime;
        } else {
            $this->fetch($source->name, $content, $timestamp);
            $source->timestamp = $timestamp ?? false;
            if (isset($content)) {
                $source->content = $content;
            }
        }

        $source->exists = (bool) $source->timestamp;
    }

    /**
     * Load template's source into current template object
     *
     * @param Smarty_Template_Source $source source object
     *
     * @return string                 template source
     */
    #[\Override]
    public function getContent(
        Smarty_Template_Source $source
    ) {
        $this->fetch($source->name, $content, $timestamp);
        if (isset($content)) {
            return $content;
        }

        throw new SmartyException(sprintf("Unable to read template %s '%s'", $source->type, $source->name));
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
        return basename($this->generateSafeName($source->name));
    }

    /**
     * fetch template and its modification time from data source
     *
     * @param string  $name    template name
     * @param string  $source template source
     * @param integer $mtime  template modification timestamp (epoch)
     */
    abstract protected function fetch(
        $name,
        &$source,
        &$mtime
    );

    /**
     * Fetch template's modification timestamp from data source
     * {@internal implementing this method is optional.
     *  Only implement it if modification times can be accessed faster than loading the complete template source.}}
     *
     * @param string $name template name
     *
     * @return integer|boolean timestamp (epoch) the template was modified, or false if not found
     */
    protected function fetchTimestamp(
        $name
    ) {
        return null;
    }

    /**
     * Removes special characters from $name and limits its length to 127 characters.
     */
    private function generateSafeName(
        $name
    ): string {
        return substr((string) preg_replace('/[^A-Za-z0-9._]/', '', (string) $name), 0, 127);
    }
}
