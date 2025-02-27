<?php
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Used to declare maintenance methods of a plugin.
 */
class PluginMaintain
{
  /** @var string $plugin_id */
  protected $plugin_id;

  /**
   * @param string $id
   */
  function __construct($id)
  {
    $this->plugin_id = $id;
  }

  /**
   * @param string $plugin_version
   * @param array &$errors - used to return error messages
   */
  function install($plugin_version, &$errors=array()) {}

  /**
   * @param string $plugin_version
   * @param array &$errors - used to return error messages
   */
  function activate($plugin_version, &$errors=array()) {}

  function deactivate() {}

  function uninstall() {}

  /**
   * @param string $old_version
   * @param string $new_version
   * @param array &$errors - used to return error messages
   */
  function update($old_version, $new_version, &$errors=array()) {}
  
  /**
   * @removed 2.7
   */
  function autoUpdate()
  {
    if (is_admin() && !defined('IN_WS'))
    {
      trigger_error('Function PluginMaintain::autoUpdate deprecated', E_USER_WARNING);
    }
  }
}

?>