<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

require_once PHPWG_ROOT_PATH . 'admin/include/functions_metadata.php';

/**
 * Deletes a site and call delete_categories for each primary category of the site
 */
function delete_site(
    int $id
): void {
    // destruction of the categories of the site
    $query = "SELECT id FROM categories WHERE site_id = {$id};";
    $category_ids = query2array($query, null, 'id');
    delete_categories($category_ids);

    // destruction of the site
    $query = "DELETE FROM sites WHERE id = {$id};";
    pwg_query($query);
}

/**
 * Recursively deletes one or more categories.
 * It also deletes :
 *    - all the elements physically linked to the category (with delete_elements)
 *    - all the links between elements and this category
 *    - all the restrictions linked to the category
 *
 * @param int[] $ids
 * @param string $photo_deletion_mode
 *    - no_delete : delete no photo, may create orphans
 *    - delete_orphans : delete photos that are no longer linked to any category
 *    - force_delete : delete photos even if they are linked to another category
 */
function delete_categories(
    array $ids,
    string $photo_deletion_mode = 'no_delete'
): void {
    if (count($ids) == 0) {
        return;
    }

    // add sub-category ids to the given ids : if a category is deleted, all
    // sub-categories must be so
    $ids = get_subcat_ids($ids);

    // destruction of all photos physically linked to the category
    $ids_ = wordwrap(implode(', ', $ids), 80, "\n");
    $query = "SELECT id FROM images WHERE storage_category_id IN ({$ids_});";
    $element_ids = query2array($query, null, 'id');
    delete_elements($element_ids);

    // now, should we delete photos that are virtually linked to the category?
    if ($photo_deletion_mode === 'delete_orphans' || $photo_deletion_mode === 'force_delete') {
        $ids_ = implode(',', $ids);
        $query = "SELECT DISTINCT(image_id) FROM image_category WHERE category_id IN ({$ids_});";
        $image_ids_linked = query2array($query, null, 'image_id');

        if ($image_ids_linked !== []) {
            if ($photo_deletion_mode === 'delete_orphans') {
                $image_ids_linked_ = implode(',', $image_ids_linked);
                $ids_ = implode(',', $ids);
                $query = "SELECT DISTINCT(image_id) FROM image_category WHERE image_id IN ({$image_ids_linked_}) AND category_id NOT IN ({$ids_});";
                $image_ids_not_orphans = query2array($query, null, 'image_id');
                $image_ids_to_delete = array_diff($image_ids_linked, $image_ids_not_orphans);
            }

            if ($photo_deletion_mode === 'force_delete') {
                $image_ids_to_delete = $image_ids_linked;
            }

            delete_elements($image_ids_to_delete, true);
        }
    }

    // destruction of the links between images and this category
    $ids_ = wordwrap(implode(', ', $ids), 80, "\n");
    $query = "DELETE FROM image_category WHERE category_id IN ({$ids_});";
    pwg_query($query);

    // destruction of the access linked to the category
    $ids_ = wordwrap(implode(', ', $ids), 80, "\n");
    $query = "DELETE FROM user_access WHERE cat_id IN ({$ids_});";
    pwg_query($query);

    $ids_ = wordwrap(implode(', ', $ids), 80, "\n");
    $query = "DELETE FROM group_access WHERE cat_id IN ({$ids_});";
    pwg_query($query);

    // destruction of the category
    $ids_ = wordwrap(implode(', ', $ids), 80, "\n");
    $query = "DELETE FROM categories WHERE id IN ({$ids_});";
    pwg_query($query);

    $ids_ = implode(',', $ids);
    $query = "DELETE FROM old_permalinks WHERE cat_id IN ({$ids_});";
    pwg_query($query);

    $ids_ = implode(',', $ids);
    $query = "DELETE FROM user_cache_categories WHERE cat_id IN ({$ids_});";
    pwg_query($query);

    trigger_notify('delete_categories', $ids);
    pwg_activity('album', $ids, 'delete', [
        'photo_deletion_mode' => $photo_deletion_mode,
    ]);
}

/**
 * Deletes all files (on disk) related to given image ids.
 *
 * @param int[] $ids
 * @return 0|int[] image ids where files were successfully deleted
 */
function delete_element_files(
    array $ids
): array|int {
    global $conf;
    if (count($ids) == 0) {
        return 0;
    }

    $new_ids = [];
    $formats_of = [];

    $ids_ = implode(',', $ids);
    $query = "SELECT image_id, ext FROM image_format WHERE image_id IN ({$ids_});";
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result)) {
        if (! isset($formats_of[$row['image_id']])) {
            $formats_of[$row['image_id']] = [];
        }

        $formats_of[$row['image_id']][] = $row['ext'];
    }

    $ids_ = implode(',', $ids);
    $query = "SELECT id, path, representative_ext FROM images WHERE id IN ({$ids_});";
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result)) {
        if (url_is_remote($row['path'])) {
            continue;
        }

        $files = [];
        $files[] = get_element_path($row);

        if (! empty($row['representative_ext'])) {
            $files[] = original_to_representative($files[0], $row['representative_ext']);
        }

        if (isset($formats_of[$row['id']])) {
            foreach ($formats_of[$row['id']] as $format_ext) {
                $files[] = original_to_format($files[0], $format_ext);
            }
        }

        $ok = true;
        if (! isset($conf['never_delete_originals'])) {
            foreach ($files as $path) {
                if (is_file($path) && ! unlink($path)) {
                    $ok = false;
                    trigger_error('"' . $path . '" cannot be removed', E_USER_WARNING);
                    break;
                }
            }
        }

        if ($ok) {
            delete_element_derivatives($row);
            $new_ids[] = $row['id'];
        } else {
            break;
        }
    }

    return $new_ids;
}

/**
 * Deletes elements from database.
 * It also deletes :
 *    - all the comments related to elements
 *    - all the links between categories/tags and elements
 *    - all the favorites/rates associated to elements
 *    - removes elements from caddie
 *
 * @param int[] $ids
 * @return int number of deleted elements
 */
function delete_elements(
    array $ids,
    bool $physical_deletion = false
): int {
    if (count($ids) == 0) {
        return 0;
    }

    trigger_notify('begin_delete_elements', $ids);

    if ($physical_deletion) {
        $ids = delete_element_files($ids);
        if (count($ids) == 0) {
            return 0;
        }
    }

    $ids_str = wordwrap(implode(', ', $ids), 80, "\n");

    // destruction of the comments on the image
    $query = "DELETE FROM comments WHERE image_id IN ({$ids_str});";
    pwg_query($query);

    // destruction of the links between images and categories
    $query = "DELETE FROM image_category WHERE image_id IN ({$ids_str});";
    pwg_query($query);

    // destruction of the formats
    $query = "DELETE FROM image_format WHERE image_id IN ({$ids_str});";
    pwg_query($query);

    // destruction of the links between images and tags
    $query = "DELETE FROM image_tag WHERE image_id IN ({$ids_str});";
    pwg_query($query);

    // destruction of the favorites associated with the picture
    $query = "DELETE FROM favorites WHERE image_id IN ({$ids_str});";
    pwg_query($query);

    // destruction of the rates associated to this element
    $query = "DELETE FROM rate WHERE element_id IN ({$ids_str});";
    pwg_query($query);

    // destruction of the caddie associated to this element
    $query = "DELETE FROM caddie WHERE element_id IN ({$ids_str});";
    pwg_query($query);

    // destruction of the image
    $query = "DELETE FROM images WHERE id IN ({$ids_str});";
    pwg_query($query);

    // are the photo used as category representant?
    $query = "SELECT id FROM categories WHERE representative_picture_id IN ({$ids_str});";
    $category_ids = query2array($query, null, 'id');
    if ($category_ids !== []) {
        update_category($category_ids);
    }

    trigger_notify('delete_elements', $ids);
    pwg_activity('photo', $ids, 'delete');
    return count($ids);
}

/**
 * Deletes an user.
 * It also deletes all related data (accesses, favorites, permissions, etc.)
 * @todo : accept array input
 */
function delete_user(
    int $user_id
): void {
    global $conf;
    $tables = [
        // destruction of the access linked to the user
        'user_access',
        // destruction of data notification by mail for this user
        'user_mail_notification',
        // destruction of data RSS notification for this user
        'user_feed',
        // deletion of calculated permissions linked to the user
        'user_cache',
        // deletion of computed cache data linked to the user
        'user_cache_categories',
        // destruction of the group links for this user
        'user_group',
        // destruction of the favorites associated with the user
        'favorites',
        // destruction of the caddie associated with the user
        'caddie',
        // deletion of piwigo specific informations
        'user_infos',
        'user_auth_keys',
    ];

    foreach ($tables as $table) {
        $query = "DELETE FROM {$table} WHERE user_id = {$user_id};";
        pwg_query($query);
    }

    // purge of sessions
    delete_user_sessions($user_id);

    // destruction of the user
    $query = "DELETE FROM users WHERE {$conf['user_fields']['id']} = {$user_id};";
    pwg_query($query);

    trigger_notify('delete_user', $user_id);
    pwg_activity('user', $user_id, 'delete');
}

/**
 * Deletes all tags linked to no photo
 */
function delete_orphan_tags(): void
{
    $orphan_tags = get_orphan_tags();

    if ($orphan_tags !== []) {
        $orphan_tag_ids = [];
        foreach ($orphan_tags as $tag) {
            $orphan_tag_ids[] = $tag['id'];
        }

        delete_tags($orphan_tag_ids);
    }
}

/**
 * Get all tags (id + name) linked to no photo
 */
function get_orphan_tags(): array
{
    $query = 'SELECT id, name FROM tags LEFT JOIN image_tag ON id = tag_id WHERE tag_id IS NULL';

    if (DB_ENGINE === 'MySQL') {
        $query .= ' AND lastmodified < SUBDATE(NOW(), INTERVAL 1 DAY)';
    }

    if (DB_ENGINE === 'PostgreSQL') {
        $query .= " AND lastmodified < NOW() - INTERVAL '1 DAY'";
    }

    return query2array($query);
}

/**
 * Verifies that the representative picture really exists in the db and
 * picks up a random representative if possible and based on config.
 *
 * @param 'all'|int|int[] $ids
 */
