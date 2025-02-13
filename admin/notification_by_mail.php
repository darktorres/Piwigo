<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

// +-----------------------------------------------------------------------+
// | include                                                               |
// +-----------------------------------------------------------------------+

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
include_once(PHPWG_ROOT_PATH . 'admin/include/functions_notification_by_mail.inc.php');
include_once(PHPWG_ROOT_PATH . 'admin/include/tabsheet.class.php');
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');
include_once(PHPWG_ROOT_PATH . 'include/functions_notification.inc.php');
include_once(PHPWG_ROOT_PATH . 'include/functions_mail.inc.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_ADMINISTRATOR);

check_input_parameter('mode', $_GET, false, '/^(param|subscribe|send)$/');

// +-----------------------------------------------------------------------+
// | Initialization                                                        |
// +-----------------------------------------------------------------------+
$base_url = get_root_url() . 'admin.php';
$must_repost = false;

// +-----------------------------------------------------------------------+
// | functions                                                             |
// +-----------------------------------------------------------------------+

/*
 * Do timeout treatment to finish sending mails
 *
 * @param $post_keyname: key of check_key post array
 * @param $check_key_treated: array of check_key treated
 * @return none
 */
function do_timeout_treatment($post_keyname, $check_key_treated = [])
{
    global $env_nbm, $base_url, $page, $must_repost;

    if ($env_nbm['is_sendmail_timeout']) {
        if (isset($_POST[$post_keyname])) {
            $post_count = count($_POST[$post_keyname]);
            $treated_count = count($check_key_treated);
            if ($treated_count != 0) {
                $time_refresh = ceil((get_moment() - $env_nbm['start_time']) * $post_count / $treated_count);
            } else {
                $time_refresh = 0;
            }
            $_POST[$post_keyname] = array_diff($_POST[$post_keyname], $check_key_treated);

            $must_repost = true;
            $page['errors'][] = l10n_dec(
                'Execution time is out, treatment must be continue [Estimated time: %d second].',
                'Execution time is out, treatment must be continue [Estimated time: %d seconds].',
                $time_refresh
            );
        }
    }

}

/*
 * Get the authorized_status for each tab
 * return corresponding status
 */
function get_tab_status($mode)
{
    $result = ACCESS_WEBMASTER;
    switch ($mode) {
        case 'param':
        case 'subscribe':
            $result = ACCESS_WEBMASTER;
            break;
        case 'send':
            $result = ACCESS_ADMINISTRATOR;
            break;
        default:
            $result = ACCESS_WEBMASTER;
            break;
    }
    return $result;
}

/*
 * Inserting News users
 */
function insert_new_data_user_mail_notification()
{
    global $conf, $page, $env_nbm, $base_url;

    // Set null mail_address empty
    $query = <<<SQL
        UPDATE users
        SET {$conf['user_fields']['email']} = NULL
        WHERE TRIM({$conf['user_fields']['email']}) = '';
        SQL;
    pwg_query($query);

    // null mail_address are not selected in the list
    $query = <<<SQL
        SELECT u.{$conf['user_fields']['id']} AS user_id, u.{$conf['user_fields']['username']} AS username, u.{$conf['user_fields']['email']} AS mail_address
        FROM users AS u
        LEFT JOIN user_mail_notification AS m ON u.{$conf['user_fields']['id']} = m.user_id
        WHERE u.{$conf['user_fields']['email']} IS NOT NULL
            AND m.user_id IS NULL
        ORDER BY user_id;
        SQL;

    $result = pwg_query($query);

    if (pwg_db_num_rows($result) > 0) {
        $inserts = [];
        $check_key_list = [];

        while ($nbm_user = pwg_db_fetch_assoc($result)) {
            // Calculate key
            $nbm_user['check_key'] = find_available_check_key();

            // Save key
            $check_key_list[] = $nbm_user['check_key'];

            // Insert new nbm_users
            $inserts[] = [
                'user_id' => $nbm_user['user_id'],
                'check_key' => $nbm_user['check_key'],
                'enabled' => 'false', // By default, if false, set to true with specific functions
            ];

            $page['infos'][] = l10n(
                'User %s [%s] added.',
                stripslashes($nbm_user['username']),
                $nbm_user['mail_address']
            );
        }

        // Insert new nbm_users
        mass_inserts('user_mail_notification', ['user_id', 'check_key', 'enabled'], $inserts);
        // Update field enabled with specific function
        $check_key_treated = do_subscribe_unsubscribe_notification_by_mail(
            true,
            $conf['nbm_default_value_user_enabled'],
            $check_key_list
        );

        // On timeout simulate like tabsheet send
        if ($env_nbm['is_sendmail_timeout']) {
            $quoted_check_key_list = quote_check_key_list(array_diff($check_key_list, $check_key_treated));
            if (count($quoted_check_key_list) != 0) {
                $imploded_check_key_list = implode(',', $quoted_check_key_list);
                $query = <<<SQL
                    DELETE FROM user_mail_notification
                    WHERE check_key IN ({$imploded_check_key_list});
                    SQL;
                $result = pwg_query($query);

                redirect($base_url . get_query_string_diff([], false), l10n('Operation in progress') . "\n" . l10n('Please wait...'));
            }
        }
    }
}

/*
 * Apply global functions to mail content
 * return customize mail content rendered
 */
function render_global_customize_mail_content($customize_mail_content)
{
    global $conf;

    if ($conf['nbm_send_html_mail'] and ! (strpos($customize_mail_content, '<') === 0)) {
        // On HTML mail, detects if the content is HTML format.
        // If it's plain text format, convert content to readable HTML
        return nl2br(htmlspecialchars($customize_mail_content));
    }

    return $customize_mail_content;

}

/*
 * Send mail for notification to all users
 * Return list of "selected" users for 'list_to_send'
 * Return list of "treated" check_key for 'send'
 */
function do_action_send_mail_notification($action = 'list_to_send', $check_key_list = [], $customize_mail_content = '')
{
    global $conf, $page, $user, $lang_info, $lang, $env_nbm;
    $return_list = [];

    if (in_array($action, ['list_to_send', 'send'])) {
        list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));

        $is_action_send = ($action == 'send');

        // disabled and null mail_address are not selected in the list
        $data_users = get_user_notifications('send', $check_key_list);

        // List all if it's defined on options or on timeout
        $is_list_all_without_test = ($env_nbm['is_sendmail_timeout'] or $conf['nbm_list_all_enabled_users_to_send']);

        // Check if exist news to list user or send mails
        if ((! $is_list_all_without_test) or ($is_action_send)) {
            if (count($data_users) > 0) {
                $datas = [];

                if (! isset($customize_mail_content)) {
                    $customize_mail_content = $conf['nbm_complementary_mail_content'];
                }

                $customize_mail_content =
                  trigger_change('nbm_render_global_customize_mail_content', $customize_mail_content);

                // Prepare message after change language
                if ($is_action_send) {
                    $msg_break_timeout = l10n('Time to send mail is limited. Others mails are skipped.');
                } else {
                    $msg_break_timeout = l10n('Prepared time for list of users to send mail is limited. Others users are not listed.');
                }

                // Begin nbm users environment
                begin_users_env_nbm($is_action_send);

                foreach ($data_users as $nbm_user) {
                    if ((! $is_action_send) and check_sendmail_timeout()) {
                        // Stop fill list on 'list_to_send' if the quota is overridden
                        $page['infos'][] = $msg_break_timeout;
                        break;
                    }
                    if (($is_action_send) and check_sendmail_timeout()) {
                        // Stop fill list on 'send' if the quota is overridden
                        $page['errors'][] = $msg_break_timeout;
                        break;
                    }

                    // set env nbm user
                    set_user_on_env_nbm($nbm_user, $is_action_send);

                    if ($is_action_send) {
                        $auth = null;
                        $add_url_params = [];

                        $auth_key = create_user_auth_key($nbm_user['user_id'], $nbm_user['status']);

                        if ($auth_key !== false) {
                            $auth = $auth_key['auth_key'];
                            $add_url_params['auth'] = $auth;
                        }

                        set_make_full_url();
                        // Fill return list of "treated" check_key for 'send'
                        $return_list[] = $nbm_user['check_key'];

                        if ($conf['nbm_send_detailed_content']) {
                            $news = news($nbm_user['last_send'], $dbnow, false, $conf['nbm_send_html_mail'], $auth);
                            $exist_data = count($news) > 0;
                        } else {
                            $exist_data = news_exists($nbm_user['last_send'], $dbnow);
                        }

                        if ($exist_data) {
                            $subject = '[' . $conf['gallery_title'] . '] ' . l10n('New photos added');

                            // Assign current var for nbm mail
                            assign_vars_nbm_mail_content($nbm_user);

                            if ($nbm_user['last_send'] !== null) {
                                $env_nbm['mail_template']->assign(
                                    'content_new_elements_between',
                                    [
                                        'DATE_BETWEEN_1' => $nbm_user['last_send'],
                                        'DATE_BETWEEN_2' => $dbnow,
                                    ]
                                );
                            } else {
                                $env_nbm['mail_template']->assign(
                                    'content_new_elements_single',
                                    [
                                        'DATE_SINGLE' => $dbnow,
                                    ]
                                );
                            }

                            if ($conf['nbm_send_detailed_content']) {
                                $env_nbm['mail_template']->assign('global_new_lines', $news);
                            }

                            $nbm_user_customize_mail_content =
                              trigger_change(
                                  'nbm_render_user_customize_mail_content',
                                  $customize_mail_content,
                                  $nbm_user
                              );
                            if (! empty($nbm_user_customize_mail_content)) {
                                $env_nbm['mail_template']->assign(
                                    'custom_mail_content',
                                    $nbm_user_customize_mail_content
                                );
                            }

                            if ($conf['nbm_send_html_mail'] and $conf['nbm_send_recent_post_dates']) {
                                $recent_post_dates = get_recent_post_dates_array(
                                    $conf['recent_post_dates']['NBM']
                                );
                                foreach ($recent_post_dates as $date_detail) {
                                    $env_nbm['mail_template']->append(
                                        'recent_posts',
                                        [
                                            'TITLE' => get_title_recent_post_date($date_detail),
                                            'HTML_DATA' => get_html_description_recent_post_date($date_detail, $auth),
                                        ]
                                    );
                                }
                            }

                            $env_nbm['mail_template']->assign(
                                [
                                    'GOTO_GALLERY_TITLE' => $conf['gallery_title'],
                                    'GOTO_GALLERY_URL' => add_url_params(get_gallery_home_url(), $add_url_params),
                                    'SEND_AS_NAME' => $env_nbm['send_as_name'],
                                ]
                            );

                            $ret = pwg_mail(
                                [
                                    'name' => stripslashes($nbm_user['username']),
                                    'email' => $nbm_user['mail_address'],
                                ],
                                [
                                    'from' => $env_nbm['send_as_mail_formated'],
                                    'subject' => $subject,
                                    'email_format' => $env_nbm['email_format'],
                                    'content' => $env_nbm['mail_template']->parse('notification_by_mail', true),
                                    'content_format' => $env_nbm['email_format'],
                                    'auth_key' => $auth,
                                ]
                            );

                            if ($ret) {
                                inc_mail_sent_success($nbm_user);

                                $datas[] = [
                                    'user_id' => $nbm_user['user_id'],
                                    'last_send' => $dbnow,
                                ];
                            } else {
                                inc_mail_sent_failed($nbm_user);
                            }

                            unset_make_full_url();
                        }
                    } else {
                        if (news_exists($nbm_user['last_send'], $dbnow)) {
                            // Fill return list of "selected" users for 'list_to_send'
                            $return_list[] = $nbm_user;
                        }
                    }

                    // unset env nbm user
                    unset_user_on_env_nbm();
                }

                // Restore nbm environment
                end_users_env_nbm();

                if ($is_action_send) {
                    mass_updates(
                        'user_mail_notification',
                        [
                            'primary' => ['user_id'],
                            'update' => ['last_send'],
                        ],
                        $datas
                    );

                    display_counter_info();
                }
            } else {
                if ($is_action_send) {
                    $page['errors'][] = l10n('No user to send notifications by mail.');
                }
            }
        } else {
            // Quick List, don't check news
            // Fill return list of "selected" users for 'list_to_send'
            $return_list = $data_users;
        }
    }

    // Return list of "selected" users for 'list_to_send'
    // Return list of "treated" check_key for 'send'
    return $return_list;
}

