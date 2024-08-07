<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | Piwigo - a PHP based photo gallery                                    |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2014 Piwigo Team                  http://piwigo.org |
// | Copyright(C) 2003-2008 PhpWebGallery Team    http://phpwebgallery.net |
// | Copyright(C) 2002-2003 Pierrick LE GALL   http://le-gall.net/pierrick |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

function language_controler_switch(): void
{
    global $user;

    $same = $user['language'];

    if (isset($_GET['lang'])) {
        require_once PHPWG_ROOT_PATH . 'admin/include/languages.class.php';
        $languages = new languages();
        if (! in_array($_GET['lang'], array_keys($languages->fs_languages))) {
            $_GET['lang'] = PHPWG_DEFAULT_LANGUAGE;
        }

        if (! empty($_GET['lang']) && file_exists(PHPWG_ROOT_PATH . 'language/' . $_GET['lang'] . '/common.lang.php')) {
            if (is_a_guest() || is_generic()) {
                pwg_set_session_var('lang_switch', $_GET['lang']);
            } else {
                $query = "UPDATE user_infos SET language = '{$_GET['lang']}' WHERE user_id = {$user['id']};";
                pwg_query($query);
            }

            $user['language'] = $_GET['lang'];
        }

        if (isset($_GET['redirect_to_home'])) {
            redirect(get_absolute_root_url());
        }
    } elseif ((is_a_guest() || is_generic())) {
        $user['language'] = pwg_get_session_var('lang_switch', $user['language']);
    }

    // Reload language only if it isn't the same one
    if ($same !== $user['language']) {
        load_language('common.lang', '', [
            'language' => $user['language'],
        ]);

        load_language(
            'lang',
            PHPWG_ROOT_PATH . PWG_LOCAL_DIR,
            [
                'language' => $user['language'],
                'no_fallback' => true,
                'local' => true,
            ]
        );

        if (defined('IN_ADMIN') && IN_ADMIN) {
            // Never currently
            load_language('admin.lang', '', [
                'language' => $user['language'],
            ]);
        }
    }
}

function language_controler_flags(): void
{
    global $user, $template, $conf, $page;

    $available_lang = get_languages();

    if (isset($conf['no_flag_languages'])) {
        $available_lang = array_diff_key($available_lang, array_flip($conf['no_flag_languages']));
    }

    $url_starting = get_query_string_diff(['lang']);

    if (isset($page['section']) && $page['section'] == 'additional_page' && isset($page['additional_page'])) {
        $base_url = make_index_url([
            'section' => 'page',
        ]) . '/' . ($page['additional_page']['permalink'] ?? $page['additional_page']['id']);
    } else {
        $base_url = duplicate_index_url();
    }

    // Bootstrap Darkroom does not consider index?/categories as the homepage, thus doesn't display
    // the full height banner (if configured this way). We need to force a redirect (after language
    // has switched) in this specific case.
    $url_options = [];
    if (preg_match('/^index(\.php\?)?\?\/categories$/', $base_url)) {
        $url_options['redirect_to_home'] = 1;
    }

    foreach ($available_lang as $code => $displayname) {
        $qlc = [
            'url' => add_url_params($base_url, array_merge($url_options, [
                'lang' => $code,
            ])),
            'alt' => ucwords($displayname),
            'title' => substr($displayname, 0, -4), // remove [FR] or [RU]
            'code' => $code,
        ];

        $lsw['flags'][$code] = $qlc;

        if ($code == $user['language']) {
            $lsw['Active'] = $qlc;
        }
    }

    $safe_themes = ['clear', 'dark', 'elegant', 'Sylvia', 'simple-grey', 'simple-black', 'simple-white', 'kardon', 'luciano', 'montblancxl']; // stripped (2.6)

    $template->assign([
        'lang_switch' => $lsw,
        'LANGUAGE_SWITCH_PATH' => LANGUAGE_SWITCH_PATH,
        'LANGUAGE_SWITCH_LOAD_STYLE' => ! in_array($user['theme'], $safe_themes),
    ]);

    $template->set_template_dir(realpath(__DIR__));
    $template->set_filename('language_flags', 'language_switch_flags.tpl');
    $template->concat('PLUGIN_INDEX_ACTIONS', $template->parse('language_flags', true));
    $template->clear_assign('lang_switch');
}
