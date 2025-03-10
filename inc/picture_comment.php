<?php

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * This file is included by the picture page to manage user comments
 */

// the picture is commentable if it belongs at least to one category which
// is commentable
use Piwigo\inc\dblayer\functions_mysqli;
use Piwigo\inc\functions;
use Piwigo\inc\functions_comment;
use Piwigo\inc\functions_html;
use Piwigo\inc\functions_plugins;
use Piwigo\inc\functions_session;
use Piwigo\inc\functions_url;
use Piwigo\inc\functions_user;

$page['show_comments'] = false;
foreach ($related_categories as $category) {
    if ($category['commentable'] == 'true') {
        $page['show_comments'] = true;
        break;
    }
}

if ($page['show_comments'] and isset($_POST['content'])) {
    if (functions_user::is_a_guest() and ! $conf['comments_forall']) {
        die('Session expired');
    }

    $comm = [
        'author' => empty(@$_POST['author']) ? '' : trim(@$_POST['author']),
        'content' => empty(@$_POST['content']) ? '' : trim($_POST['content']),
        'website_url' => empty(@$_POST['website_url']) ? '' : trim(@$_POST['website_url']),
        'email' => empty(@$_POST['email']) ? '' : trim(@$_POST['email']),
        'image_id' => $page['image_id'],
    ];

    include_once(PHPWG_ROOT_PATH . 'inc/functions_comment.php');

    $comment_action = functions_comment::insert_user_comment($comm, @$_POST['key'], $page['errors']);

    switch ($comment_action) {
        case 'moderate':
            $page['infos'][] = functions::l10n('An administrator must authorize your comment before it is visible.');
            // no break
        case 'validate':
            $page['infos'][] = functions::l10n('Your comment has been registered');
            break;
        case 'reject':
            functions_html::set_status_header(403);
            $page['errors'][] = functions::l10n('Your comment has NOT been registered because it did not pass the validation rules');
            break;
        default:
            trigger_error('Invalid comment action ' . $comment_action, E_USER_WARNING);
    }

    // allow plugins to notify what's going on
    functions_plugins::trigger_notify(
        'user_comment_insertion',
        array_merge($comm, [
            'action' => $comment_action,
        ])
    );
} elseif (isset($_POST['content'])) {
    functions_html::set_status_header(403);
    die('ugly spammer');
}

