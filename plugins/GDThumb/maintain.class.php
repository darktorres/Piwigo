<?php

use Piwigo\inc\PluginMaintain;
use function Piwigo\inc\conf_delete_param;
use function Piwigo\inc\conf_update_param;

defined('PHPWG_ROOT_PATH') || die('Hacking attempt!');

class GDThumb_maintain extends PluginMaintain
{
    private $installed = false;

    #[\Override]
    public function install($plugin_version, &$errors = [])
    {
        include(__DIR__ . '/config_default.inc.php');
        global $conf;
        if (empty($conf['gdThumb'])):
            conf_update_param('gdThumb', $config_default, true);
        endif;

        $this->installed = true;
    }

    #[\Override]
    public function update($old_version, $new_version, &$errors = [])
    {
        $this->install($new_version, $errors);
    }

    #[\Override]
    public function activate($plugin_version, &$errors = [])
    {
        if (! $this->installed):
            $this->install($plugin_version, $errors);
            $this->cleanUp();
        endif;
    }

    #[\Override]
    public function uninstall()
    {
        $this->cleanUp();
        conf_delete_param('gdThumb');
    }

    private function cleanUp()
    {
        if (is_dir(PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'GDThumb')):
            $this->gtdeltree(PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'GDThumb');
        endif;
    }

    private function gtdeltree($path)
    {
        if (is_dir($path)):
            $fh = opendir($path);
            while ($file = readdir($fh)) {
                if ($file !== '.' && $file !== '..'):
                    $pathfile = $path . '/' . $file;
                    if (is_dir($pathfile)):
                        self::gtdeltree($pathfile);
                    else:
                        @unlink($pathfile);
                    endif;
                endif;
            }

        closedir($fh);
        return @rmdir($path);
        endif;

        return null;
    }
}
