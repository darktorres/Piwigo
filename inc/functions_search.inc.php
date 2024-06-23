<?php

namespace Piwigo\inc;

use function Piwigo\inc\dbLayer\pwg_db_fetch_assoc;
use function Piwigo\inc\dbLayer\pwg_db_fetch_row;
use function Piwigo\inc\dbLayer\pwg_get_db_version;
use function Piwigo\inc\dbLayer\pwg_query;
use function Piwigo\inc\dbLayer\query2array;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Returns search rules stored into a serialized array in "search"
 * table. Each search rules set is numericaly identified.
 *
 * @param int $search_id
 * @return array
 */
function get_search_array(
    $search_id
) {
    if (! is_numeric($search_id)) {
        die('Search id must be an integer');
    }

    $query = '
SELECT rules
  FROM ' . SEARCH_TABLE . '
  WHERE id = ' . $search_id . '
;';
    [$serialized_rules] = pwg_db_fetch_row(pwg_query($query));

    return unserialize($serialized_rules ?? '');
}

/**
 * Returns the SQL clause for a search.
 * Transforms the array returned by get_search_array() into SQL sub-query.
 *
 * @param array $search
 * @return string
 */
function get_sql_search_clause(
    $search
) {
    // SQL where clauses are stored in $clauses array during query
    // construction
    $clauses = [];

    foreach (['file', 'name', 'comment', 'author'] as $textfield) {
        if (isset($search['fields'][$textfield])) {
            $local_clauses = [];
            foreach ($search['fields'][$textfield]['words'] as $word) {
                $local_clauses[] = $textfield === 'author' ? $textfield . "='" . $word . "'" : $textfield . " LIKE '%" . $word . "%'";
            }

            // adds brackets around where clauses
            $local_clauses = prepend_append_array_items($local_clauses, '(', ')');

            $clauses[] = implode(
                ' ' . $search['fields'][$textfield]['mode'] . ' ',
                $local_clauses
            );
        }
    }

    if (isset($search['fields']['allwords']) && count($search['fields']['allwords']['fields']) > 0) {
        $fields = ['file', 'name', 'comment'];

        if (isset($search['fields']['allwords']['fields']) && count($search['fields']['allwords']['fields']) > 0) {
            $fields = array_intersect($fields, $search['fields']['allwords']['fields']);
        }

        // in the OR mode, request bust be :
        // ((field1 LIKE '%word1%' OR field2 LIKE '%word1%')
        // OR (field1 LIKE '%word2%' OR field2 LIKE '%word2%'))
        //
        // in the AND mode :
        // ((field1 LIKE '%word1%' OR field2 LIKE '%word1%')
        // AND (field1 LIKE '%word2%' OR field2 LIKE '%word2%'))
        $word_clauses = [];
        foreach ($search['fields']['allwords']['words'] as $word) {
            $field_clauses = [];
            foreach ($fields as $field) {
                $field_clauses[] = $field . " LIKE '%" . $word . "%'";
            }

            // adds brackets around where clauses
            $word_clauses[] = implode(
                "\n          OR ",
                $field_clauses
            );
        }

        array_walk(
            $word_clauses,
            static function (&$s) {
                $s = '(' . $s . ')';
            }
        );

        // make sure the "mode" is either OR or AND
        if ($search['fields']['allwords']['mode'] != 'AND' && $search['fields']['allwords']['mode'] != 'OR') {
            $search['fields']['allwords']['mode'] = 'AND';
        }

        $clauses[] = "\n         " .
          implode(
              "\n         " . $search['fields']['allwords']['mode'] . "\n         ",
              $word_clauses
          );
    }

    foreach (['date_available', 'date_creation'] as $datefield) {
        if (isset($search['fields'][$datefield])) {
            $clauses[] = $datefield . " = '" . $search['fields'][$datefield]['date'] . "'";
        }

        foreach (['after', 'before'] as $suffix) {
            $key = $datefield . '-' . $suffix;

            if (isset($search['fields'][$key])) {
                $clauses[] = $datefield .
                  ($suffix === 'after' ? ' >' : ' <') .
                  ($search['fields'][$key]['inc'] ? '=' : '') .
                  " '" . $search['fields'][$key]['date'] . "'";
            }
        }
    }

    if (isset($search['fields']['cat'])) {
        if ($search['fields']['cat']['sub_inc']) {
            // searching all the categories id of sub-categories
            $cat_ids = get_subcat_ids(
                $search['fields']['cat']['words']
            );
        } else {
            $cat_ids = $search['fields']['cat']['words'];
        }

        $local_clause = 'category_id IN (' . implode(',', $cat_ids) . ')';
        $clauses[] = $local_clause;
    }

    // adds brackets around where clauses
    $clauses = prepend_append_array_items($clauses, '(', ')');

    return implode(
        "\n    " . ($search['mode'] ?? '') . ' ',
        $clauses
    );
}

