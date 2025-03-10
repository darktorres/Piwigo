<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * class DummyPlugin_maintain
 * used when a plugin uses the old procedural declaration of maintenance methods
 */
class DummyPlugin_maintain extends PluginMaintain
{
    #[\Override]
    public function install(
        string $plugin_version,
        array &$errors = []
    ): void {
        if (is_callable('plugin_install')) {
            plugin_install($this->plugin_id, $plugin_version, $errors);
        }
    }

    #[\Override]
    public function activate(
        string $plugin_version,
        array &$errors = []
    ): void {
        if (is_callable('plugin_activate')) {
            plugin_activate($this->plugin_id, $plugin_version, $errors);
        }
    }

    #[\Override]
    public function deactivate(): void
    {
        if (is_callable('plugin_deactivate')) {
            plugin_deactivate($this->plugin_id);
        }
    }

    #[\Override]
    public function uninstall(): void
    {
        if (is_callable('plugin_uninstall')) {
            plugin_uninstall($this->plugin_id);
        }
    }

    #[\Override]
    public function update(
        string $old_version,
        string $new_version,
        array &$errors = []
    ): void {}
}

class plugins
{
    public array $fs_plugins = [];

    public array $db_plugins_by_id = [];

    public array $server_plugins = [];

    public array $default_plugins = ['LocalFilesEditor', 'language_switch', 'TakeATour', 'AdminTools'];

    /**
     * Initialize $fs_plugins and $db_plugins_by_id
     */
    public function __construct()
    {
        $this->get_fs_plugins();

        foreach (get_db_plugins() as $db_plugin) {
            $this->db_plugins_by_id[$db_plugin['id']] = $db_plugin;
        }
    }

