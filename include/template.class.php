<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

function customErrorHandler(
    int $errno,
    string $errstr,
    string $errfile,
    int $errline
): bool {
    // Define error types and corresponding prefixes
    $error_types = [
        E_ERROR => 'error',
        E_WARNING => 'warn',
        E_PARSE => 'error',
        E_NOTICE => 'info',
        E_CORE_ERROR => 'error',
        E_CORE_WARNING => 'warn',
        E_COMPILE_ERROR => 'error',
        E_COMPILE_WARNING => 'warn',
        E_USER_ERROR => 'error',
        E_USER_WARNING => 'warn',
        E_USER_NOTICE => 'info',
        E_STRICT => 'info',
        E_RECOVERABLE_ERROR => 'error',
        E_DEPRECATED => 'warn',
        E_USER_DEPRECATED => 'warn',
    ];

    // Determine the error type
    $error_type = $error_types[$errno] ?? 'Unknown Error';

    // Construct the error message
    $errorMessage = "PHP: {$errstr} in {$errfile} on line {$errline}";

    // Store in global var
    global $custom_error_log;
    $custom_error_log .= '<script>console.' . $error_type . '(' . json_encode($errorMessage) . ');</script>';

    // Ensure PHP's internal error handler is not bypassed
    return false;
}

set_error_handler(customErrorHandler(...));

/** default rank for buttons */
define('BUTTONS_RANK_NEUTRAL', 50);

/**
 * This a wrapper arround Smarty classes proving various custom mechanisms for templates.
 */
class Template
{
    /**
     * @const string
     */
    public const string COMBINED_SCRIPTS_TAG = '<!-- COMBINED_SCRIPTS -->';

    /**
     * @const string
     */
    public const string COMBINED_CSS_TAG = '<!-- COMBINED_CSS -->';

    public Smarty $smarty;

    public string $output = '';

    /**
     * @var string[] - Hash of filenames for each template handle.
     */
    public array $files = [];

    /**
     * @var string[] - Template extents filenames for each template handle.
     */
    public array $extents = [];

    /**
     * Templates prefilter from external sources (plugins)
     */
    public array $external_filters = [];

    /**
     * Content to add before </head> tag
     */
    public array $html_head_elements = [];

    public ScriptLoader $scriptLoader;

    public CssLoader $cssLoader;

    /**
     * Runtime buttons on picture page
     */
    public array $picture_buttons = [];

    /**
     * Runtime buttons on index page
     */
    public array $index_buttons = [];

    /**
     * Runtime CSS rules
     */
    private string $html_style = '';

    public function __construct(
        string $root = '.',
        string $theme = '',
        string $path = 'template'
    ) {
        global $conf, $lang_info;

        SmartyException::$escape = false;

        $this->scriptLoader = new ScriptLoader();
        $this->cssLoader = new CssLoader();
        $this->smarty = new Smarty();
        $this->smarty->debugging = $conf['debug_template'];
        if (! $this->smarty->debugging) {
            $this->smarty->error_reporting = error_reporting() & ~E_NOTICE;
        }

        $this->smarty->compile_check = $conf['template_compile_check'];
        $this->smarty->force_compile = $conf['template_force_compile'];

        if (! isset($conf['data_dir_checked'])) {
            $dir = PHPWG_ROOT_PATH . $conf['data_location'];
            mkgetdir($dir, MKGETDIR_DEFAULT & ~MKGETDIR_DIE_ON_ERROR);
            if (! is_writable($dir)) {
                load_language('admin.lang');
                fatal_error(
                    l10n(
                        'Give write access (chmod 777) to "%s" directory at the root of your Piwigo installation',
                        $conf['data_location']
                    ),
                    l10n('an error happened'),
                    false // show trace
                );
            }

            if (function_exists('pwg_query')) {
                conf_update_param('data_dir_checked', 1);
            }
        }

        $compile_dir = PHPWG_ROOT_PATH . $conf['data_location'] . 'templates_c';
        mkgetdir($compile_dir);

        $this->smarty->setCompileDir($compile_dir);

        $this->smarty->assign('pwg', new PwgTemplateAdapter());
        $this->smarty->registerPlugin('modifiercompiler', 'translate', self::modcompiler_translate(...));
        $this->smarty->registerPlugin('modifiercompiler', 'translate_dec', self::modcompiler_translate_dec(...));
        $this->smarty->registerPlugin('modifier', 'sprintf', sprintf(...));
        $this->smarty->registerPlugin('modifier', 'urlencode', urlencode(...));
        $this->smarty->registerPlugin('modifier', 'intval', intval(...));
        $this->smarty->registerPlugin('modifier', 'file_exists', file_exists(...));
        $this->smarty->registerPlugin('modifier', 'constant', constant(...));
        $this->smarty->registerPlugin('modifier', 'json_encode', json_encode(...));
        $this->smarty->registerPlugin('modifier', 'htmlspecialchars', htmlspecialchars(...));
        $this->smarty->registerPlugin('modifier', 'implode', implode(...));
        $this->smarty->registerPlugin('modifier', 'stripslashes', stripslashes(...));
        $this->smarty->registerPlugin('modifier', 'in_array', in_array(...));
        $this->smarty->registerPlugin('modifier', 'ucfirst', ucfirst(...));
        $this->smarty->registerPlugin('modifier', 'strstr', strstr(...));
        $this->smarty->registerPlugin('modifier', 'stristr', stristr(...));
        $this->smarty->registerPlugin('modifier', 'trim', trim(...));
        $this->smarty->registerPlugin('modifier', 'md5', md5(...));
        $this->smarty->registerPlugin('modifier', 'strtolower', strtolower(...));
        $this->smarty->registerPlugin('modifier', 'str_ireplace', str_ireplace(...));
        $this->smarty->registerPlugin('modifier', 'explode', self::mod_explode(...));
        $this->smarty->registerPlugin('modifier', 'ternary', self::mod_ternary(...));
        $this->smarty->registerPlugin('modifier', 'get_extent', $this->get_extent(...));
        $this->smarty->registerPlugin('modifier', 'count', count(...));
        $this->smarty->registerPlugin('modifier', 'strpos', strpos(...));
        $this->smarty->registerPlugin('modifier', 'is_admin', is_admin(...));
        $this->smarty->registerPlugin('block', 'html_head', $this->block_html_head(...));
        $this->smarty->registerPlugin('block', 'html_style', $this->block_html_style(...));
        $this->smarty->registerPlugin('function', 'combine_script', $this->func_combine_script(...));
        $this->smarty->registerPlugin('function', 'get_combined_scripts', $this->func_get_combined_scripts(...));
        $this->smarty->registerPlugin('function', 'combine_css', $this->func_combine_css(...));
        $this->smarty->registerPlugin('function', 'define_derivative', $this->func_define_derivative(...));
        $this->smarty->registerPlugin('compiler', 'get_combined_css', $this->func_get_combined_css(...));
        $this->smarty->registerPlugin('block', 'footer_script', $this->block_footer_script(...));
        $this->smarty->registerFilter('pre', self::prefilter_white_space(...));
        if ($conf['compiled_template_cache_language']) {
            $this->smarty->registerFilter('post', self::postfilter_language(...));
        }

        $this->smarty->setTemplateDir([]);
        if ($theme !== '' && $theme !== '0') {
            $this->set_theme($root, $theme, $path);
            if (! defined('IN_ADMIN')) {
                $this->set_prefilter('header', self::prefilter_local_css(...));
            }
        } else {
            $this->set_template_dir($root);
        }

        if (isset($lang_info['code']) && ! isset($lang_info['jquery_code'])) {
            $lang_info['jquery_code'] = $lang_info['code'];
        }

        if (isset($lang_info['jquery_code']) && ! isset($lang_info['plupload_code'])) {
            $lang_info['plupload_code'] = str_replace('-', '_', $lang_info['jquery_code']);
        }

        $this->smarty->assign('lang_info', $lang_info);

        if (! defined('IN_ADMIN') && isset($conf['extents_for_templates'])) {
            $tpl_extents = unserialize($conf['extents_for_templates']);
            $this->set_extents($tpl_extents, './template-extension/', true, $theme);
        }
    }

