<?php

namespace Piwigo\themes\bootstrap_darkroom\inc;

use Piwigo\inc\dblayer\functions_mysqli;
use Piwigo\inc\derivative_std_params;
use Piwigo\inc\functions;
use Piwigo\inc\functions_html;
use Piwigo\inc\functions_plugins;
use Piwigo\inc\functions_url;
use Piwigo\inc\ImageStdParams;
use Piwigo\inc\SrcImage;

class ThemeController
{
    private $config;

    public function __construct()
    {
        $this->config = new Config();
    }

    public function init()
    {
        functions::load_language('theme.lang', PHPWG_THEMES_PATH . 'bootstrap_darkroom/');
        functions::load_language('lang', PHPWG_ROOT_PATH . PWG_LOCAL_DIR, [
            'no_fallback' => true,
            'local' => true,
        ]);

        functions_plugins::add_event_handler('init', [$this, 'assignConfig']);
        functions_plugins::add_event_handler('init', [$this, 'setInitValues']);

        if ($this->config->bootstrap_theme === 'darkroom' || $this->config->bootstrap_theme === 'material' || $this->config->bootstrap_theme === 'bootswatch') {
            $this->config->bootstrap_theme = 'bootstrap-darkroom';
            $this->config->save();
            functions_plugins::add_event_handler('loc_begin_page_header', [$this, 'showUpgradeWarning']);
        }

        $shortname = $this->config->comments_disqus_shortname;
        if ($this->config->comments_type == 'disqus' && ! empty($shortname)) {
            functions_plugins::add_event_handler('blockmanager_apply', [$this, 'hideMenus']);
        }

        functions_plugins::add_event_handler('loc_begin_page_header', [$this, 'checkIfHomepage']);
        functions_plugins::add_event_handler('loc_after_page_header', [$this, 'stripBreadcrumbs']);
        functions_plugins::add_event_handler('format_exif_data', [$this, 'exifReplacements']);
        functions_plugins::add_event_handler('loc_end_picture', [$this, 'registerPictureTemplates'], 1000);
        functions_plugins::add_event_handler('loc_begin_index_thumbnails', [$this, 'returnPageStart']);

        if ($this->config->slick_enabled === true || $this->config->photoswipe === true) {
            functions_plugins::add_event_handler('loc_end_picture', [$this, 'getAllThumbnailsInCategory']);
            // also needed on index.tpl for compatibility with GThumb+/GDThumb
            functions_plugins::add_event_handler('loc_end_index', [$this, 'getAllThumbnailsInCategory']);
        }
    }

    public function assignConfig()
    {
        global $template, $conf;

        if (array_key_exists('bootstrap_darkroom_navbar_main_style', $conf) && ! empty($conf['bootstrap_darkroom_navbar_main_style'])) {
            $this->config->navbar_main_style = $conf['bootstrap_darkroom_navbar_main_style'];
        }
        if (array_key_exists('bootstrap_darkroom_navbar_main_bg', $conf) && ! empty($conf['bootstrap_darkroom_navbar_main_bg'])) {
            $this->config->navbar_main_bg = $conf['bootstrap_darkroom_navbar_main_bg'];
        }
        if (array_key_exists('bootstrap_darkroom_navbar_contextual_style', $conf) && ! empty($conf['bootstrap_darkroom_navbar_contextual_style'])) {
            $this->config->navbar_contextual_style = $conf['bootstrap_darkroom_navbar_contextual_style'];
        }
        if (array_key_exists('bootstrap_darkroom_navbar_contextual_bg', $conf) && ! empty($conf['bootstrap_darkroom_navbar_contextual_bg'])) {
            $this->config->navbar_contextual_bg = $conf['bootstrap_darkroom_navbar_contextual_bg'];
        }

        $template->assign('theme_config', $this->config);
    }

    public function showUpgradeWarning()
    {
        global $page;
        $page['errors'][] = functions::l10n('Your selected color style has been reset to "bootstrap-darkroom". You can select a different color style in the admin section.');
    }

    public function hideMenus($menus)
    {
        $menu = &$menus[0];

        $mbMenu = $menu->get_block('mbMenu');
        unset($mbMenu->data['comments']);
    }

    public function returnPageStart()
    {
        global $page, $template;

        $template->assign('START_ID', $page['start']);
    }

    public function checkIfHomepage()
    {
        global $template, $page;

        if (isset($page['is_homepage']) and $page['is_homepage']) {
            $template->assign('is_homepage', true);
        } else {
            $template->assign('is_homepage', false);
        }
    }

    public function setInitValues()
    {
        global $template, $pwg_loaded_plugins, $conf;

        $template->assign([
            'loaded_plugins' => $GLOBALS['pwg_loaded_plugins'],
            'meta_ref_enabled' => $conf['meta_ref'],
        ]);
        if (array_key_exists('bootstrap_darkroom_core_js_in_header', $conf)) {
            $template->assign('bootstrap_darkroom_core_js_in_header', $conf['bootstrap_darkroom_core_js_in_header']);
        } else {
            $template->assign('bootstrap_darkroom_core_js_in_header', false);
        }

        if (isset($pwg_loaded_plugins['language_switch'])) {
            functions_plugins::add_event_handler('loc_end_search', 'language_controler_flags', 95);
            functions_plugins::add_event_handler('loc_end_identification', 'language_controler_flags', 95);
            functions_plugins::add_event_handler('loc_end_tags', 'language_controler_flags', 95);
            functions_plugins::add_event_handler('loc_begin_about', 'language_controler_flags', 95);
            functions_plugins::add_event_handler('loc_end_register', 'language_controler_flags', 95);
            functions_plugins::add_event_handler('loc_end_password', 'language_controler_flags', 95);
        }

        if (isset($pwg_loaded_plugins['exif_view'])) {
            functions::load_language('lang.exif', PHPWG_PLUGINS_PATH . 'exif_view/');
        }
    }

