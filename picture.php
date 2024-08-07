<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

define('PHPWG_ROOT_PATH', './');
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');
include(PHPWG_ROOT_PATH . 'include/section_init.inc.php');
include_once(PHPWG_ROOT_PATH . 'include/functions_picture.inc.php');

// Check Access and exit when user status is not ok
check_status(ACCESS_GUEST);

// access authorization check
if (isset($page['category'])) {
    check_restrictions($page['category']['id']);
}

$page['rank_of'] = array_flip($page['items']);

// if this image_id doesn't correspond to this category, an error message is
// displayed, and execution is stopped
if (! isset($page['rank_of'][$page['image_id']])) {
    $query = 'SELECT id, file, level FROM images WHERE ';
    if ($page['image_id'] > 0) {
        $query .= "id = {$page['image_id']};";
    } else {// url given by file name
        assert(! empty($page['image_file']));
        $image_file_ = str_replace(['_', '%'], ['/_', '/%'], $page['image_file']);
        $query .= "file LIKE '{$image_file_}%' ESCAPE '/' LIMIT 1;";
    }

    if (! ($row = pwg_db_fetch_assoc(pwg_query($query)))) {// element does not exist
        page_not_found(
            'The requested image does not exist',
            duplicate_index_url()
        );
    }

    if ($row['level'] > $user['level']) {
        access_denied();
    }

    $page['image_id'] = $row['id'];
    $page['image_file'] = $row['file'];
    if (! isset($page['rank_of'][$page['image_id']])) {// the image can still be non accessible (filter/cat perm) and/or not in the set
        global $filter;
        if (! empty($filter['visible_images']) && ! in_array($page['image_id'], explode(',', (string) $filter['visible_images']))) {
            page_not_found(
                'The requested image is filtered',
                duplicate_index_url()
            );
        }

        if ($page['section'] == 'categories' && ! isset($page['category'])) {// flat view - all items
            access_denied();
        } else {// try to see if we can access it differently
            $filters_and_forbidden = get_sql_condition_FandF(
                [
                    'forbidden_categories' => 'category_id',
                ],
                ' AND'
            );
            $query =
            "SELECT id FROM images INNER JOIN image_category ON id = image_id WHERE id = {$page['image_id']} {$filters_and_forbidden} LIMIT 1;";
            if (pwg_db_num_rows(pwg_query($query)) == 0) {
                access_denied();
            } elseif ($page['section'] == 'best_rated') {
                $page['rank_of'][$page['image_id']] = count($page['items']);
                $page['items'][] = $page['image_id'];
            } else {
                $url = make_picture_url(
                    [
                        'image_id' => $page['image_id'],
                        'image_file' => $page['image_file'],
                        'section' => 'categories',
                        'flat' => true,
                    ]
                );
                set_status_header($page['section'] == 'recent_pics' ? 301 : 302);
                redirect_http($url);
            }
        }
    }
}

// There is cookie, so we must handle it at the beginning
if (isset($_GET['metadata'])) {
    if (pwg_get_session_var('show_metadata') == null) {
        pwg_set_session_var('show_metadata', 1);
    } else {
        pwg_unset_session_var('show_metadata');
    }
}

// add default event handler for rendering element content
add_event_handler('render_element_content', default_picture_content(...));
// add default event handler for rendering element description
add_event_handler('render_element_description', pwg_nl2br(...));

/**
 * pwg_nl2br is useful for PHP 5.2 which doesn't accept more than 1
 * parameter on nl2br() (and anyway the second parameter of nl2br does not
 * match what Piwigo gives.
 */
function pwg_nl2br(
    string $string
): string {
    return nl2br($string);
}

trigger_notify('loc_begin_picture');