    /**
     * Perform requested actions
     * @param string $action - action
     * @param string $plugin_id - plugin id
     * @param array $options - errors
     */
    public function perform_action(
        string $action,
        string $plugin_id,
        array $options = []
    ): mixed {
        global $conf;

        if (! $conf['enable_extensions_install'] && $action === 'delete') {
            die('Piwigo extensions install/update/delete system is disabled');
        }

        if (isset($this->db_plugins_by_id[$plugin_id])) {
            $crt_db_plugin = $this->db_plugins_by_id[$plugin_id];
        }

        if ($action !== 'update') { // wait for files to be updated
            $plugin_maintain = $this->build_maintain_class($plugin_id);
        }

        $activity_details = [
            'plugin_id' => $plugin_id,
        ];

        $errors = [];

        switch ($action) {
            case 'install':
                if (! empty($crt_db_plugin) || ! isset($this->fs_plugins[$plugin_id])) {
                    break;
                }

                $plugin_maintain->install($this->fs_plugins[$plugin_id]['version'], $errors);
                $activity_details['version'] = $this->fs_plugins[$plugin_id]['version'];

                if ($errors === []) {
                    $query = <<<SQL
                        INSERT INTO plugins
                            (id, version)
                        VALUES
                            ('{$plugin_id}', '{$this->fs_plugins[$plugin_id]['version']}');
                        SQL;
                    pwg_query($query);
                } else {
                    $activity_details['result'] = 'error';
                }

                break;

            case 'update':
                $previous_version = $this->fs_plugins[$plugin_id]['version'];
                $activity_details['from_version'] = $previous_version;
                $errors[0] = $this->extract_plugin_files('upgrade', $options['revision'], $plugin_id);

                if ($errors[0] === 'ok') {
                    $this->get_fs_plugin($plugin_id); // refresh plugins list
                    $new_version = $this->fs_plugins[$plugin_id]['version'];
                    $activity_details['to_version'] = $new_version;

                    $plugin_maintain = $this->build_maintain_class($plugin_id);
                    $plugin_maintain->update($previous_version, $new_version, $errors);

                    if ($new_version != 'auto') {
                        $query = <<<SQL
                            UPDATE plugins
                            SET version = '{$new_version}'
                            WHERE id = '{$plugin_id}';
                            SQL;
                        pwg_query($query);
                    }
                } else {
                    $activity_details['result'] = 'error';
                }

                break;

            case 'activate':
                if (! isset($crt_db_plugin)) {
                    $errors = $this->perform_action('install', $plugin_id);
                    [$crt_db_plugin] = get_db_plugins(null, $plugin_id);
                    load_conf_from_db();
                } elseif ($crt_db_plugin['state'] == 'active') {
                    break;
                }

                if (empty($errors)) {
                    $plugin_maintain->activate($crt_db_plugin['version'], $errors);
                    $activity_details['version'] = $crt_db_plugin['version'];
                }

                if (empty($errors)) {
                    $query = <<<SQL
                        UPDATE plugins
                        SET state = 'active'
                        WHERE id = '{$plugin_id}';
                        SQL;
                    pwg_query($query);
                } else {
                    $activity_details['result'] = 'error';
                }

                break;

            case 'deactivate':
                if (! isset($crt_db_plugin) || $crt_db_plugin['state'] != 'active') {
                    $activity_details['result'] = 'error';
                    break;
                }

                $query = <<<SQL
                    UPDATE plugins
                    SET state = 'inactive'
                    WHERE id = '{$plugin_id}';
                    SQL;
                pwg_query($query);

                $plugin_maintain->deactivate();

                if (isset($crt_db_plugin['version'])) {
                    $activity_details['version'] = $crt_db_plugin['version'];
                }

                break;

            case 'uninstall':
                if (! isset($crt_db_plugin)) {
                    $activity_details['result'] = 'error';
                    $activity_details['error'] = 'plugin not installed';
                    break;
                }

                if (isset($crt_db_plugin['version'])) {
                    $activity_details['version'] = $crt_db_plugin['version'];
                }

                if ($crt_db_plugin['state'] == 'active') {
                    $this->perform_action('deactivate', $plugin_id);
                }

                $query = <<<SQL
                    DELETE FROM plugins
                    WHERE id = '{$plugin_id}';
                    SQL;
                pwg_query($query);

                $plugin_maintain->uninstall();
                break;

            case 'restore':
                $this->perform_action('uninstall', $plugin_id);
                unset($this->db_plugins_by_id[$plugin_id]);
                $errors = $this->perform_action('activate', $plugin_id);
                break;

            case 'delete':
                if (! empty($crt_db_plugin)) {
                    if (isset($crt_db_plugin['version'])) {
                        $activity_details['db_version'] = $crt_db_plugin['version'];
                    }

                    $this->perform_action('uninstall', $plugin_id);
                }

                if (! isset($this->fs_plugins[$plugin_id])) {
                    break;
                }

                $activity_details['fs_version'] = $this->fs_plugins[$plugin_id]['version'];

                require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';
                deltree(PHPWG_PLUGINS_PATH . $plugin_id, PHPWG_PLUGINS_PATH . 'trash');
                break;
        }

        pwg_activity('system', ACTIVITY_SYSTEM_PLUGIN, $action, $activity_details);

        return $errors;
    }

    /**
     * Get plugins defined in the plugin directory
     */
    public function get_fs_plugins(): void
    {
        $dir = opendir(PHPWG_PLUGINS_PATH);
        while ($file = readdir($dir)) {
            if (($file !== '.' && $file !== '..') && preg_match('/^[a-zA-Z0-9-_]+$/', $file)) {
                $this->get_fs_plugin($file);
            }
        }

        closedir($dir);
    }