function update_category(
    string|int|array $ids = 'all'
): bool|null {
    global $conf;

    if ($ids == 'all') {
        $where_cats = '1 = 1';
    } elseif (! is_array($ids)) {
        $where_cats = "%s = {$ids}";
    } else {
        if (count($ids) == 0) {
            return false;
        }

        $where_cats = '%s IN(' . wordwrap(implode(', ', $ids), 120, "\n") . ')';
    }

    // find all categories where the setted representative is not possible :
    // the picture does not exist
    $where_cats_ = sprintf($where_cats, 'c.id');
    $query = "SELECT DISTINCT c.id FROM categories AS c LEFT JOIN images AS i ON c.representative_picture_id = i.id WHERE representative_picture_id IS NOT NULL AND {$where_cats_} AND i.id IS NULL;";
    $wrong_representant = query2array($query, null, 'id');

    if ($wrong_representant !== []) {
        $wrong_representant_ = wordwrap(implode(', ', $wrong_representant), 120, "\n");
        $query = "UPDATE categories SET representative_picture_id = NULL WHERE id IN ({$wrong_representant_});";
        pwg_query($query);
    }

    if (! $conf['allow_random_representative']) {
        // If the random representant is not allowed, we need to find
        // categories with elements and with no representant. Those categories
        // must be added to the list of categories to set to a random
        // representant.
        $where_cats_ = sprintf($where_cats, 'category_id');
        $query = "SELECT DISTINCT id FROM categories INNER JOIN image_category ON id = category_id WHERE representative_picture_id IS NULL AND {$where_cats_};";
        $to_rand = query2array($query, null, 'id');
        if ($to_rand !== []) {
            set_random_representant($to_rand);
        }
    }

    return null;
}

/**
 * Checks and repairs image_category integrity.
 * Removes all entries from the table which correspond to a deleted image.
 */
function images_integrity(): void
{
    $query = 'SELECT image_id FROM image_category LEFT JOIN images ON id = image_id WHERE id IS NULL;';
    $orphan_image_ids = query2array($query, null, 'image_id');

    if ($orphan_image_ids !== []) {
        $orphan_image_ids_ = implode(',', $orphan_image_ids);
        $query = "DELETE FROM image_category WHERE image_id IN ({$orphan_image_ids_});";
        pwg_query($query);
    }
}

/**
 * Checks and repairs integrity on categories.
 * Removes all entries from related tables which correspond to a deleted category.
 */
function categories_integrity(): void
{
    $related_columns = [
        'image_category.category_id',
        'user_access.cat_id',
        'group_access.cat_id',
        'old_permalinks.cat_id',
        'user_cache_categories.cat_id',
    ];

    foreach ($related_columns as $fullcol) {
        [$table, $column] = explode('.', $fullcol);

        $query = "SELECT {$column} FROM {$table} LEFT JOIN categories ON id = {$column} WHERE id IS NULL;";
        $orphans = array_unique(query2array($query, null, $column));

        if ($orphans !== []) {
            $orphans_ = implode(',', $orphans);
            $query = "DELETE FROM {$table} WHERE {$column} IN ({$orphans_});";
            pwg_query($query);
        }
    }
}

/**
 * Returns an array containing sub-directories which are potentially
 * a category.
 * Directories named ".svn", "thumbnail", "pwg_high" or "pwg_representative"
 * are omitted.
 *
 * @return string[]
 */
function get_fs_directories(
    string $path,
    bool $recursive = true
): array {
    global $conf;

    $dirs = [];
    $path = rtrim($path, '/');

    $exclude_folders = array_merge(
        $conf['sync_exclude_folders'],
        [
            '.', '..', '.svn',
            'thumbnail', 'pwg_high',
            'pwg_representative',
            'pwg_format',
        ]
    );
    $exclude_folders = array_flip($exclude_folders);

    if (is_dir($path) && ($contents = opendir($path))) {
        while (($node = readdir($contents)) !== false) {
            if (is_dir($path . '/' . $node) && ! isset($exclude_folders[$node])) {
                $dirs[] = $path . '/' . $node;
                if ($recursive) {
                    $dirs = array_merge($dirs, get_fs_directories($path . '/' . $node));
                }
            }
        }

        closedir($contents);
    }

    return $dirs;
}

/**
 * save the rank depending on given categories order
 *
 * The list of ordered categories id is supposed to be in the same parent
 * category
 */
function save_categories_order(
    array $categories
): void {
    $current_rank_for_id_uppercat = [];
    $current_rank = 0;

    $datas = [];
    foreach ($categories as $category) {
        if (is_array($category)) {
            $id = $category['id'];
            $id_uppercat = $category['id_uppercat'];

            if (! isset($current_rank_for_id_uppercat[$id_uppercat])) {
                $current_rank_for_id_uppercat[$id_uppercat] = 0;
            }

            $current_rank = ++$current_rank_for_id_uppercat[$id_uppercat];
        } else {
            $id = $category;
            $current_rank++;
        }

        $datas[] = [
            'id' => $id,
            'rank' => $current_rank,
        ];
    }

    $fields = [
        'primary' => ['id'],
        'update' => ['rank'],
    ];
    mass_updates('categories', $fields, $datas);

    update_global_rank();
}

/**
 * Orders categories (update categories.rank and global_rank database fields)
 * so that rank field are consecutive integers starting at 1 for each child.
 */
function update_global_rank(): int
{
    $query = 'SELECT id, id_uppercat, uppercats, rank_column, global_rank FROM categories ORDER BY id_uppercat, rank_column, name;';

    global $cat_map; // used in preg_replace callback
    $cat_map = [];

    $current_rank = 0;
    $current_uppercat = '';

    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result)) {
        if ($row['id_uppercat'] != $current_uppercat) {
            $current_rank = 0;
            $current_uppercat = $row['id_uppercat'];
        }

        ++$current_rank;
        $cat =
          [
              'rank' => $current_rank,
              'rank_changed' => $current_rank != $row['rank_column'],
              'global_rank' => $row['global_rank'],
              'uppercats' => $row['uppercats'],
          ];
        $cat_map[$row['id']] = $cat;
    }

    $datas = [];

    $cat_map_callback = fn ($m): int => $cat_map[$m[1]]['rank'];

    foreach ($cat_map as $id => $cat) {
        $new_global_rank = preg_replace_callback(
            '/(\d+)/',
            $cat_map_callback,
            str_replace(',', '.', $cat['uppercats'])
        );

        if ($cat['rank_changed'] || $new_global_rank !== $cat['global_rank']) {
            $datas[] = [
                'id' => $id,
                'rank' => $cat['rank'],
                'global_rank' => $new_global_rank,
            ];
        }
    }

    unset($cat_map);

    mass_updates(
        'categories',
        [
            'primary' => ['id'],
            'update' => ['rank_column', 'global_rank'],
        ],
        $datas
    );
    return count($datas);
}

/**
 * Change the **visible** property on a set of categories.
 *
 * @param int[] $categories
 * @param boolean $unlock_child optional   default false
 */
function set_cat_visible(
    array $categories,
    bool|string $value,
    bool $unlock_child = false
): bool|null {
    if (($value = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)) === null) {
        trigger_error("set_cat_visible invalid param {$value}", E_USER_WARNING);
        return false;
    }

    // unlocking a category => all its parent categories become unlocked
    if ($value) {
        $cats = get_uppercat_ids($categories);
        if ($unlock_child) {
            $cats = array_merge($cats, get_subcat_ids($categories));
        }

        $cats_ = implode(',', $cats);
        $query = "UPDATE categories SET visible = 'true' WHERE id IN ({$cats_});";
        pwg_query($query);
    }
    // locking a category   => all its child categories become locked
    else {
        $subcats = get_subcat_ids($categories);
        $subcats_ = implode(',', $subcats);
        $query = "UPDATE categories SET visible = 'false' WHERE id IN ({$subcats_});";
        pwg_query($query);
    }

    return null;
}

/**
 * Change the **status** property on a set of categories : private or public.
 *
 * @param int[] $categories
 */
function set_cat_status(
    array $categories,
    string $value
): bool|null {
    if (! in_array($value, ['public', 'private'])) {
        trigger_error("set_cat_status invalid param {$value}", E_USER_WARNING);
        return false;
    }

    // make public a category => all its parent categories become public
    if ($value === 'public') {
        $uppercats = get_uppercat_ids($categories);
        $uppercats_ = implode(',', $uppercats);
        $query = "UPDATE categories SET status = 'public' WHERE id IN ({$uppercats_});";
        pwg_query($query);
    }

    // make a category private => all its child categories become private
    if ($value === 'private') {
        $subcats = get_subcat_ids($categories);

        $subcats_ = implode(',', $subcats);
        $query = "UPDATE categories SET status = 'private' WHERE id IN ({$subcats_});";
        pwg_query($query);

        // We have to keep permissions consistant: a sub-album can't be
        // permitted to a user or group if its parent album is not permitted to
        // the same user or group. Let's remove all permissions on sub-albums if
        // it is not consistant. Let's take the following example:
        //
        // A1        permitted to U1,G1
        // A1/A2     permitted to U1,U2,G1,G2
        // A1/A2/A3  permitted to U3,G1
        // A1/A2/A4  permitted to U2
        // A1/A5     permitted to U4
        // A6        permitted to U4
        // A6/A7     permitted to G1
        //
        // (we consider that it can be possible to start with inconsistant
        // permission, given that public albums can have hidden permissions,
        // revealed once the album returns to private status)
        //
        // The admin selects A2,A3,A4,A5,A6,A7 to become private (all but A1,
        // which is private, which can be true if we're moving A2 into A1). The
        // result must be:
        //
        // A2 permission removed to U2,G2
        // A3 permission removed to U3
        // A4 permission removed to U2
        // A5 permission removed to U2
        // A6 permission removed to U4
        // A7 no permission removed
        //
        // 1) we must extract "top albums": A2, A5 and A6
        // 2) for each top album, decide which album is the reference for permissions
        // 3) remove all inconsistant permissions from sub-albums of each top-album

        // step 1, search top albums
        $top_categories = [];
        $parent_ids = [];

        $categories_ = implode(',', $categories);
        $query = "SELECT id, name, id_uppercat, uppercats, global_rank FROM categories WHERE id IN ({$categories_});";
        $all_categories = query2array($query);
        usort($all_categories, global_rank_compare(...));

        foreach ($all_categories as $cat) {
            $is_top = true;

            if (! empty($cat['id_uppercat'])) {
                foreach (explode(',', (string) $cat['uppercats']) as $id_uppercat) {
                    if (isset($top_categories[$id_uppercat])) {
                        $is_top = false;
                        break;
                    }
                }
            }

            if ($is_top) {
                $top_categories[$cat['id']] = $cat;

                if (! empty($cat['id_uppercat'])) {
                    $parent_ids[] = $cat['id_uppercat'];
                }
            }
        }

        // step 2, search the reference album for permissions
        //
        // to find the reference of each top album, we will need the parent albums
        $parent_cats = [];

        if ($parent_ids !== []) {
            $parent_ids_ = implode(',', $parent_ids);
            $query = "SELECT id, status FROM categories WHERE id IN ({$parent_ids_});";
            $parent_cats = query2array($query, 'id');
        }

        $tables = [
            'user_access' => 'user_id',
            'group_access' => 'group_id',
        ];

        foreach ($top_categories as $top_category) {
            // what is the "reference" for list of permissions? The parent album
            // if it is private, else the album itself
            $ref_cat_id = $top_category['id'];

            if (! empty($top_category['id_uppercat']) && isset($parent_cats[$top_category['id_uppercat']]) && $parent_cats[$top_category['id_uppercat']]['status'] == 'private') {
                $ref_cat_id = $top_category['id_uppercat'];
            }

            $subcats = get_subcat_ids([$top_category['id']]);

            foreach ($tables as $table => $field) {
                // what are the permissions user/group of the reference album
                $query = "SELECT {$field} FROM {$table} WHERE cat_id = {$ref_cat_id};";
                $ref_access = query2array($query, null, $field);

                if (count($ref_access) == 0) {
                    $ref_access[] = -1;
                }

                // step 3, remove the inconsistant permissions from sub-albums
                $ref_access_ = implode(',', $ref_access);
                $subcats_ = implode(',', $subcats);
                $query = "DELETE FROM {$table} WHERE {$field} NOT IN ({$ref_access_}) AND cat_id IN ({$subcats_});";
                pwg_query($query);
            }
        }
    }

    return null;
}