// this is the default handler that generates the display for the element
function default_picture_content(
    string $content,
    array $element_info
): string {
    global $conf;

    if ($content !== '' && $content !== '0') {// someone hooked us - so we skip;
        return $content;
    }

    if (isset($_COOKIE['picture_deriv'])) {
        if (array_key_exists($_COOKIE['picture_deriv'], ImageStdParams::get_defined_type_map())) {
            pwg_set_session_var('picture_deriv', $_COOKIE['picture_deriv']);
        }

        setcookie('picture_deriv', false, [
            'expires' => 0,
            'path' => cookie_path(),
        ]);
    }

    $deriv_type = pwg_get_session_var('picture_deriv', $conf['derivative_default_size']);
    $selected_derivative = $element_info['derivatives'][$deriv_type];

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

        // in case we do not display the sizes icon, we only add the selected size to unique_derivatives
        if ($conf['picture_sizes_icon'] || $type == $deriv_type) {
            $unique_derivatives[$type] = $derivative;
        }
    }

    global $page, $template;

    if ($show_original) {
        $template->assign('U_ORIGINAL', $element_info['element_url']);
    }

    $template->append('current', [
        'selected_derivative' => $selected_derivative,
        'unique_derivatives' => $unique_derivatives,
    ], true);

    $template->set_filenames(
        [
            'default_content' => 'picture_content.tpl',
        ]
    );

    $template->assign(
        [
            'ALT_IMG' => $element_info['file'],
            'COOKIE_PATH' => cookie_path(),
        ]
    );
    return $template->parse('default_content', true);
}

// +-----------------------------------------------------------------------+
// |                            initialization                             |
// +-----------------------------------------------------------------------+

// caching first_rank, last_rank, current_rank in the displayed
// section. This should also help in readability.
$page['first_rank'] = 0;
$page['last_rank'] = count($page['items']) - 1;
$page['current_rank'] = $page['rank_of'][$page['image_id']];

// caching current item : readability purpose
$page['current_item'] = $page['image_id'];

if ($page['current_rank'] != $page['first_rank']) {
    // caching first & previous item : readability purpose
    $page['previous_item'] = $page['items'][$page['current_rank'] - 1];
    $page['first_item'] = $page['items'][$page['first_rank']];
}

if ($page['current_rank'] != $page['last_rank']) {
    // caching next & last item : readability purpose
    $page['next_item'] = $page['items'][$page['current_rank'] + 1];
    $page['last_item'] = $page['items'][$page['last_rank']];
}

$url_up = duplicate_index_url(
    [
        'start' =>
          floor($page['current_rank'] / $page['nb_image_page'])
          * $page['nb_image_page'],
    ],
    [
        'start',
    ]
);

$url_self = duplicate_picture_url();

// +-----------------------------------------------------------------------+
// |                                actions                                |
// +-----------------------------------------------------------------------+

/**
 * Actions are favorite adding, user comment deletion, setting the picture
 * as representative of the current category...
 *
 * Actions finish by a redirection
 */

