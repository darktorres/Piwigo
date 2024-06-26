<?php
/**
 * Smarty Internal Plugin Templateparser Parsetree
 * These are classes to build parsetree in the template parser
 *
 * @subpackage Compiler
 */

/**
 * @subpackage Compiler
 * @ignore
 */
abstract class Smarty_Internal_ParseTree
{
    /**
     * Buffer content
     *
     * @var mixed
     */
    public $data;

    /**
     * Subtree array
     *
     * @var array
     */
    public $subtrees = [];

    /**
     * Template data object destructor
     */
    public function __destruct()
    {
        $this->data = null;
        $this->subtrees = null;
    }

    /**
     * Return buffer
     *
     * @return string buffer content
     */
    abstract public function to_smarty_php(
        Smarty_Internal_Templateparser $parser
    );
}