/**
 * Returns all uppercats category ids of the given category ids.
 *
 * @param int[] $cat_ids
 * @return int[]
 */
function get_uppercat_ids(
    array $cat_ids
): array {
    if (! is_array($cat_ids) || count($cat_ids) < 1) {
        return [];
    }

    $uppercats = [];

    $cat_ids_ = implode(',', $cat_ids);
    $query = "SELECT uppercats FROM categories WHERE id IN ({$cat_ids_});";
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result)) {
        $uppercats = array_merge(
            $uppercats,
            explode(',', (string) $row['uppercats'])
        );
    }

    return array_unique($uppercats);
}

function get_category_representant_properties(
    string $image_id,
    string $size = null
): array {
    $query = "SELECT id, representative_ext, path FROM images WHERE id = {$image_id};";

    $row = pwg_db_fetch_assoc(pwg_query($query));
    $src = $size == null ? DerivativeImage::thumb_url($row) : DerivativeImage::url($size, $row);

    $url = get_root_url() . 'admin.php?page=photo-' . $image_id;

    return [
        'src' => $src,
        'url' => $url,
    ];
}

/**
 * Set a new random representant to the categories.
 *
 * @param int[] $categories
 */
function set_random_representant(
    array $categories
): void {
    $datas = [];
    foreach ($categories as $category_id) {
        $query = "SELECT image_id FROM image_category WHERE category_id = {$category_id} ORDER BY " . DB_RANDOM_FUNCTION . ' LIMIT 1;';
        [$representative] = pwg_db_fetch_row(pwg_query($query));

        $datas[] = [
            'id' => $category_id,
            'representative_picture_id' => $representative,
        ];
    }

    mass_updates(
        'categories',
        [
            'primary' => ['id'],
            'update' => ['representative_picture_id'],
        ],
        $datas
    );
}

/**
 * Returns the fulldir for each given category id.
 *
 * @param int[] $cat_ids intcat_ids
 * @return string[]
 */
function get_fulldirs(
    array $cat_ids
): array {
    if (count($cat_ids) == 0) {
        return [];
    }

    // caching directories of existing categories
    global $cat_dirs; // used in preg_replace callback
    $query = 'SELECT id, dir FROM categories WHERE dir IS NOT NULL;';
    $cat_dirs = query2array($query, 'id', 'dir');

    // caching galleries_url
    $query = 'SELECT id, galleries_url FROM sites;';
    $galleries_url = query2array($query, 'id', 'galleries_url');

    // categories : id, site_id, uppercats
    $cat_ids_ = wordwrap(implode(', ', $cat_ids), 80, "\n");
    $query = "SELECT id, uppercats, site_id FROM categories WHERE dir IS NOT NULL AND id IN ({$cat_ids_});";
    $categories = query2array($query);

    // filling $cat_fulldirs
    $cat_dirs_callback = fn ($m) => $cat_dirs[$m[1]];

    $cat_fulldirs = [];
    foreach ($categories as $category) {
        $uppercats = str_replace(',', '/', $category['uppercats']);
        $cat_fulldirs[$category['id']] = $galleries_url[$category['site_id']];
        $cat_fulldirs[$category['id']] .= preg_replace_callback(
            '/(\d+)/',
            $cat_dirs_callback,
            $uppercats
        );
    }

    unset($cat_dirs);

    return $cat_fulldirs;
}

/**
 * Returns an array with all file system files according to $conf['file_ext']
 *
 * @deprecated 2.4
 */
function get_fs(
    string $path,
    bool $recursive = true
): array {
    global $conf;

    // because isset is faster than in_array...
    if (! isset($conf['flip_picture_ext'])) {
        $conf['flip_picture_ext'] = array_flip($conf['picture_ext']);
    }

    if (! isset($conf['flip_file_ext'])) {
        $conf['flip_file_ext'] = array_flip($conf['file_ext']);
    }

    $fs['elements'] = [];
    $fs['thumbnails'] = [];
    $fs['representatives'] = [];
    $subdirs = [];

    if (is_dir($path)) {
        if ($contents = opendir($path)) {
            while (($node = readdir($contents)) !== false) {
                if ($node === '.' || $node === '..') {
                    continue;
                }

                if (is_file($path . '/' . $node)) {
                    $extension = get_extension($node);

                    if (isset($conf['flip_picture_ext'][$extension])) {
                        if (basename($path) === 'thumbnail') {
                            $fs['thumbnails'][] = $path . '/' . $node;
                        } elseif (basename($path) === 'pwg_representative') {
                            $fs['representatives'][] = $path . '/' . $node;
                        } else {
                            $fs['elements'][] = $path . '/' . $node;
                        }
                    } elseif (isset($conf['flip_file_ext'][$extension])) {
                        $fs['elements'][] = $path . '/' . $node;
                    }
                } elseif (is_dir($path . '/' . $node) && $node !== 'pwg_high' && $recursive) {
                    $subdirs[] = $node;
                }
            }
        }

        closedir($contents);

        foreach ($subdirs as $subdir) {
            $tmp_fs = get_fs($path . '/' . $subdir);

            $fs['elements'] = array_merge(
                $fs['elements'],
                $tmp_fs['elements']
            );

            $fs['thumbnails'] = array_merge(
                $fs['thumbnails'],
                $tmp_fs['thumbnails']
            );

            $fs['representatives'] = array_merge(
                $fs['representatives'],
                $tmp_fs['representatives']
            );
        }
    }

    return $fs;
}

/**
 * Synchronize base users list and related users list.
 *
 * Compares and synchronizes base users table with its child
 * tables (user_infos, USER_ACCESS, USER_CACHE, USER_GROUP) : each
 * base user must be present in child tables, users in child tables not
 * present in base table must be deleted.
 */
function sync_users(): void
{
    global $conf;

    $query = "SELECT {$conf['user_fields']['id']} AS id FROM users;";
    $base_users = query2array($query, null, 'id');

    $query = 'SELECT user_id FROM user_infos;';
    $infos_users = query2array($query, null, 'user_id');

    // users present in $base_users and not in $infos_users must be added
    $to_create = array_diff($base_users, $infos_users);

    if ($to_create !== []) {
        create_user_infos($to_create);
    }

    // users present in user related tables must be present in the base user
    // table
    $tables = [
        'user_mail_notification',
        'user_feed',
        'user_infos',
        'user_access',
        'user_cache',
        'user_cache_categories',
        'user_group',
    ];

    foreach ($tables as $table) {
        $query = "SELECT DISTINCT user_id FROM {$table};";
        $to_delete = array_diff(
            query2array($query, null, 'user_id'),
            $base_users
        );

        if ($to_delete !== []) {
            $to_delete_ = implode(',', $to_delete);
            $query = "DELETE FROM {$table} WHERE user_id in ({$to_delete_});";
            pwg_query($query);
        }
    }
}

/**
 * Updates categories.uppercats field based on categories.id + categories.id_uppercat
 */
function update_uppercats(): void
{
    $query = 'SELECT id, id_uppercat, uppercats FROM categories;';
    $cat_map = query2array($query, 'id');

    $datas = [];
    foreach ($cat_map as $id => $cat) {
        $upper_list = [];

        $uppercat = $id;
        while ($uppercat) {
            $upper_list[] = $uppercat;
            $uppercat = $cat_map[$uppercat]['id_uppercat'];
        }

        $new_uppercats = implode(',', array_reverse($upper_list));
        if ($new_uppercats != $cat['uppercats']) {
            $datas[] = [
                'id' => $id,
                'uppercats' => $new_uppercats,
            ];
        }
    }

    $fields = [
        'primary' => ['id'],
        'update' => ['uppercats'],
    ];
    mass_updates('categories', $fields, $datas);
}

/**
 * Update images.path field base on images.file and storage categories fulldirs.
 */
function update_path(): void
{
    $query = 'SELECT DISTINCT(storage_category_id) FROM images WHERE storage_category_id IS NOT NULL;';
    $cat_ids = query2array($query, null, 'storage_category_id');
    $fulldirs = get_fulldirs($cat_ids);

    foreach ($cat_ids as $cat_id) {
        $query = "UPDATE images SET path = '{$fulldirs[$cat_id]}/file' WHERE storage_category_id = {$cat_id};";
        pwg_query($query);
    }
}

/**
 * Change the parent category of the given categories. The categories are
 * supposed virtual.
 *
 * @param int[] $category_ids
 * @param int $new_parent (-1 for root)
 */