if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'add_to_favorites':

            $query = "INSERT INTO favorites (image_id, user_id) VALUES ({$page['image_id']}, {$user['id']});";
            pwg_query($query);

            redirect($url_self);

            break;

        case 'remove_from_favorites':

            $query = "DELETE FROM favorites WHERE user_id = {$user['id']} AND image_id = {$page['image_id']};";
            pwg_query($query);

            if ($page['section'] == 'favorites') {
                redirect($url_up);
            } else {
                redirect($url_self);
            }

            break;

        case 'set_as_representative':

            if (is_admin() && isset($page['category'])) {
                $query = "UPDATE categories SET representative_picture_id = {$page['image_id']} WHERE id = {$page['category']['id']};";
                pwg_query($query);
                pwg_activity('album', $page['category']['id'], 'edit', [
                    'action' => $_GET['action'],
                    'image_id' => $page['image_id'],
                ]);

                include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
                invalidate_user_cache();
            }

            redirect($url_self);

            break;

        case 'add_to_caddie':

            fill_caddie([$page['image_id']]);
            redirect($url_self);
            break;

        case 'rate':

            include_once(PHPWG_ROOT_PATH . 'include/functions_rate.inc.php');
            rate_picture($page['image_id'], $_POST['rate']);
            redirect($url_self);

            // no break
        case 'edit_comment':

            include_once(PHPWG_ROOT_PATH . 'include/functions_comment.inc.php');
            check_input_parameter('comment_to_edit', $_GET, false, PATTERN_ID);
            $author_id = get_comment_author_id($_GET['comment_to_edit']);

            if (can_manage_comment('edit', $author_id)) {
                if (! empty($_POST['content'])) {
                    check_pwg_token();
                    $comment_action = update_user_comment(
                        [
                            'comment_id' => $_GET['comment_to_edit'],
                            'image_id' => $page['image_id'],
                            'content' => $_POST['content'],
                            'website_url' => $_POST['website_url'],
                        ],
                        $_POST['key']
                    );

                    $perform_redirect = false;
                    switch ($comment_action) {
                        case 'moderate':
                            $_SESSION['page_infos'][] = l10n('An administrator must authorize your comment before it is visible.');
                            // no break
                        case 'validate':
                            $_SESSION['page_infos'][] = l10n('Your comment has been registered');
                            $perform_redirect = true;
                            break;
                        case 'reject':
                            $_SESSION['page_errors'][] = l10n('Your comment has NOT been registered because it did not pass the validation rules');
                            break;
                        default:
                            trigger_error('Invalid comment action ' . $comment_action, E_USER_WARNING);
                    }

                    if ($perform_redirect) {
                        redirect($url_self);
                    }

                    unset($_POST['content']);
                }

                $edit_comment = $_GET['comment_to_edit'];
            }

            break;

        case 'delete_comment':

            check_pwg_token();

            include_once(PHPWG_ROOT_PATH . 'include/functions_comment.inc.php');

            check_input_parameter('comment_to_delete', $_GET, false, PATTERN_ID);

            $author_id = get_comment_author_id($_GET['comment_to_delete']);

            if (can_manage_comment('delete', $author_id)) {
                delete_user_comment($_GET['comment_to_delete']);
            }

            redirect($url_self);

            // no break
        case 'validate_comment':

            check_pwg_token();

            include_once(PHPWG_ROOT_PATH . 'include/functions_comment.inc.php');

            check_input_parameter('comment_to_validate', $_GET, false, PATTERN_ID);

            $author_id = get_comment_author_id($_GET['comment_to_validate']);

            if (can_manage_comment('validate', $author_id)) {
                validate_user_comment($_GET['comment_to_validate']);
            }

            redirect($url_self);

    }
}

//---------- incrementation of the number of hits
$inc_hit_count = ! isset($_POST['content']);
// don't increment counter if in the Mozilla Firefox prefetch
if (isset($_SERVER['HTTP_X_MOZ']) && $_SERVER['HTTP_X_MOZ'] == 'prefetch') {
    $inc_hit_count = false;
} else {
    // don't increment counter if comming from the same picture (actions)
    if (pwg_get_session_var('referer_image_id', 0) == $page['image_id']) {
        $inc_hit_count = false;
    }

    pwg_set_session_var('referer_image_id', $page['image_id']);
}

// don't increment if adding a comment
if (trigger_change('allow_increment_element_hit_count', $inc_hit_count, $page['image_id'])) {
    increase_image_visit_counter($page['image_id']);
}

//---------------------------------------------------------- related categories
$filters_and_forbidden = get_sql_condition_FandF(
    [
        'forbidden_categories' => 'id',
        'visible_categories' => 'id',
    ],
    'AND'
);
$query =
"SELECT id, uppercats, commentable, visible, status, global_rank FROM image_category INNER JOIN categories ON category_id = id
 WHERE image_id = {$page['image_id']} {$filters_and_forbidden};";
$related_categories = query2array($query);
usort($related_categories, global_rank_compare(...));
//-------------------------first, prev, current, next & last picture management
$picture = [];

$ids = [$page['image_id']];
if (isset($page['previous_item'])) {
    $ids[] = $page['previous_item'];
    $ids[] = $page['first_item'];
}

if (isset($page['next_item'])) {
    $ids[] = $page['next_item'];
    $ids[] = $page['last_item'];
}

