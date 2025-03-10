<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/** returns a category id that corresponds to the given permalink (or null)
 */
function get_cat_id_from_permalink(
    string $permalink
): mixed {
    $query = <<<SQL
        SELECT id FROM categories
        WHERE permalink = '{$permalink}';
        SQL;
    $ids = query2array($query, null, 'id');
    if ($ids !== []) {
        return $ids[0];
    }

    return null;
}

/** returns a category id that has used before this permalink (or null)
 */
function get_cat_id_from_old_permalink(
    string $permalink
): mixed {
    $query = <<<SQL
        SELECT c.id
        FROM old_permalinks op
        INNER JOIN categories c ON op.cat_id = c.id
        WHERE op.permalink = '{$permalink}'
        LIMIT 1;
        SQL;
    $result = pwg_query($query);
    $cat_id = null;
    if (pwg_db_num_rows($result) !== 0) {
        [$cat_id] = pwg_db_fetch_row($result);
    }

    return $cat_id;
}

/** deletes the permalink associated with a category
 * returns true on success
 * @param int $cat_id the target category id
 * @param bool $save if true, the current category-permalink association
 * is saved in the old permalinks table in case external links hit it
 */
function delete_cat_permalink(
    int $cat_id,
    bool $save
): bool {
    global $page, $cache;
    $query = <<<SQL
        SELECT permalink
        FROM categories
        WHERE id = '{$cat_id}';
        SQL;
    $result = pwg_query($query);
    if (pwg_db_num_rows($result) !== 0) {
        [$permalink] = pwg_db_fetch_row($result);
    }

    if (! isset($permalink)) {// no permalink; nothing to do
        return true;
    }

    if ($save) {
        $old_cat_id = get_cat_id_from_old_permalink($permalink);
        if (isset($old_cat_id) && $old_cat_id != $cat_id) {
            $page['errors'][] =
              sprintf(
                  l10n('Permalink %s has been previously used by album %s. Delete from the permalink history first'),
                  $permalink,
                  $old_cat_id
              );
            return false;
        }
    }

    $query = <<<SQL
        UPDATE categories
        SET permalink = NULL
        WHERE id = {$cat_id}
        LIMIT 1;
        SQL;
    pwg_query($query);

    unset($cache['cat_names']); //force regeneration
    if ($save) {
        if (isset($old_cat_id)) {
            $query = <<<SQL
                UPDATE old_permalinks
                SET date_deleted = NOW()
                WHERE cat_id = {$cat_id}
                    AND permalink = '{$permalink}';
                SQL;
        } else {
            $query = <<<SQL
                INSERT INTO old_permalinks
                    (permalink, cat_id, date_deleted)
                VALUES
                    ('{$permalink}', {$cat_id}, NOW());
                SQL;
        }

        pwg_query($query);
    }

    return true;
}

/** sets a new permalink for a category
 * returns true on success
 * @param int $cat_id the target category id
 * @param string $permalink the new permalink
 * @param bool $save if true, the current category-permalink association
 * is saved in the old permalinks table in case external links hit it
 */
function set_cat_permalink(
    int $cat_id,
    string $permalink,
    bool $save
): bool {
    global $page, $cache;

    $sanitized_permalink = preg_replace('#[^a-zA-Z0-9_/-]#', '', $permalink);
    $sanitized_permalink = trim((string) $sanitized_permalink, '/');
    $sanitized_permalink = str_replace('//', '/', $sanitized_permalink);
    if ($sanitized_permalink != $permalink || preg_match('#^(\d)+(-.*)?$#', $permalink)) {
        $page['errors'][] = '{' . $permalink . '} ' . l10n('The permalink name must be composed of a-z, A-Z, 0-9, "-", "_" or "/". It must not be numeric or start with number followed by "-"');
        return false;
    }

    // check if the new permalink is actively used
    $existing_cat_id = get_cat_id_from_permalink($permalink);
    if (isset($existing_cat_id)) {
        if ($existing_cat_id == $cat_id) {// no change required
            return true;
        }

        $page['errors'][] =
          sprintf(
              l10n('Permalink %s is already used by album %s'),
              $permalink,
              $existing_cat_id
          );
        return false;

    }

    // check if the new permalink was historically used
    $old_cat_id = get_cat_id_from_old_permalink($permalink);
    if (isset($old_cat_id) && $old_cat_id != $cat_id) {
        $page['errors'][] =
          sprintf(
              l10n('Permalink %s has been previously used by album %s. Delete from the permalink history first'),
              $permalink,
              $old_cat_id
          );
        return false;
    }

    if (! delete_cat_permalink($cat_id, $save)) {
        return false;
    }

    if (isset($old_cat_id)) {// the new permalink must not be active and old at the same time
        assert($old_cat_id == $cat_id);
        $query = <<<SQL
            DELETE FROM old_permalinks
            WHERE cat_id = {$old_cat_id}
                AND permalink = '{$permalink}';
            SQL;
        pwg_query($query);
    }

    $query = <<<SQL
        UPDATE categories
        SET permalink = '{$permalink}'
        WHERE id = {$cat_id};
        SQL;
    //  LIMIT 1';
    pwg_query($query);

    unset($cache['cat_names']); //force regeneration

    return true;
}