/**
 * Returns the list of items corresponding to the advanced search array.
 *
 * @param array $search
 * @param string $images_where optional additional restriction on images table
 * @return array
 */
function get_regular_search_results(
    $search,
    $images_where = ''
) {
    global $conf, $logger;

    $logger->debug(__FUNCTION__, $search);

    $forbidden = get_sql_condition_FandF(
        [
            'forbidden_categories' => 'category_id',
            'visible_categories' => 'category_id',
            'visible_images' => 'id',
        ],
        "\n  AND"
    );

    $items = [];
    $tag_items = [];

    if (isset($search['fields']['search_in_tags'])) {
        $word_clauses = [];
        foreach ($search['fields']['allwords']['words'] as $word) {
            $word_clauses[] = "name LIKE '%" . $word . "%'";
        }

        $query = '
SELECT
    id
  FROM ' . TAGS_TABLE . '
  WHERE ' . implode(' OR ', $word_clauses) . '
;';
        $tag_ids = query2array($query, null, 'id');

        $search_in_tags_items = get_image_ids_for_tags($tag_ids, 'OR');

        $logger->debug(__FUNCTION__ . ' ' . count($search_in_tags_items) . ' items in $search_in_tags_items');
    }

    if (isset($search['fields']['tags'])) {
        $tag_items = get_image_ids_for_tags(
            $search['fields']['tags']['words'],
            $search['fields']['tags']['mode']
        );

        $logger->debug(__FUNCTION__ . ' ' . count($tag_items) . ' items in $tag_items');
    }

    $search_clause = get_sql_search_clause($search);

    if (! empty($search_clause)) {
        $query = '
SELECT DISTINCT(id)
  FROM ' . IMAGES_TABLE . ' i
    INNER JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON id = ic.image_id
  WHERE ' . $search_clause;
        if (! empty($images_where)) {
            $query .= "\n  AND " . $images_where;
        }

        $query .= $forbidden . '
  ' . $conf['order_by'];
        $items = array_from_query($query, 'id');

        $logger->debug(__FUNCTION__ . ' ' . count($items) . ' items in $items');
    }

    if (isset($search_in_tags_items)) {
        // TODO the sorting order will not match $conf['order_by'], a solution would be
        // to have a new SQL query 'where id in (merged ids) order by $conf[order_by]'
        //
        // array_values will reset the numeric keys, without changing the sorting order.
        // picture.php relies on these keys to be sequential {0,1,2} and not {0,1,5}
        $items = array_values(
            array_unique(
                array_merge(
                    $items,
                    $search_in_tags_items
                )
            )
        );
    }

    if (! empty($tag_items)) {
        switch ($search['mode']) {
            case 'AND':
                if (empty($search_clause) && ! isset($search_in_tags_items)) {
                    $items = $tag_items;
                } else {
                    $items = array_values(array_intersect($items, $tag_items));
                }

                break;
            case 'OR':
                $items = array_values(
                    array_unique(
                        array_merge(
                            $items,
                            $tag_items
                        )
                    )
                );
                break;
        }
    }

    return $items;
}