$ids_ = implode(',', $ids);
$query = "SELECT * FROM images WHERE id IN ({$ids_});";

$result = pwg_query($query);

while ($row = pwg_db_fetch_assoc($result)) {
    if (isset($page['previous_item']) && $row['id'] == $page['previous_item']) {
        $i = 'previous';
    } elseif (isset($page['next_item']) && $row['id'] == $page['next_item']) {
        $i = 'next';
    } elseif (isset($page['first_item']) && $row['id'] == $page['first_item']) {
        $i = 'first';
    } elseif (isset($page['last_item']) && $row['id'] == $page['last_item']) {
        $i = 'last';
    } else {
        $i = 'current';
    }

    $row['src_image'] = new SrcImage($row);
    $row['derivatives'] = DerivativeImage::get_all($row['src_image']);

    $extTab = explode('.', (string) $row['path']);
    $row['path_ext'] = strtolower(get_extension($row['path']));
    $row['file_ext'] = strtolower(get_extension($row['file']));

    if ($i === 'current') {
        $row['element_path'] = get_element_path($row);

        if ($row['src_image']->is_original() !== 0) {// we have a photo
            if ($user['enabled_high'] == 'true') {
                $row['element_url'] = $row['src_image']->get_url();
                $row['download_url'] = get_action_url($row['id'], 'e', true);
            }
        } else { // not a pic - need download link
            $row['element_url'] = get_element_url($row);
            $row['download_url'] = get_action_url($row['id'], 'e', true);
        }
    }

    $row['url'] = duplicate_picture_url(
        [
            'image_id' => $row['id'],
            'image_file' => $row['file'],
        ],
        [
            'start',
        ]
    );

    $picture[$i] = $row;
    $picture[$i]['TITLE'] = render_element_name($row);
    $picture[$i]['TITLE_ESC'] = str_replace('"', '&quot;', $picture[$i]['TITLE']);

    if ($i === 'previous' && $page['previous_item'] == $page['first_item']) {
        $picture['first'] = $picture[$i];
    }

    if ($i === 'next' && $page['next_item'] == $page['last_item']) {
        $picture['last'] = $picture[$i];
    }
}

$slideshow_params = [];
$slideshow_url_params = [];

if (isset($_GET['slideshow'])) {
    $page['slideshow'] = true;
    $page['meta_robots'] = [
        'noindex' => 1,
        'nofollow' => 1,
    ];

    $slideshow_params = decode_slideshow_params($_GET['slideshow']);
    $slideshow_url_params['slideshow'] = encode_slideshow_params($slideshow_params);

    if ($slideshow_params['play']) {
        $id_pict_redirect = '';
        if (isset($page['next_item'])) {
            $id_pict_redirect = 'next';
        } elseif ($slideshow_params['repeat'] && isset($page['first_item'])) {
            $id_pict_redirect = 'first';
        }

        if ($id_pict_redirect !== '' && $id_pict_redirect !== '0') {
            // $refresh, $url_link and $title are required for creating
            // an automated refresh page in header.tpl
            $refresh = $slideshow_params['period'];
            $url_link = add_url_params(
                $picture[$id_pict_redirect]['url'],
                $slideshow_url_params
            );
        }
    }
} else {
    $page['slideshow'] = false;
}

if ($page['slideshow'] && $conf['light_slideshow']) {
    $template->set_filenames([
        'slideshow' => 'slideshow.tpl',
    ]);
} else {
    $template->set_filenames([
        'picture' => 'picture.tpl',
    ]);
}

$title = $picture['current']['TITLE'];
$title_nb = ($page['current_rank'] + 1) . '/' . count($page['items']);

// metadata
$url_metadata = duplicate_picture_url();
$url_metadata = add_url_params($url_metadata, [
    'metadata' => null,
]);

// do we have a plugin that can show metadata for something else than images?
$metadata_showable = trigger_change(
    'get_element_metadata_available',
    (
        ($conf['show_exif'] || $conf['show_iptc']) && ! $picture['current']['src_image']->is_mimetype()
    ),
    $picture['current']
);

