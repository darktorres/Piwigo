<?php

namespace Piwigo\inc;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

require_once __DIR__ . '/template.inc.php';

/**
 * Manage a list of required scripts for a page, by optimizing their loading location (head, footer, async)
 * and later on by combining them in a unique file respecting at the same time dependencies.
 */
class ScriptLoader
{
    /**
     * @var string[]
     */
    public $inline_scripts;

    /**
     * @var Script[]
     */
    private $registered_scripts;

    /**
     * @var bool
     */
    private $did_head;

    /**
     * @var bool
     */
    private $head_done_scripts;

    /**
     * @var bool
     */
    private $did_footer;

    private static $known_paths = [
        'core.scripts' => 'themes/default/js/scripts.js',
        'jquery' => 'themes/default/js/jquery.min.js',
        'jquery.ui' => 'themes/default/js/ui/minified/jquery.ui.core.min.js',
        'jquery.ui.effect' => 'themes/default/js/ui/minified/jquery.ui.effect.min.js',
    ];

    private static $ui_core_dependencies = [
        'jquery.ui.widget' => ['jquery'],
        'jquery.ui.position' => ['jquery'],
        'jquery.ui.mouse' => ['jquery', 'jquery.ui', 'jquery.ui.widget'],
    ];

    public function __construct()
    {
        $this->clear();
    }

    public function clear()
    {
        $this->registered_scripts = [];
        $this->inline_scripts = [];
        $this->head_done_scripts = [];
        $this->did_head = false;
        $this->did_footer = false;
    }

    /**
     * @return bool
     */
    public function did_head()
    {
        return $this->did_head;
    }

    /**
     * @return Script[]
     */
    public function get_all()
    {
        return $this->registered_scripts;
    }

    /**
     * @param string $code
     * @param string[] $require
     */
    public function add_inline($code, $require)
    {
        if ($this->did_footer) {
            trigger_error(
                'Attempt to add inline script but the footer has been written',
                E_USER_WARNING
            );
        }

        if (! empty($require)) {
            foreach ($require as $id) {
                if (! isset($this->registered_scripts[$id]) && ! $this->load_known_required_script($id, 1)) {
                    fatal_error('inline script not found require ' . $id);
                }

                $s = $this->registered_scripts[$id];
                if ($s->load_mode == 2) {
                    $s->load_mode = 1;
                } // until now the implementation does not allow executing inline script depending on another async script
            }
        }

        $this->inline_scripts[] = $code;
    }

    /**
     * @param string $id
     * @param int $load_mode
     * @param string[] $require
     * @param string $path
     * @param string $version
     */
    public function add(
        $id,
        $load_mode,
        $require,
        $path,
        $version = 0,
        $is_template = false
    ) {
        if ($this->did_head && $load_mode == 0) {
            trigger_error(sprintf('Attempt to add script %s but the head has been written', $id), E_USER_WARNING);
        } elseif ($this->did_footer) {
            trigger_error(sprintf('Attempt to add script %s but the footer has been written', $id), E_USER_WARNING);
        }

        if (! isset($this->registered_scripts[$id])) {
            $script = new Script($load_mode, $id, $path, $version, $require);
            $script->is_template = $is_template;
            $this->fill_well_known($id, $script);
            $this->registered_scripts[$id] = $script;

            // Load or modify all UI core files
            if ($id == 'jquery.ui' && $script->path == self::$known_paths['jquery.ui']) {
                foreach (self::$ui_core_dependencies as $script_id => $required_ids) {
                    $this->add($script_id, $load_mode, $required_ids, null, $version);
                }
            }

            // Try to load undefined required script
            foreach ($script->precedents as $script_id) {
                if (! isset($this->registered_scripts[$script_id])) {
                    $this->load_known_required_script($script_id, $load_mode);
                }
            }
        } else {
            $script = $this->registered_scripts[$id];
            if (count($require)) {
                $script->precedents = array_unique(array_merge($script->precedents, $require));
            }

            $script->set_path($path);
            if ($version && version_compare($script->version, $version) < 0) {
                $script->version = $version;
            }

            if ($load_mode < $script->load_mode) {
                $script->load_mode = $load_mode;
            }
        }
    }

    /**
     * Returns combined scripts loaded in header.
     *
     * @return Combinable[]
     */
    public function get_head_scripts()
    {
        $this->check_load_dep($this->registered_scripts);
        foreach (array_keys($this->registered_scripts) as $id) {
            $this->compute_script_topological_order($id);
        }

        uasort($this->registered_scripts, $this->cmp_by_mode_and_order(...));

        foreach ($this->registered_scripts as $id => $script) {
            if ($script->load_mode > 0) {
                break;
            }

            if (! empty($script->path)) {
                $this->head_done_scripts[$id] = $script;
            } else {
                trigger_error(sprintf('Script %s has an undefined path', $id), E_USER_WARNING);
            }
        }

        $this->did_head = true;
        return $this->do_combine($this->head_done_scripts, 0);
    }

