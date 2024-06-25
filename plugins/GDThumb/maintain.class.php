<?php

declare(strict_types=1);

defined('PHPWG_ROOT_PATH') || die('Hacking attempt!');

class GDThumb_maintain extends PluginMaintain
{
    private bool $installed = false;

    public function install($plugin_version, &$errors = []): void
    {
        include(dirname(__FILE__) . '/config_default.inc.php');
        global $conf;
        if (empty($conf['gdThumb'])):
            conf_update_param('gdThumb', $config_default, true);
        endif;

        $this->installed = true;
    }

    public function update($old_version, $new_version, &$errors = []): void
    {
        $this->install($new_version, $errors);
    }

    public function activate($plugin_version, &$errors = []): void
    {
        if (! $this->installed):
            $this->install($plugin_version, $errors);
            $this->cleanUp();
        endif;
    }

    public function uninstall(): void
    {
        $this->cleanUp();
        conf_delete_param('gdThumb');
    }

    private function cleanUp(): void
    {
        if (is_dir(PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'GDThumb')):
            $this->gtdeltree(PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'GDThumb');
        endif;
    }

    private function gtdeltree($path): void
    {
        if (is_dir($path)):
            $fh = opendir($path);
            while ($file = readdir($fh)) {
                if ($file != '.' && $file != '..'):
                    $pathfile = $path . '/' . $file;
                    if (is_dir($pathfile)):
                        $this->gtdeltree($pathfile);
                    else:
                        unlink($pathfile);
                    endif;
                endif;
            }
        closedir($fh);
        rmdir($path);
        endif;
    }
}
