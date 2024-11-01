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

include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

if (! $conf['enable_synchronization']) {
    die('synchronization is disabled');
}

check_status(ACCESS_ADMINISTRATOR);

if (! is_numeric($_GET['site'])) {
    die('site param missing or invalid');
}

$site_id = $_GET['site'];

$query = "SELECT galleries_url FROM sites WHERE id = {$site_id};";
[$site_url] = pwg_db_fetch_row(pwg_query($query));
if (! isset($site_url)) {
    die('site ' . $site_id . ' does not exist');
}

$site_is_remote = url_is_remote($site_url);

[$dbnow] = pwg_db_fetch_row(pwg_query('SELECT NOW();'));
define('CURRENT_DATE', $dbnow);

$error_labels = [
    'PWG-ERROR-NO-FS' => [
        l10n('File/directory read error'),
        l10n('The file or directory cannot be accessed (either it does not exist or the access is denied)'),
    ],
];
$errors = [];
$infos = [];

if ($site_is_remote) {
    fatal_error('remote sites not supported');
} else {
    include_once(PHPWG_ROOT_PATH . 'admin/site_reader_local.php');
    $site_reader = new LocalSiteReader($site_url);
}

if (isset($page['no_md5sum_number'])) {
    $page['messages'][] = '<a href="admin.php?page=batch_manager&amp;filter=prefilter-no_sync_md5sum">' . l10n('Some checksums are missing.') . '<i class="icon-right"></i></a>';
}

// +-----------------------------------------------------------------------+
// | tabs                                                                  |
// +-----------------------------------------------------------------------+

include_once(PHPWG_ROOT_PATH . 'admin/include/tabsheet.class.php');
$my_base_url = get_root_url() . 'admin.php?page=';

$tabsheet = new tabsheet();
$tabsheet->set_id('site_update');
$tabsheet->select('synchronization');
$tabsheet->assign();

// +-----------------------------------------------------------------------+
// | Quick sync                                                            |
// +-----------------------------------------------------------------------+

if (isset($_GET['quick_sync'])) {
    check_pwg_token();

    $_POST['sync'] = 'files';
    $_POST['display_info'] = '1';
    $_POST['add_to_caddie'] = '1';
    $_POST['privacy_level'] = '0';
    $_POST['sync_meta'] = '1';
    $_POST['simulate'] = '0';
    $_POST['subcats-included'] = '1';
    $_POST['submit'] = 'Quick Local Synchronization';
}

$general_failure = true;
if (isset($_POST['submit'])) {

    if ($site_reader->open()) {
        $general_failure = false;
    }

    // shall we simulate only
    $simulate = isset($_POST['simulate']) && $_POST['simulate'] == 1;
}

// +-----------------------------------------------------------------------+
// |                      directories / categories                         |
// +-----------------------------------------------------------------------+
if (isset($_POST['submit']) && ($_POST['sync'] == 'dirs' || $_POST['sync'] == 'files')) {
    $counts['new_categories'] = 0;
    $counts['del_categories'] = 0;
    $counts['del_elements'] = 0;
    $counts['new_elements'] = 0;
    $counts['upd_elements'] = 0;
}