    /**
     * Loads theme's parameters.
     */
    public function set_theme(
        string $root,
        string $theme,
        string $path,
        bool $load_css = true,
        bool $load_local_head = true,
        string $colorscheme = 'dark'
    ): void {
        $this->set_template_dir($root . '/' . $theme . '/' . $path);

        $themeconf = $this->load_themeconf($root . '/' . $theme);

        if (isset($themeconf['parent']) && $themeconf['parent'] != $theme) {
            $this->set_theme(
                $root,
                $themeconf['parent'],
                $path,
                $themeconf['load_parent_css'] ?? $load_css,
                $themeconf['load_parent_local_head'] ?? $load_local_head
            );
        }

        $tpl_var = [
            'id' => $theme,
            'load_css' => $load_css,
        ];
        if (! empty($themeconf['local_head']) && $load_local_head) {
            $tpl_var['local_head'] = realpath($root . '/' . $theme . '/' . $themeconf['local_head']);
        }

        $themeconf['id'] = $theme;

        if (! isset($themeconf['colorscheme'])) {
            $themeconf['colorscheme'] = $colorscheme;
        }

        $this->smarty->append('themes', $tpl_var);
        $this->smarty->append('themeconf', $themeconf, true);
    }

    /**
     * Adds template directory for this Template object.
     * Also set compile id if not exists.
     */
    public function set_template_dir(
        string $dir
    ): void {
        $this->smarty->addTemplateDir($dir);

        if ($this->smarty->compile_id === null) {
            $compile_id = '1';
            $compile_id .= ($real_dir = realpath($dir)) === false ? $dir : $real_dir;
            $this->smarty->compile_id = base_convert(hash('crc32b', $compile_id), 16, 36);
        }
    }

    /**
     * Gets the template root directory for this Template object.
     *
     * @return string
     */
    public function get_template_dir(): array|string
    {
        return $this->smarty->getTemplateDir();
    }

    /**
     * Deletes all compiled templates.
     */
    public function delete_compiled_templates(): void
    {
        $save_compile_id = $this->smarty->compile_id;
        $this->smarty->compile_id = null;
        $this->smarty->clearCompiledTemplate();
        $this->smarty->compile_id = $save_compile_id;
        file_put_contents($this->smarty->getCompileDir() . '/index.htm', 'Not allowed!');
    }

    /**
     * Returns theme's parameter.
     */
    public function get_themeconf(
        string $val
    ): mixed {
        $tc = $this->smarty->getTemplateVars('themeconf');
        return $tc[$val] ?? '';
    }

    /**
     * Sets the template filename for handle.
     */
    public function set_filename(
        string $handle,
        string $filename
    ): bool {
        return $this->set_filenames([
            $handle => $filename,
        ]);
    }

    /**
     * Sets the template filenames for handles.
     *
     * @param string[] $filename_array hashmap of handle=>filename
     * @return true
     */
    public function set_filenames(
        array $filename_array
    ): bool {
        if (! is_array($filename_array)) {
            return false;
        }

        reset($filename_array);
        foreach ($filename_array as $handle => $filename) {
            if ($filename === null) {
                unset($this->files[$handle]);
            } else {
                $this->files[$handle] = $this->get_extent($filename, $handle);
            }
        }

        return true;
    }

    /**
     * Sets template extention filename for handles.
     */
    public function set_extent(
        string $filename,
        mixed $param,
        string $dir = '',
        bool $overwrite = true,
        string $theme = 'N/A'
    ): bool {
        return $this->set_extents([
            $filename => $param,
        ], $dir, $overwrite);
    }

    /**
     * Sets template extentions filenames for handles.
     *
     * @param string[] $filename_array hashmap of handle=>filename
     */
    public function set_extents(
        array $filename_array,
        string $dir = '',
        bool $overwrite = true,
        string $theme = 'N/A'
    ): bool {
        if (! is_array($filename_array)) {
            return false;
        }

        foreach ($filename_array as $filename => $value) {
            if (is_array($value)) {
                $handle = $value[0];
                $param = $value[1];
                $thm = $value[2];
            } elseif (is_string($value)) {
                $handle = $value;
                $param = 'N/A';
                $thm = 'N/A';
            } else {
                return false;
            }

            if ((stripos(implode('', array_keys($_GET)), '/' . $param) !== false || $param === 'N/A') && ($thm === $theme || $thm === 'N/A') && (! isset($this->extents[$handle]) || $overwrite) && file_exists($dir . $filename)) {
                $this->extents[$handle] = realpath($dir . $filename);
            }
        }

        return true;
    }

    /**
     * Returns template extension if exists.
     *
     * @param string $filename should be empty!
     * @return string
     */
    public function get_extent(
        string $filename = '',
        string $handle = ''
    ): bool|string {
        if (isset($this->extents[$handle])) {
            $filename = $this->extents[$handle];
        }

        return $filename;
    }

    /**
     * Assigns a template variable.
     * @see http://www.smarty.net/manual/en/api.assign.php
     *
     * @param string|array $tpl_var can be a var name or a hashmap of variables
     *    (in this case, do not use the _$value_ parameter)
     */
    public function assign(
        string|array $tpl_var,
        mixed $value = null
    ): void {
        $this->smarty->assign($tpl_var, $value);
    }

