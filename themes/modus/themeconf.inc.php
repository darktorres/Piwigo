<?php
/*
Theme Name: modus
Version: 14.5.0
Description: Responsive, horizontal menu, retina aware, no lost space.
Theme URI: https://piwigo.org/ext/extension_view.php?eid=728
Author: rvelices
Author URI: http://www.modusoptimus.com
*/
$themeconf = [
    'name' => 'modus',
    'parent' => 'default',
    'colorscheme' => 'dark',
];

define('MODUS_STR_RECENT', "\xe2\x9c\xbd"); //HEAVY TEARDROP-SPOKED ASTERISK
define('MODUS_STR_RECENT_CHILD', "\xe2\x9c\xbb"); //TEARDROP-SPOKED ASTERISK

if (! empty($_GET['skin']) && ! preg_match('/[^a-zA-Z0-9_-]/', (string) $_GET['skin'])) {
    $conf['modus_theme']['skin'] = $_GET['skin'];
}

// we're mainly interested in an override of the colorscheme
require __DIR__ . '/skins/' . $conf['modus_theme']['skin'] . '.inc.php';

$this->assign(
    [
        'MODUS_CSS_VERSION' => (string) crc32(implode(',', [
            'a' . $conf['modus_theme']['skin'],
            $conf['modus_theme']['album_thumb_size'],
            ImageStdParams::get_by_type(IMG_SQUARE)->max_width(),
            $conf['index_created_date_icon'],
            $conf['index_posted_date_icon'],
        ])),
        'MODUS_DISPLAY_PAGE_BANNER' => $conf['modus_theme']['display_page_banner'],
    ]
);

if (file_exists(__DIR__ . '/skins/' . $conf['modus_theme']['skin'] . '.css')) {
    $this->assign('MODUS_CSS_SKIN', $conf['modus_theme']['skin']);
}

if (! $conf['compiled_template_cache_language']) {
    load_language('theme.lang', __DIR__ . '/');
    load_language('lang', PHPWG_ROOT_PATH . PWG_LOCAL_DIR, [
        'no_fallback' => true,
        'local' => true,
    ]);
}

if (isset($_COOKIE['caps'])) {
    setcookie('caps', '', [
        'expires' => 0,
        'path' => cookie_path(),
    ]);
    pwg_set_session_var('caps', explode('x', (string) $_COOKIE['caps']));
    /*file_put_contents(PHPWG_ROOT_PATH.$conf['data_location'].'tmp/modus.log', implode("\t", array(
        date("Y-m-d H:i:s"), $_COOKIE['caps'], $_SERVER['HTTP_USER_AGENT']
        ))."\n", FILE_APPEND);*/
}

if (get_device() === 'mobile') {
    $conf['tag_letters_column_number'] = 1;
} elseif (get_device() === 'tablet') {
    $conf['tag_letters_column_number'] = min($conf['tag_letters_column_number'], 3);
}

$this->smarty->registerFilter('pre', 'modus_smarty_prefilter_wrap');
function modus_smarty_prefilter_wrap(
    string $source
): array|string|null {
    require_once __DIR__ . '/functions.inc.php';
    return modus_smarty_prefilter($source);
}

if (! defined('IN_ADMIN') && defined('RVCDN')) {
    $this->smarty->registerFilter('pre', 'rv_cdn_prefilter');
    add_event_handler('combined_script', rv_cdn_combined_script(...));
}

function rv_cdn_prefilter(
    string $source,
    Smarty &$smarty
): string {
    $source = str_replace('src="{$ROOT_URL}{$themeconf.icon_dir}/', 'src="' . RVCDN_ROOT_URL . '{$themeconf.icon_dir}/', $source);
    return str_replace('url({$ROOT_URL}', 'url(' . RVCDN_ROOT_URL, $source);
}