define('QST_QUOTED', 0x01);
define('QST_NOT', 0x02);
define('QST_OR', 0x04);
define('QST_WILDCARD_BEGIN', 0x08);
define('QST_WILDCARD_END', 0x10);
define('QST_WILDCARD', QST_WILDCARD_BEGIN | QST_WILDCARD_END);
define('QST_BREAK', 0x20);

function qsearch_get_text_token_search_sql($token, $fields)
{
    global $page;

    $clauses = [];
    $variants = array_merge([$token->term], $token->variants);
    $fts = [];
    foreach ($variants as $variant) {
        $use_ft = mb_strlen((string) $variant) > 3;
        if (($token->modifier & QST_WILDCARD_BEGIN) !== 0) {
            $use_ft = false;
        }

        if (($token->modifier & (QST_QUOTED | QST_WILDCARD_END) === (QST_QUOTED | QST_WILDCARD_END)) !== 0) {
            $use_ft = false;
        }

        if ($use_ft) {
            $max = max(array_map(
                'mb_strlen',
                preg_split('/[' . preg_quote('-\'!"#$%&()*+,./:;<=>?@[\]^`{|}~', '/') . ']+/', (string) $variant)
            ));
            if ($max < 4) {
                $use_ft = false;
            }
        }

        if (! $use_ft) {// odd term or too short for full text search; fallback to regex but unfortunately this is diacritic/accent sensitive
            if (! isset($page['use_regexp_ICU'])) {
                // Prior to MySQL 8.0.4, MySQL used the Henry Spencer regular expression library to support
                // regular expression operations, rather than International Components for Unicode (ICU)
                $page['use_regexp_ICU'] = false;
                $db_version = pwg_get_db_version();
                if (! preg_match('/mariadb/i', $db_version) && version_compare($db_version, '8.0.4', '>')) {
                    $page['use_regexp_ICU'] = true;
                }
            }

            $pre = (($token->modifier & QST_WILDCARD_BEGIN) !== 0) ? '' : ($page['use_regexp_ICU'] ? '\\\\b' : '[[:<:]]');
            $post = (($token->modifier & QST_WILDCARD_END) !== 0) ? '' : ($page['use_regexp_ICU'] ? '\\\\b' : '[[:>:]]');
            foreach ($fields as $field) {
                $clauses[] = $field . " REGEXP '" . $pre . addslashes(preg_quote((string) $variant)) . $post . "'";
            }
        } else {
            $ft = $variant;
            if (($token->modifier & QST_QUOTED) !== 0) {
                $ft = '"' . $ft . '"';
            }

            if (($token->modifier & QST_WILDCARD_END) !== 0) {
                $ft .= '*';
            }

            $fts[] = $ft;
        }
    }

    if ($fts !== []) {
        $clauses[] = 'MATCH(' . implode(', ', $fields) . ") AGAINST( '" . addslashes(
            implode(' ', $fts)
        ) . "' IN BOOLEAN MODE)";
    }

    return $clauses;
}

