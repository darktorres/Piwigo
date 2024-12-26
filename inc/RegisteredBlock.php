<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Represents a menu block registered in a BlockManager object.
 */
class RegisteredBlock
{
    public function __construct(
        protected string $id,
        protected string $name,
        protected string $owner
    ) {}

    public function get_id(): string
    {
        return $this->id;
    }

    public function get_name(): string
    {
        return $this->name;
    }

    public function get_owner(): string
    {
        return $this->owner;
    }
}