    /**
     * Load metadata of a plugin in `fs_plugins` array
     * @from 2.7
     */
    public function get_fs_plugin(
        string $plugin_id
    ): array|bool {
        $path = PHPWG_PLUGINS_PATH . $plugin_id;

        if (is_dir($path) && ! is_link($path) && file_exists($path . '/main.inc.php')
        ) {
            $plugin = [
                'name' => $plugin_id,
                'version' => '0',
                'uri' => '',
                'description' => '',
                'author' => '',
                'hasSettings' => false,
            ];
            $plg_data = file_get_contents($path . '/main.inc.php', false, null, 0, 2048);

            if (preg_match('|Plugin Name:\\s*(.+)|', $plg_data, $val)) {
                $plugin['name'] = trim($val[1]);
            }

            if (preg_match('|Version:\\s*([\\w.-]+)|', $plg_data, $val)) {
                $plugin['version'] = trim($val[1]);
            }

            if (preg_match('|Plugin URI:\\s*(https?:\\/\\/.+)|', $plg_data, $val)) {
                $plugin['uri'] = trim($val[1]);
            }

            if ($desc = load_language('description.txt', $path . '/', [
                'return' => true,
            ])) {
                $plugin['description'] = trim($desc);
            } elseif (preg_match('|Description:\\s*(.+)|', $plg_data, $val)) {
                $plugin['description'] = trim($val[1]);
            }

            if (preg_match('|Author:\\s*(.+)|', $plg_data, $val)) {
                $plugin['author'] = trim($val[1]);
            }

            if (preg_match('|Author URI:\\s*(https?:\\/\\/.+)|', $plg_data, $val)) {
                $plugin['author uri'] = trim($val[1]);
            }

            if (preg_match('/Has Settings:\\s*([Tt]rue|[Ww]ebmaster)/', $plg_data, $val)) {
                if (strtolower($val[1]) === 'webmaster') {
                    global $user;

                    if (isset($user) && $user['status'] == 'webmaster') {
                        $plugin['hasSettings'] = true;
                    }
                } else {
                    $plugin['hasSettings'] = true;
                }
            }

            if (isset($plugin['uri']) && ($plugin['uri'] !== '' && $plugin['uri'] !== '0') && strpos($plugin['uri'], 'extension_view.php?eid=')) {
                [, $extension] = explode('extension_view.php?eid=', $plugin['uri']);
                if (is_numeric($extension)) {
                    $plugin['extension'] = $extension;
                }
            }

            // IMPORTANT SECURITY!
            $plugin = array_map(htmlspecialchars(...), $plugin);
            $this->fs_plugins[$plugin_id] = $plugin;

            return $plugin;
        }

        return false;
    }

    /**
     * Sort fs_plugins
     */
    public function sort_fs_plugins(
        string $order = 'name'
    ): void {
        switch ($order) {
            case 'name':
                uasort($this->fs_plugins, name_compare(...));
                break;
            case 'status':
                $this->sort_plugins_by_state();
                break;
            case 'author':
                uasort($this->fs_plugins, $this->plugin_author_compare(...));
                break;
            case 'id':
                uksort($this->fs_plugins, strcasecmp(...));
                break;
        }
    }

    // Retrieve PEM versions
    // Beta test: return last version on PEM if the current version isn't known or else return the current and the last version
    public function get_versions_to_check(
        bool $beta_test = false,
        string $version = PHPWG_VERSION
    ): array {
        global $conf;

        $versions_to_check = [];
        $url = PEM_URL . '/api/get_version_list.php?category_id=' . $conf['pem_plugins_category'] . '&format=php';
        if (fetchRemote($url, $result) && ($pem_versions = unserialize($result))) {
            $i = 0;

            // If the actual version exists, put the PEM id in $versions_to_check
            while ($i < count($pem_versions) && count($versions_to_check) == 0) {
                if (get_branch_from_version($pem_versions[$i]['name']) === get_branch_from_version($version)) {
                    $versions_to_check[] = $pem_versions[$i]['id'];
                }

                $i++;
            }

            // If $beta_test is true, search the previous version
            if ($beta_test) {
                // If the actual version is not in PEM, put the latest PEM version
                if (count($versions_to_check) == 0) {
                    $versions_to_check[] = $pem_versions[0]['id'];
                } else { // Else search the next version in PEM
                    $has_found_previous_version = false;
                    while ($i < count($pem_versions) && ! $has_found_previous_version) {
                        if ($pem_versions[$i]['id'] != $versions_to_check[0]) {
                            $versions_to_check[] = $pem_versions[$i]['id'];
                            $has_found_previous_version = true;
                        }

                        $i++;
                    }
                }
            }

            // if (!preg_match('/^\d+\.\d+\.\d+$/', $version))
            // {
            //   $version = $pem_versions[0]['name'];
            // }
            // $branch = get_branch_from_version($version);
            // foreach ($pem_versions as $pem_version)
            // {
            //   if (strpos($pem_version['name'], $branch) === 0)
            //   {
            //     $versions_to_check[] = $pem_version['id'];
            //   }
            // }
        }

        return $versions_to_check;
    }