function qsearch_get_images(QExpression $expr, QResults $qsr)
{
    $qsr->images_iids = array_fill(0, count($expr->stokens), []);

    $query_base = 'SELECT id from ' . IMAGES_TABLE . ' i WHERE
';
    $counter = count($expr->stokens);
    for ($i = 0; $i < $counter; ++$i) {
        $token = $expr->stokens[$i];
        $scope_id = isset($token->scope) ? $token->scope->id : 'photo';
        $clauses = [];

        $like = addslashes($token->term);
        $like = str_replace(['%', '_'], ['\\%', '\\_'], $like); // escape LIKE specials %_
        $file_like = "CONVERT(file, CHAR) LIKE '%" . $like . "%'";

        switch ($scope_id) {
            case 'photo':
                $clauses[] = $file_like;
                $clauses = array_merge($clauses, qsearch_get_text_token_search_sql($token, ['name', 'comment']));
                break;

            case 'file':
                $clauses[] = $file_like;
                break;
            case 'author':
                if (strlen($token->term) !== 0) {
                    $clauses = array_merge($clauses, qsearch_get_text_token_search_sql($token, ['author']));
                } elseif (($token->modifier & QST_WILDCARD) !== 0) {
                    $clauses[] = 'author IS NOT NULL';
                } else {
                    $clauses[] = 'author IS NULL';
                }

                break;
            case 'width':
            case 'height':
                $clauses[] = $token->scope->get_sql($scope_id, $token);
                break;
            case 'ratio':
                $clauses[] = $token->scope->get_sql('width/height', $token);
                break;
            case 'size':
                $clauses[] = $token->scope->get_sql('width*height', $token);
                break;
            case 'hits':
                $clauses[] = $token->scope->get_sql('hit', $token);
                break;
            case 'score':
                $clauses[] = $token->scope->get_sql('rating_score', $token);
                break;
            case 'filesize':
                $clauses[] = $token->scope->get_sql('1024*filesize', $token);
                break;
            case 'created':
                $clauses[] = $token->scope->get_sql('date_creation', $token);
                break;
            case 'posted':
                $clauses[] = $token->scope->get_sql('date_available', $token);
                break;
            case 'id':
                $clauses[] = $token->scope->get_sql($scope_id, $token);
                break;
            default:
                // allow plugins to have their own scope with columns added in db by themselves
                $clauses = trigger_change(
                    'qsearch_get_images_sql_scopes',
                    $clauses,
                    $token,
                    $expr
                );
                break;
        }

        if (! empty($clauses)) {
            $query = $query_base . '(' . implode("\n OR ", $clauses) . ')';
            $qsr->images_iids[$i] = query2array($query, null, 'id');
        }
    }
}

function qsearch_get_tags(QExpression $expr, QResults $qsr)
{
    $token_tag_ids = array_fill(0, count($expr->stokens), []);
    $qsr->tag_iids = $token_tag_ids;
    $all_tags = [];
    $counter = count($expr->stokens);

    for ($i = 0; $i < $counter; ++$i) {
        $token = $expr->stokens[$i];
        if (isset($token->scope) && $token->scope->id != 'tag') {
            continue;
        }

        if (empty($token->term)) {
            continue;
        }

        $clauses = qsearch_get_text_token_search_sql($token, ['name']);
        $query = 'SELECT * FROM ' . TAGS_TABLE . '
WHERE (' . implode("\n OR ", $clauses) . ')';
        $result = pwg_query($query);
        while ($tag = pwg_db_fetch_assoc($result)) {
            $token_tag_ids[$i][] = $tag['id'];
            $all_tags[$tag['id']] = $tag;
        }
    }

    // check adjacent short words
    for ($i = 0; $i < count($expr->stokens) - 1; ++$i) {
        if ((strlen($expr->stokens[$i]->term) <= 3 || strlen($expr->stokens[$i + 1]->term) <= 3)
          && (($expr->stoken_modifiers[$i] & (QST_QUOTED | QST_WILDCARD)) == 0)
          && (($expr->stoken_modifiers[$i + 1] & (QST_BREAK | QST_QUOTED | QST_WILDCARD)) == 0)) {
            $common = array_intersect($token_tag_ids[$i], $token_tag_ids[$i + 1]);
            if ($common !== []) {
                $token_tag_ids[$i] = $token_tag_ids[$i + 1] = $common;
            }
        }
    }

    // get images
    $positive_ids = [];
    $not_ids = [];
    $counter = count($expr->stokens);
    for ($i = 0; $i < $counter; ++$i) {
        $tag_ids = $token_tag_ids[$i];
        $token = $expr->stokens[$i];

        if (! empty($tag_ids)) {
            $query = '
SELECT image_id FROM ' . IMAGE_TAG_TABLE . '
  WHERE tag_id IN (' . implode(',', $tag_ids) . ')
  GROUP BY image_id';
            $qsr->tag_iids[$i] = query2array($query, null, 'image_id');
            if (($expr->stoken_modifiers[$i] & QST_NOT) !== 0) {
                $not_ids = array_merge($not_ids, $tag_ids);
            } elseif (strlen($token->term) > 2 || count(
                $expr->stokens
            ) == 1 || isset($token->scope) || ($token->modifier & (QST_WILDCARD | QST_QUOTED))) {
                // add tag ids to list only if the word is not too short (such as de / la /les ...)
                $positive_ids = array_merge(
                    $positive_ids,
                    $tag_ids
                );
            }
        } elseif (isset($token->scope) && $token->scope->id == 'tag' && strlen($token->term) == 0) {
            if (($token->modifier & QST_WILDCARD) !== 0) {// eg. 'tag:*' returns all tagged images
                $qsr->tag_iids[$i] = query2array(
                    'SELECT DISTINCT image_id FROM ' . IMAGE_TAG_TABLE,
                    null,
                    'image_id'
                );
            } else {// eg. 'tag:' returns all untagged images
                $qsr->tag_iids[$i] = query2array(
                    'SELECT id FROM ' . IMAGES_TABLE . ' LEFT JOIN ' . IMAGE_TAG_TABLE . ' ON id=image_id WHERE image_id IS NULL',
                    null,
                    'id'
                );
            }
        }
    }

    $all_tags = array_intersect_key($all_tags, array_flip(array_diff($positive_ids, $not_ids)));
    usort($all_tags, '\Piwigo\inc\tag_alpha_compare');
    foreach ($all_tags as &$tag) {
        $tag['name'] = trigger_change('render_tag_name', $tag['name'], $tag);
    }

    $qsr->all_tags = $all_tags;
    $qsr->tag_ids = $token_tag_ids;
}

