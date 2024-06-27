<?php

/**
 * Smarty exception class
 */
class SmartyException extends Exception
{
    public static $escape = false;

    #[\Override]
    public function __toString(): string
    {
        return ' --> Smarty: ' . (self::$escape ? htmlentities((string) $this->message) : $this->message) . ' <-- ';
    }
}