    /**
     * Retrieve PEM server data to $server_plugins
     * $beta_test parameter add plugins compatible with the previous version
     */
    public function get_server_plugins(
        bool $new = false,
        bool $beta_test = false
    ): bool {
        global $user, $conf;

        $versions_to_check = $this->get_versions_to_check($beta_test);
        if ($versions_to_check === []) {
            return true;
        }

        // Plugins to check
        $plugins_to_check = [];
        foreach ($this->fs_plugins as $fs_plugin) {
            if (isset($fs_plugin['extension'])) {
                $plugins_to_check[] = $fs_plugin['extension'];
            }
        }

        // Retrieve PEM plugins infos
        $url = PEM_URL . '/api/get_revision_list-next.php';
        $get_data = [
            'category_id' => $conf['pem_plugins_category'],
            'format' => 'php',
            'last_revision_only' => 'true',
            'version' => implode(',', $versions_to_check),
            'lang' => substr((string) $user['language'], 0, 2),
            'get_nb_downloads' => 'true',
        ];

        if ($plugins_to_check !== []) {
            if ($new) {
                $get_data['extension_exclude'] = implode(',', $plugins_to_check);
            } else {
                $get_data['extension_include'] = implode(',', $plugins_to_check);
            }
        }

        if (fetchRemote($url, $result, $get_data)) {
            $pem_plugins = is_serialized($result) ? unserialize($result) : null;
            if (! is_array($pem_plugins)) {
                return false;
            }

            foreach ($pem_plugins as $plugin) {
                $this->server_plugins[$plugin['extension_id']] = $plugin;
            }

            return true;
        }

        return false;
    }

    public function get_incompatible_plugins(
        bool $actualize = false
    ): mixed {
        if (isset($_SESSION['incompatible_plugins']) && ! $actualize && $_SESSION['incompatible_plugins']['~~expire~~'] > time()) {
            return $_SESSION['incompatible_plugins'];
        }

        $_SESSION['incompatible_plugins'] = [
            '~~expire~~' => time() + 300,
        ];

        $versions_to_check = $this->get_versions_to_check();
        if ($versions_to_check === []) {
            return false;
        }

        global $conf;

        // Plugins to check
        $plugins_to_check = [];
        foreach ($this->fs_plugins as $fs_plugin) {
            if (isset($fs_plugin['extension'])) {
                $plugins_to_check[] = $fs_plugin['extension'];
            }
        }

        // Retrieve PEM plugins infos
        $url = PEM_URL . '/api/get_revision_list.php';
        $get_data = [
            'category_id' => $conf['pem_plugins_category'],
            'format' => 'php',
            'version' => implode(',', $versions_to_check),
            'extension_include' => implode(',', $plugins_to_check),
        ];

        if (fetchRemote($url, $result, $get_data)) {
            $pem_plugins = is_serialized($result) ? unserialize($result) : null;
            if (! is_array($pem_plugins)) {
                return false;
            }

            $server_plugins = [];
            foreach ($pem_plugins as $plugin) {
                if (! isset($server_plugins[$plugin['extension_id']])) {
                    $server_plugins[$plugin['extension_id']] = [];
                }

                $server_plugins[$plugin['extension_id']][] = $plugin['revision_name'];
            }

            foreach ($this->fs_plugins as $plugin_id => $fs_plugin) {
                if (isset($fs_plugin['extension']) && ! in_array($plugin_id, $this->default_plugins) && $fs_plugin['version'] != 'auto' && (! isset($server_plugins[$fs_plugin['extension']]) || ! in_array($fs_plugin['version'], $server_plugins[$fs_plugin['extension']]))) {
                    $_SESSION['incompatible_plugins'][$plugin_id] = $fs_plugin['version'];
                }
            }

            return $_SESSION['incompatible_plugins'];
        }

        return false;
    }

