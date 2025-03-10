<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Loads a SQL file and executes all queries.
 * Before executing a query, $replaced is... replaced by $replacing. This is
 * useful when the SQL file contains generic words. Drop table queries are
 * not executed.
 */
function execute_sqlfile(
    string $filepath,
    string $dblayer
): void {
    // todo: check if file exists
    $sql_lines = file($filepath);
    $query = '';
    foreach ($sql_lines as $sql_line) {
        $sql_line = trim($sql_line);
        if (preg_match('/(^--|^$)/', $sql_line)) {
            continue;
        }

        $query .= ' ' . $sql_line;
        // if we reached the end of query, we execute it and reinitialize the
        // variable "query"
        if (preg_match('/;$/', $sql_line)) {
            $query = trim($query);
            // we don't execute "DROP TABLE" queries
            if (! preg_match('/^DROP TABLE/i', $query)) {
                pwg_query($query);
            }

            $query = '';
        }
    }
}

/**
 * Automatically activate all core themes in the "themes" directory.
 */
function activate_core_themes(): void
{
    require_once PHPWG_ROOT_PATH . 'admin/include/themes.class.php';
    $themes = new themes();
    foreach (array_keys($themes->fs_themes) as $theme_id) {
        if (in_array($theme_id, ['modus', 'smartpocket'])) {
            $themes->perform_action('activate', $theme_id);
        }
    }
}

/**
 * Automatically activate some core plugins
 */
function activate_core_plugins(): void
{
    require_once PHPWG_ROOT_PATH . 'admin/include/plugins.class.php';

    $plugins = new plugins();

    foreach (array_keys($plugins->fs_plugins) as $plugin_id) {
        if (in_array($plugin_id, [])) {
            $plugins->perform_action('activate', $plugin_id);
        }
    }
}