if ($page['show_comments']) {
    if (! functions_user::is_admin()) {
        $validated_clause = '  AND validated = \'true\'';
    } else {
        $validated_clause = '';
    }

    // number of comments for this picture
    $query = '
SELECT
    COUNT(*) AS nb_comments
  FROM comments
  WHERE image_id = ' . $page['image_id']
    . $validated_clause . '
;';
    $row = functions_mysqli::pwg_db_fetch_assoc(functions_mysqli::pwg_query($query));

    // navigation bar creation
    if (! isset($page['start'])) {
        $page['start'] = 0;
    }

    $navigation_bar = functions::create_navigation_bar(
        functions_url::duplicate_picture_url([], ['start']),
        $row['nb_comments'],
        $page['start'],
        $conf['nb_comment_page'],
        true // We want a clean URL
    );

    $template->assign(
        [
            'COMMENT_COUNT' => $row['nb_comments'],
            'navbar' => $navigation_bar,
            'comments' => [],
        ]
    );

    if ($row['nb_comments'] > 0) {
        // comments order (get, session, conf)
        if (! empty($_GET['comments_order']) && in_array(strtoupper($_GET['comments_order']), ['ASC', 'DESC'])) {
            functions_session::pwg_set_session_var('comments_order', $_GET['comments_order']);
        }

        $comments_order = functions_session::pwg_get_session_var('comments_order', $conf['comments_order']);

        $template->assign([
            'COMMENTS_ORDER_URL' => functions_url::add_url_params(functions_url::duplicate_picture_url(), [
                'comments_order' => ($comments_order == 'ASC' ? 'DESC' : 'ASC'),
            ]),
            'COMMENTS_ORDER_TITLE' => $comments_order == 'ASC' ? functions::l10n('Show latest comments first') : functions::l10n('Show oldest comments first'),
        ]);

        $query = '
SELECT
    com.id,
    com.author,
    com.author_id,
    u.' . $conf['user_fields']['email'] . ' AS user_email,
    com.date,
    com.image_id,
    com.website_url,
    com.email,
    com.content,
    com.validated
  FROM comments AS com
  LEFT JOIN users AS u
    ON u.' . $conf['user_fields']['id'] . ' = author_id
  WHERE com.image_id = ' . $page['image_id'] . '
    ' . $validated_clause . '
  ORDER BY com.date ' . $comments_order . '
  LIMIT ' . $conf['nb_comment_page'] . ' OFFSET ' . $page['start'] . '
;';
        $result = functions_mysqli::pwg_query($query);

        while ($row = functions_mysqli::pwg_db_fetch_assoc($result)) {
            if ($row['author'] == 'guest') {
                $row['author'] = functions::l10n('guest');
            }

            $email = null;
            if (! empty($row['user_email'])) {
                $email = $row['user_email'];
            } elseif (! empty($row['email'])) {
                $email = $row['email'];
            }

            $tpl_comment =
              [
                  'ID' => $row['id'],
                  'AUTHOR' => functions_plugins::trigger_change('render_comment_author', $row['author']),
                  'DATE' => functions::format_date($row['date'], ['day_name', 'day', 'month', 'year', 'time']),
                  'CONTENT' => functions_plugins::trigger_change('render_comment_content', $row['content']),
                  'WEBSITE_URL' => $row['website_url'],
              ];

            if (functions_user::can_manage_comment('delete', $row['author_id'])) {
                $tpl_comment['U_DELETE'] = functions_url::add_url_params(
                    $url_self,
                    [
                        'action' => 'delete_comment',
                        'comment_to_delete' => $row['id'],
                        'pwg_token' => functions::get_pwg_token(),
                    ]
                );
            }

            if (functions_user::can_manage_comment('edit', $row['author_id'])) {
                $tpl_comment['U_EDIT'] = functions_url::add_url_params(
                    $url_self,
                    [
                        'action' => 'edit_comment',
                        'comment_to_edit' => $row['id'],
                    ]
                );
                if (isset($edit_comment) and ($row['id'] == $edit_comment)) {
                    $tpl_comment['IN_EDIT'] = true;
                    $key = functions::get_ephemeral_key(2, $page['image_id']);
                    $tpl_comment['KEY'] = $key;
                    $tpl_comment['CONTENT'] = $row['content'];
                    $tpl_comment['PWG_TOKEN'] = functions::get_pwg_token();
                    $tpl_comment['U_CANCEL'] = $url_self;
                }
            }

            if (functions_user::is_admin()) {
                $tpl_comment['EMAIL'] = $email;

                if ($row['validated'] != 'true') {
                    $tpl_comment['U_VALIDATE'] = functions_url::add_url_params(
                        $url_self,
                        [
                            'action' => 'validate_comment',
                            'comment_to_validate' => $row['id'],
                            'pwg_token' => functions::get_pwg_token(),
                        ]
                    );
                }
            }

            $template->append('comments', $tpl_comment);
        }
    }

    $show_add_comment_form = true;
    if (isset($edit_comment)) {
        $show_add_comment_form = false;
    }

    if (functions_user::is_a_guest() and ! $conf['comments_forall']) {
        $show_add_comment_form = false;
    }

    if ($show_add_comment_form) {
        $key = functions::get_ephemeral_key(3, $page['image_id']);

        $tpl_var = [
            'F_ACTION' => $url_self,
            'KEY' => $key,
            'CONTENT' => '',
            'SHOW_AUTHOR' => ! functions_user::is_classic_user(),
            'AUTHOR_MANDATORY' => $conf['comments_author_mandatory'],
            'AUTHOR' => '',
            'WEBSITE_URL' => '',
            'SHOW_EMAIL' => ! functions_user::is_classic_user() or empty($user['email']),
            'EMAIL_MANDATORY' => $conf['comments_email_mandatory'],
            'EMAIL' => '',
            'SHOW_WEBSITE' => $conf['comments_enable_website'],
        ];

        if (@$comment_action == 'reject') {
            foreach (['content', 'author', 'website_url', 'email'] as $k) {
                $tpl_var[strtoupper($k)] = isset($_POST[$k]) ? htmlspecialchars(stripslashes(@$_POST[$k])) : '';
            }
        }

        $template->assign('comment_add', $tpl_var);
    }

    $template->set_filenames([
        'comment_list' => 'comment_list.tpl',
    ]);
    $template->assign_var_from_handle('COMMENT_LIST', 'comment_list');
}