    public function exifReplacements($exif)
    {
        global $conf;

        if (array_key_exists('bootstrap_darkroom_ps_exif_replacements', $conf)) {
            foreach ($conf['bootstrap_darkroom_ps_exif_replacements'] as $tag => $replacement) {
                if (is_array($exif) && array_key_exists($tag, $exif)) {
                    $exif[$tag] = str_replace($replacement[0], $replacement[1], $exif[$tag]);
                }
            }
        }
        return $exif;
    }

    // register additional template files
    public function registerPictureTemplates()
    {
        global $template;

        $template->set_filenames([
            'picture_nav' => 'picture_nav.tpl',
        ]);
        $template->parse_picture_buttons();
        $template->parse_index_buttons();
        $template->assign_var_from_handle('PICTURE_NAV', 'picture_nav');
    }

    public function stripBreadcrumbs()
    {
        global $page, $template;

        $l_sep = $template->get_template_vars('LEVEL_SEPARATOR');
        $title = $template->get_template_vars('TITLE');
        $section_title = $template->get_template_vars('SECTION_TITLE');
        if (empty($title)) {
            $title = $section_title;
        }
        if (! empty($title)) {
            $splt = strpos($title, '[');
            if ($splt) {
                $title_links = substr($title, 0, $splt);
                $title = $title_links;
            }

            $title = str_replace('<a href', '<a class="nav-breadcrumb-item" href', $title);
            $title = str_replace($l_sep, '', $title);
            if ($page['section'] == 'recent_cats' or $page['section'] == 'favorites') {
                $title = preg_replace('/<\/a>([a-zA-Z0-9]+)/', '</a><a class="nav-breadcrumb-item" href="' . functions_url::make_index_url([
                    'section' => $page['section'],
                ]) . '">${1}', $title) . '</a>';
            }
            if (empty($section_title)) {
                $template->assign('TITLE', $title);
            } else {
                $template->assign('SECTION_TITLE', $title);
            }
        }
    }

    public function getAllThumbnailsInCategory()
    {
        global $template, $conf, $user, $page;

        if (! $page['items'] || ($page['section'] == 'categories' && ! isset($page['category']) && ! isset($page['chronology_field']) && ! isset($page['flat']))) {
            return;
        }

        if (count($page['items']) > 1000) {
            $this->config->slick_enabled = false;
            $this->config->photoswipe = false;

            return;
        }

        // select all pictures for this category
        $query = '
            SELECT
              id,
              file,
              representative_ext,
              name,
              comment,
              width,
              height,
              date_creation,
              path,
              rotation
            FROM ' . IMAGES_TABLE . '
            WHERE id IN (' . implode(',', $page['items']) . ')
            ORDER BY FIELD(id, ' . implode(',', $page['items']) . ')
            ;';

        $result = functions_mysqli::pwg_query($query);

        $pictures = [];
        while ($row = functions_mysqli::pwg_db_fetch_assoc($result)) {
            $pictures[] = $row;
        }

        $tpl_thumbnails_var = [];

        $theme_config = $template->get_template_vars('theme_config');

        foreach ($pictures as $row) {
            $url = functions_url::duplicate_picture_url(
                [
                    'image_id' => $row['id'],
                    'image_file' => $row['file'],
                ],
                ['start']
            );

            $name = functions_html::render_element_name($row);
            $desc = functions_html::render_element_description($row, 'main_page_element_description');

            $tpl_var = array_merge($row, [
                'NAME' => $name,
                'TN_ALT' => htmlspecialchars(strip_tags($name)),
                'URL' => $url,
                'DESCRIPTION' => htmlspecialchars(strip_tags($desc)),
                'src_image' => new SrcImage($row),
                'SIZE' => $row['width'] . 'x' . $row['height'],
                'PATH' => $row['path'],
                'DATE_CREATED' => $row['date_creation'],
                'file_ext' => strtolower(functions::get_extension($row['file'])),
                'path_ext' => strtolower(functions::get_extension($row['path'])),
            ]);

            $tpl_thumbnails_var[] = $tpl_var;
        }

        $template->assign('thumbnails', $tpl_thumbnails_var);

        $template->assign([
            'derivative_params_square' => functions_plugins::trigger_change('get_index_derivative_params', ImageStdParams::get_by_type(derivative_std_params::IMG_SQUARE)),
            'derivative_params_medium' => functions_plugins::trigger_change('get_index_derivative_params', ImageStdParams::get_by_type(derivative_std_params::IMG_MEDIUM)),
            'derivative_params_large' => functions_plugins::trigger_change('get_index_derivative_params', ImageStdParams::get_by_type(derivative_std_params::IMG_LARGE)),
            'derivative_params_xxlarge' => functions_plugins::trigger_change('get_index_derivative_params', ImageStdParams::get_by_type(derivative_std_params::IMG_XXLARGE)),
        ]);

        unset($tpl_thumbnails_var, $pictures);
    }
}
