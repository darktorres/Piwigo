<?php

declare(strict_types=1);

class smartpocket_maintain extends ThemeMaintain
{
    private array $default_conf = [
        'loop' => true,
        //true - false
        'autohide' => 5000,
        //5000 - 0
    ];

    #[\Override]
    public function activate(
        string $theme_version,
        array &$errors = []
    ): void {
        global $conf;

        if (empty($conf['smartpocket'])) {
            conf_update_param('smartpocket', $this->default_conf, true);
        } elseif (count($conf['smartpocket']) != 2) {
            $conff = $conf['smartpocket'];

            $config = [
                'loop' => (empty($conff['loop'])) ? true : $conff['loop'],
                'autohide' => (empty($conff['autohide'])) ? 5000 : $conff['autohide'],
            ];

            conf_update_param('smartpocket', $config, true);
        }
    }

    #[\Override]
    public function deactivate(): void {}

    #[\Override]
    public function delete(): void
    {
        // delete configuration
        conf_delete_param('smartpocket');
    }
}