// +-----------------------------------------------------------------------+
// | Main                                                                  |
// +-----------------------------------------------------------------------+
if (! isset($_GET['mode'])) {
    $page['mode'] = 'send';
} else {
    $page['mode'] = $_GET['mode'];
}

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(get_tab_status($page['mode']));

// +-----------------------------------------------------------------------+
// | Add event handler                                                     |
// +-----------------------------------------------------------------------+
add_event_handler('nbm_render_global_customize_mail_content', 'render_global_customize_mail_content');
trigger_notify('nbm_event_handler_added');

// +-----------------------------------------------------------------------+
// | Insert new users with mails                                           |
// +-----------------------------------------------------------------------+
if (! isset($_POST) or (count($_POST) == 0)) {
    // No insert data in post mode
    insert_new_data_user_mail_notification();
}

// +-----------------------------------------------------------------------+
// | Treatment of tab post                                                 |
// +-----------------------------------------------------------------------+

if (! empty($_POST)) {
    check_pwg_token();
}

switch ($page['mode']) {
    case 'param':

        if (isset($_POST['param_submit'])) {
            $_POST['nbm_send_mail_as'] = strip_tags($_POST['nbm_send_mail_as']);

            check_input_parameter('nbm_send_html_mail', $_POST, false, '/^(true|false)$/');
            check_input_parameter('nbm_send_detailed_content', $_POST, false, '/^(true|false)$/');
            check_input_parameter('nbm_send_recent_post_dates', $_POST, false, '/^(true|false)$/');

            $updated_param_count = 0;
            // Update param
            $query = <<<SQL
                SELECT param, value FROM config WHERE param LIKE 'nbm\_%';
                SQL;
            $result = pwg_query($query);
            while ($nbm_user = pwg_db_fetch_assoc($result)) {
                if (isset($_POST[$nbm_user['param']])) {
                    conf_update_param($nbm_user['param'], $_POST[$nbm_user['param']], true);
                    $updated_param_count++;
                }
            }

            $page['infos'][] = l10n_dec(
                '%d parameter was updated.',
                '%d parameters were updated.',
                $updated_param_count
            );
        }

        // no break
    case 'subscribe':

        if (isset($_POST['falsify']) and isset($_POST['cat_true'])) {
            $check_key_treated = unsubscribe_notification_by_mail(true, $_POST['cat_true']);
            do_timeout_treatment('cat_true', $check_key_treated);
        } elseif (isset($_POST['trueify']) and isset($_POST['cat_false'])) {
            $check_key_treated = subscribe_notification_by_mail(true, $_POST['cat_false']);
            do_timeout_treatment('cat_false', $check_key_treated);
        }
        break;

    case 'send':

        if (isset($_POST['send_submit']) and isset($_POST['send_selection']) and isset($_POST['send_customize_mail_content'])) {
            $check_key_treated = do_action_send_mail_notification('send', $_POST['send_selection'], stripslashes($_POST['send_customize_mail_content']));
            do_timeout_treatment('send_selection', $check_key_treated);
        }

}