function qsearch_get_categories(QExpression $expr, QResults $qsr)
{
    global $user, $conf;
    $token_cat_ids = array_fill(0, count($expr->stokens), []);
    $qsr->cat_iids = $token_cat_ids;
    $all_cats = [];
    $counter = count($expr->stokens);

    for ($i = 0; $i < $counter; ++$i) {
        $token = $expr->stokens[$i];
        if (isset($token->scope) && $token->scope->id != 'category') { // not relevant yet
            continue;
        }

        if (empty($token->term)) {
            continue;
        }

        $clauses = qsearch_get_text_token_search_sql($token, ['name', 'comment']);
        $query = '
SELECT
    *
  FROM ' . CATEGORIES_TABLE . '
    INNER JOIN ' . USER_CACHE_CATEGORIES_TABLE . ' ON id = cat_id and user_id = ' . $user['id'] . '
  WHERE (' . implode("\n OR ", $clauses) . ')';
        $result = pwg_query($query);
        while ($cat = pwg_db_fetch_assoc($result)) {
            $token_cat_ids[$i][] = $cat['id'];
            $all_cats[$cat['id']] = $cat;
        }
    }

    // check adjacent short words
    for ($i = 0; $i < count($expr->stokens) - 1; ++$i) {
        if ((strlen($expr->stokens[$i]->term) <= 3 || strlen($expr->stokens[$i + 1]->term) <= 3)
          && (($expr->stoken_modifiers[$i] & (QST_QUOTED | QST_WILDCARD)) == 0)
          && (($expr->stoken_modifiers[$i + 1] & (QST_BREAK | QST_QUOTED | QST_WILDCARD)) == 0)) {
            $common = array_intersect($token_cat_ids[$i], $token_cat_ids[$i + 1]);
            if ($common !== []) {
                $token_cat_ids[$i] = $token_cat_ids[$i + 1] = $common;
            }
        }
    }

    // get images
    $positive_ids = [];
    $not_ids = [];
    $counter = count($expr->stokens);
    for ($i = 0; $i < $counter; ++$i) {
        $cat_ids = $token_cat_ids[$i];
        $token = $expr->stokens[$i];

        if (! empty($cat_ids)) {
            if ($conf['quick_search_include_sub_albums']) {
                $query = '
SELECT
    id
  FROM ' . CATEGORIES_TABLE . '
    INNER JOIN ' . USER_CACHE_CATEGORIES_TABLE . ' ON id = cat_id and user_id = ' . $user['id'] . '
  WHERE id IN (' . implode(',', get_subcat_ids($cat_ids)) . ')
;';
                $cat_ids = query2array($query, null, 'id');
            }

            $query = '
SELECT image_id FROM ' . IMAGE_CATEGORY_TABLE . '
  WHERE category_id IN (' . implode(',', $cat_ids) . ')
  GROUP BY image_id';
            $qsr->cat_iids[$i] = query2array($query, null, 'image_id');
            if (($expr->stoken_modifiers[$i] & QST_NOT) !== 0) {
                $not_ids = array_merge($not_ids, $cat_ids);
            } elseif (strlen($token->term) > 2 || count(
                $expr->stokens
            ) == 1 || isset($token->scope) || ($token->modifier & (QST_WILDCARD | QST_QUOTED))) {
                // add cat ids to list only if the word is not too short (such as de / la /les ...)
                $positive_ids = array_merge(
                    $positive_ids,
                    $cat_ids
                );
            }
        } elseif (isset($token->scope) && $token->scope->id == 'category' && strlen($token->term) == 0) {
            if (($token->modifier & QST_WILDCARD) !== 0) {// eg. 'category:*' returns all images associated to an album
                $qsr->cat_iids[$i] = query2array(
                    'SELECT DISTINCT image_id FROM ' . IMAGE_CATEGORY_TABLE,
                    null,
                    'image_id'
                );
            } else {// eg. 'category:' returns all orphan images
                $qsr->cat_iids[$i] = query2array(
                    'SELECT id FROM ' . IMAGES_TABLE . ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' ON id=image_id WHERE image_id IS NULL',
                    null,
                    'id'
                );
            }
        }
    }

    $all_cats = array_intersect_key($all_cats, array_flip(array_diff($positive_ids, $not_ids)));
    usort($all_cats, '\Piwigo\inc\tag_alpha_compare');
    foreach ($all_cats as &$cat) {
        $cat['name'] = trigger_change('render_category_name', $cat['name'], $cat);
    }

    $qsr->all_cats = $all_cats;
    $qsr->cat_ids = $token_cat_ids;
}