function move_categories(
    array $category_ids,
    int $new_parent = -1
): void {
    global $page;

    if (count($category_ids) == 0) {
        return;
    }

    $new_parent = $new_parent < 1 ? 'NULL' : $new_parent;

    $categories = [];

    $category_ids_ = implode(',', $category_ids);
    $query = "SELECT id, id_uppercat, status, uppercats FROM categories WHERE id IN ({$category_ids_});";
    $result = pwg_query($query);
    while ($row = pwg_db_fetch_assoc($result)) {
        $categories[$row['id']] =
          [
              'parent' => empty($row['id_uppercat']) ? 'NULL' : $row['id_uppercat'],
              'status' => $row['status'],
              'uppercats' => $row['uppercats'],
          ];
    }

    // is the movement possible? The movement is impossible if you try to move
    // a category in a sub-category or itself
    if ($new_parent != 'NULL') {
        $query = "SELECT uppercats FROM categories WHERE id = {$new_parent};";
        [$new_parent_uppercats] = pwg_db_fetch_row(pwg_query($query));

        foreach ($categories as $category) {
            // technically, you can't move a category with uppercats 12,125,13,14
            // into a new parent category with uppercats 12,125,13,14,24
            if (preg_match('/^' . $category['uppercats'] . '(,|$)/', (string) $new_parent_uppercats)) {
                $page['errors'][] = l10n('You cannot move an album in its own sub album');
                return;
            }
        }
    }

    $tables = [
        'user_access' => 'user_id',
        'group_access' => 'group_id',
    ];

    $category_ids_ = implode(',', $category_ids);
    $query = "UPDATE categories SET id_uppercat = {$new_parent} WHERE id IN ({$category_ids_});";
    pwg_query($query);

    update_uppercats();
    update_global_rank();

    // status and related permissions management
    if ($new_parent == 'NULL') {
        $parent_status = 'public';
    } else {
        $query = "SELECT status FROM categories WHERE id = {$new_parent};";
        [$parent_status] = pwg_db_fetch_row(pwg_query($query));
    }

    if ($parent_status == 'private') {
        set_cat_status(array_keys($categories), 'private');
    }

    $page['infos'][] = l10n_dec(
        '%d album moved',
        '%d albums moved',
        count($categories)
    );

    pwg_activity('album', $category_ids, 'move', [
        'parent' => $new_parent,
    ]);
}

/**
 * Create a virtual category.
 *
 * @param array $options
 *    - boolean commentable
 *    - boolean visible
 *    - string status
 *    - string comment
 *    - boolean inherit
 * @return array ('info', 'id') or ('error')
 */
function create_virtual_category(
    string $category_name,
    int $parent_id = null,
    array $options = []
): array {
    global $conf, $user;

    // is the given category name only containing blank spaces ?
    if (preg_match('/^\s*$/', $category_name)) {
        return [
            'error' => l10n('The name of an album must not be empty'),
        ];
    }

    $rank = 0;
    if ($conf['newcat_default_position'] == 'last') {
        //what is the current higher rank for this parent?
        $parent_id_ = ($parent_id === null || $parent_id === 0 ? 'IS NULL' : "= {$parent_id}");
        $query = "SELECT MAX(rank_column) AS max_rank FROM categories WHERE id_uppercat {$parent_id_};";
        $row = pwg_db_fetch_assoc(pwg_query($query));

        if (is_numeric($row['max_rank'])) {
            $rank = $row['max_rank'] + 1;
        }
    }

    $insert = [
        'name' => $category_name,
        'rank' => $rank,
        'global_rank' => 0,
    ];

    // is the album commentable?
    if (isset($options['commentable']) && is_bool($options['commentable'])) {
        $insert['commentable'] = $options['commentable'];
    } else {
        $insert['commentable'] = $conf['newcat_default_commentable'];
    }

    $insert['commentable'] = boolean_to_string($insert['commentable']);

    // is the album temporarily locked? (only visible by administrators,
    // whatever permissions) (may be overwritten if parent album is not
    // visible)
    if (isset($options['visible']) && is_bool($options['visible'])) {
        $insert['visible'] = $options['visible'];
    } else {
        $insert['visible'] = $conf['newcat_default_visible'];
    }

    $insert['visible'] = boolean_to_string($insert['visible']);

    // is the album private? (may be overwritten if parent album is private)
    if (isset($options['status']) && $options['status'] == 'private') {
        $insert['status'] = 'private';
    } else {
        $insert['status'] = $conf['newcat_default_status'];
    }

    // any description for this album?
    if (isset($options['comment'])) {
        $insert['comment'] = $conf['allow_html_descriptions'] ? $options['comment'] : strip_tags((string) $options['comment']);
    }

    if ($parent_id !== null && $parent_id !== 0 && is_numeric($parent_id)) {
        $query = "SELECT id, uppercats, global_rank, visible, status FROM categories WHERE id = {$parent_id};";
        $parent = pwg_db_fetch_assoc(pwg_query($query));

        $insert['id_uppercat'] = $parent['id'];
        $insert['global_rank'] = $parent['global_rank'] . '.' . $insert['rank'];

        // at creation, must a category be visible or not ? Warning : if the
        // parent category is invisible, the category is automatically create
        // invisible. (invisible = locked)
        if ($parent['visible'] == 'false') {
            $insert['visible'] = 'false';
        }

        // at creation, must a category be public or private ? Warning : if the
        // parent category is private, the category is automatically create
        // private.
        if ($parent['status'] == 'private') {
            $insert['status'] = 'private';
        }

        $uppercats_prefix = $parent['uppercats'] . ',';
    } else {
        $uppercats_prefix = '';
    }

    // we have then to add the virtual category
    single_insert('categories', $insert);
    $inserted_id = pwg_db_insert_id();

    single_update(
        'categories',
        [
            'uppercats' => $uppercats_prefix . $inserted_id,
        ],
        [
            'id' => $inserted_id,
        ]
    );

    update_global_rank();

    if ($insert['status'] == 'private' && ! empty($insert['id_uppercat']) && (isset($options['inherit']) && $options['inherit'] || $conf['inheritance_by_default'])) {
        $query = "SELECT group_id FROM group_access WHERE cat_id = {$insert['id_uppercat']};";
        $granted_grps = query2array($query, null, 'group_id');
        $inserts = [];
        foreach ($granted_grps as $granted_grp) {
            $inserts[] = [
                'group_id' => $granted_grp,
                'cat_id' => $inserted_id,
            ];
        }

        mass_inserts('group_access', ['group_id', 'cat_id'], $inserts);

        $query = "SELECT user_id FROM user_access WHERE cat_id = {$insert['id_uppercat']};";
        $granted_users = query2array($query, null, 'user_id');
        add_permission_on_category($inserted_id, $granted_users);
    } elseif ($insert['status'] == 'private') {
        add_permission_on_category($inserted_id, array_unique(array_merge(get_admins(), [$user['id']])));
    }

    trigger_notify('create_virtual_category', array_merge([
        'id' => $inserted_id,
    ], $insert));
    pwg_activity('album', $inserted_id, 'add');

    return [
        'info' => l10n('Album added'),
        'id' => $inserted_id,
    ];
}

/**
 * Set tags to an image.
 * Warning: given tags are all tags associated to the image, not additionnal tags.
 *
 * @param int[] $tags
 */
function set_tags(
    array $tags,
    string $image_id
): void {
    set_tags_of([
        $image_id => $tags,
    ]);
}

/**
 * Add new tags to a set of images.
 *
 * @param int[] $tags
 * @param int[] $images
 */
function add_tags(
    array $tags,
    array $images
): void {
    if (count($tags) == 0 || count($images) == 0) {
        return;
    }

    $taglist_before = get_image_tag_ids($images);

    // we can't insert twice the same {image_id,tag_id} so we must first
    // delete lines we'll insert later
    $images_ = implode(',', $images);
    $tags_ = implode(',', $tags);
    $query = "DELETE FROM image_tag WHERE image_id IN ({$images_}) AND tag_id IN ({$tags_});";
    pwg_query($query);

    $inserts = [];
    foreach ($images as $image_id) {
        foreach (array_unique($tags) as $tag_id) {
            $inserts[] = [
                'image_id' => $image_id,
                'tag_id' => $tag_id,
            ];
        }
    }

    mass_inserts(
        'image_tag',
        array_keys($inserts[0]),
        $inserts
    );

    $taglist_after = get_image_tag_ids($images);
    $images_to_update = compare_image_tag_lists($taglist_before, $taglist_after);
    update_images_lastmodified($images_to_update);

    invalidate_user_cache_nb_tags();
}

/**
 * Delete tags and tags associations.
 *
 * @param int[] $tag_ids
 */
function delete_tags(
    array $tag_ids
): bool|null {
    if (is_numeric($tag_ids)) {
        $tag_ids = [$tag_ids];
    }

    if (! is_array($tag_ids)) {
        return false;
    }

    // we need the list of impacted images, to update their lastmodified
    $tag_ids_ = implode(',', $tag_ids);
    $query = "SELECT image_id FROM image_tag WHERE tag_id IN ({$tag_ids_});";
    $image_ids = query2array($query, null, 'image_id');

    $tag_ids_ = implode(',', $tag_ids);
    $query = "DELETE FROM image_tag WHERE tag_id IN ({$tag_ids_});";
    pwg_query($query);

    $tag_ids_ = implode(',', $tag_ids);
    $query = "DELETE FROM tags WHERE id IN ({$tag_ids_});";
    pwg_query($query);

    trigger_notify('delete_tags', $tag_ids);
    pwg_activity('tag', $tag_ids, 'delete');

    update_images_lastmodified($image_ids);
    invalidate_user_cache_nb_tags();

    return null;
}

/**
 * Returns a tag id from its name. If nothing found, create a new tag.
 *
 * @return int
 */
function tag_id_from_tag_name(
    string $tag_name
): mixed {
    global $page;

    $tag_name = trim($tag_name);
    if (isset($page['tag_id_from_tag_name_cache'][$tag_name])) {
        return $page['tag_id_from_tag_name_cache'][$tag_name];
    }

    // search existing by exact name
    $query = "SELECT id FROM tags WHERE name = '{$tag_name}';";
    if (count($existing_tags = query2array($query, null, 'id')) == 0) {
        $url_name = trigger_change('render_tag_url', $tag_name);
        // search existing by url name
        $query = "SELECT id FROM tags WHERE url_name = '{$url_name}';";
        if (count($existing_tags = query2array($query, null, 'id')) == 0) {
            // search by extended description (plugin sub name)
            $sub_name_where = trigger_change('get_tag_name_like_where', [], $tag_name);
            if (count($sub_name_where) > 0) {
                $sub_name_where_ = implode(' OR ', $sub_name_where);
                $query = "SELECT id FROM tags WHERE {$sub_name_where_};";
                $existing_tags = query2array($query, null, 'id');
            }

            if (count($existing_tags) == 0) {// finally create the tag
                mass_inserts(
                    'tags',
                    ['name', 'url_name'],
                    [
                        [
                            'name' => $tag_name,
                            'url_name' => $url_name,
                        ],
                    ]
                );

                $page['tag_id_from_tag_name_cache'][$tag_name] = pwg_db_insert_id();

                invalidate_user_cache_nb_tags();

                return $page['tag_id_from_tag_name_cache'][$tag_name];
            }
        }
    }

    $page['tag_id_from_tag_name_cache'][$tag_name] = $existing_tags[0];
    return $page['tag_id_from_tag_name_cache'][$tag_name];
}

