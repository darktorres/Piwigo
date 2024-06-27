<?php

/**
 * Smarty Internal Plugin Templateparser Parse Tree
 * These are classes to build parse tree in the template parser
 *
 * @subpackage Compiler
 * @subpackage Compiler
 * @ignore
 */
class Smarty_Internal_ParseTree_Text extends Smarty_Internal_ParseTree
{
    /**
     * Create template text buffer
     *
     * @param string $data text
     * @param bool $toBeStripped wether this section should be stripped on output to smarty php
     */
    public function __construct(
        $data, /**
     * Wether this section should be stripped on output to smarty php
     */
        private $toBeStripped = false
    ) {
        $this->data = $data;
    }

    /**
     * Wether this section should be stripped on output to smarty php
     * @return bool
     */
    public function isToBeStripped()
    {
        return $this->toBeStripped;
    }

    /**
     * Return buffer content
     *
     * @return string text
     */
    #[\Override]
    public function to_smarty_php(Smarty_Internal_Templateparser $parser)
    {
        return $this->data;
    }
}