if (isset($_POST['submit']) && ($_POST['sync'] == 'dirs' || $_POST['sync'] == 'files') && ! $general_failure) {
    $start = get_moment();
    // which categories to update ?
    $query = "SELECT id, uppercats, global_rank, status, visible FROM categories WHERE dir IS NOT NULL AND site_id = {$site_id}";
    if (isset($_POST['cat']) && is_numeric($_POST['cat'])) {
        if (isset($_POST['subcats-included']) && $_POST['subcats-included'] == 1) {
            $query .= ' AND uppercats ' . DB_REGEX_OPERATOR . " '(^|,){$_POST['cat']}(,|$)'";
        } else {
            $query .= " AND id = {$_POST['cat']}";
        }
    }

    $db_categories = query2array($query, 'id');

    // get categort full directories in an array for comparison with file
    // system directory tree
    $db_fulldirs = get_fulldirs(array_keys($db_categories));

    // what is the base directory to search file system sub-directories ?
    if (isset($_POST['cat']) && is_numeric($_POST['cat'])) {
        $basedir = $db_fulldirs[$_POST['cat']];
    } else {
        $basedir = preg_replace('#/*$#', '', (string) $site_url);
    }

    // we need to have fulldirs as keys to make efficient comparison
    $db_fulldirs = array_flip($db_fulldirs);

    // finding next rank for each id_uppercat. By default, each category id
    // has 1 for next rank on its sub-categories to create
    $next_rank['NULL'] = 1;

    $query = 'SELECT id FROM categories';
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result)) {
        $next_rank[$row['id']] = 1;
    }

    // let's see if some categories already have some sub-categories...
    $query = 'SELECT id_uppercat, MAX(rank_column) + 1 AS next_rank FROM categories GROUP BY id_uppercat';
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result)) {
        // for the id_uppercat NULL, we write 'NULL' and not the empty string
        if (! isset($row['id_uppercat']) || $row['id_uppercat'] == '') {
            $row['id_uppercat'] = 'NULL';
        }

        $next_rank[$row['id_uppercat']] = $row['next_rank'];
    }

    // next category id available
    $next_id = pwg_db_nextval('id', 'categories');

    // retrieve sub-directories fulldirs from the site reader
    $fs_fulldirs = $site_reader->get_full_directories($basedir);

    // get_full_directories doesn't include the base directory, so if it's a
    // category directory, we need to include it in our array
    if (isset($_POST['cat'])) {
        $fs_fulldirs[] = $basedir;
    }

    // If $_POST['subcats-included'] != 1 ("Search in sub-albums" is unchecked)
    // $db_fulldirs doesn't include any subdirectories and $fs_fulldirs does
    // So $fs_fulldirs will be limited to the selected basedir
    // (if that one is in $fs_fulldirs)
    if (! isset($_POST['subcats-included']) || $_POST['subcats-included'] != 1) {
        $fs_fulldirs = array_intersect($fs_fulldirs, array_keys($db_fulldirs));
    }

    $inserts = [];
    // new categories are the directories not present yet in the database
    foreach (array_diff($fs_fulldirs, array_keys($db_fulldirs)) as $fulldir) {
        $dir = basename((string) $fulldir);
        $insert = [
            'id' => $next_id++,
            'dir' => $dir,
            'name' => str_replace('_', ' ', $dir),
            'site_id' => $site_id,
            'commentable' =>
                boolean_to_string($conf['newcat_default_commentable']),
            'status' => $conf['newcat_default_status'],
            'visible' => boolean_to_string($conf['newcat_default_visible']),
        ];

        if (isset($db_fulldirs[dirname((string) $fulldir)])) {
            $parent = $db_fulldirs[dirname((string) $fulldir)];

            $insert['id_uppercat'] = $parent;
            $insert['uppercats'] =
                $db_categories[$parent]['uppercats'] . ',' . $insert['id'];
            $insert['rank'] = $next_rank[$parent]++;
            $insert['global_rank'] =
                $db_categories[$parent]['global_rank'] . '.' . $insert['rank'];
            if ($db_categories[$parent]['status'] == 'private') {
                $insert['status'] = 'private';
            }

            if ($db_categories[$parent]['visible'] == 'false') {
                $insert['visible'] = 'false';
            }
        } else {
            $insert['uppercats'] = $insert['id'];
            $insert['rank'] = $next_rank['NULL']++;
            $insert['global_rank'] = $insert['rank'];
        }

        $inserts[] = $insert;
        $infos[] = [
            'path' => $fulldir,
            'info' => l10n('added'),
        ];

        // add the new category to $db_categories and $db_fulldirs array
        $db_categories[$insert['id']] =
            [
                'id' => $insert['id'],
                'parent' => $parent ?? null,
                'status' => $insert['status'],
                'visible' => $insert['visible'],
                'uppercats' => $insert['uppercats'],
                'global_rank' => $insert['global_rank'],
            ];
        $db_fulldirs[$fulldir] = $insert['id'];
        $next_rank[$insert['id']] = 1;
    }

    if ($inserts !== []) {
        if (! $simulate) {
            $dbfields = [
                'id', 'dir', 'name', 'site_id', 'id_uppercat', 'uppercats', 'commentable',
                'visible', 'status', 'rank_column', 'global_rank',
            ];
            mass_inserts('categories', $dbfields, $inserts);

            // add default permissions to categories
            $category_ids = [];
            $category_up = [];
            foreach ($inserts as $category) {
                $category_ids[] = $category['id'];
                if (! empty($category['id_uppercat'])) {
                    $category_up[] = $category['id_uppercat'];
                }
            }

            pwg_activity('album', $category_ids, 'add', [
                'sync' => true,
            ]);

            $category_up = implode(',', array_unique($category_up));
            if ($conf['inheritance_by_default'] && ($category_up !== '' && $category_up !== '0')) {
                $query = "SELECT * FROM group_access WHERE cat_id IN ({$category_up});";
                $result = pwg_query($query);
                if (! empty($result)) {
                    $granted_grps = [];
                    while ($row = pwg_db_fetch_assoc($result)) {
                        if (! isset($granted_grps[$row['cat_id']])) {
                            $granted_grps[$row['cat_id']] = [];
                        }

                        // TODO: explanaition
                        $granted_grps[] = [
                            $row['cat_id'] => array_push($granted_grps[$row['cat_id']], $row['group_id']),
                        ];
                    }
                }

                $query = "SELECT * FROM user_access WHERE cat_id IN ({$category_up});";
                $result = pwg_query($query);
                if (! empty($result)) {
                    $granted_users = [];
                    while ($row = pwg_db_fetch_assoc($result)) {
                        if (! isset($granted_users[$row['cat_id']])) {
                            $granted_users[$row['cat_id']] = [];
                        }

                        // TODO: explanaition
                        $granted_users[] = [
                            $row['cat_id'] => array_push($granted_users[$row['cat_id']], $row['user_id']),
                        ];
                    }
                }

                $insert_granted_users = [];
                $insert_granted_grps = [];
                foreach ($category_ids as $ids) {
                    $parent_id = $db_categories[$ids]['parent'];
                    while (in_array($parent_id, $category_ids)) {
                        $parent_id = $db_categories[$parent_id]['parent'];
                    }

                    if ($db_categories[$ids]['status'] == 'private' && $parent_id !== null) {
                        if (isset($granted_grps[$parent_id])) {
                            foreach ($granted_grps[$parent_id] as $granted_grp) {
                                $insert_granted_grps[] = [
                                    'group_id' => $granted_grp,
                                    'cat_id' => $ids,
                                ];
                            }
                        }

                        if (isset($granted_users[$parent_id])) {
                            foreach ($granted_users[$parent_id] as $granted_user) {
                                $insert_granted_users[] = [
                                    'user_id' => $granted_user,
                                    'cat_id' => $ids,
                                ];
                            }
                        }
                    }
                }

                mass_inserts('group_access', ['group_id', 'cat_id'], $insert_granted_grps);
                $insert_granted_users = array_unique($insert_granted_users, SORT_REGULAR);
                mass_inserts('user_access', ['user_id', 'cat_id'], $insert_granted_users);
            } else {
                add_permission_on_category($category_ids, get_admins());
            }
        }

        $counts['new_categories'] = count($inserts);
    }

    // to delete categories
    $to_delete = [];
    $to_delete_derivative_dirs = [];

    foreach (array_diff(array_keys($db_fulldirs), $fs_fulldirs) as $fulldir) {
        $to_delete[] = $db_fulldirs[$fulldir];
        unset($db_fulldirs[$fulldir]);

        $infos[] = [
            'path' => $fulldir,
            'info' => l10n('deleted'),
        ];

        if (substr_compare($fulldir, '../', 0, 3) == 0) {
            $fulldir = substr($fulldir, 3);
        }

        $to_delete_derivative_dirs[] = PHPWG_ROOT_PATH . PWG_DERIVATIVE_DIR . $fulldir;
    }

    if ($to_delete !== []) {
        if (! $simulate) {
            delete_categories($to_delete);
            foreach ($to_delete_derivative_dirs as $to_delete_dir) {
                if (is_dir($to_delete_dir)) {
                    clear_derivative_cache_rec($to_delete_dir, '#.+#');
                }
            }
        }

        $counts['del_categories'] = count($to_delete);
    }

    $template->append('footer_elements', '<!-- scanning dirs : '
      . get_elapsed_time($start, get_moment())
      . ' -->');
}