/**
 * Set tags of images. Overwrites all existing associations.
 *
 * @param array $tags_of - keys are image ids, values are array of tag ids
 */
function set_tags_of(
    array $tags_of
): void {
    if ($tags_of !== []) {
        $taglist_before = get_image_tag_ids(array_keys($tags_of));
        global $logger;
        $logger->debug('taglist_before', $taglist_before);

        $tags_of_ = implode(',', array_keys($tags_of));
        $query = "DELETE FROM image_tag WHERE image_id IN ({$tags_of_});";
        pwg_query($query);

        $inserts = [];

        foreach ($tags_of as $image_id => $tag_ids) {
            foreach (array_unique($tag_ids) as $tag_id) {
                $inserts[] = [
                    'image_id' => $image_id,
                    'tag_id' => $tag_id,
                ];
            }
        }

        if ($inserts !== []) {
            mass_inserts(
                'image_tag',
                array_keys($inserts[0]),
                $inserts
            );
        }

        $taglist_after = get_image_tag_ids(array_keys($tags_of));
        global $logger;
        $logger->debug('taglist_after', $taglist_after);
        $images_to_update = compare_image_tag_lists($taglist_before, $taglist_after);
        global $logger;
        $logger->debug('$images_to_update', $images_to_update);

        update_images_lastmodified($images_to_update);
        invalidate_user_cache_nb_tags();
    }
}

/**
 * Get list of tag ids for each image. Returns an empty list if the image has
 * no tags.
 *
 * @since 2.9
 * @return array, image_id => list of tag ids
 */
function get_image_tag_ids(
    array $image_ids
): array {
    if (! is_array($image_ids) && is_int($image_ids)) {
        $images_ids = [$image_ids];
    }

    if (count($image_ids) == 0) {
        return [];
    }

    $image_ids_ = implode(',', $image_ids);
    $query = "SELECT image_id, tag_id FROM image_tag WHERE image_id IN ({$image_ids_});";

    $tags_of = array_fill_keys($image_ids, []);
    $image_tags = query2array($query);
    foreach ($image_tags as $image_tag) {
        $tags_of[$image_tag['image_id']][] = $image_tag['tag_id'];
    }

    return $tags_of;
}

/**
 * Compare the list of tags, for each image. Returns image_ids where tag list has changed.
 *
 * @since 2.9
 * @param array $taglist_before - for each image_id (key), list of tag ids
 * @param array $taglist_after - for each image_id (key), list of tag ids
 * @return array - image_ids where the list has changed
 */
function compare_image_tag_lists(
    array $taglist_before,
    array $taglist_after
): array {
    $images_to_update = [];

    foreach ($taglist_after as $image_id => $list_after) {
        sort($list_after);

        $list_before = $taglist_before[$image_id] ?? [];
        sort($list_before);

        if ($list_after != $list_before) {
            $images_to_update[] = $image_id;
        }
    }

    return $images_to_update;
}

/**
 * Instead of associating images to categories, add them in the lounge, waiting for take-off.
 *
 * @since 12
 * @param array $images - list of image ids
 * @param array $categories - list of category ids
 */
function fill_lounge(
    array $images,
    array $categories
): void {
    $inserts = [];
    foreach ($categories as $category_id) {
        foreach ($images as $image_id) {
            $inserts[] = [
                'image_id' => $image_id,
                'category_id' => $category_id,
            ];
        }
    }

    if ($inserts !== []) {
        mass_inserts(
            'lounge',
            array_keys($inserts[0]),
            $inserts,
            [
                'ignore' => true,
            ]
        );
    }
}

/**
 * Move images from the lounge to the categories they were intended for.
 *
 * @since 12
 * @return int number of images moved
 */
function empty_lounge(
    bool $invalidate_user_cache = true
): array|null {
    global $logger;

    if (isset($conf['empty_lounge_running'])) {
        [$running_exec_id, $running_exec_start_time] = explode('-', (string) $conf['empty_lounge_running']);
        if (time() - $running_exec_start_time > 60) {
            $logger->debug(__FUNCTION__ . ', exec=' . $running_exec_id . ', timeout stopped by another call to the function');
            conf_delete_param('empty_lounge_running');
        }
    }

    $exec_id = generate_key(4);
    $logger->debug(__FUNCTION__ . (isset($_REQUEST['method']) ? ' (API:' . $_REQUEST['method'] . ')' : '') . ', exec=' . $exec_id . ', begins');

    // if lounge is already being emptied, skip
    // todo: rewrite this to be compatible with both postgre/mysql
    $value_ = $exec_id . '-' . time();
    $query = "INSERT IGNORE INTO config SET param = 'empty_lounge_running', value = '{$value_}';";
    pwg_query($query);

    [$empty_lounge_running] = pwg_db_fetch_row(pwg_query("SELECT value FROM config WHERE param = 'empty_lounge_running';"));
    [$running_exec_id] = explode('-', (string) $empty_lounge_running);

    if ($running_exec_id !== $exec_id) {
        $logger->debug(__FUNCTION__ . ', exec=' . $exec_id . ', skip');
        return null;
    }

    $logger->debug(__FUNCTION__ . ', exec=' . $exec_id . ' wins the race and gets the token!');

    $max_image_id = 0;

    $query = 'SELECT image_id, category_id FROM lounge ORDER BY category_id ASC, image_id ASC;';

    $rows = query2array($query);

    $images = [];
    foreach ($rows as $idx => $row) {
        if ($row['image_id'] > $max_image_id) {
            $max_image_id = $row['image_id'];
        }

        $images[] = $row['image_id'];

        if (! isset($rows[$idx + 1]) || $rows[$idx + 1]['category_id'] != $row['category_id']) {
            // if we're at the end of the loop OR if category changes
            associate_images_to_categories($images, [$row['category_id']]);
            $images = [];
        }
    }

    $query = "DELETE FROM lounge WHERE image_id <= {$max_image_id};";
    pwg_query($query);

    if ($invalidate_user_cache) {
        invalidate_user_cache();
    }

    conf_delete_param('empty_lounge_running');

    $logger->debug(__FUNCTION__ . ', exec=' . $exec_id . ', ends');

    trigger_notify('empty_lounge', $rows);

    return $rows;
}

/**
 * Associate a list of images to a list of categories.
 * The function will not duplicate links and will preserve ranks.
 *
 * @param int[] $images
 * @param int[] $categories
 */
function associate_images_to_categories(
    array $images,
    array $categories
): bool|null {
    if (count($images) == 0 || count($categories) == 0) {
        return false;
    }

    // get existing associations
    $images_ = implode(',', $images);
    $categories_ = implode(',', $categories);
    $query = "SELECT image_id, category_id FROM image_category WHERE image_id IN ({$images_}) AND category_id IN ({$categories_});";
    $result = pwg_query($query);

    $existing = [];
    while ($row = pwg_db_fetch_assoc($result)) {
        $existing[$row['category_id']][] = $row['image_id'];
    }

    // get max rank of each categories
    $categories_ = implode(',', $categories);
    $query = "SELECT category_id, MAX(rank_column) AS max_rank FROM image_category WHERE rank_column IS NOT NULL AND category_id IN ({$categories_}) GROUP BY category_id;";
    $current_rank_of = query2array(
        $query,
        'category_id',
        'max_rank'
    );

    // associate only not already associated images
    $inserts = [];
    foreach ($categories as $category_id) {
        if (! isset($current_rank_of[$category_id])) {
            $current_rank_of[$category_id] = 0;
        }

        if (! isset($existing[$category_id])) {
            $existing[$category_id] = [];
        }

        foreach ($images as $image_id) {
            if (! in_array($image_id, $existing[$category_id])) {
                $rank = ++$current_rank_of[$category_id];

                $inserts[] = [
                    'image_id' => $image_id,
                    'category_id' => $category_id,
                    'rank_column' => $rank,
                ];
            }
        }
    }

    if ($inserts !== []) {
        mass_inserts(
            'image_category',
            array_keys($inserts[0]),
            $inserts
        );

        update_category($categories);
    }

    return null;
}

/**
 * Dissociate a list of images from a category.
 *
 * @param int[] $images
 */
function dissociate_images_from_category($images, string $category): int
{
    // physical links must not be broken, so we must first retrieve image_id
    // which create virtual links with the category to "dissociate from".
    $images_ = implode(',', $images);
    $query =
    "SELECT id FROM image_category INNER JOIN images ON image_id = id WHERE category_id = {$category} AND id IN ({$images_})
     AND (category_id != storage_category_id OR storage_category_id IS NULL);";
    $dissociables = query2array($query, null, 'id');

    if ($dissociables !== []) {
        $dissociables_ = implode(',', $dissociables);
        $query = "DELETE FROM image_category WHERE category_id = {$category} AND image_id IN ({$dissociables_});";
        pwg_query($query);
    }

    return count($dissociables);
}

/**
 * Dissociate images from all old categories except their storage category and
 * associate to new categories.
 * This function will preserve ranks.
 *
 * @param int[] $images
 * @param int[] $categories
 */
function move_images_to_categories(
    array $images,
    array $categories
): bool|null {
    if (count($images) == 0) {
        return false;
    }

    // let's first break links with all old albums but their "storage album"
    $images_ = implode(',', $images);
    $query = "DELETE image_category.* FROM image_category JOIN images ON image_id = id WHERE id IN ({$images_})";

    if (is_array($categories) && $categories !== []) {
        $categories_ = implode(',', $categories);
        $query .= " AND category_id NOT IN ({$categories_})";
    }

    $query .= ' AND (storage_category_id IS NULL OR storage_category_id != category_id);';
    pwg_query($query);

    if (is_array($categories) && $categories !== []) {
        associate_images_to_categories($images, $categories);
    }

    return null;
}

/**
 * Associate images associated to a list of source categories to a list of
 * destination categories.
 *
 * @param int[] $sources
 * @param int[] $destinations
 */
function associate_categories_to_categories(
    array $sources,
    array $destinations
): bool|null {
    if (count($sources) == 0) {
        return false;
    }

    $sources_ = implode(',', $sources);
    $query = "SELECT image_id FROM image_category WHERE category_id IN ({$sources_});";
    $images = query2array($query, null, 'image_id');

    associate_images_to_categories($images, $destinations);

    return null;
}

/**
 * Refer main Piwigo URLs (currently PHPWG_DOMAIN domain)
 *
 * @return string[]
 */