if ($metadata_showable && pwg_get_session_var('show_metadata')) {
    $page['meta_robots'] = [
        'noindex' => 1,
        'nofollow' => 1,
    ];
}

$page['body_id'] = 'thePicturePage';

// allow plugins to change what we computed before passing data to template
$picture = trigger_change('picture_pictures_data', $picture);

//------------------------------------------------------- navigation management
foreach (['first', 'previous', 'next', 'last', 'current'] as $which_image) {
    if (isset($picture[$which_image])) {
        $template->assign(
            $which_image,
            array_merge(
                $picture[$which_image],
                [
                    // Params slideshow was transmit to navigation buttons
                    'U_IMG' =>
                      add_url_params(
                          $picture[$which_image]['url'],
                          $slideshow_url_params
                      ),
                ]
            )
        );
    }
}

if ($conf['picture_download_icon'] && ! empty($picture['current']['download_url']) && $user['enabled_high'] == 'true') {
    $template->append('current', [
        'U_DOWNLOAD' => $picture['current']['download_url'],
    ], true);

    if ($conf['enable_formats']) {
        $query = "SELECT * FROM image_format WHERE image_id = {$picture['current']['id']};";
        $formats = query2array($query);

        // let's add the original as a format among others. It will just have a
        // specific download URL
        array_unshift(
            $formats,
            [
                'download_url' => $picture['current']['download_url'],
                'ext' => get_extension($picture['current']['file']),
                'filesize' => $picture['current']['filesize'],
            ]
        );

        foreach ($formats as &$format) {
            if (! isset($format['download_url'])) {
                $format['download_url'] = 'action.php?format=' . $format['format_id'] . '&amp;download';
            }

            $format['label'] = strtoupper((string) $format['ext']);
            $lang_key = 'format ' . strtoupper((string) $format['ext']);
            if (isset($lang[$lang_key])) {
                $format['label'] = $lang[$lang_key];
            }

            $format['filesize'] = sprintf('%.1fMB', $format['filesize'] / 1024);
        }

        $template->append('current', [
            'formats' => $formats,
        ], true);
    }
}

if ($page['slideshow']) {
    $tpl_slideshow = [];

    //slideshow end
    $template->assign(
        [
            'U_SLIDESHOW_STOP' => $picture['current']['url'],
        ]
    );

    foreach (['repeat', 'play'] as $p) {
        $var_name =
          'U_'
          . ($slideshow_params[$p] ? 'STOP_' : 'START_')
          . strtoupper($p);

        $tpl_slideshow[$var_name] =
              add_url_params(
                  $picture['current']['url'],
                  [
                      'slideshow' =>
                                    encode_slideshow_params(
                                        array_merge(
                                            $slideshow_params,
                                            [
                                                $p => ! $slideshow_params[$p],
                                            ]
                                        )
                                    ),
                  ]
              );
    }

    foreach (['dec', 'inc'] as $op) {
        $new_period = $slideshow_params['period'] + ((($op === 'dec') ? -1 : 1) * $conf['slideshow_period_step']);
        $new_slideshow_params =
          correct_slideshow_params(
              array_merge(
                  $slideshow_params,
                  [
                      'period' => $new_period,
                  ]
              )
          );

        if ($new_slideshow_params['period'] === $new_period) {
            $var_name = 'U_' . strtoupper($op) . '_PERIOD';
            $tpl_slideshow[$var_name] =
                  add_url_params(
                      $picture['current']['url'],
                      [
                          'slideshow' => encode_slideshow_params($new_slideshow_params),
                      ]
                  );
        }
    }

    $template->assign('slideshow', $tpl_slideshow);
} elseif ($conf['picture_slideshow_icon']) {
    $template->assign(
        [
            'U_SLIDESHOW_START' =>
              add_url_params(
                  $picture['current']['url'],
                  [
                      'slideshow' => '',
                  ]
              ),
        ]
    );
}