// +-----------------------------------------------------------------------+
// | template initialization                                               |
// +-----------------------------------------------------------------------+
$template->set_filenames(
    [
        'double_select' => 'double_select.tpl',
        'notification_by_mail' => 'notification_by_mail.tpl',
    ]
);

$template->assign(
    [
        'PWG_TOKEN' => get_pwg_token(),
        'U_HELP' => get_root_url() . 'admin/popuphelp.php?page=notification_by_mail',
        'F_ACTION' => $base_url . get_query_string_diff([]),
    ]
);

if (is_autorize_status(ACCESS_WEBMASTER)) {
    // TabSheet
    $tabsheet = new tabsheet();
    $tabsheet->set_id('nbm');
    $tabsheet->select($page['mode']);
    $tabsheet->assign();
}

if ($must_repost) {
    // Get name of submit button
    $repost_submit_name = '';
    if (isset($_POST['falsify'])) {
        $repost_submit_name = 'falsify';
    } elseif (isset($_POST['trueify'])) {
        $repost_submit_name = 'trueify';
    } elseif (isset($_POST['send_submit'])) {
        $repost_submit_name = 'send_submit';
    }

    $template->assign('REPOST_SUBMIT_NAME', $repost_submit_name);
}

switch ($page['mode']) {
    case 'param':

        $template->assign(
            $page['mode'],
            [
                'SEND_HTML_MAIL' => $conf['nbm_send_html_mail'],
                'SEND_MAIL_AS' => $conf['nbm_send_mail_as'],
                'SEND_DETAILED_CONTENT' => $conf['nbm_send_detailed_content'],
                'COMPLEMENTARY_MAIL_CONTENT' => $conf['nbm_complementary_mail_content'],
                'SEND_RECENT_POST_DATES' => $conf['nbm_send_recent_post_dates'],
            ]
        );
        break;

    case 'subscribe':

        $template->assign($page['mode'], true);

        $template->assign(
            [
                'L_CAT_OPTIONS_TRUE' => l10n('Subscribed'),
                'L_CAT_OPTIONS_FALSE' => l10n('Unsubscribed'),
            ]
        );

        $data_users = get_user_notifications('subscribe');

        $opt_true = [];
        $opt_true_selected = [];
        $opt_false = [];
        $opt_false_selected = [];
        foreach ($data_users as $nbm_user) {
            if (get_boolean($nbm_user['enabled'])) {
                $opt_true[$nbm_user['check_key']] = stripslashes($nbm_user['username']) . '[' . $nbm_user['mail_address'] . ']';
                if ((isset($_POST['falsify']) and isset($_POST['cat_true']) and in_array($nbm_user['check_key'], $_POST['cat_true']))) {
                    $opt_true_selected[] = $nbm_user['check_key'];
                }
            } else {
                $opt_false[$nbm_user['check_key']] = stripslashes($nbm_user['username']) . '[' . $nbm_user['mail_address'] . ']';
                if (isset($_POST['trueify']) and isset($_POST['cat_false']) and in_array($nbm_user['check_key'], $_POST['cat_false'])) {
                    $opt_false_selected[] = $nbm_user['check_key'];
                }
            }
        }
        $template->assign(
            [
                'category_option_true' => $opt_true,
                'category_option_true_selected' => $opt_true_selected,
                'category_option_false' => $opt_false,
                'category_option_false_selected' => $opt_false_selected,
            ]
        );
        $template->assign_var_from_handle('DOUBLE_SELECT', 'double_select');
        break;

    case 'send':

        $tpl_var = [
            'users' => [],
        ];

        $data_users = do_action_send_mail_notification('list_to_send');

        $tpl_var['CUSTOMIZE_MAIL_CONTENT'] =
          isset($_POST['send_customize_mail_content'])
            ? stripslashes($_POST['send_customize_mail_content'])
            : $conf['nbm_complementary_mail_content'];

        if (count($data_users)) {
            foreach ($data_users as $nbm_user) {
                if (
                    (! $must_repost) or // Not timeout, normal treatment
                    (($must_repost) and in_array($nbm_user['check_key'], $_POST['send_selection']))  // Must be reposted, show only user to send
                ) {
                    $tpl_var['users'][] =
                      [
                          'ID' => $nbm_user['check_key'],
                          'CHECKED' => ( // not check if not selected,  on init select<all
                              isset($_POST['send_selection']) and // not init
                              ! in_array($nbm_user['check_key'], $_POST['send_selection']) // not selected
                          ) ? '' : 'checked="checked"',
                          'USERNAME' => stripslashes($nbm_user['username']),
                          'EMAIL' => $nbm_user['mail_address'],
                          'LAST_SEND' => $nbm_user['last_send'],
                      ];
                }
            }
        }
        $template->assign($page['mode'], $tpl_var);

        if ($conf['auth_key_duration'] > 0) {
            $template->assign(
                'auth_key_duration',
                time_since(
                    strtotime('now -' . $conf['auth_key_duration'] . ' second'),
                    'second',
                    null,
                    false
                )
            );
        }

        break;

}

$template->assign('ADMIN_PAGE_TITLE', l10n('Send mail to users'));

// +-----------------------------------------------------------------------+
// | Sending html code                                                     |
// +-----------------------------------------------------------------------+
$template->assign_var_from_handle('ADMIN_CONTENT', 'notification_by_mail');