    /**
     * Returns combined scripts loaded in footer.
     *
     * @return Combinable[]
     */
    public function get_footer_scripts()
    {
        if (! $this->did_head) {
            $this->check_load_dep($this->registered_scripts);
        }

        $this->did_footer = true;
        $todo = [];
        foreach ($this->registered_scripts as $id => $script) {
            if (! isset($this->head_done_scripts[$id])) {
                $todo[$id] = $script;
            }
        }

        foreach (array_keys($todo) as $id) {
            $this->compute_script_topological_order($id);
        }

        uasort($todo, $this->cmp_by_mode_and_order(...));

        $result = [[], []];
        foreach ($todo as $id => $script) {
            if (! is_string($script->load_mode)) {
                $result[$script->load_mode - 1][$id] = $script;
            }
        }

        return [$this->do_combine($result[0], 1), $this->do_combine($result[1], 2)];
    }

    /**
     * @param Script[] $scripts
     * @param int $load_mode
     * @return Combinable[]
     */
    private function do_combine(
        $scripts,
        $load_mode
    ) {
        $combiner = new FileCombiner('js', $scripts);
        return $combiner->combine();
    }

    /**
     * Checks dependencies among Scripts.
     * Checks that if B depends on A, then B->load_mode >= A->load_mode in order to respect execution order.
     *
     * @param Script[] $scripts
     */
    private function check_load_dep(
        $scripts
    ) {
        global $conf;
        do {
            $changed = false;
            foreach ($scripts as $id => $script) {
                $load = $script->load_mode;
                foreach ($script->precedents as $precedent) {
                    if (! isset($scripts[$precedent])) {
                        continue;
                    }

                    if ($scripts[$precedent]->load_mode > $load) {
                        $scripts[$precedent]->load_mode = $load;
                        $changed = true;
                    }

                    if ($load == 2 && $scripts[$precedent]->load_mode == 2 && ($scripts[$precedent]->is_remote() || ! $conf['template_combine_files'])) {// we are async -> a predecessor cannot be async unlesss it can be merged; otherwise script execution order is not guaranteed
                        $scripts[$precedent]->load_mode = 1;
                        $changed = true;
                    }
                }
            }
        } while ($changed);
    }

    /**
     * Fill a script dependancies with the known jQuery UI scripts.
     *
     * @param string $id in FileCombiner::$known_paths
     * @param Script $script
     */
    private function fill_well_known(
        $id,
        $script
    ) {
        if (empty($script->path) && isset(self::$known_paths[$id])) {
            $script->path = self::$known_paths[$id];
        }

        if (str_starts_with($id, 'jquery.')) {
            $required_ids = ['jquery'];

            if (str_starts_with($id, 'jquery.ui.effect-')) {
                $required_ids = ['jquery', 'jquery.ui.effect'];

                if (empty($script->path)) {
                    $script->path = dirname((string) self::$known_paths['jquery.ui.effect']) . sprintf(
                        '/%s.min.js',
                        $id
                    );
                }
            } elseif (str_starts_with($id, 'jquery.ui.')) {
                if (! isset(self::$ui_core_dependencies[$id])) {
                    $required_ids = array_merge(['jquery', 'jquery.ui'], array_keys(self::$ui_core_dependencies));
                }

                if (empty($script->path)) {
                    $script->path = dirname((string) self::$known_paths['jquery.ui']) . sprintf('/%s.min.js', $id);
                }
            }

            foreach ($required_ids as $required_id) {
                if (! in_array($required_id, $script->precedents)) {
                    $script->precedents[] = $required_id;
                }
            }
        }
    }

    /**
     * Add a known jQuery UI script to loaded scripts.
     *
     * @param string $id in FileCombiner::$known_paths
     * @param int $load_mode
     * @return bool
     */
    private function load_known_required_script(
        $id,
        $load_mode
    ) {
        if (isset(self::$known_paths[$id]) || str_starts_with($id, 'jquery.ui.')) {
            $this->add($id, $load_mode, [], null);
            return true;
        }

        return false;
    }

    /**
     * Compute script order depending on dependencies.
     * Assigned to $script->extra['order'].
     *
     * @param string $script_id
     * @param int $recursion_limiter
     * @return int
     */
    private function compute_script_topological_order(
        $script_id,
        $recursion_limiter = 0
    ) {
        if (! isset($this->registered_scripts[$script_id])) {
            trigger_error(sprintf('Undefined script %s is required by someone', $script_id), E_USER_WARNING);
            return 0;
        }

        if ($recursion_limiter >= 5) {
            fatal_error('combined script circular dependency');
        }

        $script = $this->registered_scripts[$script_id];
        if (isset($script->extra['order'])) {
            return $script->extra['order'];
        }

        if (count($script->precedents) == 0) {
            return $script->extra['order'] = 0;
        }

        $max = 0;
        foreach ($script->precedents as $precedent) {
            $max = max($max, $this->compute_script_topological_order($precedent, $recursion_limiter + 1));
        }

        ++$max;
        return $script->extra['order'] = $max;
    }

    /**
     * Callback for scripts sorter.
     */
    private function cmp_by_mode_and_order($s1, $s2)
    {
        $ret = intval($s1->load_mode) - intval($s2->load_mode);
        if ($ret !== 0) {
            return $ret;
        }

        $ret = $s1->extra['order'] - $s2->extra['order'];
        if ($ret) {
            return $ret;
        }

        if ($s1->extra['order'] == 0 && ($s1->is_remote() xor $s2->is_remote())) {
            return $s1->is_remote() ? -1 : 1;
        }

        return strcmp($s1->id, $s2->id);
    }
}
