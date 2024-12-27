<?php

declare(strict_types=1);

namespace Piwigo\inc;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Allows merging of JavaScript and CSS files into a single one.
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
        $ini_key = $this->is_css ? [get_absolute_root_url(false)] : []; //because for CSS we modify bg url;
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
                $key[] = file_exists(PHPWG_ROOT_PATH . $combinable->path) ? filemtime(PHPWG_ROOT_PATH . $combinable->path) : false;
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

            $result[] = new Combinable('combi', $file);
        } elseif (count($pending) == 1) {
            $header = '';
            $this->process_combinable($pending[0], false, $force, $header);
            $result[] = $pending[0];
        }

        $key = [];
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
    ): ?string {
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
                $js = \JShrink\Minifier::minify($js);
            } catch (\Exception) {
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
            $cssMin = new \tubalmartin\CssMin\Minifier();
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
                    // @import. Since @import needs to be first, and we are
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
