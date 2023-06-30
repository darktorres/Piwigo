<?php declare(strict_types=1);
defined('PHPWG_ROOT_PATH') || die('Hacking attempt!');

/**
 *
 */
class GDThumb_maintain extends PluginMaintain {
  private bool $installed = false;

  /**
   * @param $plugin_version
   * @param $errors
   * @return void
   */
  public function install($plugin_version, &$errors=array()): void {
    include(dirname(__FILE__).'/config_default.inc.php');
    global $conf;
    if (empty($conf['gdThumb'])):
      conf_update_param('gdThumb', $config_default, true);
    endif;

    $this->installed = true;
  }

  /**
   * @param $old_version
   * @param $new_version
   * @param $errors
   * @return void
   */
  public function update($old_version, $new_version, &$errors=array()): void {
    $this->install($new_version, $errors);
  }

  /**
   * @param $plugin_version
   * @param $errors
   * @return void
   */
  public function activate($plugin_version, &$errors=array()): void {
    if (!$this->installed):
      $this->install($plugin_version, $errors);
      $this->cleanUp();
    endif;
  }

  /**
   * @return void
   */
  public function uninstall(): void {
    $this->cleanUp();
    conf_delete_param('gdThumb');
  }

  /**
   * @return void
   */
  private function cleanUp(): void
  {
    if (is_dir(PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'GDThumb')):
      $this->gtdeltree(PHPWG_ROOT_PATH . PWG_LOCAL_DIR . 'GDThumb');
    endif;
  }

  /**
   * @param $path
   * @return void
   */
  private function gtdeltree($path): void {
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