// Add prefilter to remove fontello loaded by piwigo 14 search,
// this avoids conflicts of loading 2 fontellos
add_event_handler('loc_begin_index', modus_loc_begin_index(...), 60);
function modus_loc_begin_index(): void
{
    global $template;
    $template->set_prefilter('index', modus_index_prefilter_1(...));
    $template->set_prefilter('index', modus_index_prefilter_2(...));
}

function modus_index_prefilter_1(
    string $content
): array|string {
    $search = '{combine_css path="themes/default/vendor/fontello/css/fontello.css" order=-10}';
    $replacement = '';
    return str_replace($search, $replacement, $content);
}

// Add pwg-icon class to search in this set icon

function modus_index_prefilter_2(
    string $content
): array|string {
    $search = '<span class="pwg-icon-search-folder"></span>';
    $replacement = '<span class="pwg-icon pwg-icon-search-folder"></span>';
    return str_replace($search, $replacement, $content);
}

function rv_cdn_combined_script(
    string $url,
    Script $script
): string {
    if (! $script->is_remote()) {
        $url = RVCDN_ROOT_URL . $script->path;
    }

    return $url;
}

if (defined('RVPT_JQUERY_SRC')) {
    add_event_handler('loc_begin_page_header', modus_loc_begin_page_header(...));
}

function modus_loc_begin_page_header(): void
{
    $all = $GLOBALS['template']->scriptLoader->get_all();
    if (($jq = $all['jquery'])) {
        $jq->set_path(RVPT_JQUERY_SRC);
    }
}

add_event_handler('combinable_preparse', modus_combinable_preparse(...));
function modus_combinable_preparse(
    Template $template
): void {
    global $conf, $template;
    require_once __DIR__ . '/functions.inc.php';

    if (! isset($template->smarty->registered_plugins['modifier']['cssGradient'])) {
        $template->smarty->registerPlugin('modifier', 'cssGradient', modus_css_gradient(...));
    }

    require __DIR__ . '/skins/' . $conf['modus_theme']['skin'] . '.inc.php';

    $template->assign([
        'conf' => $conf,
        'skin' => $skin,
        'MODUS_ALBUM_THUMB_SIZE' => intval($conf['modus_theme']['album_thumb_size']),
        'SQUARE_WIDTH' => ImageStdParams::get_by_type(IMG_SQUARE)->max_width(),
        'loaded_plugins' => $GLOBALS['pwg_loaded_plugins'],
    ]);
}

$this->smarty->registerPlugin('function', 'cssResolution', modus_css_resolution(...));
function modus_css_resolution(
    array $params
): string {
    $base = $params['base'] ?? null;
    $min = $params['min'] ?? null;
    $max = $params['max'] ?? null;

    $rules = [];
    if (! empty($base)) {
        $rules[] = $base;
    }

    foreach (['min', 'max'] as $type) {
        if (! empty(${$type})) {
            $rules[] = '(-webkit-' . $type . '-device-pixel-ratio:' . ${$type} . ')';
        }
    }

    $res = implode(' and ', $rules);

    $rules = [];
    if (! empty($base)) {
        $rules[] = $base;
    }

    foreach (['min', 'max'] as $type) {
        if (! empty(${$type})) {
            $rules[] = '(' . $type . '-resolution:' . round(96 * ${$type}, 1) . 'dpi)';
        }
    }

    return $res . (',' . implode(' and ', $rules));
}

