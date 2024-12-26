<?php

declare(strict_types=1);

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

class GDThumb_maintain extends PluginMaintain
{
    private bool $installed = false;

    public function install(
        string $plugin_version,
        array &$errors = []
    ): void {
        include(dirname(__FILE__) . '/config_default.inc.php');
        global $conf;
        if (empty($conf['gdThumb'])) {
            conf_update_param('gdThumb', $config_default, true);
        }

        $this->installed = true;
    }

    public function update(
        string $old_version,
        string $new_version,
        array &$errors = []
    ): void {
        $this->install($new_version, $errors);
    }

    public function activate(
        string $plugin_version,
        array &$errors = []
    ): void {
        if (! $this->installed) {
            $this->install($plugin_version, $errors);
            $this->cleanUp();
        }
    }

    public function uninstall(): void
    {
        $this->cleanUp();
        conf_delete_param('gdThumb');
    }

    private function cleanUp(): void
    {
        if (is_dir(PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'GDThumb')) {
            $this->gtdeltree(PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'GDThumb');
        }
    }

    private function gtdeltree(
        string $path
    ): ?bool {
        if (is_dir($path)) {
            $fh = opendir($path);
            while ($file = readdir($fh)) {
                if ($file != '.' and $file != '..') {
                    $pathfile = $path . '/' . $file;
                    if (is_dir($pathfile)) {
                        self::gtdeltree($pathfile);
                    } else {
                        unlink($pathfile);
                    }
                }
            }
            closedir($fh);
            return rmdir($path);
        }

        return null;
    }
}