    /**
     * Defines _$varname_ as the compiled result of _$handle_.
     * This can be used to effectively include a template in another template.
     * This is equivalent to assign($varname, $this->parse($handle, true)).
     *
     * @return true
     */
    public function assign_var_from_handle(
        string $varname,
        string $handle
    ): bool {
        $this->assign($varname, $this->parse($handle, true));
        return true;
    }

    /**
     * Appends a new value in a template array variable, the variable is created if needed.
     * @see http://www.smarty.net/manual/en/api.append.php
     */
    public function append(
        string $tpl_var,
        mixed $value = null,
        bool $merge = false
    ): void {
        $this->smarty->append($tpl_var, $value, $merge);
    }

    /**
     * Performs a string concatenation.
     */
    public function concat(
        string $tpl_var,
        string $value
    ): void {
        $this->assign(
            $tpl_var,
            $this->smarty->getTemplateVars($tpl_var) . $value
        );
    }

    /**
     * Removes an assigned template variable.
     * @see http://www.smarty.net/manual/en/api.clear_assign.php
     */
    public function clear_assign(
        array|string $tpl_var
    ): void {
        $this->smarty->clearAssign($tpl_var);
    }

    /**
     * Returns an assigned template variable.
     * @see http://www.smarty.net/manual/en/api.get_template_vars.php
     */
    public function get_template_vars(
        string $tpl_var = null
    ): mixed {
        return $this->smarty->getTemplateVars($tpl_var);
    }

    /**
     * Loads the template file of the handle, compiles it and appends the result to the output
     * (or returns it if _$return_ is true).
     */
    public function parse(
        string $handle,
        bool $return = false
    ): string|null {
        if (! isset($this->files[$handle])) {
            fatal_error("Template->parse(): Couldn't load template file for handle {$handle}");
        }

        $this->smarty->assign('ROOT_URL', get_root_url());

        $save_compile_id = $this->smarty->compile_id;
        $this->load_external_filters($handle);

        global $conf, $lang_info;
        if ($conf['compiled_template_cache_language'] && isset($lang_info['code'])) {
            $this->smarty->compile_id .= '_' . $lang_info['code'];
        }

        $v = $this->smarty->fetch($this->files[$handle]);

        $this->smarty->compile_id = $save_compile_id;
        $this->unload_external_filters($handle);

        if ($return) {
            return $v;
        }

        $this->output .= $v;

        return null;
    }

    /**
     * Loads the template file of the handle, compiles it and appends the result to the output,
     * then sends the output to the browser.
     */
    public function pparse(
        string $handle
    ): void {
        $this->parse($handle, false);
        $this->flush();
    }

    /**
     * Load and compile JS & CSS into the template and sends the output to the browser.
     */
    public function flush(): void
    {
        if (! $this->scriptLoader->did_head()) {
            $pos = strpos($this->output, self::COMBINED_SCRIPTS_TAG);
            if ($pos !== false) {
                $scripts = $this->scriptLoader->get_head_scripts();
                $content = [];
                foreach ($scripts as $script) {
                    $content[] =
                        '<script type="text/javascript" src="'
                        . $this->make_script_src($script)
                        . '"></script>';
                }

                $this->output = substr_replace($this->output, implode("\n", $content), $pos, strlen(self::COMBINED_SCRIPTS_TAG));
            } //else maybe error or warning ?
        }

        $css = $this->cssLoader->get_css();

        $content = [];
        foreach ($css as $combi) {
            $href = embellish_url(get_root_url() . $combi->path);
            if ($combi->version !== false) {
                $href .= '?v' . ($combi->version ?: PHPWG_VERSION);
            }

            // trigger the event for eventual use of a cdn
            $href = trigger_change('combined_css', $href, $combi);
            $content[] = '<link rel="stylesheet" type="text/css" href="' . $href . '">';
        }

        $this->output = str_replace(
            self::COMBINED_CSS_TAG,
            implode("\n", $content),
            $this->output
        );
        $this->cssLoader->clear();

        if (count($this->html_head_elements) || strlen($this->html_style)) {
            $search = "\n</head>";
            $pos = strpos($this->output, $search);
            if ($pos !== false) {
                $rep = "\n" . implode("\n", $this->html_head_elements);
                if (strlen($this->html_style) !== 0) {
                    $rep .= '<style type="text/css">' . $this->html_style . '</style>';
                }

                $this->output = substr_replace($this->output, $rep, $pos, 0);
            }

            //else maybe error or warning ?
            $this->html_head_elements = [];
            $this->html_style = '';
        }

        echo $this->output;
        $this->output = '';

        global $custom_error_log;
        echo $custom_error_log;
    }

    /**
     * Same as flush() but with optional debugging.
     * @see Template::flush()
     */
    public function p(): void
    {
        $this->flush();

        if ($this->smarty->debugging) {
            global $t2;
            $this->smarty->assign(
                [
                    'AAAA_DEBUG_TOTAL_TIME__' => get_elapsed_time($t2, get_moment()),
                ]
            );
            $this->smarty->display(__DIR__ . '/../vendor/smarty/smarty/libs/debug.tpl');
        }
    }

    /**
     * Eval a temp string to retrieve the original PHP value.
     */
    public static function get_php_str_val(
        string $str
    ): mixed {
        if (is_string($str) && strlen($str) > 1 && (($str[0] === "'" && $str[strlen($str) - 1] === "'")
          || ($str[0] === '"' && $str[strlen($str) - 1] === '"'))) {
            eval('$tmp=' . $str . ';');
            return $tmp;
        }

        return null;
    }

    /**
     * "translate" variable modifier.
     * Usage :
     *    - {'Comment'|translate}
     *    - {'%d comments'|translate:$count}
     * @see l10n()
     */
    public static function modcompiler_translate(
        array $params
    ): string {
        global $conf, $lang;

        switch (count($params)) {
            case 1:
                if ($conf['compiled_template_cache_language']
                  && ($key = self::get_php_str_val($params[0])) !== null
                  && isset($lang[$key])
                ) {
                    return var_export($lang[$key], true);
                }

                return 'l10n(' . $params[0] . ')';

            default:
                if ($conf['compiled_template_cache_language']) {
                    $ret = 'sprintf(';
                    $ret .= self::modcompiler_translate([$params[0]]);
                    $ret .= ',' . implode(',', array_slice($params, 1));
                    return $ret . ')';
                }

                return 'l10n(' . $params[0] . ',' . implode(',', array_slice($params, 1)) . ')';
        }
    }

    /**
     * "translate_dec" variable modifier.
     * Usage :
     *    - {$count|translate_dec:'%d comment':'%d comments'}
     * @see l10n_dec()
     */
    public static function modcompiler_translate_dec(
        array $params
    ): string {
        global $conf, $lang, $lang_info;
        if ($conf['compiled_template_cache_language']) {
            $ret = 'sprintf(';
            if ($lang_info['zero_plural']) {
                $ret .= '($tmp=(' . $params[0] . '))>1||$tmp==0';
            } else {
                $ret .= '($tmp=(' . $params[0] . '))>1';
            }

            $ret .= '?';
            $ret .= self::modcompiler_translate([$params[2]]);
            $ret .= ':';
            $ret .= self::modcompiler_translate([$params[1]]);
            $ret .= ',$tmp';
            return $ret . ')';
        }

        return 'l10n_dec(' . $params[1] . ',' . $params[2] . ',' . $params[0] . ')';
    }