function pwg_URL(): array
{
    return [
        'HOME' => PHPWG_URL,
        'WIKI' => PHPWG_URL . '/doc',
        'DEMO' => PHPWG_URL . '/demo',
        'FORUM' => PHPWG_URL . '/forum',
        'BUGS' => PHPWG_URL . '/bugs',
        'EXTENSIONS' => PHPWG_URL . '/ext',
    ];
}

/**
 * Invalidates cached data (permissions and category counts) for all users.
 */
function invalidate_user_cache(
    bool $full = true
): void {
    if ($full) {
        $query = 'TRUNCATE TABLE user_cache_categories;';
        pwg_query($query);
        $query = 'TRUNCATE TABLE user_cache;';
        pwg_query($query);
    } else {
        $query = "UPDATE user_cache SET need_update = 'true';";
        pwg_query($query);
    }

    conf_delete_param('count_orphans');
    trigger_notify('invalidate_user_cache', $full);
}

/**
 * Invalidates cached tags counter for all users.
 */
function invalidate_user_cache_nb_tags(): void
{
    global $user;
    unset($user['nb_available_tags']);

    $query = 'UPDATE user_cache SET nb_available_tags = NULL;';
    pwg_query($query);
}

/**
 * Returns access levels as array used on template with html_options functions.
 */
function get_user_access_level_html_options(
    int $MinLevelAccess = ACCESS_FREE,
    int $MaxLevelAccess = ACCESS_CLOSED
): array {
    $tpl_options = [];
    for ($level = $MinLevelAccess; $level <= $MaxLevelAccess; $level++) {
        $tpl_options[$level] = l10n(sprintf('ACCESS_%d', $level));
    }

    return $tpl_options;
}

/**
 * returns a list of templates currently available in template-extension.
 * Each .tpl file is extracted from template-extension.
 *
 * @param string $start (internal use)
 * @return string[]
 */
function get_extents(
    string $start = ''
): array {
    if ($start === '') {
        $start = './template-extension';
    }

    $dir = opendir($start);
    $extents = [];

    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..' || $file === '.svn') {
            continue;
        }

        $path = $start . '/' . $file;
        if (is_dir($path)) {
            $extents = array_merge($extents, get_extents($path));
        } elseif (! is_link($path) && file_exists($path) && get_extension($path) === 'tpl') {
            $extents[] = substr($path, 21);
        }
    }

    return $extents;
}

/**
 * Create a new tag.
 *
 * @return array ('id', info') or ('error')
 */
function create_tag(
    string $tag_name
): array {
    // clean the tag, no html/js allowed in tag name
    $tag_name = strip_tags($tag_name);

    // does the tag already exists?
    $query = "SELECT id FROM tags WHERE name = '{$tag_name}';";
    $existing_tags = query2array($query, null, 'id');

    if (count($existing_tags) == 0) {
        single_insert(
            'tags',
            [
                'name' => $tag_name,
                'url_name' => trigger_change('render_tag_url', $tag_name),
            ]
        );

        $inserted_id = pwg_db_insert_id();

        return [
            'info' => l10n('Tag "%s" was added', stripslashes($tag_name)),
            'id' => $inserted_id,
        ];
    }

    return [
        'error' => l10n('Tag "%s" already exists', stripslashes($tag_name)),
    ];

}

/**
 * Is the category accessible to the (Admin) user ?
 * Note : if the user is not authorized to see this category, category jump
 * will be replaced by admin cat_modify page
 */
function cat_admin_access(
    int $category_id
): bool {
    global $user;
    // $filter['visible_categories'] and $filter['visible_images']
    // are not used because it's not necessary (filter <> restriction)
    return ! in_array($category_id, explode(',', $user['forbidden_categories'] ?? ''));
}

/**
 * Retrieve data from external URL.
 *
 * @param string|resource $dest - can be a file resource or string
 * @param array $get_data - data added to request url
 * @param array $post_data - data transmitted with POST
 * @param int $step (internal use)
 */
function fetchRemote(
    string $src,
    mixed &$dest,
    array $get_data = [],
    array $post_data = [],
    string $user_agent = 'Piwigo',
    int $step = 0
): bool {
    global $conf;

    // Try to retrieve data from local file?
    if (! url_is_remote($src)) {
        $content = file_get_contents($src);
        if ($content !== false) {
            is_resource($dest) ? fwrite($dest, $content) : $dest = $content;
            return true;
        }

        return false;

    }

    // After 3 redirections, return false
    if ($step > 3) {
        return false;
    }

    // Initialization
    $method = $post_data === [] ? 'GET' : 'POST';
    $request = $post_data === [] ? '' : http_build_query($post_data, '', '&');
    if ($get_data !== []) {
        $src .= str_contains($src, '?') ? '&' : '?';
        $src .= http_build_query($get_data, '', '&');
    }

    // Initialize $dest
    if (! is_resource($dest)) {
        $dest = '';
    }

    // Try curl to read remote file
    if (function_exists('curl_init') && function_exists('curl_exec')) {
        $ch = curl_init();

        if (isset($conf['use_proxy']) && $conf['use_proxy']) {
            curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 0);
            curl_setopt($ch, CURLOPT_PROXY, $conf['proxy_server']);
            if (isset($conf['proxy_auth']) && ! empty($conf['proxy_auth'])) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $conf['proxy_auth']);
            }
        }

        curl_setopt($ch, CURLOPT_URL, $src);
        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, $user_agent);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $request);
        }

        $content = curl_exec($ch);
        $header_length = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($content !== false && $status >= 200 && $status < 400) {
            if (preg_match('/Location:\s+?(.+)/', substr($content, 0, $header_length), $m)) {
                return fetchRemote($m[1], $dest, [], [], $user_agent, $step + 1);
            }

            $content = substr($content, $header_length);
            is_resource($dest) ? fwrite($dest, $content) : $dest = $content;
            return true;
        }
    }

    // Try file_get_contents to read remote file
    if (ini_get('allow_url_fopen')) {
        $opts = [
            'http' => [
                'method' => $method,
                'user_agent' => $user_agent,
                'header' => str_contains($src, 'format=php') ? "Content-type: application/x-www-form-urlencoded\r\n" : '',
            ],
        ];
        if ($method === 'POST') {
            $opts['http']['content'] = $request;
        }

        $context = stream_context_create($opts);
        $content = file_get_contents($src, false, $context);
        if ($content !== false) {
            is_resource($dest) ? fwrite($dest, $content) : $dest = $content;
            return true;
        }
    }

    // Try fsockopen to read remote file
    $src = parse_url($src);
    $host = $src['host'];
    $path = $src['path'] ?? '/';
    $path .= isset($src['query']) ? '?' . $src['query'] : '';

    if (($s = fsockopen($host, 80, $errno, $errstr, 5)) === false) {
        return false;
    }

    $http_request = $method . ' ' . $path . " HTTP/1.0\r\n";
    $http_request .= 'Host: ' . $host . "\r\n";
    if ($method === 'POST') {
        $http_request .= "Content-Type: application/x-www-form-urlencoded;\r\n";
        $http_request .= 'Content-Length: ' . strlen($request) . "\r\n";
    }

    $http_request .= 'User-Agent: ' . $user_agent . "\r\n";
    $http_request .= "Accept: */*\r\n";
    $http_request .= "\r\n";
    $http_request .= $request;

    fwrite($s, $http_request);

    $i = 0;
    $in_content = false;
    while (! feof($s)) {
        $line = fgets($s);

        if (rtrim($line, "\r\n") === '' && ! $in_content) {
            $in_content = true;
            $i++;
            continue;
        }

        if ($i == 0) {
            if (! preg_match('/HTTP\/(\\d\\.\\d)\\s*(\\d+)\\s*(.*)/', rtrim($line, "\r\n"), $m)) {
                fclose($s);
                return false;
            }

            $status = (int) $m[2];
            if ($status < 200 || $status >= 400) {
                fclose($s);
                return false;
            }
        }

        if (! $in_content) {
            if (preg_match('/Location:\s+?(.+)$/', rtrim($line, "\r\n"), $m)) {
                fclose($s);
                return fetchRemote(trim($m[1]), $dest, [], [], $user_agent, $step + 1);
            }

            $i++;
            continue;
        }

        is_resource($dest) ? fwrite($dest, $line) : $dest .= $line;
        $i++;
    }

    fclose($s);
    return true;
}

/**
 * Returns the groupname corresponding to the given group identifier if exists.
 *
 * @return string|false
 */
function get_groupname(
    int $group_id
): mixed {
    $group_id_ = intval($group_id);
    $query = "SELECT name FROM groups_table WHERE id = {$group_id_};";
    $result = pwg_query($query);
    if (pwg_db_num_rows($result) > 0) {
        [$groupname] = pwg_db_fetch_row($result);
    } else {
        return false;
    }

    return $groupname;
}

function delete_groups(
    array $group_ids
): array|bool {

    if (count($group_ids) == 0) {
        trigger_error('There is no group to delete', E_USER_WARNING);
        return false;
    }

    if (preg_match('/^group:(\d+)$/', (string) conf_get_param('email_admin_on_new_user', 'undefined'), $matches)) {
        foreach ($group_ids as $group_id) {
            if ($group_id == $matches[1]) {
                conf_update_param('email_admin_on_new_user', 'all', true);
            }
        }
    }

    $group_id_string = implode(',', $group_ids);

    // destruction of the access linked to the group
    $query = "DELETE FROM group_access WHERE group_id IN ({$group_id_string});";
    pwg_query($query);

    // destruction of the users links for this group
    $query = "DELETE FROM user_group WHERE group_id IN ({$group_id_string});";
    pwg_query($query);

    $query = "SELECT id, name FROM groups_table WHERE id IN ({$group_id_string});";

    $group_list = query2array($query, 'id', 'name');
    $groupids = array_keys($group_list);

    // destruction of the group
    $query = "DELETE FROM groups_table WHERE id IN ({$group_id_string});";
    pwg_query($query);

    trigger_notify('delete_group', $groupids);
    pwg_activity('group', $groupids, 'delete');

    return $group_list;
}

/**
 * Returns the username corresponding to the given user identifier if exists.
 *
 * @return string|false
 */
function get_username(
    int $user_id
): bool|string {
    global $conf;

    $user_id_ = intval($user_id);
    $query = "SELECT {$conf['user_fields']['username']} FROM users WHERE {$conf['user_fields']['id']} = {$user_id_};";
    $result = pwg_query($query);
    if (pwg_db_num_rows($result) > 0) {
        [$username] = pwg_db_fetch_row($result);
    } else {
        return false;
    }

    return stripslashes((string) $username);
}

/**
 * Get url on piwigo.org for newsletter subscription
 *
 * @param string $language (unused)
 */