function qsearch_eval(QMultiToken $expr, QResults $qsr, &$qualifies, &$ignored_terms)
{
    $qualifies = false; // until we find at least one positive term
    $ignored_terms = [];
    $ids = [];
    $not_ids = [];
    $counter = count($expr->tokens);

    for ($i = 0; $i < $counter; ++$i) {
        $crt = $expr->tokens[$i];
        if ($crt->is_single) {
            $crt_ids = $qsr->iids[$crt->idx] = array_unique(
                array_merge(
                    $qsr->images_iids[$crt->idx],
                    $qsr->cat_iids[$crt->idx],
                    $qsr->tag_iids[$crt->idx]
                )
            );
            $crt_qualifies = $crt_ids !== [] || count($qsr->tag_ids[$crt->idx]) > 0;
            $crt_ignored_terms = $crt_qualifies ? [] : [(string) $crt];
        } else {
            $crt_ids = qsearch_eval($crt, $qsr, $crt_qualifies, $crt_ignored_terms);
        }

        $modifier = $crt->modifier;
        if (($modifier & QST_NOT) !== 0) {
            $not_ids = array_unique(array_merge($not_ids, $crt_ids));
        } else {
            $ignored_terms = array_merge($ignored_terms, $crt_ignored_terms);
            if (($modifier & QST_OR) !== 0) {
                $ids = array_unique(array_merge($ids, $crt_ids));
                $qualifies |= $crt_qualifies;
            } elseif ($crt_qualifies) {
                $ids = $qualifies ? array_intersect($ids, $crt_ids) : $crt_ids;
                $qualifies = true;
            }
        }
    }

    if ($not_ids !== []) {
        $ids = array_diff($ids, $not_ids);
    }

    return $ids;
}