    /**
     * "explode" variable modifier.
     * Usage :
     *    - {assign var=valueExploded value=$value|explode:','}
     *
     * @return array
     */
    public static function mod_explode(
        string $text,
        string $delimiter = ','
    ): array|bool {
        return explode($delimiter, $text);
    }

    /**
     * ternary variable modifier.
     * Usage :
     *    - {$variable|ternary:'yes':'no'}
     */
    public static function mod_ternary(
        mixed $param,
        mixed $true,
        mixed $false
    ): mixed {
        return $param ? $true : $false;
    }

    /**
     * The "html_head" block allows to add content just before
     * </head> element in the output after the head has been parsed.
     *
     * @param array $params (unused)
     */
    public function block_html_head(
        array $params,
        string|null $content
    ): void {
        $content = isset($content) ? trim($content) : '';
        if ($content !== '' && $content !== '0') { // second call
            $this->html_head_elements[] = $content;
        }
    }

    /**
     * The "html_style" block allows to add CSS juste before
     * </head> element in the output after the head has been parsed.
     *
     * @param array|null $params (unused)
     */
    public function block_html_style(
        array|null $params,
        string|null $content
    ): void {
        $content = isset($content) ? trim($content) : '';
        if ($content !== '' && $content !== '0') { // second call
            $this->html_style .= "\n" . $content;
        }
    }

    /**
     * The "define_derivative" function allows to define derivative from tpl file.
     * It assigns a DerivativeParams object to _name_ template variable.
     *
     * @param array $params
     *    - name (required)
     *    - type (optional)
     *    - width (required if type is empty)
     *    - height (required if type is empty)
     *    - crop (optional, used if type is empty)
     *    - min_height (optional, used with crop)
     *    - min_height (optional, used with crop)
     */
    public function func_define_derivative(
        array $params,
        Smarty_Internal_Template $smarty
    ): void {
        if (empty($params['name'])) {
            fatal_error('define_derivative missing name');
        }

        if (isset($params['type'])) {
            $derivative = ImageStdParams::get_by_type($params['type']);
            $smarty->assign($params['name'], $derivative);
            return;
        }

        if (empty($params['width'])) {
            fatal_error('define_derivative missing width');
        }

        if (empty($params['height'])) {
            fatal_error('define_derivative missing height');
        }

        $w = intval($params['width']);
        $h = intval($params['height']);
        $crop = 0;
        $minw = null;
        $minh = null;

        if (isset($params['crop'])) {
            if (is_bool($params['crop'])) {
                $crop = $params['crop'] ? 1 : 0;
            } else {
                $crop = round($params['crop'] / 100, 2);
            }

            if ($crop) {
                $minw = empty($params['min_width']) ? $w : intval($params['min_width']);
                if ($minw > $w) {
                    fatal_error('define_derivative invalid min_width');
                }

                $minh = empty($params['min_height']) ? $h : intval($params['min_height']);
                if ($minh > $h) {
                    fatal_error('define_derivative invalid min_height');
                }
            }
        }

        $smarty->assign($params['name'], ImageStdParams::get_custom($w, $h, $crop, $minw, $minh));
    }

    /**
     * The "combine_script" functions allows inclusion of a javascript file in the current page.
     * The engine will combine several js files into a single one.
     *
     * @param array $params
     *   - id (required)
     *   - path (required)
     *   - load (optional) 'header', 'footer' or 'async'
     *   - require (optional) comma separated list of script ids required to be loaded
     *     and executed before this one
     *   - version (optional) used to force a browser refresh
     */
    public function func_combine_script(
        array $params
    ): void {
        if (! isset($params['id'])) {
            trigger_error("combine_script: missing 'id' parameter", E_USER_ERROR);
        }

        $load = 0;
        if (isset($params['load'])) {
            switch ($params['load']) {
                case 'header': break;
                case 'footer': $load = 1;
                    break;
                case 'async': $load = 2;
                    break;
                default: trigger_error("combine_script: invalid 'load' parameter", E_USER_ERROR);
            }
        }

        $this->scriptLoader->add(
            $params['id'],
            $load,
            empty($params['require']) ? [] : explode(',', (string) $params['require']),
            $params['path'] ?? null,
            $params['version'] ?? 0,
            $params['template'] ?? null
        );
    }