function get_newsletter_subscribe_base_url(
    string $language = 'en_UK'
): string {
    return PHPWG_URL . '/announcement/subscribe/';
}

/**
 * Return admin menu id for accordion.
 *
 * @return int
 */
function get_active_menu(
    string $menu_page
): mixed {
    global $page;

    if (isset($page['active_menu'])) {
        return $page['active_menu'];
    }

    switch ($menu_page) {
        case 'photo':
        case 'photos_add':
        case 'rating':
        case 'tags':
        case 'batch_manager':
            return 0;

        case 'album':
        case 'cat_list':
        case 'albums':
        case 'cat_options':
        case 'cat_search':
        case 'permalinks':
            return 1;

        case 'user_list':
        case 'user_perm':
        case 'group_list':
        case 'group_perm':
        case 'notification_by_mail':
        case 'user_activity':
            return 2;

        case 'site_manager':
        case 'site_update':
        case 'stats':
        case 'history':
        case 'maintenance':
        case 'comments':
        case 'updates':
            return 3;

        case 'configuration':
        case 'derivatives':
        case 'extend_for_templates':
        case 'menubar':
        case 'themes':
        case 'theme':
        case 'languages':
            return 4;

        default:
            return -1;
    }
}

/**
 * Get tags list from SQL query (ids are surrounded by ~~, for get_tag_ids()).
 *
 * @param boolean $only_user_language - if true, only local name is returned for
 *    multilingual tags (if ExtendedDescription plugin is active)
 * @return array[] ('id', 'name')
 */
function get_taglist(
    string $query,
    bool $only_user_language = true
): array {
    $result = pwg_query($query);

    $taglist = [];
    $altlist = [];
    while ($row = pwg_db_fetch_assoc($result)) {
        $raw_name = $row['name'];
        $name = trigger_change('render_tag_name', $raw_name, $row);

        $taglist[] = [
            'name' => $name,
            'id' => '~~' . $row['id'] . '~~',
        ];

        if (! $only_user_language) {
            $alt_names = trigger_change('get_tag_alt_names', [], $raw_name);

            foreach (array_diff(array_unique($alt_names), [$name]) as $alt) {
                $altlist[] = [
                    'name' => $alt,
                    'id' => '~~' . $row['id'] . '~~',
                ];
            }
        }
    }

    usort($taglist, tag_alpha_compare(...));
    if ($altlist !== []) {
        usort($altlist, tag_alpha_compare(...));
        $taglist = array_merge($taglist, $altlist);
    }

    return $taglist;
}

/**
 * Get tags ids from a list of raw tags (existing tags or new tags).
 *
 * In $raw_tags we receive something like array('~~6~~', '~~59~~', 'New
 * tag', 'Another new tag') The ~~34~~ means that it is an existing
 * tag. We added the surrounding ~~ to permit creation of tags like "10"
 * or "1234" (numeric characters only)
 *
 * @param string|string[] $raw_tags - array or comma separated string
 * @return int[]
 */
function get_tag_ids(
    array $raw_tags,
    bool $allow_create = true
): array {
    $tag_ids = [];
    if (! is_array($raw_tags)) {
        $raw_tags = explode(',', $raw_tags);
    }

    foreach ($raw_tags as $raw_tag) {
        if (preg_match('/^~~(\d+)~~$/', (string) $raw_tag, $matches)) {
            $tag_ids[] = $matches[1];
        } elseif ($allow_create) {
            // we have to create a new tag
            $tag_ids[] = tag_id_from_tag_name(strip_tags((string) $raw_tag));
        }
    }

    return $tag_ids;
}

/**
 * Returns the argument_ids array with new sequenced keys based on related
 * names. Sequence is not case sensitive.
 * Warning: By definition, this function breaks original keys.
 *
 * @param string[] $name - names of elements, indexed by ids
 * @return int[]
 */
function order_by_name(
    array $element_ids,
    array $name
): array {
    $ordered_element_ids = [];
    foreach ($element_ids as $k_id => $element_id) {
        $key = strtolower($name[$element_id]) . '-' . $name[$element_id] . '-' . $k_id;
        $ordered_element_ids[$key] = $element_id;
    }

    ksort($ordered_element_ids);
    return $ordered_element_ids;
}

/**
 * Grant access to a list of categories for a list of users.
 *
 * @param int[]|int $category_ids
 * @param int[]|int $user_ids
 */
function add_permission_on_category(
    array|int $category_ids,
    array|int $user_ids
): void {
    if (! is_array($category_ids)) {
        $category_ids = [$category_ids];
    }

    if (! is_array($user_ids)) {
        $user_ids = [$user_ids];
    }

    // check for emptiness
    if (count($category_ids) == 0 || count($user_ids) == 0) {
        return;
    }

    // make sure categories are private and select uppercats or subcats
    $cat_ids = get_uppercat_ids($category_ids);
    if (isset($_POST['apply_on_sub'])) {
        $cat_ids = array_merge($cat_ids, get_subcat_ids($category_ids));
    }

    $cat_ids_ = implode(',', $cat_ids);
    $query = "SELECT id FROM categories WHERE id IN ({$cat_ids_}) AND status = 'private';";
    $private_cats = query2array($query, null, 'id');

    if (count($private_cats) == 0) {
        return;
    }

    $inserts = [];
    foreach ($private_cats as $cat_id) {
        foreach ($user_ids as $user_id) {
            $inserts[] = [
                'user_id' => $user_id,
                'cat_id' => $cat_id,
            ];
        }
    }

    mass_inserts(
        'user_access',
        ['user_id', 'cat_id'],
        $inserts,
        [
            'ignore' => true,
        ]
    );
}

/**
 * Returns the list of admin users.
 *
 * @return int[]
 */
function get_admins(
    bool $include_webmaster = true
): array {
    $status_list = ['admin'];

    if ($include_webmaster) {
        $status_list[] = 'webmaster';
    }

    $status_list_ = implode("','", $status_list);
    $query = "SELECT user_id FROM user_infos WHERE status in ('{$status_list_}');";

    return query2array($query, null, 'user_id');
}

/**
 * Delete all derivative files for one or several types
 *
 * @param 'all'|int[] $types
 */
function clear_derivative_cache(
    string|array $types = 'all'
): void {
    if ($types === 'all') {
        $types = ImageStdParams::get_all_types();
        $types[] = IMG_CUSTOM;
    } elseif (! is_array($types)) {
        $types = [$types];
    }

    $counter = count($types);

    for ($i = 0; $i < $counter; $i++) {
        $type = $types[$i];
        if ($type == IMG_CUSTOM) {
            $type = derivative_to_url($type) . '_[a-zA-Z0-9]+';
        } elseif (in_array($type, ImageStdParams::get_all_types())) {
            $type = derivative_to_url($type);
        } else {//assume a custom type
            $type = derivative_to_url(IMG_CUSTOM) . '_' . $type;
        }

        $types[$i] = $type;
    }

    $pattern = '#.*-';
    if (count($types) > 1) {
        $pattern .= '(' . implode('|', $types) . ')';
    } else {
        $pattern .= $types[0];
    }

    $pattern .= '\.[a-zA-Z0-9]{3,4}$#';

    if ($contents = opendir(PHPWG_ROOT_PATH . PWG_DERIVATIVE_DIR)) {
        while (($node = readdir($contents)) !== false) {
            if ($node !== '.' && $node !== '..' && is_dir(PHPWG_ROOT_PATH . PWG_DERIVATIVE_DIR . $node)) {
                clear_derivative_cache_rec(PHPWG_ROOT_PATH . PWG_DERIVATIVE_DIR . $node, $pattern);
            }
        }

        closedir($contents);
    }
}

/**
 * Used by clear_derivative_cache()
 * @ignore
 */
function clear_derivative_cache_rec(
    string $path,
    string $pattern
): bool|int|null {
    $rmdir = true;
    $rm_index = false;

    if ($contents = opendir($path)) {
        while (($node = readdir($contents)) !== false) {
            if ($node === '.' || $node === '..') {
                continue;
            }

            if (is_dir($path . '/' . $node)) {
                $rmdir &= clear_derivative_cache_rec($path . '/' . $node, $pattern);
            } elseif (preg_match($pattern, $node)) {
                unlink($path . '/' . $node);
            } elseif ($node === 'index.htm') {
                $rm_index = true;
            } else {
                $rmdir = false;
            }
        }

        closedir($contents);

        if ($rmdir) {
            if ($rm_index) {
                unlink($path . '/index.htm');
            }

            clearstatcache();
            rmdir($path);
        }

        return $rmdir;
    }

    return null;
}

/**
 * Deletes derivatives of a particular element
 *
 * @param array $infos ('path'[, 'representative_ext'])
 * @param 'all'|int $type
 */
function delete_element_derivatives(
    array $infos,
    string|int $type = 'all'
): void {
    $path = $infos['path'];
    if (! empty($infos['representative_ext'])) {
        $path = original_to_representative($path, $infos['representative_ext']);
    }

    if (substr_compare((string) $path, '../', 0, 3) == 0) {
        $path = substr((string) $path, 3);
    }

    $dot = strrpos((string) $path, '.');
    $pattern = $type == 'all' ? '-*' : '-' . derivative_to_url($type) . '*';

    $path = substr_replace($path, $pattern, $dot, 0);
    if (($glob = glob(PHPWG_ROOT_PATH . PWG_DERIVATIVE_DIR . $path)) !== false) {
        foreach ($glob as $file) {
            unlink($file);
        }
    }
}

/**
 * Returns an array containing sub-directories, excluding ".svn"
 *
 * @return string[]
 */
function get_dirs(
    string $directory
): array {
    $sub_dirs = [];
    if ($opendir = opendir($directory)) {
        while ($file = readdir($opendir)) {
            if ($file !== '.' && $file !== '..' && is_dir($directory . '/' . $file) && $file !== '.svn') {
                $sub_dirs[] = $file;
            }
        }

        closedir($opendir);
    }

    return $sub_dirs;
}

/**
 * Recursively delete a directory.
 *
 * @param string $trash_path, try to move the directory to this path if it cannot be delete
 */
function deltree(
    string $path,
    string $trash_path = null
): bool|null {
    if (is_dir($path)) {
        $fh = opendir($path);
        while ($file = readdir($fh)) {
            if ($file !== '.' && $file !== '..') {
                $pathfile = $path . '/' . $file;
                if (is_dir($pathfile)) {
                    deltree($pathfile, $trash_path);
                } else {
                    unlink($pathfile);
                }
            }
        }

        closedir($fh);

        if (rmdir($path)) {
            return true;
        } elseif ($trash_path !== null && $trash_path !== '' && $trash_path !== '0') {
            if (! is_dir($trash_path)) {
                mkgetdir($trash_path, MKGETDIR_RECURSIVE | MKGETDIR_DIE_ON_ERROR | MKGETDIR_PROTECT_HTACCESS);
            }

            while ($r = $trash_path . '/' . md5(uniqid((string) mt_rand(), true))) {
                if (! is_dir($r)) {
                    rename($path, $r);
                    break;
                }
            }
        } else {
            return false;
        }
    }

    return null;
}

