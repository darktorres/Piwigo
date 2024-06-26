<?php

namespace Piwigo\inc;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

require_once __DIR__ . '/functions_plugins.inc.php';

/**
 * Used to declare maintenance methods of a plugin.
 */
class PluginMaintain
{
    /**
     * @param string $plugin_id
     */
    public function __construct(
        protected $plugin_id
    ) {
    }

    /**
     * @param string $plugin_version
     * @param array $errors - used to return error messages
     */
    public function install(
        $plugin_version,
        &$errors = [
        ]
    ) {
    }

    /**
     * @param string $plugin_version
     * @param array $errors - used to return error messages
     */
    public function activate(
        $plugin_version,
        &$errors = [
        ]
    ) {
    }

    public function deactivate()
    {
    }

    public function uninstall()
    {
    }

    /**
     * @param string $old_version
     * @param string $new_version
     * @param array $errors - used to return error messages
     */
    public function update(
        $old_version,
        $new_version,
        &$errors = [
        ]
    ) {
    }

    /**
     * @removed 2.7
     */
    public function autoUpdate()
    {
        if (is_admin() && ! defined('IN_WS')) {
            trigger_error('Function PluginMaintain::autoUpdate deprecated', E_USER_WARNING);
        }
    }
}