// +-----------------------------------------------------------------------+
// |                           files / elements                            |
// +-----------------------------------------------------------------------+
if (isset($_POST['submit']) && $_POST['sync'] == 'files' && ! $general_failure) {
    $start_files = get_moment();
    $start = $start_files;

    $fs = $site_reader->get_elements($basedir);

    $template->append('footer_elements', '<!-- get_elements: '
      . get_elapsed_time($start, get_moment())
      . ' -->');

    $cat_ids = array_diff(array_keys($db_categories), $to_delete);

    $db_elements = [];

    if ($cat_ids !== []) {
        $cat_ids_ = wordwrap(
            implode(', ', $cat_ids),
            160,
            "\n"
        );
        $query = "SELECT id, path FROM images WHERE storage_category_id IN ({$cat_ids_});";
        $db_elements = query2array($query, 'id', 'path');
    }

    // next element id available
    $next_element_id = pwg_db_nextval('id', 'images');

    $start = get_moment();

    $inserts = [];
    $insert_links = [];
    $insert_formats = [];
    $formats_to_delete = [];

    foreach (array_diff(array_keys($fs), $db_elements) as $path) {
        $insert = [];
        // storage category must exist
        $dirname = dirname($path);
        if (! isset($db_fulldirs[$dirname])) {
            continue;
        }

        $filename = basename($path);

        $insert = [
            'id' => $next_element_id++,
            'file' => pwg_db_real_escape_string($filename),
            'name' => pwg_db_real_escape_string(get_name_from_file($filename)),
            'date_available' => CURRENT_DATE,
            'path' => pwg_db_real_escape_string($path),
            'representative_ext' => $fs[$path]['representative_ext'],
            'storage_category_id' => $db_fulldirs[$dirname],
            'added_by' => $user['id'],
        ];

        if ($_POST['privacy_level'] != 0) {
            $insert['level'] = $_POST['privacy_level'];
        }

        $inserts[] = $insert;

        $insert_links[] = [
            'image_id' => $insert['id'],
            'category_id' => $insert['storage_category_id'],
        ];

        $infos[] = [
            'path' => $insert['path'],
            'info' => l10n('added'),
        ];

        if ($conf['enable_formats']) {
            foreach ($fs[$path]['formats'] as $ext => $filesize) {
                $insert_formats[] = [
                    'image_id' => $insert['id'],
                    'ext' => $ext,
                    'filesize' => $filesize,
                ];

                $infos[] = [
                    'path' => $insert['path'],
                    'info' => l10n('format %s added', $ext),
                ];
            }
        }

        $caddiables[] = $insert['id'];
    }

    // search new/removed formats on photos already registered in database
    if ($conf['enable_formats']) {
        $db_elements_flip = array_flip($db_elements);

        $existing_ids = [];

        foreach (array_keys(array_intersect_key($fs, $db_elements_flip)) as $path) {
            $existing_ids[] = $db_elements_flip[$path];
        }

        $logger->debug('existing_ids', $existing_ids);

        if ($existing_ids !== []) {
            $db_formats = [];

            // find formats for existing photos (already in database)
            $existing_ids_ = implode(',', $existing_ids);
            $query = "SELECT * FROM image_format WHERE image_id IN ({$existing_ids_});";
            $result = pwg_query($query);
            while ($row = pwg_db_fetch_assoc($result)) {
                if (! isset($db_formats[$row['image_id']])) {
                    $db_formats[$row['image_id']] = [];
                }

                $db_formats[$row['image_id']][$row['ext']] = $row['format_id'];
            }

            // first we search the formats that were removed
            foreach ($db_formats as $image_id => $formats) {
                $image_formats_to_delete = array_diff_key($formats, $fs[$db_elements[$image_id]]['formats']);
                $logger->debug('image_formats_to_delete', $image_formats_to_delete);
                foreach ($image_formats_to_delete as $ext => $format_id) {
                    $formats_to_delete[] = $format_id;

                    $infos[] = [
                        'path' => $db_elements[$image_id],
                        'info' => l10n('format %s removed', $ext),
                    ];
                }
            }

            // then we search for new formats on existing photos
            foreach ($existing_ids as $image_id) {
                $path = $db_elements[$image_id];

                $formats = [];
                if (isset($db_formats[$image_id])) {
                    $formats = $db_formats[$image_id];
                }

                $image_formats_to_insert = array_diff_key($fs[$path]['formats'], $formats);
                $logger->debug('image_formats_to_insert', $image_formats_to_insert);
                foreach ($image_formats_to_insert as $ext => $filesize) {
                    $insert_formats[] = [
                        'image_id' => $image_id,
                        'ext' => $ext,
                        'filesize' => $filesize,
                    ];

                    $infos[] = [
                        'path' => $db_elements[$image_id],
                        'info' => l10n('format %s added', $ext),
                    ];
                }
            }
        }
    }

    if (! $simulate) {
        // inserts all new elements
        if ($inserts !== []) {
            mass_inserts(
                'images',
                array_keys($inserts[0]),
                $inserts
            );

            // inserts all links between new elements and their storage category
            mass_inserts(
                'image_category',
                array_keys($insert_links[0]),
                $insert_links
            );

            pwg_activity('photo', $caddiables, 'add', [
                'sync' => true,
            ]);

            // add new photos to caddie
            if (isset($_POST['add_to_caddie']) && $_POST['add_to_caddie'] == 1) {
                fill_caddie($caddiables);
            }
        }

        // inserts all formats
        if ($insert_formats !== []) {
            mass_inserts(
                'image_format',
                array_keys($insert_formats[0]),
                $insert_formats
            );
        }

        if ($formats_to_delete !== []) {
            $formats_to_delete_ = implode(',', $formats_to_delete);
            $query = "DELETE FROM image_format WHERE format_id IN ({$formats_to_delete_});";
            pwg_query($query);
        }
    }

    $counts['new_elements'] = count($inserts);

    // delete elements that are in database but not in the filesystem
    $to_delete_elements = [];
    foreach (array_diff($db_elements, array_keys($fs)) as $path) {
        $to_delete_elements[] = array_search($path, $db_elements, true);
        $infos[] = [
            'path' => $path,
            'info' => l10n('deleted'),
        ];
    }

    if ($to_delete_elements !== []) {
        if (! $simulate) {
            delete_elements($to_delete_elements);
        }

        $counts['del_elements'] = count($to_delete_elements);
    }

    $template->append('footer_elements', '<!-- scanning files : '
      . get_elapsed_time($start_files, get_moment())
      . ' -->');
}

