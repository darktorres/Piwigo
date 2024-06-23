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
    /**
     * @var mixed
     */
    public $data;

    /**
     * @var string
     */
    public $template;

    /**
     * @var string
     */
    public $raw_content;

    /**
     * @var int
     */
    protected $_position;

    /**
     * @var string
     */
    protected $_title;

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

    /**
     * @return int
     */
    public function get_position()
    {
        return $this->_position;
    }

    /**
     * @param int $position
     */
    public function set_position($position)
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

    /**
     * @param string $title
     */
    public function set_title($title)
    {
        $this->_title = $title;
    }
}