$this->smarty->registerPlugin('function', 'modus_thumbs', modus_thumbs(...));
function modus_thumbs(
    array $x,
    Smarty\Template $smarty
): void {
    global $template, $page, $conf;

    $default_params = $smarty->getTemplateVars('derivative_params');
    $row_height = $default_params->max_height();
    $device = get_device();
    $container_margin = 5;

    if ($device === 'mobile') {
        $horizontal_margin = floor(0.01 * $row_height);
        $container_margin = 0;
    } elseif ($device === 'tablet') {
        $horizontal_margin = floor(0.015 * $row_height);
    } else {
        $horizontal_margin = floor(0.02 * $row_height);
    }

    $vertical_margin = $horizontal_margin + 1;

    $candidates = [$default_params];
    foreach (ImageStdParams::get_defined_type_map() as $params) {
        if ($params->max_height() > $row_height && $params->sizing->max_crop == $default_params->sizing->max_crop) {
            $candidates[] = $params;
            if (count($candidates) == 3) {
                break;
            }
        }
    }

    $do_over = $device === 'desktop';

    $new_icon = ' <span class=albSymbol title="' . l10n('posted on %s') . '">' . MODUS_STR_RECENT . '</span>';

    foreach ($smarty->getTemplateVars('thumbnails') as $item) {
        $src_image = $item['src_image'];
        $new = empty($item['icon_ts']) ? '' : sprintf($new_icon, format_date($item['date_available']));

        $idx = 0;
        do {
            $cparams = $candidates[$idx];
            $c = new DerivativeImage($cparams, $src_image);
            $csize = $c->get_size();
            $idx++;
        } while ($csize[1] < $row_height - 2 && $idx < count($candidates));

        $a_style = '';
        if ($csize[1] < $row_height) {
            $a_style = ' style="top:' . floor(($row_height - $csize[1]) / 2) . 'px"';
        } elseif ($csize[1] > $row_height) {
            $csize = $c->get_scaled_size(9999, $row_height);
        }

        if ($do_over) {?>
<li class="path-ext-<?= $item['path_ext']?> file-ext-<?= $item['file_ext']?>" style=width:<?= $csize[0]?>px;height:<?= $row_height?>px><a href="<?= $item['URL']?>"<?= $a_style?>><img src="<?= $c->get_url()?>" width=<?= $csize[0]?> height=<?= $csize[1]?> alt="<?= $item['TN_ALT']?>"></a><div class=overDesc><?= $item['NAME']?><?= $new?></div></li>
<?php
        } else {?>
<li class="path-ext-<?= $item['path_ext']?> file-ext-<?= $item['file_ext']?>" style=width:<?= $csize[0]?>px;height:<?= $row_height?>px><a href="<?= $item['URL']?>"<?= $a_style?>><img src="<?= $c->get_url()?>" width=<?= $csize[0]?> height=<?= $csize[1]?> alt="<?= $item['TN_ALT']?>"></a></li>
<?php
        }
    }

    $template->block_html_style(
        null,
        '<style type="text/css">#thumbnails{text-align:justify;overflow:hidden;margin-left:' . ($container_margin - $horizontal_margin) . 'px;margin-right:' . $container_margin . 'px}
#thumbnails>li{float:left;overflow:hidden;position:relative;margin-bottom:' . $vertical_margin . 'px;margin-left:' . $horizontal_margin . 'px}#thumbnails>li>a{position:absolute;border:0}</style>'
    );
    $template->block_footer_script(null, '<script>rvgtProcessor=new RVGThumbs({hMargin:' . $horizontal_margin . ',rowHeight:' . $row_height . '});</script>');

    $my_base_name = basename(__DIR__);
    // not async to avoid visible flickering reflow
    $template->scriptLoader->add('modus.arange', 1, ['jquery'], 'themes/' . $my_base_name . '/js/thumb.arrange.js', 0);
}

add_event_handler('loc_end_index', modus_on_end_index(...));
function modus_on_end_index(): void
{
    global $template;
    if (! pwg_get_session_var('caps')) {
        $template->block_footer_script(null, '<script>try{document.cookie="caps="+(window.devicePixelRatio?window.devicePixelRatio:1)+"x"+document.documentElement.clientWidth+"x"+document.documentElement.clientHeight+";path=' . cookie_path() . '"}catch(er){document.cookie="caps=1x1x1x"+err.message;}</script>');
    }

}

