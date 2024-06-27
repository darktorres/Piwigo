<?php

/**
 * class for undefined variable object
 * This class defines an object for undefined variable handling
 *
 * @subpackage Template
 */
class Smarty_Undefined_Variable extends Smarty_Variable
{
    /**
     * Returns null for not existing properties
     *
     * @param string $name
     */
    public function __get(
        $name
    ) {
        return null;
    }

    /**
     * Always returns an empty string.
     */
    #[\Override]
    public function __toString(): string
    {
        return '';
    }
}