    /**
     * Sort $server_plugins
     */
    public function sort_server_plugins(
        string $order = 'date'
    ): void {
        switch ($order) {
            case 'date':
                krsort($this->server_plugins);
                break;
            case 'revision':
                usort($this->server_plugins, $this->extension_revision_compare(...));
                break;
            case 'name':
                uasort($this->server_plugins, $this->extension_name_compare(...));
                break;
            case 'author':
                uasort($this->server_plugins, $this->extension_author_compare(...));
                break;
            case 'downloads':
                usort($this->server_plugins, $this->extension_downloads_compare(...));
                break;
        }
    }

    /**
     * Extract plugin files from archive
     * @param string $action - install or upgrade
     * @param string $revision - archive URL
     * @param string $dest - plugin id or extension id
     */
    public function extract_plugin_files(
        string $action,
        string $revision,
        string $dest,
        ?string &$plugin_id = null
    ): mixed {
        global $logger;

        if ($archive = tempnam(PHPWG_PLUGINS_PATH, 'zip')) {
            $url = PEM_URL . '/download.php';
            $get_data = [
                'rid' => $revision,
                'origin' => 'piwigo_' . $action,
            ];

            if (($handle = fopen($archive, 'wb')) && fetchRemote($url, $handle, $get_data)) {
                fclose($handle);
                $zip = new PclZip($archive);
                if ($list = $zip->listContent()) {
                    foreach ($list as $file) {
                        // we search main.inc.php in archive
                        if (basename((string) $file['filename']) === 'main.inc.php' && (! isset($main_filepath) || strlen((string) $file['filename']) < strlen((string) $main_filepath))) {
                            $main_filepath = $file['filename'];
                        }
                    }

                    $logger->debug(__FUNCTION__ . ', $main_filepath = ' . $main_filepath);

                    if (isset($main_filepath)) {
                        $root = dirname((string) $main_filepath); // main.inc.php path in archive
                        if ($action === 'upgrade') {
                            $plugin_id = $dest;
                        } else {
                            $plugin_id = ($root === '.' ? 'extension_' . $dest : basename($root));
                        }

                        $extract_path = PHPWG_PLUGINS_PATH . $plugin_id;
                        $logger->debug(__FUNCTION__ . ', $extract_path = ' . $extract_path);

                        if ($result = $zip->extract(
                            PCLZIP_OPT_PATH,
                            $extract_path,
                            PCLZIP_OPT_REMOVE_PATH,
                            $root,
                            PCLZIP_OPT_REPLACE_NEWER
                        )) {
                            foreach ($result as $file) {
                                if ($file['stored_filename'] == $main_filepath) {
                                    $status = $file['status'];
                                    break;
                                }
                            }

                            if (file_exists($extract_path . '/obsolete.list') && ($old_files = file($extract_path . '/obsolete.list', FILE_IGNORE_NEW_LINES)) && $old_files !== []) {
                                $old_files[] = 'obsolete.list';
                                $logger->debug(__FUNCTION__ . ', $old_files = {' . implode('},{', $old_files) . '}');

                                $extract_path_realpath = realpath($extract_path);

                                foreach ($old_files as $old_file) {
                                    $old_file = trim($old_file);
                                    $old_file = trim($old_file, '/'); // prevent path starting with a "/"

                                    if ($old_file === '' || $old_file === '0') { // empty here means the extension itself
                                        continue;
                                    }

                                    $path = $extract_path . '/' . $old_file;

                                    // make sure the obsolete file is withing the extension directory, prevent path traversal
                                    $realpath = realpath($path);
                                    if ($realpath === false || ! str_starts_with($realpath, (string) $extract_path_realpath)) {
                                        continue;
                                    }

                                    $logger->debug(__FUNCTION__ . ', to delete = ' . $path);

                                    if (is_file($path)) {
                                        unlink($path);
                                    } elseif (is_dir($path)) {
                                        deltree($path, PHPWG_PLUGINS_PATH . 'trash');
                                    }
                                }
                            }
                        } else {
                            $status = 'extract_error';
                        }
                    } else {
                        $status = 'archive_error';
                    }
                } else {
                    $status = 'archive_error';
                }
            } else {
                $status = 'dl_archive_error';
            }
        } else {
            $status = 'temp_path_error';
        }

        unlink($archive);
        return $status;
    }