/**
 * Returns keys to identify the state of main tables. A key consists of the
 * last modification timestamp and the total of items (separated by a _).
 * Additionally returns the hash of root path.
 * Used to invalidate LocalStorage cache on admin pages.
 *
 * @param string|string[] $requested list of keys to retrieve (categories,groups,images,tags,users)
 * @return string[]
 */
function get_admin_client_cache_keys(
    array $requested = []
): array {
    $tables = [
        'categories' => 'categories',
        'groups' => 'groups_table',
        'images' => 'images',
        'tags' => 'tags',
        'users' => 'user_infos',
    ];

    if (! is_array($requested)) {
        $requested = [$requested];
    }

    $requested = $requested === [] ? array_keys($tables) : array_intersect($requested, array_keys($tables));

    $keys = [
        '_hash' => md5(get_absolute_root_url()),
    ];

    foreach ($requested as $item) {
        $query = 'SELECT CONCAT(';

        if (DB_ENGINE === 'MySQL') {
            $query .= ' UNIX_TIMESTAMP(MAX(lastmodified)),';
        }

        if (DB_ENGINE === 'PostgreSQL') {
            $query .= ' EXTRACT(EPOCH FROM MAX(lastmodified))::BIGINT,';
        }

        $query .= "'_', COUNT(*)) FROM {$tables[$item]};";
        [$keys[$item]] = pwg_db_fetch_row(pwg_query($query));
    }

    return $keys;
}

/**
 * Return the list of image ids where md5sum is null
 *
 * @return int[] image_ids
 */
function get_photos_no_md5sum(): array
{
    $query = 'SELECT id FROM images WHERE md5sum IS NULL;';
    return query2array($query, null, 'id');
}

/**
 * Compute and add the md5sum of image ids (where md5sum is null)
 * @param int[] $ids list of image ids and there paths
 * @return int number of md5sum added
 */
function add_md5sum(
    array $ids
): int {
    $ids_ = implode(', ', $ids);
    $query = "SELECT path FROM images WHERE id IN ({$ids_});";
    $paths = query2array($query, null, 'path');
    $imgs_ids_paths = array_combine($ids, $paths);
    $updates = [];
    foreach ($ids as $id) {
        $file = PHPWG_ROOT_PATH . $imgs_ids_paths[$id];
        $md5sum = md5_file($file);
        $updates[] = [
            'id' => $id,
            'md5sum' => $md5sum,
        ];
    }

    mass_updates(
        'images',
        [
            'primary' => ['id'],
            'update' => ['md5sum'],
        ],
        $updates
    );
    return count($ids);
}

function count_orphans(): mixed
{
    if (conf_get_param('count_orphans') === null) {
        // we don't care about the list of image_ids, we only care about the number
        // of orphans, so let's use a faster method than calling count(get_orphans())
        $query = 'SELECT COUNT(*) FROM images;';
        [$image_counter_all] = pwg_db_fetch_row(pwg_query($query));

        $query = 'SELECT COUNT(DISTINCT(image_id)) FROM image_category;';
        [$image_counter_in_categories] = pwg_db_fetch_row(pwg_query($query));

        $counter = $image_counter_all - $image_counter_in_categories;
        conf_update_param('count_orphans', $counter, true);
    }

    return conf_get_param('count_orphans');
}

/**
 * Return the list of image ids associated to no album
 *
 * @return int[] $image_ids
 */
function get_orphans(): array
{
    // exclude images in the lounge
    $query = 'SELECT image_id FROM lounge;';
    $lounged_ids = query2array($query, null, 'image_id');

    $query = 'SELECT id FROM images LEFT JOIN image_category ON id = image_id WHERE category_id IS NULL;';

    if ($lounged_ids !== []) {
        $lounged_ids_ = implode(',', $lounged_ids);
        $query .= " AND id NOT IN ({$lounged_ids_})";
    }

    $query .= ' ORDER BY id ASC;';

    return query2array($query, null, 'id');
}

/**
 * save the rank depending on given images order
 *
 * The list of ordered images id is supposed to be in the same parent
 * category
 *
 * @param int[] $images
 */
function save_images_order(
    int $category_id,
    array $images
): void {
    $current_rank = 0;
    $datas = [];
    foreach ($images as $id) {
        $datas[] = [
            'category_id' => $category_id,
            'image_id' => $id,
            'rank' => ++$current_rank,
        ];
    }

    $fields = [
        'primary' => ['image_id', 'category_id'],
        'update' => ['rank'],
    ];
    mass_updates('image_category', $fields, $datas);
}

/**
 * Force update on images.lastmodified column. Useful when modifying the tag
 * list.
 *
 * @since 2.9
 */
function update_images_lastmodified(
    array $image_ids
): void {
    if (! is_array($image_ids) && is_int($image_ids)) {
        $images_ids = [$image_ids];
    }

    if (count($image_ids) == 0) {
        return;
    }

    $images_ids_ = implode(',', $image_ids);
    $query = "UPDATE images SET lastmodified = NOW() WHERE id IN ({$images_ids_});";
    pwg_query($query);
}

/**
 * Get a more human friendly representation of big numbers. Like 17.8k instead of 17832
 *
 * @since 2.9
 */
function number_format_human_readable(
    float|null $numbers
): string {
    $readable = ['',  'k', 'M'];
    $index = 0;
    $numbers = empty($numbers) ? 0 : $numbers;

    while ($numbers >= 1000) {
        $numbers /= 1000;
        $index++;

        if ($index > count($readable) - 1) {
            $index--;
            break;
        }
    }

    $decimals = 1;
    if ($readable[$index] === '') {
        $decimals = 0;
    }

    return number_format($numbers, $decimals) . $readable[$index];
}

/**
 * Get infos related to an image
 *
 * @since 2.9
 */
function get_image_infos(
    string $image_id,
    bool $die_on_missing = false
): mixed {
    if (! is_numeric($image_id)) {
        fatal_error('[' . __FUNCTION__ . '] invalid image identifier ' . htmlentities($image_id));
    }

    $query = "SELECT * FROM images WHERE id = {$image_id};";
    $images = query2array($query);
    if (count($images) == 0) {
        if ($die_on_missing) {
            fatal_error('photo ' . $image_id . ' does not exist');
        }

        return null;
    }

    return $images[0];
}

/**
 * Return each cache image sizes.
 *
 * @since 12
 */
function get_cache_size_derivatives(
    string $path
): array {
    $msizes = []; //final res
    $subdirs = []; //sous-rep

    if (is_dir($path)) {
        if ($contents = opendir($path)) {
            while (($node = readdir($contents)) !== false) {
                if ($node === '.' || $node === '..') {
                    continue;
                }

                if (is_file($path . '/' . $node)) {
                    if ($split = explode('-', $node)) {
                        $size_code = substr(end($split), 0, 2);
                        $msizes[$size_code] = ($msizes[$size_code] ?? null) + filesize($path . '/' . $node);
                    }
                } elseif (is_dir($path . '/' . $node)) {
                    $tmp_msizes = get_cache_size_derivatives($path . '/' . $node);
                    foreach ($tmp_msizes as $size_key => $value) {
                        $msizes[$size_key] = ($msizes[$size_key] ?? null) + $value;
                    }
                }
            }
        }

        closedir($contents);
    }

    return $msizes;
}

/**
 * Displays a header warning if we find missing photos on a random sample.
 *
 * @since 13.4.0
 */
function fs_quick_check(): void
{
    global $page, $conf;

    if ($conf['fs_quick_check_period'] == 0) {
        return;
    }

    if (isset($page[__FUNCTION__ . '_already_called'])) {
        return;
    }

    $page[__FUNCTION__ . '_already_called'] = true;
    conf_update_param('fs_quick_check_last_check', date('c'));

    $query = "SELECT id FROM images WHERE date_available < '2022-12-08 00:00:00' AND path LIKE './upload/%' LIMIT 5000;";
    $issue1827_ids = query2array($query, null, 'id');
    shuffle($issue1827_ids);
    $issue1827_ids = array_slice($issue1827_ids, 0, 50);

    $query = 'SELECT id FROM images LIMIT 5000;';
    $random_image_ids = query2array($query, null, 'id');
    shuffle($random_image_ids);
    $random_image_ids = array_slice($random_image_ids, 0, 50);

    $fs_quick_check_ids = array_unique(array_merge($issue1827_ids, $random_image_ids));

    if (count($fs_quick_check_ids) < 1) {
        return;
    }

    $fs_quick_check_ids_ = implode(',', $fs_quick_check_ids);
    $query = "SELECT id, path FROM images WHERE id IN ({$fs_quick_check_ids_});";
    $fsqc_paths = query2array($query, 'id', 'path');

    foreach ($fsqc_paths as $id => $path) {
        if (! file_exists($path)) {
            global $template;

            $template->assign(
                'header_msgs',
                [
                    l10n('Some photos are missing from your file system. Details provided by plugin Check Uploads'),
                ]
            );

            return;
        }
    }
}

/**
 * Return latest news from piwigo.org.
 *
 * @since 13
 */
function get_piwigo_news(): mixed
{
    global $lang_info;

    $news = null;

    $cache_path = PHPWG_ROOT_PATH . conf_get_param('data_location') . 'cache/piwigo_latest_news-' . $lang_info['code'] . '.cache.php';
    if (! is_file($cache_path) || filemtime($cache_path) < strtotime('24 hours ago')) {
        $url = PHPWG_URL . '/ws.php?method=porg.news.getLatest&format=json';

        if (fetchRemote($url, $content)) {
            $all_news = [];

            $porg_news_getLatest = json_decode((string) $content, true);

            if (isset($porg_news_getLatest['result'])) {
                $topic = $porg_news_getLatest['result'];

                $news = [
                    'id' => $topic['topic_id'],
                    'subject' => $topic['subject'],
                    'posted_on' => $topic['posted_on'],
                    'posted' => format_date($topic['posted_on']),
                    'url' => $topic['url'],
                ];
            }

            if (mkgetdir(dirname($cache_path))) {
                file_put_contents($cache_path, serialize($news));
            }
        } else {
            return [];
        }
    }

    if ($news === null) {
        $news = unserialize(file_get_contents($cache_path));
    }

    return $news;
}