/**
 * Returns the search results corresponding to a quick/query search.
 * A quick/query search returns many items (search is not strict), but results
 * are sorted by relevance unless $super_order_by is true. Returns:
 *  array (
 *    'items' => array of matching images
 *    'qs'    => array(
 *      'unmatched_terms' => array of terms from the input string that were not matched
 *      'matching_tags' => array of matching tags
 *      'matching_cats' => array of matching categories
 *      'matching_cats_no_images' =>array(99) - matching categories without images
 *      )
 *    )
 *
 * @param string $q
 * @return array
 */
function get_quick_search_results(
    $q,
    $options
) {
    global $persistent_cache, $conf, $user;

    $cache_key = $persistent_cache->make_key([
        strtolower($q),
        $conf['order_by'],
        $user['id'], $user['cache_update_time'],
        isset($options['permissions']) ? (bool) $options['permissions'] : true,
        $options['images_where'] ?? '',
    ]);
    if ($persistent_cache->get($cache_key, $res)) {
        return $res;
    }

    $res = get_quick_search_results_no_cache($q, $options);

    if (count($res['items']) > 0) {// cache the results only if not empty - otherwise it is useless
        $persistent_cache->set($cache_key, $res, 300);
    }

    return $res;
}

/**
 * @see get_quick_search_results but without result caching
 */
