<?php

declare(strict_types=1);

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

class GDThumb_maintain extends PluginMaintain
{
    private $installed = false;

    public function install($plugin_version, &$errors = [])
    {
        include(dirname(__FILE__) . '/config_default.inc.php');
        global $conf;
        if (empty($conf['gdThumb'])) {
            conf_update_param('gdThumb', $config_default, true);
        }

        $this->installed = true;
    }

    public function update($old_version, $new_version, &$errors = [])
    {
        $this->install($new_version, $errors);
    }

    public function activate($plugin_version, &$errors = [])
    {
        if (! $this->installed) {
            $this->install($plugin_version, $errors);
            $this->cleanUp();
        }
    }

    public function uninstall()
    {
        $this->cleanUp();
        conf_delete_param('gdThumb');
    }

    private function cleanUp()
    {
        if (is_dir(PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'GDThumb')) {
            $this->gtdeltree(PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'GDThumb');
        }
    }

    private function gtdeltree($path)
    {
        if (is_dir($path)) {
            $fh = opendir($path);
            while ($file = readdir($fh)) {
                if ($file != '.' and $file != '..') {
                    $pathfile = $path . '/' . $file;
                    if (is_dir($pathfile)) {
                        gtdeltree($pathfile);
                    } else {
                        @unlink($pathfile);
                    }
                }
            }
            closedir($fh);
            return @rmdir($path);
        }
    }
}