    /**
     * The "get_combined_scripts" function returns HTML tag of combined scripts.
     * It can returns a placeholder for delayed JS files combination and minification.
     *
     * @param array $params
     *    - load (required)
     */
    public function func_get_combined_scripts(
        array $params
    ): string {
        if (! isset($params['load'])) {
            trigger_error("get_combined_scripts: missing 'load' parameter", E_USER_ERROR);
        }

        $load = $params['load'] == 'header' ? 0 : 1;
        $content = [];

        if ($load == 0) {
            return self::COMBINED_SCRIPTS_TAG;
        }

        $scripts = $this->scriptLoader->get_footer_scripts();
        foreach ($scripts[0] as $script) {
            $content[] =
              '<script type="text/javascript" src="'
              . $this->make_script_src($script)
              . '"></script>';
        }

        if ($this->scriptLoader->inline_scripts !== []) {
            $content[] = '<script type="text/javascript">//<![CDATA[
';
            $content = array_merge($content, $this->scriptLoader->inline_scripts);
            $content[] = '//]]></script>';
        }

        if (count($scripts[1]) > 0) {
            $content[] = '<script type="text/javascript">';
            $content[] = '(function() {
var s,after = document.getElementsByTagName(\'script\')[document.getElementsByTagName(\'script\').length-1];';
            foreach ($scripts[1] as $script) {
                $content[] =
                  "s=document.createElement('script'); s.type='text/javascript'; s.async=true; s.src='"
                  . $this->make_script_src($script)
                  . "';";
                $content[] = 'after = after.parentNode.insertBefore(s, after);';
            }

            $content[] = '})();';
            $content[] = '</script>';
        }

        return implode("\n", $content);
    }

    /**
     * The "footer_script" block allows to add runtime script in the HTML page.
     *
     * @param array|null $params
     *    - require (optional) comma separated list of script ids
     */
    public function block_footer_script(
        array|null $params,
        string|null $content
    ): void {
        $content = isset($content) ? trim($content) : '';
        if ($content !== '' && $content !== '0') { // second call

            $this->scriptLoader->add_inline(
                $content,
                empty($params['require']) ? [] : explode(',', (string) $params['require'])
            );
        }
    }

    /**
     * The "combine_css" function allows inclusion of a css file in the current page.
     * The engine will combine several css files into a single one.
     *
     * @param array $params
     *    - id (optional) used to deal with multiple inclusions from plugins
     *    - path (required)
     *    - version (optional) used to force a browser refresh
     *    - order (optional)
     *    - template (optional) set to true to allow smarty syntax in the css file
     */
    public function func_combine_css(
        array $params
    ): void {
        if (empty($params['path'])) {
            fatal_error('combine_css missing path');
        }

        if (! isset($params['id'])) {
            $params['id'] = md5((string) $params['path']);
        }

        $this->cssLoader->add($params['id'], $params['path'], $params['version'] ?? 0, (int) ($params['order'] ?? null), (bool) ($params['template'] ?? null));
    }

    /**
     * The "get_combined_scripts" function returns a placeholder for delayed
     * CSS files combination and minification.
     *
     * @param array $params (unused)
     */
    public function func_get_combined_css(
        array $params
    ): string {
        return self::COMBINED_CSS_TAG;
    }

    /**
     * Declares a Smarty prefilter from a plugin, allowing it to modify template
     * source before compilation and without changing core files.
     * They will be processed by weight ascending.
     * @see http://www.smarty.net/manual/en/advanced.features.prefilters.php
     */
    public function set_prefilter(
        string $handle,
        callable $callback,
        int $weight = 50
    ): void {
        $this->external_filters[$handle][$weight][] = ['pre', $callback];
        ksort($this->external_filters[$handle]);
    }

    /**
     * Declares a Smarty postfilter.
     * They will be processed by weight ascending.
     * @see http://www.smarty.net/manual/en/advanced.features.postfilters.php
     */
    public function set_postfilter(
        string $handle,
        callable $callback,
        int $weight = 50
    ): void {
        $this->external_filters[$handle][$weight][] = ['post', $callback];
        ksort($this->external_filters[$handle]);
    }

    /**
     * Declares a Smarty outputfilter.
     * They will be processed by weight ascending.
     * @see http://www.smarty.net/manual/en/advanced.features.outputfilters.php
     */
    public function set_outputfilter(
        string $handle,
        callable $callback,
        int $weight = 50
    ): void {
        $this->external_filters[$handle][$weight][] = ['output', $callback];
        ksort($this->external_filters[$handle]);
    }

    /**
     * Register the filters for the tpl file.
     */
    public function load_external_filters(
        string $handle
    ): void {
        if (isset($this->external_filters[$handle])) {
            $compile_id = '';
            foreach ($this->external_filters[$handle] as $filters) {
                foreach ($filters as $filter) {
                    [$type, $callback] = $filter;

                    if (is_array($callback)) {
                        $callbackString = implode('', $callback);
                    } elseif (is_string($callback)) {
                        $callbackString = $callback;
                    } elseif ($callback instanceof \Closure) {
                        $callbackString = 'closure';
                    }

                    $compile_id .= $type . $callbackString;
                    $this->smarty->registerFilter($type, $callback);
                }
            }

            $this->smarty->compile_id .= '.' . base_convert(hash('crc32b', $compile_id), 16, 36);
        }
    }

    /**
     * Unregister the filters for the tpl file.
     */
    public function unload_external_filters(
        string $handle
    ): void {
        if (isset($this->external_filters[$handle])) {
            foreach ($this->external_filters[$handle] as $filters) {
                foreach ($filters as $filter) {
                    [$type, $callback] = $filter;
                    $this->smarty->unregisterFilter($type, $callback);
                }
            }
        }
    }

    /**
     * @toto : description of Template::prefilter_white_space
     */
    public static function prefilter_white_space(
        string $source,
        Smarty_Internal_Template $smarty
    ): string {
        $ld = $smarty->left_delimiter;
        $rd = $smarty->right_delimiter;
        $ldq = preg_quote($ld, '#');
        $rdq = preg_quote($rd, '#');

        $regex = [];
        $tags = ['if', 'foreach', 'section', 'footer_script'];
        foreach ($tags as $tag) {
            $regex[] = "#^[ \t]+({$ldq}{$tag}" . "[^{$ld}{$rd}]*{$rdq})\s*$#m";
            $regex[] = "#^[ \t]+({$ldq}/{$tag}{$rdq})\s*$#m";
        }

        $tags = ['include', 'else', 'combine_script', 'html_head'];
        foreach ($tags as $tag) {
            $regex[] = "#^[ \t]+({$ldq}{$tag}" . "[^{$ld}{$rd}]*{$rdq})\s*$#m";
        }

        return preg_replace($regex, '$1', $source);
    }

    /**
     * Postfilter used when $conf['compiled_template_cache_language'] is true.
     */
    public static function postfilter_language(
        string $source,
        Smarty $smarty
    ): string {
        // replaces echo PHP_STRING_LITERAL; with the string literal value
        $source = preg_replace_callback(
            '/\\<\\?php echo ((?:\'(?:(?:\\\\.)|[^\'])*\')|(?:"(?:(?:\\\\.)|[^"])*"));\\?\\>\\n/',
            function (array $matches) {
                eval('$tmp=' . $matches[1] . ';');
                return $tmp;
            },
            $source
        );
        return $source;
    }

    /**
     * Prefilter used to add theme local CSS files.
     */
    public static function prefilter_local_css(
        string $source,
        Smarty_Internal_Template $smarty
    ): string {
        $css = [];
        foreach ($smarty->getTemplateVars('themes') as $theme) {
            $f = PWG_LOCAL_DIR . 'css/' . $theme['id'] . '-rules.css';
            if (file_exists(PHPWG_ROOT_PATH . $f)) {
                $css[] = "{combine_css path='{$f}' order=10}";
            }
        }

        $f = PWG_LOCAL_DIR . 'css/rules.css';
        if (file_exists(PHPWG_ROOT_PATH . $f)) {
            $css[] = "{combine_css path='{$f}' order=10}";
        }

        if ($css !== []) {
            $source = str_replace('{get_combined_css}', implode("\n", $css) . "\n{get_combined_css}", $source);
        }

        return $source;
    }

    /**
     * Loads the configuration file from a theme directory and returns it.
     */
    public function load_themeconf(
        string $dir
    ): array {
        global $themeconfs, $conf;

        $dir = realpath($dir);
        if (! isset($themeconfs[$dir])) {
            $themeconf = [];
            include($dir . '/themeconf.inc.php');
            // Put themeconf in cache
            $themeconfs[$dir] = $themeconf;
        }

        return $themeconfs[$dir];
    }

    /**
     * Registers a button to be displayed on picture page.
     */
    public function add_picture_button(
        string $content,
        int $rank = BUTTONS_RANK_NEUTRAL
    ): void {
        $this->picture_buttons[$rank][] = $content;
    }

    /**
     * Registers a button to be displayed on index pages.
     */
    public function add_index_button(
        string $content,
        int $rank = BUTTONS_RANK_NEUTRAL
    ): void {
        $this->index_buttons[$rank][] = $content;
    }

    /**
     * Assigns PLUGIN_PICTURE_BUTTONS template variable with registered picture buttons.
     */
    public function parse_picture_buttons(): void
    {
        if ($this->picture_buttons !== []) {
            ksort($this->picture_buttons);
            $buttons = [];
            foreach ($this->picture_buttons as $row) {
                $buttons = array_merge($buttons, $row);
            }

            $this->assign('PLUGIN_PICTURE_BUTTONS', $buttons);

            // only for PHP 5.3
            // $this->assign('PLUGIN_PICTURE_BUTTONS',
            // array_reduce(
            // $this->picture_buttons,
            // create_function('$v,$w', 'return array_merge($v, $w);'),
            // array()
            // ));
        }
    }

    /**
     * Assigns PLUGIN_INDEX_BUTTONS template variable with registered index buttons.
     */
    public function parse_index_buttons(): void
    {
        if ($this->index_buttons !== []) {
            ksort($this->index_buttons);
            $buttons = [];
            foreach ($this->index_buttons as $row) {
                $buttons = array_merge($buttons, $row);
            }

            $this->assign('PLUGIN_INDEX_BUTTONS', $buttons);

            // only for PHP 5.3
            // $this->assign('PLUGIN_INDEX_BUTTONS',
            // array_reduce(
            // $this->index_buttons,
            // create_function('$v,$w', 'return array_merge($v, $w);'),
            // array()
            // ));
        }
    }

    /**
     * Returns clean relative URL to script file.
     */
    private function make_script_src(
        Combinable $script
    ): string {
        $ret = '';
        if ($script->is_remote()) {
            $ret = $script->path;
        } else {
            $ret = get_root_url() . $script->path;
            if ($script->version !== false) {
                $ret .= '?v' . ($script->version ?: PHPWG_VERSION);
            }
        }

        // trigger the event for eventual use of a cdn
        $ret = trigger_change('combined_script', $ret, $script);
        return embellish_url($ret);
    }
}

/**
 * This class contains basic functions that can be called directly from the
 * templates in the form $pwg->l10n('edit')
 */
class PwgTemplateAdapter
{
    /**
     * @deprecated use "translate" modifier
     */
    public function l10n(
        string $text
    ): string {
        return l10n($text);
    }