// +-----------------------------------------------------------------------+
// |                          synchronize files                            |
// +-----------------------------------------------------------------------+
if (isset($_POST['submit']) && ($_POST['sync'] == 'dirs' || $_POST['sync'] == 'files') && ! $general_failure) {
    if (! $simulate) {
        $start = get_moment();
        update_category('all');
        $template->append('footer_elements', '<!-- update_category(all) : '
          . get_elapsed_time($start, get_moment())
          . ' -->');
        $start = get_moment();
        update_global_rank();
        $template->append('footer_elements', '<!-- ordering categories : '
          . get_elapsed_time($start, get_moment())
          . ' -->');
    }

    if ($_POST['sync'] == 'files') {
        $start = get_moment();
        $opts['category_id'] = '';
        $opts['recursive'] = true;
        if (isset($_POST['cat'])) {
            $opts['category_id'] = $_POST['cat'];
            if (! isset($_POST['subcats-included']) || $_POST['subcats-included'] != 1) {
                $opts['recursive'] = false;
            }
        }

        $files = get_filelist(
            $opts['category_id'],
            $site_id,
            $opts['recursive'],
            false
        );
        $template->append('footer_elements', '<!-- get_filelist : '
          . get_elapsed_time($start, get_moment())
          . ' -->');
        $start = get_moment();

        $datas = [];
        foreach ($files as $id => $file) {
            $file = $file['path'];
            $data = $site_reader->get_element_update_attributes($file);
            if (! is_array($data)) {
                continue;
            }

            $data['id'] = $id;
            $datas[] = $data;
        } // end foreach file

        $counts['upd_elements'] = count($datas);
        if (! $simulate && $datas !== []) {
            mass_updates(
                'images',
                // fields
                [
                    'primary' => ['id'],
                    'update' => $site_reader->get_update_attributes(),
                ],
                $datas
            );
        }

        $template->append('footer_elements', '<!-- update files : '
          . get_elapsed_time($start, get_moment())
          . ' -->');
    }// end if sync files
}

