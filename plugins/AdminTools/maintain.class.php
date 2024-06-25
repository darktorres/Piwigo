<?php

defined('PHPWG_ROOT_PATH') || die('Hacking attempt!');

class AdminTools_maintain extends PluginMaintain
{
    private $default_conf = [
        'default_open' => true,
        'closed_position' => 'left',
        'public_quick_edit' => true,
    ];

    #[\Override]
    public function install($plugin_version, &$errors = [])
    {
        global $conf;

        if (empty($conf['AdminTools'])) {
            conf_update_param('AdminTools', $this->default_conf, true);
        }
    }

    #[\Override]
    public function update($old_version, $new_version, &$errors = [])
    {
        $this->install($new_version, $errors);
    }

    #[\Override]
    public function uninstall()
    {
        conf_delete_param('AdminTools');
    }
}
