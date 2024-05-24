<?php

class smartpocket_maintain extends ThemeMaintain
{
    private $installed = false;

    private $default_conf = [
        'loop' => true,
        //true - false
        'autohide' => 5000,
        //5000 - 0
    ];

    #[\Override]
    public function activate($theme_version, &$errors = [])
    {
        global $conf, $prefixeTable;

        if (empty($conf['smartpocket'])) {
            conf_update_param('smartpocket', $this->default_conf, true);
        } elseif (count(safe_unserialize($conf['smartpocket'])) != 2) {
            $conff = safe_unserialize($conf['smartpocket']);

            $config = [
                'loop' => (empty($conff['loop'])) ? true : $conff['loop'],
                'autohide' => (empty($conff['autohide'])) ? 5000 : $conff['autohide'],
            ];

            conf_update_param('smartpocket', $config, true);
        }

        $this->installed = true;
    }

    #[\Override]
    public function deactivate()
    {
    }

    #[\Override]
    public function delete()
    {
        // delete configuration
        conf_delete_param('smartpocket');
    }
}
