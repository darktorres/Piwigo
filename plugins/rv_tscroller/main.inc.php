<?php

declare(strict_types=1);

/*
Plugin Name: RV Thumb Scroller
Version: 12.a
Plugin URI: http://piwigo.org/ext/extension_view.php?eid=493
Description: Infinite scroll - loads thumbnails on index page as you scroll down the page
Author: rvelices
Author URI: http://www.modusoptimus.com
Has Settings: false
*/
define('RVTS_VERSION', '12.a');

class RVTS
{
    public static function on_end_section_init(): void
    {
        // global $page;
        // $page['nb_image_page'] *= pwg_get_session_var('rvts_mult', 1);
        // if (count($page['items']) < $page['nb_image_page'] + 3 && (! $page['start'] || script_basename() === 'picture')) {
        //     $page['nb_image_page'] = max($page['nb_image_page'], count($page['items']));
        // }

        add_event_handler('loc_begin_index', self::on_index_begin(...), EVENT_HANDLER_PRIORITY_NEUTRAL + 10);
    }

    public static function on_index_begin(): void
    {
        global $page;
        $is_ajax = isset($_GET['rvts']);
        if (! $is_ajax) {
            if (empty($page['items'])) {
                add_event_handler('loc_end_index', self::on_end_index(...));
            } else {
                add_event_handler('loc_end_index_thumbnails', self::on_index_thumbnails(...));
            }
        } else {
            $adj = (int) ($_GET['adj'] ?? null);
            if ($adj !== 0) {
                $mult = pwg_get_session_var('rvts_mult', 1);
                if ($adj > 0 && $mult < 5) {
                    pwg_set_session_var('rvts_mult', ++$mult);
                }

                if ($adj < 0 && $mult > 1) {
                    pwg_set_session_var('rvts_mult', --$mult);
                }
            }

            // $page['nb_image_page'] = (int) $_GET['rvts'];
            add_event_handler('loc_end_index_thumbnails', self::on_index_thumbnails_ajax(...), EVENT_HANDLER_PRIORITY_NEUTRAL + 5);
            $page['root_path'] = get_absolute_root_url(false);
            $page['body_id'] = 'scroll';
            global $user, $template, $conf;
            include(PHPWG_ROOT_PATH . 'include/category_default.inc.php');
        }
    }

    public static function on_index_thumbnails(
        array $thumbs
    ): array {
        global $page, $template;
        $total = count($page['items']);
        if (count($thumbs) >= $total) {
            add_event_handler('loc_end_index', self::on_end_index(...));
            return $thumbs;
        }

        $url_model = str_replace('123456789', '%start%', duplicate_index_url([
            'start' => 123456789,
        ]));
        $ajax_url_model = add_url_params($url_model, [
            'rvts' => '%per%',
        ]);

        $url_model = str_replace('&amp;', '&', $url_model);
        $ajax_url_model = str_replace('&amp;', '&', $ajax_url_model);

        $my_base_name = basename(__DIR__);
        $ajax_loader_image = get_root_url() . "plugins/{$my_base_name}/ajax-loader.gif";
        $template->func_combine_script([
            'id' => 'jquery',
            'load' => 'footer',
            'path' => 'themes/default/js/jquery.min.js',
        ]);
        $template->func_combine_script([
            'id' => $my_base_name,
            'load' => 'async',
            'path' => 'plugins/' . $my_base_name . '/rv_tscroller.min.js',
            'require' => 'jquery',
            'version' => RVTS_VERSION,
        ]);
        $start = (int) $page['start'];
        $per_page = $page['nb_image_page'];
        $moreMsg = 'See the remaining %d photos';
        if ($GLOBALS['lang_info']['code'] != 'en') {
            load_language('lang', __DIR__ . '/');
            $moreMsg = l10n($moreMsg);
        }

        // the String.fromCharCode comes from google bot which somehow manage to get these urls
        $template->block_footer_script(
            null,
            '
                var RVTS = {
                    ajaxUrlModel: String.fromCharCode(' . ord($ajax_url_model[0]) . ")+'" . substr($ajax_url_model, 1) . "',
                    start: {$start},
                    perPage: {$per_page},
                    next: " . ($start + $per_page) . ",
                    total: {$total},
                    urlModel: String.fromCharCode(" . ord($url_model[0]) . ")+'" . substr($url_model, 1) . "',
                    moreMsg: '{$moreMsg}',
                    prevMsg: '" . l10n('Previous') . "',
                    ajaxLoaderImage: '{$ajax_loader_image}'
                };
                jQuery('.navigationBar').hide();
            "
        );
        return $thumbs;
    }

    public static function on_index_thumbnails_ajax(
        array $thumbs
    ): void {
        global $template;
        $template->assign('thumbnails', $thumbs);
        header('Content-Type: text/html; charset=utf-8');
        $template->pparse('index_thumbnails');
        exit;
    }

    public static function on_end_index(): void
    {
        global $template;
        $req = null;
        foreach ($template->scriptLoader->get_all() as $script) {
            if ($script->load_mode == 2 && ! $script->is_remote() && count($script->precedents) == 0) {
                $req = $script->id;
            }
        }

        if ($req != null) {
            $my_base_name = basename(__DIR__);
            $template->func_combine_script([
                'id' => $my_base_name,
                'load' => 'async',
                'path' => 'plugins/' . $my_base_name . '/rv_tscroller.min.js',
                'require' => $req,
                'version' => RVTS_VERSION,
            ]);
        }

        //var_export($template->scriptLoader);
    }
}

add_event_handler('loc_end_section_init', RVTS::on_end_section_init(...));