// +-----------------------------------------------------------------------+
// |                          synchronize files                            |
// +-----------------------------------------------------------------------+
if (isset($_POST['submit']) && ($_POST['sync'] == 'dirs' || $_POST['sync'] == 'files')) {
    $template->assign(
        'update_result',
        [
            'NB_NEW_CATEGORIES' => $counts['new_categories'],
            'NB_DEL_CATEGORIES' => $counts['del_categories'],
            'NB_NEW_ELEMENTS' => $counts['new_elements'],
            'NB_DEL_ELEMENTS' => $counts['del_elements'],
            'NB_UPD_ELEMENTS' => $counts['upd_elements'],
            'NB_ERRORS' => count($errors),
        ]
    );
}

// +-----------------------------------------------------------------------+
// |                          synchronize metadata                         |
// +-----------------------------------------------------------------------+
if (isset($_POST['submit']) && isset($_POST['sync_meta']) && ! $general_failure) {
    // sync only never synchronized files ?
    $opts['only_new'] = ! isset($_POST['meta_all']);
    $opts['category_id'] = '';
    $opts['recursive'] = true;

    if (isset($_POST['cat'])) {
        $opts['category_id'] = $_POST['cat'];
        // recursive ?
        if (! isset($_POST['subcats-included']) || $_POST['subcats-included'] != 1) {
            $opts['recursive'] = false;
        }
    }

    $start = get_moment();
    $files = get_filelist(
        $opts['category_id'],
        $site_id,
        $opts['recursive'],
        $opts['only_new']
    );

    $template->append('footer_elements', '<!-- get_filelist : '
      . get_elapsed_time($start, get_moment())
      . ' -->');

    $start = get_moment();
    $datas = [];
    $tags_of = [];

    foreach ($files as $id => $element_infos) {
        $data = $site_reader->get_element_metadata($element_infos);

        if (is_array($data)) {
            $data['date_metadata_update'] = CURRENT_DATE;
            $data['id'] = $id;
            $datas[] = $data;

            foreach (['keywords', 'tags'] as $key) {
                if (isset($data[$key])) {
                    if (! isset($tags_of[$id])) {
                        $tags_of[$id] = [];
                    }

                    foreach (explode(',', (string) $data[$key]) as $tag_name) {
                        $tags_of[$id][] = tag_id_from_tag_name($tag_name);
                    }
                }
            }
        } else {
            $errors[] = [
                'path' => $element_infos['path'],
                'type' => 'PWG-ERROR-NO-FS',
            ];
        }
    }

    if (! $simulate) {
        if ($datas !== []) {
            mass_updates(
                'images',
                // fields
                [
                    'primary' => ['id'],
                    'update' => array_unique(
                        array_merge(
                            array_diff(
                                $site_reader->get_metadata_attributes(),
                                // keywords and tags fields are managed separately
                                ['keywords', 'tags']
                            ),
                            ['date_metadata_update']
                        )
                    ),
                ],
                $datas,
                isset($_POST['meta_empty_overrides']) ? 0 : MASS_UPDATES_SKIP_EMPTY
            );
        }

        set_tags_of($tags_of);
    }

    $template->append('footer_elements', '<!-- metadata update : '
      . get_elapsed_time($start, get_moment())
      . ' -->');

    $template->assign(
        'metadata_result',
        [
            'NB_ELEMENTS_DONE' => count($datas),
            'NB_ELEMENTS_CANDIDATES' => count($files),
            'NB_ERRORS' => count($errors),
        ]
    );
}