$template->assign(
    [
        'SECTION_TITLE' => $page['section_title'],
        'PHOTO' => $title_nb,
        'IS_HOME' => ($page['section'] == 'categories' && ! isset($page['category'])),

        'LEVEL_SEPARATOR' => $conf['level_separator'],

        'U_UP' => $url_up,
        'DISPLAY_NAV_BUTTONS' => $conf['picture_navigation_icons'],
        'DISPLAY_NAV_THUMB' => $conf['picture_navigation_thumb'],
    ]
);

if ($conf['picture_metadata_icon']) {
    $template->assign('U_METADATA', $url_metadata);
}

//------------------------------------------------------- upper menu management

// admin links
if (is_admin()) {
    if (isset($page['category']) && $conf['picture_representative_icon']) {
        $template->assign(
            [
                'U_SET_AS_REPRESENTATIVE' => add_url_params(
                    $url_self,
                    [
                        'action' => 'set_as_representative',
                    ]
                ),
            ]
        );
    }

    if ($conf['picture_edit_icon']) {
        $url_admin =
          get_root_url() . 'admin.php?page=photo-' . $page['image_id']
          . (isset($page['category']) ? '&amp;cat_id=' . $page['category']['id'] : '')
        ;

        $template->assign('U_PHOTO_ADMIN', $url_admin);
    }

    if ($conf['picture_caddie_icon']) {
        $template->assign(
            'U_CADDIE',
            add_url_params($url_self, [
                'action' => 'add_to_caddie',
            ])
        );
    }

    $template->assign('available_permission_levels', get_privacy_level_options());
}

// favorite manipulation
if (! is_a_guest() && $conf['picture_favorite_icon']) {
    // verify if the picture is already in the favorite of the user
    $query = "SELECT COUNT(*) AS nb_fav FROM favorites WHERE image_id = {$page['image_id']} AND user_id = {$user['id']};";
    $row = pwg_db_fetch_assoc(pwg_query($query));
    $is_favorite = $row['nb_fav'] != 0;

    $template->assign(
        'favorite',
        [
            'IS_FAVORITE' => $is_favorite,
            'U_FAVORITE' => add_url_params(
                $url_self,
                [
                    'action' => $is_favorite ? 'remove_from_favorites' : 'add_to_favorites',
                ]
            ),
        ]
    );
}

//--------------------------------------------------------- picture information
// legend
if (isset($picture['current']['comment']) && ! empty($picture['current']['comment'])) {
    $template->assign(
        'COMMENT_IMG',
        trigger_change(
            'render_element_description',
            $picture['current']['comment'],
            'picture_page_element_description'
        )
    );
}

// author
if (! empty($picture['current']['author'])) {
    $infos['INFO_AUTHOR'] = $picture['current']['author'];
}

// creation date
if (! empty($picture['current']['date_creation'])) {
    $val = format_date($picture['current']['date_creation']);
    $url = make_index_url(
        [
            'chronology_field' => 'created',
            'chronology_style' => 'monthly',
            'chronology_view' => 'list',
            'chronology_date' => explode('-', substr((string) $picture['current']['date_creation'], 0, 10)),
        ]
    );
    $infos['INFO_CREATION_DATE'] =
      '<a href="' . $url . '" rel="nofollow">' . $val . '</a>';
}

// date of availability
$val = format_date($picture['current']['date_available']);
$url = make_index_url(
    [
        'chronology_field' => 'posted',
        'chronology_style' => 'monthly',
        'chronology_view' => 'list',
        'chronology_date' => explode(
            '-',
            substr((string) $picture['current']['date_available'], 0, 10)
        ),
    ]
);
$infos['INFO_POSTED_DATE'] = '<a href="' . $url . '" rel="nofollow">' . $val . '</a>';

// size in pixels
if ($picture['current']['src_image']->is_original() && isset($picture['current']['width'])) {
    $infos['INFO_DIMENSIONS'] =
      $picture['current']['width'] . '*' . $picture['current']['height'];
}

// filesize
if (! empty($picture['current']['filesize'])) {
    $infos['INFO_FILESIZE'] = l10n('%d Kb', $picture['current']['filesize']);
}

