<?php

declare(strict_types=1);

namespace Piwigo\inc;

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
    protected array $registered_blocks = [];

    protected array $display_blocks = [];

    public function __construct(
        protected string $id
    ) {
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
        $mb_conf = $conf[$conf_id] ?? [];
        if (! is_array($mb_conf)) {
            $mb_conf = unserialize($mb_conf);
        }

        $idx = 1;
        foreach ($this->registered_blocks as $id => $block) {
            $pos = $mb_conf[$id] ?? $idx * 50;
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
    public function is_hidden(string $block_id): bool
    {
        return ! isset($this->display_blocks[$block_id]);
    }

    /**
     * Remove a block from the displayed blocks.
     */
    public function hide_block(string $block_id): void
    {
        unset($this->display_blocks[$block_id]);
    }

    /**
     * Returns a visible block.
     */
    public function get_block(string $block_id): ?DisplayBlock
    {
        return $this->display_blocks[$block_id] ?? null;
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
            if (empty($block->raw_content) && empty($block->template)) {
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
        uasort($this->display_blocks, $this->cmp_by_position(...));
    }

    /**
     * Callback for blocks sorting.
     */
    protected static function cmp_by_position($a, $b)
    {
        return $a->get_position() - $b->get_position();
    }
}