// +-----------------------------------------------------------------------+
// |                        template initialization                        |
// +-----------------------------------------------------------------------+
$template->set_filenames([
    'update' => 'site_update.tpl',
]);
$result_title = '';
if (isset($simulate) && $simulate) {
    $result_title .= '[' . l10n('Simulation') . '] ';
}

// used_metadata string is displayed to inform admin which metadata will be
// used from files for synchronization
$used_metadata = implode(', ', $site_reader->get_metadata_attributes());
if ($site_is_remote && ! isset($_POST['submit'])) {
    $used_metadata .= ' + ...';
}

$template->assign(
    [
        'SITE_URL' => $site_url,
        'U_SITE_MANAGER' => get_root_url() . 'admin.php?page=site_manager',
        'L_RESULT_UPDATE' => $result_title . l10n('Search for new images in the directories'),
        'L_RESULT_METADATA' => $result_title . l10n('Metadata synchronization results'),
        'METADATA_LIST' => $used_metadata,
        'U_HELP' => get_root_url() . 'admin/popuphelp.php?page=synchronize',
        'ADMIN_PAGE_TITLE' => l10n('Synchronize'),
    ]
);

// +-----------------------------------------------------------------------+
// |                        introduction : choices                         |
// +-----------------------------------------------------------------------+
if (isset($_POST['submit'])) {
    $tpl_introduction = [
        'sync' => $_POST['sync'],
        'sync_meta' => isset($_POST['sync_meta']),
        'display_info' => isset($_POST['display_info']) && $_POST['display_info'] == 1,
        'add_to_caddie' => isset($_POST['add_to_caddie']) && $_POST['add_to_caddie'] == 1,
        'subcats_included' => isset($_POST['subcats-included']) && $_POST['subcats-included'] == 1,
        'privacy_level_selected' => (int) $_POST['privacy_level'],
        'meta_all' => isset($_POST['meta_all']),
        'meta_empty_overrides' => isset($_POST['meta_empty_overrides']),
    ];

    $cat_selected = isset($_POST['cat']) && is_numeric($_POST['cat']) ? [$_POST['cat']] : [];
} else {
    $tpl_introduction = [
        'sync' => 'dirs',
        'sync_meta' => true,
        'display_info' => false,
        'add_to_caddie' => false,
        'subcats_included' => true,
        'privacy_level_selected' => 0,
        'meta_all' => false,
        'meta_empty_overrides' => false,
    ];

    $cat_selected = [];

    if (isset($_GET['cat_id'])) {
        check_input_parameter('cat_id', $_GET, false, PATTERN_ID);

        $cat_selected = [$_GET['cat_id']];
        $tpl_introduction['sync'] = 'files';
    }
}