    /**
     * @deprecated use "translate_dec" modifier
     */
    public function l10n_dec(
        string $s,
        string $p,
        int $v
    ): string {
        return l10n_dec($s, $p, $v);
    }

    /**
     * @deprecated use "translate" or "sprintf" modifier
     */
    public function sprintf(...$args): mixed
    {
        return sprintf(...$args);
    }

    public function derivative(
        string|DerivativeParams $type,
        SrcImage $img
    ): DerivativeImage {
        return new DerivativeImage($type, $img);
    }

    public function derivative_url(
        string|DerivativeParams $type,
        SrcImage $img
    ): string {
        return DerivativeImage::url($type, $img);
    }
}

/**
 * A Combinable represents a JS or CSS file ready for cobination and minification.
 */
class Combinable
{
    public string $path;

    public bool|null $is_template = false;

    public function __construct(
        public string $id,
        string|null $path,
        public int|string $version = 0
    ) {
        $this->set_path($path);
    }

    public function set_path(
        string|null $path
    ): void {
        if ($path !== null && $path !== '' && $path !== '0') {
            $this->path = $path;
        }
    }

    public function is_remote(): bool
    {
        return url_is_remote($this->path) || str_starts_with($this->path, '//');
    }
}

/**
 * Implementation of Combinable for JS files.
 */
final class Script extends Combinable
{
    public array $extra = [];

    /**
     * @param int $load_mode 0,1,2
     */
    public function __construct(
        public int $load_mode,
        string $id,
        string|null $path,
        int|string $version = 0,
        public array $precedents = []
    ) {
        parent::__construct($id, $path, $version);
    }
}

/**
 * Implementation of Combinable for CSS files.
 */
final class Css extends Combinable
{
    public function __construct(
        string $id,
        string $path,
        int|string $version = 0,
        public int $order = 0
    ) {
        parent::__construct($id, $path, $version);
    }
}

/**
 * Manages a list of CSS files and combining them in a unique file.
 */
class CssLoader
{
    /**
     * @param Css[]
     */
    private array $registered_css;

    /**
     * @param int used to keep declaration order
     */
    private int $counter;

    public function __construct()
    {
        $this->clear();
    }

    public function clear(): void
    {
        $this->registered_css = [];
        $this->counter = 0;
    }

    /**
     * @return Combinable[] array of combined CSS.
     */
    public function get_css(): array
    {
        uasort($this->registered_css, $this->cmp_by_order(...));
        $combiner = new FileCombiner('css', $this->registered_css);
        return $combiner->combine();
    }

    /**
     * Adds a new file, if a file with the same $id already exsists, the one with
     * the higher $order or higher $version is kept.
     */
    public function add(
        string $id,
        string $path,
        int|string $version = 0,
        int $order = 0,
        bool $is_template = false
    ): void {
        if (! isset($this->registered_css[$id])) {
            // costum order as an higher impact than declaration order
            $css = new Css($id, $path, $version, $order * 1000 + $this->counter);
            $css->is_template = $is_template;
            $this->registered_css[$id] = $css;
            $this->counter++;
        } else {
            $css = $this->registered_css[$id];
            if ($css->order < $order * 1000 || version_compare((string) $css->version, (string) $version) < 0) {
                unset($this->registered_css[$id]);
                $this->add($id, $path, $version, $order, $is_template);
            }
        }
    }

    /**
     * Callback for CSS files sorting.
     */
    private function cmp_by_order(
        Css $a,
        Css $b
    ): int {
        return $a->order - $b->order;
    }
}

/**
 * Manage a list of required scripts for a page, by optimizing their loading location (head, footer, async)
 * and later on by combining them in a unique file respecting at the same time dependencies.
 */
class ScriptLoader
{
    /**
     * @var string[]
     */
    public array $inline_scripts;

    /**
     * @var Script[]
     */
    private array $registered_scripts;

    private bool $did_head;

    /**
     * @var Script[]
     */
    private array $head_done_scripts;

    private bool $did_footer;

    private static array $known_paths = [
        'core.scripts' => 'themes/default/js/scripts.js',
        'jquery' => 'themes/default/js/jquery.min.js',
        'jquery.ui' => 'themes/default/js/ui/minified/jquery.ui.core.min.js',
        'jquery.ui.effect' => 'themes/default/js/ui/minified/jquery.ui.effect.min.js',
    ];

