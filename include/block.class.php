<?php declare(strict_types=1);
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * @package functions\menubar
 */


/**
 * Manages a set of RegisteredBlock and DisplayBlock.
 */
class BlockManager
{
  protected string $id;
  protected array $registered_blocks = array();
  protected array $display_blocks = array();

  /**
   * @param string $id
   */
  public function __construct(string $id)
  {
    $this->id = $id;
  }

  /**
   * Triggers a notice that allows plugins of menu blocks to register the blocks.
   */
  public function load_registered_blocks(): void
  {
    trigger_notify('blockmanager_register_blocks', array($this));
  }
  
  /**
   * @return string
   */
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
   *
   * @param RegisteredBlock $block
   * @return bool
   */
  public function register_block(RegisteredBlock $block): bool
  {
    if (isset($this->registered_blocks[$block->get_id()]))
    {
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
    $conf_id = 'blk_'.$this->id;
    $mb_conf = $conf[$conf_id] ?? array();
    if (!is_array($mb_conf))
    {
      $mb_conf = unserialize($mb_conf);
    }

    $idx = 1;
    foreach ($this->registered_blocks as $id => $block)
    {
      $pos = $mb_conf[$id] ?? $idx*50;
      if ($pos>0)
      {
        $this->display_blocks[$id] = new DisplayBlock($block);
        $this->display_blocks[$id]->set_position($pos);
      }
      $idx++;
    }
    $this->sort_blocks();
    trigger_notify('blockmanager_prepare_display', array($this));
    $this->sort_blocks();
  }

  /**
   * Returns true if the block is hidden.
   *
   * @param string $block_id
   * @return bool
   */
  public function is_hidden(string $block_id): bool
  {
    return !isset($this->display_blocks[$block_id]);
  }

  /**
   * Remove a block from the displayed blocks.
   *
   * @param string $block_id
   */
  public function hide_block(string $block_id): void
  {
    unset($this->display_blocks[$block_id]);
  }

  /**
   * Returns a visible block.
   *
   * @param string $block_id
   * @return DisplayBlock|null
   */
  public function get_block(string $block_id): ?DisplayBlock
  {
    if (isset($this->display_blocks[$block_id]))
    {
      return $this->display_blocks[$block_id];
    }
    return null;
  }

  /**
   * Changes the position of a block.
   *
   * @param string $block_id
   * @param int $position
   */
  public function set_block_position(string $block_id, int $position): void
  {
    if (isset($this->display_blocks[$block_id]))
    {
      $this->display_blocks[$block_id]->set_position($position);
    }
  }

  /**
   * Sorts the blocks.
   */
  protected function sort_blocks(): void
  {
    uasort($this->display_blocks, array('BlockManager', 'cmp_by_position'));
  }

  /**
   * Callback for blocks sorting.
   */
  protected static function cmp_by_position($a, $b)
  {
    return $a->get_position() - $b->get_position();
  }

  /**
   * Parse the menu and assign the result in a template variable.
   *
   * @param string $var
   * @param string $file
   * @throws Smarty\Exception
   */
  public function apply(string $var, string $file): void
  {
    global $template;

    $template->set_filename('menubar', $file);
    trigger_notify('blockmanager_apply', array($this) );

    foreach ($this->display_blocks as $id=>$block)
    {
      if (empty($block->raw_content) && empty($block->template))
      {
        $this->hide_block($id);
      }
    }
    $this->sort_blocks();
    $template->assign('blocks', $this->display_blocks);
    $template->assign_var_from_handle($var, 'menubar');
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

  /**
   * @param string $id
   * @param string $name
   * @param string $owner
   */
  public function __construct(string $id, string $name, string $owner)
  {
    $this->id = $id;
    $this->name = $name;
    $this->owner = $owner;
  }

  /**
   * @return string
   */
  public function get_id(): string
  {
    return $this->id;
  }

  /**
   * @return string
   */
  public function get_name(): string
  {
    return $this->name;
  }

  /**
   * @return string
   */
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
  protected RegisteredBlock $_registeredBlock;
  protected int $_position;
  protected string $_title;

  public mixed $data;
  public string $template;
  public string $raw_content;

  /**
   * @param RegisteredBlock $block
   */
  public function __construct(RegisteredBlock $block)
  {
    $this->_registeredBlock = $block;
  }

  /**
   * @return RegisteredBlock
   */
  public function get_block(): RegisteredBlock
  {
    return $this->_registeredBlock;
  }

  /**
   * @return int
   */
  public function get_position(): int
  {
    return $this->_position;
  }

  /**
   * @param int $position
   */
  public function set_position(int $position): void
  {
    $this->_position = $position;
  }

  /**
   * @return string
   */
  public function get_title(): string
  {
    return $this->_title ?? $this->_registeredBlock->get_name();
  }

  /**
   * @param string $title
   */
  public function set_title(string $title): void
  {
    $this->_title = $title;
  }
}