// number of visits
$infos['INFO_VISITS'] = $picture['current']['hit'];

// file
$infos['INFO_FILE'] = $picture['current']['file'];

$template->assign($infos);
$template->assign('display_info', unserialize($conf['picture_informations']));

// related tags
$tags = get_common_tags([$page['image_id']], -1);
if ($tags !== []) {
    foreach ($tags as $tag) {
        $template->append(
            'related_tags',
            array_merge(
                $tag,
                [
                    'URL' => make_index_url(
                        [
                            'tags' => [$tag],
                        ]
                    ),
                    'U_TAG_IMAGE' => duplicate_picture_url(
                        [
                            'section' => 'tags',
                            'tags' => [$tag],
                        ]
                    ),
                ]
            )
        );
    }
}

// related categories
if (count($related_categories) == 1 && isset($page['category']) && $related_categories[0]['id'] == $page['category']['id']) { // no need to go to db, we have all the info
    $template->append(
        'related_categories',
        get_cat_display_name($page['category']['upper_names'])
    );
} else { // use only 1 sql query to get names for all related categories
    $ids = [];
    foreach ($related_categories as $category) {// add all uppercats to $ids
        $ids = array_merge($ids, explode(',', (string) $category['uppercats']));
    }

    $ids = array_unique($ids);
    $ids_ = implode(',', $ids);
    $query = "SELECT id, name, permalink FROM categories WHERE id IN ({$ids_});";
    $cat_map = query2array($query, 'id');
    foreach ($related_categories as $category) {
        $cats = [];
        foreach (explode(',', (string) $category['uppercats']) as $id) {
            $cats[] = $cat_map[$id];
        }

        $template->append('related_categories', get_cat_display_name($cats));
    }
}

// maybe someone wants a special display (call it before page_header so that
// they can add stylesheets)
$element_content = trigger_change(
    'render_element_content',
    '',
    $picture['current']
);
$template->assign('ELEMENT_CONTENT', $element_content);

if (isset($picture['next']) && $picture['next']['src_image']->is_original() && $template->get_template_vars('U_PREFETCH') == null && ! str_contains((string) $_SERVER['HTTP_USER_AGENT'], 'Chrome/')) {
    $template->assign(
        'U_PREFETCH',
        $picture['next']['derivatives'][pwg_get_session_var('picture_deriv', $conf['derivative_default_size'])]->get_url()
    );
}

$template->assign(
    'U_CANONICAL',
    make_picture_url(
        [
            'image_id' => $picture['current']['id'],
            'image_file' => $picture['current']['file'],
        ]
    )
);

// +-----------------------------------------------------------------------+
// |                               sub pages                               |
// +-----------------------------------------------------------------------+

include(PHPWG_ROOT_PATH . 'include/picture_rate.inc.php');
if ($conf['activate_comments']) {
    include(PHPWG_ROOT_PATH . 'include/picture_comment.inc.php');
}

if ($metadata_showable && pwg_get_session_var('show_metadata') != null) {
    include(PHPWG_ROOT_PATH . 'include/picture_metadata.inc.php');
}

// include menubar
$themeconf = $template->get_template_vars('themeconf');
if ($conf['picture_menu'] && (! isset($themeconf['hide_menu_on']) || ! in_array('thePicturePage', $themeconf['hide_menu_on']))) {
    if (! isset($page['start'])) {
        $page['start'] = 0;
    }

    include(PHPWG_ROOT_PATH . 'include/menubar.inc.php');
}

include(PHPWG_ROOT_PATH . 'include/page_header.php');
trigger_notify('loc_end_picture');
flush_page_messages();
if ($page['slideshow'] && $conf['light_slideshow']) {
    $template->pparse('slideshow');
} else {
    $template->parse_picture_buttons();
    $template->pparse('picture');
}

//------------------------------------------------------------ log informations
pwg_log($picture['current']['id'], 'picture');
include(PHPWG_ROOT_PATH . 'include/page_tail.php');
