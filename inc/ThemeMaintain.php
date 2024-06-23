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
 * Used to declare maintenance methods of a theme.
 */
class ThemeMaintain
{
    /**
     * @param string $theme_id
     */
    public function __construct(
        protected $theme_id
    ) {
    }

    /**
     * @param string $theme_version
     * @param array $errors - used to return error messages
     */
    public function activate(
        $theme_version,
        &$errors = [
        ]
    ) {
    }

    public function deactivate()
    {
    }

    public function delete()
    {
    }
}
