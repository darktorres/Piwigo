<?php

declare(strict_types=1);

defined('PHPWG_ROOT_PATH') || die('Hacking attempt!');

class AdminTools_maintain extends PluginMaintain
{
    private array $default_conf = [
        'default_open' => true,
        'closed_position' => 'left',
        'public_quick_edit' => true,
    ];

    #[\Override]
    public function install(
        string $plugin_version,
        array &$errors = []
    ): void {
        global $conf;

        if (empty($conf['AdminTools'])) {
            conf_update_param('AdminTools', $this->default_conf, true);
        }
    }

    #[\Override]
    public function update(
        string $old_version,
        string $new_version,
        array &$errors = []
    ): void {
        $this->install($new_version, $errors);
    }

    #[\Override]
    public function uninstall(): void
    {
        conf_delete_param('AdminTools');
    }
}
