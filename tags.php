<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

// +-----------------------------------------------------------------------+
// |                           initialization                              |
// +-----------------------------------------------------------------------+

define('PHPWG_ROOT_PATH', './');
require_once PHPWG_ROOT_PATH . 'include/common.inc.php';

check_status(ACCESS_GUEST);

trigger_notify('loc_begin_tags');

// +-----------------------------------------------------------------------+
// |                       page header and options                         |
// +-----------------------------------------------------------------------+

$title = l10n('Tags');
$page['body_id'] = 'theTagsPage';

$template->set_filenames([
    'tags' => 'tags.tpl',
]);

$page['display_mode'] = $conf['tags_default_display_mode'];
if (isset($_GET['display_mode']) && in_array($_GET['display_mode'], ['cloud', 'letters'])) {
    $page['display_mode'] = $_GET['display_mode'];
}

foreach (['cloud', 'letters'] as $mode) {
    $template->assign(
        'U_' . strtoupper($mode),
        get_root_url() . 'tags.php' . ($conf['tags_default_display_mode'] == $mode ? '' : '?display_mode=' . $mode)
    );
}

$template->assign('display_mode', $page['display_mode']);

// find all tags available for the current user
$tags = get_available_tags();

// +-----------------------------------------------------------------------+
// |                       letter groups construction                      |
// +-----------------------------------------------------------------------+

if ($page['display_mode'] == 'letters') {
    // we want tags displayed in alphabetic order
    usort($tags, tag_alpha_compare(...));

    $current_letter = null;
    $nb_tags = count($tags);
    $current_column = 1;
    $current_tag_idx = 0;

    $letter = [
        'tags' => [],
    ];

    foreach ($tags as $tag) {
        $tag_letter = mb_strtoupper(mb_substr(pwg_transliterate($tag['name']), 0, 1, 'utf-8'), 'utf-8');

        if ($current_tag_idx == 0) {
            $current_letter = $tag_letter;
            $letter['TITLE'] = $tag_letter;
        }

        //Previous letter different from the next letter
        if ($tag_letter !== $current_letter) {
            if ($current_column < $conf['tag_letters_column_number'] && $current_tag_idx > $current_column * $nb_tags / $conf['tag_letters_column_number']) {
                $letter['CHANGE_COLUMN'] = true;
                $current_column++;
            }

            $letter['TITLE'] = $current_letter;

            $template->append(
                'letters',
                $letter
            );

            $current_letter = $tag_letter;
            $letter = [
                'tags' => [],
            ];
        }

        $letter['tags'][] = array_merge(
            $tag,
            [
                'URL' => make_index_url([
                    'tags' => [$tag],
                ]),
            ]
        );

        $current_tag_idx++;
    }

    // flush last letter
    if ($letter['tags'] !== []) {
        unset($letter['CHANGE_COLUMN']);
        $letter['TITLE'] = $current_letter;
        $template->append(
            'letters',
            $letter
        );
    }
} else {
    // +-----------------------------------------------------------------------+
    // |                        tag cloud construction                         |
    // +-----------------------------------------------------------------------+

    // we want only the first most represented tags, so we sort them by counter
    // and take the first tags
    usort($tags, tags_counter_compare(...));
    $tags = array_slice($tags, 0, $conf['full_tag_cloud_items_number']);

    // depending on its counter and the other tags counter, each tag has a level
    $tags = add_level_to_tags($tags);

    // we want tags diplayed in alphabetic order
    usort($tags, tag_alpha_compare(...));

    // display sorted tags
    foreach ($tags as $tag) {
        $template->append(
            'tags',
            array_merge(
                $tag,
                [
                    'URL' => make_index_url(
                        [
                            'tags' => [$tag],
                        ]
                    ),
                ]
            )
        );
    }
}

// include menubar
$themeconf = $template->get_template_vars('themeconf');
if (! isset($themeconf['hide_menu_on']) || ! in_array('theTagsPage', $themeconf['hide_menu_on'])) {
    require PHPWG_ROOT_PATH . 'include/menubar.inc.php';
}

require PHPWG_ROOT_PATH . 'include/page_header.php';
trigger_notify('loc_end_tags');
flush_page_messages();
$template->pparse('tags');
require PHPWG_ROOT_PATH . 'include/page_tail.php';
