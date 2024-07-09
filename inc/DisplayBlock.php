<?php

namespace Piwigo\inc;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Represents a menu block ready for display in the BlockManager object.
 */
class DisplayBlock
{
    public mixed $data;

    public string $template;

    public string $raw_content;

    protected int $_position;

    protected string $_title;

    /**
     * @param RegisteredBlock $_registeredBlock
     */
    public function __construct(
        protected $_registeredBlock
    ) {
    }

    /**
     * @return RegisteredBlock
     */
    public function get_block()
    {
        return $this->_registeredBlock;
    }

    public function get_position(): int
    {
        return $this->_position;
    }

    public function set_position(int $position): void
    {
        $this->_position = $position;
    }

    /**
     * @return string
     */
    public function get_title()
    {
        return $this->_title ?? $this->_registeredBlock->get_name();

    }

    public function set_title(string $title): void
    {
        $this->_title = $title;
    }
}
