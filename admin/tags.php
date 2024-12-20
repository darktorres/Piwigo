<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';
check_status(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// | tabs                                                                  |
// +-----------------------------------------------------------------------+

require_once PHPWG_ROOT_PATH . 'admin/include/tabsheet.class.php';

$my_base_url = get_root_url() . 'admin.php?page=';

$tabsheet = new tabsheet();
$tabsheet->set_id('tags');
$tabsheet->select('');
$tabsheet->assign();

// +-----------------------------------------------------------------------+
// |                           delete orphan tags                          |
// +-----------------------------------------------------------------------+

if (isset($_GET['action']) && $_GET['action'] == 'delete_orphans') {
    check_pwg_token();

    delete_orphan_tags();
    $_SESSION['message_tags'] = l10n('Orphan tags deleted');
    redirect(get_root_url() . 'admin.php?page=tags');
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->set_filenames([
    'tags' => 'tags.tpl',
]);

$template->assign(
    [
        'F_ACTION' => PHPWG_ROOT_PATH . 'admin.php?page=tags',
        'PWG_TOKEN' => get_pwg_token(),
    ]
);

// +-----------------------------------------------------------------------+
// |                              orphan tags                              |
// +-----------------------------------------------------------------------+

$warning_tags = '';

$orphan_tags = get_orphan_tags();

$orphan_tag_names_array = '[]';
$orphan_tag_names = [];
foreach ($orphan_tags as $tag) {
    $orphan_tag_names[] = trigger_change('render_tag_name', $tag['name'], $tag);
}

if ($orphan_tag_names !== []) {
    $warning_tags = sprintf(
        l10n('You have %d orphan tags %s'),
        count($orphan_tag_names),
        '<a
      class="icon-eye"
      data-url="' . get_root_url() . 'admin.php?page=tags&amp;action=delete_orphans&amp;pwg_token=' . get_pwg_token() . '">'
        . l10n('Review') . '</a>'
    );

    $orphan_tag_names_array = '["';
    $orphan_tag_names_array .= implode(
        '" ,"',
        array_map(
            htmlentities(...),
            $orphan_tag_names,
            array_fill(0, count($orphan_tag_names), ENT_QUOTES)
        )
    );
    $orphan_tag_names_array .= '"]';
}

$template->assign(
    [
        'orphan_tag_names_array' => $orphan_tag_names_array,
        'warning_tags' => $warning_tags,
    ]
);

$message_tags = '';
if (isset($_SESSION['message_tags'])) {
    $message_tags = $_SESSION['message_tags'];
    unset($_SESSION['message_tags']);
}

$template->assign('message_tags', $message_tags);

// +-----------------------------------------------------------------------+
// |                             form creation                             |
// +-----------------------------------------------------------------------+
$per_page = 100;

// tag counters
$query = <<<SQL
    SELECT tag_id, COUNT(image_id) AS counter
    FROM image_tag
    GROUP BY tag_id;
    SQL;
$tag_counters = query2array($query, 'tag_id', 'counter');

// all tags
$query = <<<SQL
    SELECT name, id, url_name
    FROM tags;
    SQL;
$result = pwg_query($query);
$all_tags = [];
while ($tag = pwg_db_fetch_assoc($result)) {
    $raw_name = $tag['name'];
    $tag['raw_name'] = $raw_name;
    $tag['name'] = trigger_change('render_tag_name', $raw_name, $tag);
    $counter = intval(($tag_counters[$tag['id']] ?? null));
    if ($counter > 0) {
        $tag['counter'] = intval($tag_counters[$tag['id']]);
    }

    $alt_names = trigger_change('get_tag_alt_names', [], $raw_name);
    $alt_names = array_diff(array_unique($alt_names), [$tag['name']]);
    if ($alt_names !== []) {
        $tag['alt_names'] = implode(', ', $alt_names);
    }

    $all_tags[] = $tag;
}

usort($all_tags, tag_alpha_compare(...));

$template->assign(
    [
        'first_tags' => array_slice($all_tags, 0, $per_page),
        'data' => $all_tags,
        'total' => count($all_tags),
        'per_page' => $per_page,
        'ADMIN_PAGE_TITLE' => l10n('Tags'),
    ]
);

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'tags');