    private static array $ui_core_dependencies = [
        'jquery.ui.widget' => ['jquery'],
        'jquery.ui.position' => ['jquery'],
        'jquery.ui.mouse' => ['jquery', 'jquery.ui', 'jquery.ui.widget'],
    ];

    public function __construct()
    {
        $this->clear();
    }

    public function clear(): void
    {
        $this->registered_scripts = [];
        $this->inline_scripts = [];
        $this->head_done_scripts = [];
        $this->did_head = false;
        $this->did_footer = false;
    }

    public function did_head(): bool
    {
        return $this->did_head;
    }

    /**
     * @return Script[]
     */
    public function get_all(): array
    {
        return $this->registered_scripts;
    }

    /**
     * @param string[] $require
     */
    public function add_inline(
        string $code,
        array $require
    ): void {
        if ($this->did_footer) {
            trigger_error('Attempt to add inline script but the footer has been written', E_USER_WARNING);
        }

        foreach ($require as $id) {
            if (! isset($this->registered_scripts[$id]) && ! $this->load_known_required_script($id, 1)) {
                fatal_error("inline script not found require {$id}");
            }

            $s = $this->registered_scripts[$id];
            if ($s->load_mode == 2) {
                $s->load_mode = 1;
            } // until now the implementation does not allow executing inline script depending on another async script
        }

        $this->inline_scripts[] = $code;
    }

    /**
     * @param string[] $require
     */
    public function add(
        string $id,
        int $load_mode,
        array $require,
        string|null $path,
        int|string $version = 0,
        bool|null $is_template = false
    ): void {
        if ($this->did_head && $load_mode == 0) {
            trigger_error("Attempt to add script {$id} but the head has been written", E_USER_WARNING);
        } elseif ($this->did_footer) {
            trigger_error("Attempt to add script {$id} but the footer has been written", E_USER_WARNING);
        }

        if (! isset($this->registered_scripts[$id])) {
            $script = new Script($load_mode, $id, $path, $version, $require);
            $script->is_template = $is_template;
            $this->fill_well_known($id, $script);
            $this->registered_scripts[$id] = $script;

            // Load or modify all UI core files
            if ($id === 'jquery.ui' && $script->path == self::$known_paths['jquery.ui']) {
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
            if ($require !== []) {
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
    public function get_head_scripts(): array
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
                trigger_error("Script {$id} has an undefined path", E_USER_WARNING);
            }
        }

        $this->did_head = true;
        return $this->do_combine($this->head_done_scripts);
    }

    /**
     * Returns combined scripts loaded in footer.
     *
     * @return Combinable[]
     */
    public function get_footer_scripts(): array
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

        return [$this->do_combine($result[0]), $this->do_combine($result[1])];
    }