add_event_handler('get_index_derivative_params', modus_get_index_photo_derivative_params(...), EVENT_HANDLER_PRIORITY_NEUTRAL + 1);
function modus_get_index_photo_derivative_params(
    DerivativeParams|string $default
): DerivativeParams|string {
    global $conf;
    if (isset($conf['modus_theme']) && pwg_get_session_var('index_deriv') === null) {
        $type = $conf['modus_theme']['index_photo_deriv'];
        if (($caps = pwg_get_session_var('caps')) && (($caps[0] >= 2 && $caps[1] >= 768) /*Ipad3 always has clientWidth 768 independently of orientation*/
            || $caps[0] >= 3)) {
            $type = $conf['modus_theme']['index_photo_deriv_hdpi'];
        }

        $new = ImageStdParams::get_by_type($type);
        if ($new) {
            return $new;
        }
    }

    return $default;
}

add_event_handler('loc_end_index_category_thumbnails', modus_index_category_thumbnails(...));
function modus_index_category_thumbnails(
    array $items
): array {
    global $page, $template, $conf;

    if ($page['section'] != 'categories' || ! ($wh = $conf['modus_theme']['album_thumb_size'])) {
        return $items;
    }

    $template->assign('album_thumb_size', $wh);

    $def_params = ImageStdParams::get_custom($wh, $wh, 1, $wh, $wh);
    foreach (ImageStdParams::get_defined_type_map() as $params) {
        if ($params->max_height() == $wh) {
            $alt_params = $params;
        }
    }

    foreach ($items as &$item) {
        $src_image = $item['representative']['src_image'];
        $src_size = $src_image->get_size();

        $item['path_ext'] = strtolower(get_extension($item['representative']['path']));
        $item['file_ext'] = strtolower(get_extension($item['representative']['file']));

        $deriv = null;
        if (isset($alt_params) && $src_size[0] >= $src_size[1]) {
            $dsize = $alt_params->compute_final_size($src_size);
            if ($dsize[0] >= $wh && $dsize[1] >= $wh) {
                $deriv = new DerivativeImage($alt_params, $src_image);
                $rect = new ImageRect($dsize);
                $rect->crop_h($dsize[0] - $wh, $item['representative']['coi']);
                $rect->crop_v($dsize[1] - $wh, $item['representative']['coi']);
                $l = -$rect->l;
                $t = -$rect->t;
            }
        }

        if (! isset($deriv)) {
            $deriv = new DerivativeImage($def_params, $src_image);
            $dsize = $deriv->get_size();
            $l = intval($wh - $dsize[0]) / 2;
            $t = intval($wh - $dsize[1]) / 2;
        }

        $item['modus_deriv'] = $deriv;

        if (! empty($item['icon_ts'])) {
            $item['icon_ts']['TITLE'] = time_since($item['max_date_last'], 'month');
        }

        $styles = [];
        if ($l < -1 || $l > 1) {
            $styles[] = 'left:' . (100 * $l / $wh) . '%';
        }

        if ($t < -1 || $t > 1) {
            $styles[] = 'top:' . $t . 'px';
        }

        $styles = count($styles) ? ' style=' . implode(';', $styles) : '';

        $item['MODUS_STYLE'] = $styles;
    }

    return $items;
}

add_event_handler('loc_begin_picture', modus_loc_begin_picture(...));
function modus_loc_begin_picture(): void
{
    global $conf, $template;
    if (isset($_GET['slideshow'])) {
        $conf['picture_menu'] = false;
        return;
    }

    if (isset($_GET['map'])) {
        return;
    }

    $template->append('head_elements', "<script>if(document.documentElement.offsetWidth>1270)document.documentElement.className='wide'</script>");
}