    public function get_merged_extensions(
        string $version = PHPWG_VERSION
    ): array {
        $file = PHPWG_ROOT_PATH . 'install/obsolete_extensions.list';
        $merged_extensions = [];

        if (file_exists($file) && ($obsolete_ext = file($file, FILE_IGNORE_NEW_LINES)) && $obsolete_ext !== []) {
            foreach ($obsolete_ext as $ext) {
                if (preg_match('/^(\d+) ?: ?(.*?)$/', $ext, $matches)) {
                    $merged_extensions[$matches[1]] = $matches[2];
                }
            }
        }

        return $merged_extensions;
    }

    /**
     * Sort functions
     */
    public function extension_revision_compare(
        array $a,
        array $b
    ): int {
        if ($a['revision_date'] < $b['revision_date']) {
            return 1;
        }

        return -1;
    }

    public function extension_name_compare(
        array $a,
        array $b
    ): int {
        return strcmp(strtolower((string) $a['extension_name']), strtolower((string) $b['extension_name']));
    }

    public function extension_author_compare(
        array $a,
        array $b
    ): int {
        $r = strcasecmp((string) $a['author_name'], (string) $b['author_name']);
        if ($r == 0) {
            return $this->extension_name_compare($a, $b);
        }

        return $r;
    }

    public function plugin_author_compare(
        array $a,
        array $b
    ): int {
        $r = strcasecmp((string) $a['author'], (string) $b['author']);
        if ($r == 0) {
            return name_compare($a, $b);
        }

        return $r;
    }

    public function extension_downloads_compare(
        array $a,
        array $b
    ): int {
        if ($a['extension_nb_downloads'] < $b['extension_nb_downloads']) {
            return 1;
        }

        return -1;
    }

    public function sort_plugins_by_state(): void
    {
        uasort($this->fs_plugins, name_compare(...));

        $active_plugins = [];
        $inactive_plugins = [];
        $not_installed = [];

        foreach ($this->fs_plugins as $plugin_id => $plugin) {
            if (isset($this->db_plugins_by_id[$plugin_id])) {
                $this->db_plugins_by_id[$plugin_id]['state'] == 'active' ?
                  $active_plugins[$plugin_id] = $plugin : $inactive_plugins[$plugin_id] = $plugin;
            } else {
                $not_installed[$plugin_id] = $plugin;
            }
        }

        $this->fs_plugins = $active_plugins + $inactive_plugins + $not_installed;
    }

    /**
     * Returns the maintain class of a plugin
     * or build a new class with the procedural methods
     */
    private function build_maintain_class(
        string $plugin_id
    ): DummyPlugin_maintain|PluginMaintain {
        $file_to_include = PHPWG_PLUGINS_PATH . $plugin_id . '/maintain';
        $classname = $plugin_id . '_maintain';

        // piwigo-videojs and piwigo-openstreetmap unfortunately have a "-" in their folder
        // name (=plugin_id) and a class name can't have a "-". So we have to replace with a "_"
        $classname = str_replace('-', '_', $classname);

        // 2.7 pattern (OO only)
        if (file_exists($file_to_include . '.class.php')) {
            require_once $file_to_include . '.class.php';
            return new $classname($plugin_id);
        }

        // before 2.7 pattern (OO or procedural)
        if (file_exists($file_to_include . '.inc.php')) {
            require_once $file_to_include . '.inc.php';

            if (class_exists($classname)) {
                return new $classname($plugin_id);
            }
        }

        return new DummyPlugin_maintain($plugin_id);
    }
}