    /**
     * @param Script[] $scripts
     * @return Combinable[]
     */
    private function do_combine(
        array $scripts
    ): array {
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
        array $scripts
    ): void {
        global $conf;
        do {
            $changed = false;
            foreach ($scripts as $script) {
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
     */
    private function fill_well_known(
        string $id,
        Script $script
    ): void {
        if (($script->path === '' || $script->path === '0') && isset(self::$known_paths[$id])) {
            $script->path = self::$known_paths[$id];
        }

        if (str_starts_with($id, 'jquery.')) {
            $required_ids = ['jquery'];

            if (str_starts_with($id, 'jquery.ui.effect-')) {
                $required_ids = ['jquery', 'jquery.ui.effect'];

                if ($script->path === '' || $script->path === '0') {
                    $script->path = dirname((string) self::$known_paths['jquery.ui.effect']) . "/{$id}.min.js";
                }
            } elseif (str_starts_with($id, 'jquery.ui.')) {
                if (! isset(self::$ui_core_dependencies[$id])) {
                    $required_ids = array_merge(['jquery', 'jquery.ui'], array_keys(self::$ui_core_dependencies));
                }

                if ($script->path === '' || $script->path === '0') {
                    $script->path = dirname((string) self::$known_paths['jquery.ui']) . "/{$id}.min.js";
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
     */
    private function load_known_required_script(
        string $id,
        int $load_mode
    ): bool {
        if (isset(self::$known_paths[$id]) || str_starts_with($id, 'jquery.ui.')) {
            $this->add($id, $load_mode, [], null);
            return true;
        }

        return false;
    }

    /**
     * Compute script order depending on dependencies.
     * Assigned to $script->extra['order'].
     */
    private function compute_script_topological_order(
        string $script_id,
        int $recursion_limiter = 0
    ): int {
        if (! isset($this->registered_scripts[$script_id])) {
            trigger_error("Undefined script {$script_id} is required by someone", E_USER_WARNING);
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

        $max++;
        return $script->extra['order'] = $max;
    }

    /**
     * Callback for scripts sorter.
     */
    private function cmp_by_mode_and_order(
        Script $s1,
        Script $s2
    ): int {
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

/**
 * Allows merging of javascript and css files into a single one.
 */
final class FileCombiner
{
    private readonly bool $is_css;

    /**
     * @param string $type 'js' or 'css'
     * @param Combinable[] $combinables
     */
    public function __construct(
        private readonly string $type,
        private array $combinables = []
    ) {
        $this->is_css = $this->type === 'css';
    }

    /**
     * Deletes all combined files from cache directory.
     */
    public static function clear_combined_files(): void
    {
        $dir = opendir(PHPWG_ROOT_PATH . PWG_COMBINED_DIR);
        while ($file = readdir($dir)) {
            if (get_extension($file) === 'js' || get_extension($file) === 'css') {
                unlink(PHPWG_ROOT_PATH . PWG_COMBINED_DIR . $file);
            }
        }

        closedir($dir);
    }

    /**
     * @param Combinable|Combinable[] $combinable
     */
    public function add(
        array|Combinable $combinable
    ): void {
        if (is_array($combinable)) {
            $this->combinables = array_merge($this->combinables, $combinable);
        } else {
            $this->combinables[] = $combinable;
        }
    }

    /**
     * @return Combinable[]
     */
    public function combine(): array
    {
        global $conf;
        $force = false;
        if (is_admin() && ($this->is_css || ! $conf['template_compile_check'])) {
            $force = (isset($_SERVER['HTTP_CACHE_CONTROL']) && str_contains((string) $_SERVER['HTTP_CACHE_CONTROL'], 'max-age=0'))
              || (isset($_SERVER['HTTP_PRAGMA']) && strpos((string) $_SERVER['HTTP_PRAGMA'], 'no-cache'));
        }

        $result = [];
        $pending = [];
        $ini_key = $this->is_css ? [get_absolute_root_url(false)] : []; //because for css we modify bg url;
        $key = $ini_key;

        foreach ($this->combinables as $combinable) {
            if ($combinable->is_remote()) {
                $this->flush_pending($result, $pending, $key, $force);
                $key = $ini_key;
                $result[] = $combinable;
                continue;
            } elseif (! $conf['template_combine_files']) {
                $this->flush_pending($result, $pending, $key, $force);
                $key = $ini_key;
            }

            $key[] = $combinable->path;
            $key[] = $combinable->version;
            if ($conf['template_compile_check']) {
                $key[] = filemtime(PHPWG_ROOT_PATH . $combinable->path);
            }

            $pending[] = $combinable;
        }

        $this->flush_pending($result, $pending, $key, $force);
        return $result;
    }

    /**
     * Process a set of pending files.
     *
     * @param string[] $key
     */
    private function flush_pending(
        array &$result,
        array &$pending,
        array $key,
        bool $force
    ): void {
        if (count($pending) > 1) {
            $key = implode('>', $key);
            $file = PWG_COMBINED_DIR . base_convert(hash('crc32b', $key), 16, 36) . '.' . $this->type;
            if ($force || ! file_exists(PHPWG_ROOT_PATH . $file)) {
                $output = '';
                $header = '';
                foreach ($pending as $combinable) {
                    $output .= "/*BEGIN {$combinable->path} */\n";
                    $output .= $this->process_combinable($combinable, true, $force, $header);
                    $output .= "\n";
                }

                $output = "/*BEGIN header */\n" . $header . "\n" . $output;
                mkgetdir(dirname(PHPWG_ROOT_PATH . $file));
                file_put_contents(PHPWG_ROOT_PATH . $file, $output);
                chmod(PHPWG_ROOT_PATH . $file, 0644);
            }

            $result[] = new Combinable('combi', $file, false);
        } elseif (count($pending) == 1) {
            $header = '';
            $this->process_combinable($pending[0], false, $force, $header);
            $result[] = $pending[0];
        }

        $pending = [];
    }

    /**
     * Process one combinable file.
     *
     * @param string $header CSS directives that must appear first in
     *                       the minified file (only used when
     *                       $return_content===true)
     */
    private function process_combinable(
        Combinable $combinable,
        bool $return_content,
        bool $force,
        string &$header
    ): string|null {
        global $conf;
        if ($combinable->is_template) {
            if (! $return_content) {
                $key = [$combinable->path, $combinable->version];
                if ($conf['template_compile_check']) {
                    $key[] = filemtime(PHPWG_ROOT_PATH . $combinable->path);
                }

                $file = PWG_COMBINED_DIR . 't' . base_convert(hash('crc32b', implode(',', $key)), 16, 36) . '.' . $this->type;
                if (! $force && file_exists(PHPWG_ROOT_PATH . $file)) {
                    $combinable->path = $file;
                    $combinable->version = 0;
                    return null;
                }
            }

            global $template;
            $handle = $this->type . '.' . $combinable->id;
            $template->set_filename($handle, realpath(PHPWG_ROOT_PATH . $combinable->path));
            trigger_notify('combinable_preparse', $template, $combinable, $this); //allow themes and plugins to set their own vars to template ...
            $content = $template->parse($handle, true);

            if ($this->is_css) {
                $content = $this->process_css($content, $combinable->path, $header);
            } else {
                $content = $this->process_js($content, $combinable->path);
            }

            if ($return_content) {
                return $content;
            }

            if (! file_exists(dirname(PHPWG_ROOT_PATH . $file))) {
                mkgetdir(dirname(PHPWG_ROOT_PATH . $file));
            }

            file_put_contents(PHPWG_ROOT_PATH . $file, $content);
            $combinable->path = $file;
        } elseif ($return_content) {
            $content = file_get_contents(PHPWG_ROOT_PATH . $combinable->path);
            if ($this->is_css) {
                $content = $this->process_css($content, $combinable->path, $header);
            } else {
                $content = $this->process_js($content, $combinable->path);
            }

            return $content;
        }

        return null;
    }

    /**
     * Process a JS file.
     *
     * @param string $js file content
     */
    private function process_js(
        string $js,
        string $file
    ): string {
        if (! str_contains($file, '.min') && ! str_contains($file, '.packed')) {
            try {
                $js = JShrink\Minifier::minify($js);
            } catch (Exception) {
            }
        }

        return trim($js, " \t\r\n;") . ";\n";
    }

    /**
     * Process a CSS file.
     *
     * @param string $css file content
     * @param string $header CSS directives that must appear first in
     *                       the minified file.
     */
    private function process_css(
        string $css,
        string $file,
        string &$header
    ): string {
        $css = self::process_css_rec($css, dirname($file), $header);
        if (! str_contains($file, '.min') && PHP_VERSION_ID >= 50200) {
            $cssMin = new tubalmartin\CssMin\Minifier();
            $css = $cssMin->run($css);
        }

        return trigger_change('combined_css_postfilter', $css);
    }

    /**
     * Resolves relative links in CSS file.
     *
     * @param string $css file content
     * @param string $header CSS directives that must appear first in
     *                       the minified file.
     */
    private static function process_css_rec(
        string $css,
        string $dir,
        string &$header
    ): string {
        static $PATTERN_URL = "#url\(\s*['|\"]{0,1}(.*?)['|\"]{0,1}\s*\)#";
        static $PATTERN_IMPORT = "#@import\s*['|\"]{0,1}(.*?)['|\"]{0,1};#";
        if (preg_match_all($PATTERN_URL, $css, $matches, PREG_SET_ORDER)) {
            $search = [];
            $replace = [];
            foreach ($matches as $match) {
                if (! url_is_remote($match[1]) && $match[1][0] !== '/' && ! str_contains($match[1], 'data:image/')) {
                    $relative = $dir . "/{$match[1]}";
                    $search[] = $match[0];
                    $replace[] = 'url(' . embellish_url(get_absolute_root_url(false) . $relative) . ')';
                }
            }

            $css = str_replace($search, $replace, $css);
        }

        if (preg_match_all($PATTERN_IMPORT, $css, $matches, PREG_SET_ORDER)) {
            $search = [];
            $replace = [];
            foreach ($matches as $match) {
                $search[] = $match[0];

                if (
                    str_contains($match[1], '..') || str_contains($match[1], '://') || ! is_readable(PHPWG_ROOT_PATH . $dir . '/' . $match[1])
                ) {
                    // If anything is suspicious, don't try to process the
                    // @import. Since @import need to be first and we are
                    // concatenating several CSS files, remove it from here and return
                    // it through $header.
                    $header .= $match[0];
                    $replace[] = '';
                } else {
                    $sub_css = file_get_contents(PHPWG_ROOT_PATH . $dir . "/{$match[1]}");
                    $replace[] = self::process_css_rec($sub_css, dirname($dir . "/{$match[1]}"), $header);
                }
            }

            $css = str_replace($search, $replace, $css);
        }

        return $css;
    }
}