add_event_handler('render_element_content', modus_picture_content(...), EVENT_HANDLER_PRIORITY_NEUTRAL - 1);
function modus_picture_content(
    string $content,
    array $element_info
): string|null {
    global $conf, $picture, $template;

    if ($content !== '' && $content !== '0') { // someone hooked us - so we skip;
        return $content;
    }

    $unique_derivatives = [];
    $show_original = isset($element_info['element_url']);
    $added = [];
    foreach ($element_info['derivatives'] as $type => $derivative) {
        if ($type == IMG_SQUARE || $type == IMG_THUMB) {
            continue;
        }

        if (! array_key_exists($type, ImageStdParams::get_defined_type_map())) {
            continue;
        }

        $url = $derivative->get_url();
        if (isset($added[$url])) {
            continue;
        }

        $added[$url] = 1;
        $show_original &= ! ($derivative->same_as_source());
        $unique_derivatives[$type] = $derivative;
    }

    if (isset($_COOKIE['picture_deriv'])) { // ignore persistence
        setcookie('picture_deriv', false, [
            'expires' => 0,
            'path' => cookie_path(),
        ]);
    }

    $selected_derivative = null;
    if (isset($_COOKIE['phavsz'])) {
        $available_size = explode('x', (string) $_COOKIE['phavsz']);
    } elseif (($caps = pwg_get_session_var('caps')) && $caps[0] > 1) {
        $available_size = [$caps[0] * $caps[1], $caps[0] * ($caps[2] - 100), $caps[0]];
    }

    if (isset($available_size)) {
        foreach ($unique_derivatives as $derivative) {
            $size = $derivative->get_size();
            if (! $size) {
                break;
            }

            if ($size[0] <= $available_size[0] && $size[1] <= $available_size[1]) {
                $selected_derivative = $derivative;
            } else {
                if ($available_size[2] > 1 || ! $selected_derivative) {
                    $selected_derivative = $derivative;
                }

                break;
            }
        }

        if ($available_size[2] > 1 && $selected_derivative) {
            $ratio_w = $size[0] / $available_size[0];
            $ratio_h = $size[1] / $available_size[1];
            if ($ratio_w > 1 || $ratio_h > 1) {
                if ($ratio_w > $ratio_h) {
                    $display_size = [$available_size[0] / $available_size[2], floor($size[1] / $ratio_w / $available_size[2])];
                } else {
                    $display_size = [floor($size[0] / $ratio_h / $available_size[2]), $available_size[1] / $available_size[2]];
                }
            } else {
                $display_size = [round($size[0] / $available_size[2]), round($size[1] / $available_size[2])];
            }

            $template->assign([
                'rvas_display_size' => $display_size,
                'rvas_natural_size' => $size,
            ]);
        }

        if (isset($picture['next']) && $picture['next']['src_image']->is_original()) {
            $next_best = null;
            foreach ($picture['next']['derivatives'] as $derivative) {
                $size = $derivative->get_size();
                if (! $size) {
                    break;
                }

                if ($size[0] <= $available_size[0] && $size[1] <= $available_size[1]) {
                    $next_best = $derivative;
                } else {
                    if ($available_size[2] > 1 || ! $next_best) {
                        $next_best = $derivative;
                    }

                    break;
                }
            }

            if (isset($next_best)) {
                $template->assign('U_PREFETCH', $next_best->get_url());
            }
        }
    }

    $as_pending = false;
    if (! $selected_derivative) {
        $as_pending = true;
        $selected_derivative = $element_info['derivatives'][pwg_get_session_var('picture_deriv', $conf['derivative_default_size'])];
    }

    if ($show_original) {
        $template->assign('U_ORIGINAL', $element_info['element_url']);
    }

    $template->append('current', [
        'selected_derivative' => $selected_derivative,
        'unique_derivatives' => $unique_derivatives,
    ], true);

    $template->set_filenames(
        [
            'default_content' => 'picture_content_asize.tpl',
        ]
    );

    $template->assign(
        [
            'ALT_IMG' => $element_info['file'],
            'COOKIE_PATH' => cookie_path(),
            'RVAS_PENDING' => $as_pending,
        ]
    );
    return $template->parse('default_content', true);
}

?>