function get_quick_search_results_no_cache(
    $q,
    $options
) {
    global $conf;

    $q = trim(stripslashes((string) $q));
    $search_results =
      [
          'items' => [],
          'qs' => [
              'q' => $q,
          ],
      ];

    $q = trigger_change('qsearch_pre', $q);

    $scopes = [];
    $scopes[] = new QSearchScope('tag', ['tags']);
    $scopes[] = new QSearchScope('photo', ['photos']);
    $scopes[] = new QSearchScope('file', ['filename']);
    $scopes[] = new QSearchScope('author', [], true);
    $scopes[] = new QNumericRangeScope('width', []);
    $scopes[] = new QNumericRangeScope('height', []);
    $scopes[] = new QNumericRangeScope('ratio', [], false, 0.001);
    $scopes[] = new QNumericRangeScope('size', []);
    $scopes[] = new QNumericRangeScope('filesize', []);
    $scopes[] = new QNumericRangeScope('hits', ['hit', 'visit', 'visits']);
    $scopes[] = new QNumericRangeScope('score', ['rating'], true);
    $scopes[] = new QNumericRangeScope('id', []);

    $createdDateAliases = ['taken', 'shot'];
    $postedDateAliases = ['added'];
    if ($conf['calendar_datefield'] == 'date_creation') {
        $createdDateAliases[] = 'date';
    } else {
        $postedDateAliases[] = 'date';
    }

    $scopes[] = new QDateRangeScope('created', $createdDateAliases, true);
    $scopes[] = new QDateRangeScope('posted', $postedDateAliases);

    // allow plugins to add their own scopes
    $scopes = trigger_change('qsearch_get_scopes', $scopes);
    $expression = new QExpression($q, $scopes);

    // get inflections for terms
    $inflector = null;
    $lang_code = substr(get_default_language(), 0, 2);
    @include_once(PHPWG_ROOT_PATH . 'inc/inflectors/' . $lang_code . '.php');
    $class_name = 'Inflector_' . $lang_code;
    if (class_exists($class_name)) {
        $inflector = new $class_name();
        foreach ($expression->stokens as $token) {
            if (isset($token->scope) && ! $token->scope->is_text) {
                continue;
            }

            if (strlen($token->term) > 2
              && ($token->modifier & (QST_QUOTED | QST_WILDCARD)) == 0
              && strcspn($token->term, "'0123456789") == strlen($token->term)) {
                $token->variants = array_unique(
                    array_diff($inflector->get_variants($token->term), [$token->term])
                );
            }
        }
    }

    trigger_notify('qsearch_expression_parsed', $expression);
    //var_export($expression);

    if (count($expression->stokens) == 0) {
        return $search_results;
    }

    $qsr = new QResults();
    qsearch_get_tags($expression, $qsr);
    qsearch_get_categories($expression, $qsr);
    qsearch_get_images($expression, $qsr);

    // allow plugins to evaluate their own scopes
    trigger_notify('qsearch_before_eval', $expression, $qsr);

    $ids = qsearch_eval($expression, $qsr, $tmp, $search_results['qs']['unmatched_terms']);

    $debug[] = "<!--\nparsed: " . htmlspecialchars($expression);
    $debug[] = count($expression->stokens) . ' tokens';
    $counter = count($expression->stokens);
    for ($i = 0; $i < $counter; ++$i) {
        $debug[] = htmlspecialchars((string) $expression->stokens[$i]) . ': ' . count(
            $qsr->tag_ids[$i]
        ) . ' tags, ' . count(
            $qsr->tag_iids[$i]
        ) . ' tiids, ' . count(
            $qsr->images_iids[$i]
        ) . ' iiids, ' . count(
            $qsr->iids[$i]
        ) . ' iids'
          . ' modifier:' . dechex($expression->stoken_modifiers[$i])
          . (empty($expression->stokens[$i]->variants) ? '' : ' variants: ' . htmlspecialchars(
              implode(', ', $expression->stokens[$i]->variants)
          ));
    }

    $debug[] = 'before perms ' . count($ids);

    $search_results['qs']['matching_tags'] = $qsr->all_tags;
    $search_results['qs']['matching_cats'] = $qsr->all_cats;
    $search_results = trigger_change('qsearch_results', $search_results, $expression, $qsr);
    if (isset($search_results['items'])) {
        $ids = array_merge($ids, $search_results['items']);
    }

    global $template;

    if (empty($ids)) {
        $debug[] = '-->';
        $template->append('footer_elements', implode("\n", $debug));
        return $search_results;
    }

    $permissions = $options['permissions'] ?? true;

    $where_clauses = [];
    $where_clauses[] = 'i.id IN (' . implode(',', $ids) . ')';
    if (! empty($options['images_where'])) {
        $where_clauses[] = '(' . $options['images_where'] . ')';
    }

    if ($permissions) {
        $where_clauses[] = get_sql_condition_FandF(
            [
                'forbidden_categories' => 'category_id',
                'forbidden_images' => 'i.id',
            ],
            null,
            true
        );
    }

    $query = '
SELECT DISTINCT(id) FROM ' . IMAGES_TABLE . ' i';
    if ($permissions) {
        $query .= '
    INNER JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON id = ic.image_id';
    }

    $query .= '
  WHERE ' . implode("\n AND ", $where_clauses) . "\n" .
    $conf['order_by'];

    $ids = query2array($query, null, 'id');

    $debug[] = count($ids) . ' final photo count -->';
    $template->append('footer_elements', implode("\n", $debug));

    $search_results['items'] = $ids;
    return $search_results;
}

/**
 * Returns an array of 'items' corresponding to the search id.
 * It can be either a quick search or a regular search.
 *
 * @param int $search_id
 * @param bool $super_order_by
 * @param string $images_where optional aditional restriction on images table
 * @return array
 */
function get_search_results(
    $search_id,
    $super_order_by,
    $images_where = ''
) {
    $search = get_search_array($search_id);
    if (! isset($search['q'])) {
        $result['items'] = get_regular_search_results($search, $images_where);
        return $result;
    }

    return get_quick_search_results($search['q'], [
        'super_order_by' => $super_order_by,
        'images_where' => $images_where,
    ]);
}
