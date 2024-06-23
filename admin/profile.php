<?php

namespace Piwigo\admin;

use function Piwigo\inc\build_user;
use function Piwigo\inc\check_input_parameter;
use function Piwigo\inc\check_pwg_token;
use function Piwigo\inc\get_root_url;
use function Piwigo\load_profile_in_template;
use function Piwigo\save_profile_from_post;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

check_input_parameter('user_id', $_GET, false, PATTERN_ID);

$edit_user = build_user($_GET['user_id'], false);

if ($_POST !== []) {
    check_pwg_token();
}

include_once(PHPWG_ROOT_PATH . 'profile.php');

$errors = [];
save_profile_from_post($edit_user, $errors);

load_profile_in_template(
    get_root_url() . 'admin.php?page=profile&amp;user_id=' . $edit_user['id'],
    get_root_url() . 'admin.php?page=user_list',
    $edit_user
);
$page['errors'] = array_merge($page['errors'], $errors);

$template->set_filename('profile', 'profile.tpl');
$template->assign_var_from_handle('ADMIN_CONTENT', 'profile');