$tpl_introduction['privacy_level_options'] = get_privacy_level_options();

$template->assign('introduction', $tpl_introduction);

$query = "SELECT id, name, uppercats, global_rank FROM categories WHERE site_id = {$site_id}";
display_select_cat_wrapper(
    $query,
    $cat_selected,
    'category_options',
    false
);

if ($errors !== []) {
    foreach ($errors as $error) {
        $template->append(
            'sync_errors',
            [
                'ELEMENT' => $error['path'],
                'LABEL' => $error['type'] . ' (' . $error_labels[$error['type']][0] . ')',
            ]
        );
    }

    foreach ($error_labels as $error_type => $error_description) {
        $template->append(
            'sync_error_captions',
            [
                'TYPE' => $error_type,
                'LABEL' => $error_description[1],
            ]
        );
    }
}

if ($infos !== [] && isset($_POST['display_info']) && $_POST['display_info'] == 1) {
    foreach ($infos as $info) {
        $template->append(
            'sync_infos',
            [
                'ELEMENT' => $info['path'],
                'LABEL' => $info['info'],
            ]
        );
    }
}

// +-----------------------------------------------------------------------+
// |                          sending html code                            |
// +-----------------------------------------------------------------------+
$template->assign_var_from_handle('ADMIN_CONTENT', 'update');
