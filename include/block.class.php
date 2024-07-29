<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Manages a set of RegisteredBlock and DisplayBlock.
 */
class BlockManager
{
    protected string $id;

    /**
     * @var RegisteredBlock[]
     */
    protected $registered_blocks = [];

    /**
     * @var DisplayBlock[]
     */
    protected $display_blocks = [];

    public function __construct(
        string $id
    ) {
        $this->id = $id;
    }

    /**
     * Triggers a notice that allows plugins of menu blocks to register the blocks.
     */
    public function load_registered_blocks(): void
    {
        trigger_notify('blockmanager_register_blocks', [$this]);
    }

    public function get_id(): string
    {
        return $this->id;
    }

    /**
     * @return RegisteredBlock[]
     */
    public function get_registered_blocks(): array
    {
        return $this->registered_blocks;
    }

    /**
     * Add a block with the menu. Usually called in 'blockmanager_register_blocks' event.
     */
    public function register_block(
        RegisteredBlock $block
    ): bool {
        if (isset($this->registered_blocks[$block->get_id()])) {
            return false;
        }
        $this->registered_blocks[$block->get_id()] = $block;
        return true;
    }

    /**
     * Performs one time preparation of registered blocks for display.
     * Triggers 'blockmanager_prepare_display' event where plugins can
     * reposition or hide blocks
     */
    public function prepare_display(): void
    {
        global $conf;
        $conf_id = 'blk_' . $this->id;
        $mb_conf = isset($conf[$conf_id]) ? $conf[$conf_id] : [];
        if (! is_array($mb_conf)) {
            $mb_conf = unserialize($mb_conf);
        }

        $idx = 1;
        foreach ($this->registered_blocks as $id => $block) {
            $pos = isset($mb_conf[$id]) ? $mb_conf[$id] : $idx * 50;
            if ($pos > 0) {
                $this->display_blocks[$id] = new DisplayBlock($block);
                $this->display_blocks[$id]->set_position($pos);
            }
            $idx++;
        }
        $this->sort_blocks();
        trigger_notify('blockmanager_prepare_display', [$this]);
        $this->sort_blocks();
    }

    /**
     * Returns true if the block is hidden.
     */
    public function is_hidden(
        string $block_id
    ): bool {
        return ! isset($this->display_blocks[$block_id]);
    }

    /**
     * Remove a block from the displayed blocks.
     */
    public function hide_block(
        string $block_id
    ): void {
        unset($this->display_blocks[$block_id]);
    }

    /**
     * Returns a visible block.
     */
    public function get_block(
        string $block_id
    ): ?DisplayBlock {
        if (isset($this->display_blocks[$block_id])) {
            return $this->display_blocks[$block_id];
        }
        return null;
    }

    /**
     * Changes the position of a block.
     */
    public function set_block_position(
        string $block_id,
        int $position
    ): void {
        if (isset($this->display_blocks[$block_id])) {
            $this->display_blocks[$block_id]->set_position($position);
        }
    }

    /**
     * Parse the menu and assign the result in a template variable.
     */
    public function apply(
        string $var,
        string $file
    ): void {
        global $template;

        $template->set_filename('menubar', $file);
        trigger_notify('blockmanager_apply', [$this]);

        foreach ($this->display_blocks as $id => $block) {
            if (empty($block->raw_content) and empty($block->template)) {
                $this->hide_block($id);
            }
        }
        $this->sort_blocks();
        $template->assign('blocks', $this->display_blocks);
        $template->assign_var_from_handle($var, 'menubar');
    }

    /**
     * Sorts the blocks.
     */
    protected function sort_blocks(): void
    {
        uasort($this->display_blocks, self::cmp_by_position(...));
    }

    /**
     * Callback for blocks sorting.
     */
    protected static function cmp_by_position(
        DisplayBlock $a,
        DisplayBlock $b
    ): int {
        return $a->get_position() - $b->get_position();
    }
}

/**
 * Represents a menu block registered in a BlockManager object.
 */
class RegisteredBlock
{
    protected string $id;

    protected string $name;

    protected string $owner;

    public function __construct(
        string $id,
        string $name,
        string $owner
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->owner = $owner;
    }

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

/**
 * Represents a menu block ready for display in the BlockManager object.
 */
class DisplayBlock
{
    public mixed $data;

    public string $template;

    public string $raw_content;

    public int $id;

    protected RegisteredBlock $_registeredBlock;

    protected int $_position;

    protected string $_title;

    public function __construct(
        RegisteredBlock $block
    ) {
        $this->_registeredBlock = $block;
    }

    public function get_block(): RegisteredBlock
    {
        return $this->_registeredBlock;
    }

    public function get_position(): int
    {
        return $this->_position;
    }

    public function set_position(
        int $position
    ): void {
        $this->_position = $position;
    }

    public function get_title(): string
    {
        if (isset($this->_title)) {
            return $this->_title;
        }

        return $this->_registeredBlock->get_name();

    }

    public function set_title(
        string $title
    ): void {
        $this->_title = $title;
    }
}
