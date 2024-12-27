<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Get standard SQL where to restrict and filter categories and images.
 * image_category must be named "ic" in the query
 */
function get_std_sql_where_restrict_filter(
    string $prefix_condition,
    string $img_field = 'ic.image_id',
    bool $force_one_condition = false
): string {
    return get_sql_condition_FandF(
        [
            'forbidden_categories' => 'ic.category_id',
            'visible_categories' => 'ic.category_id',
            'visible_images' => $img_field,
        ],
        $prefix_condition,
        $force_one_condition
    );
}

/**
 * Execute custom notification query.
 * @todo use a cache for all data returned by custom_notification_query()
 *
 * @param string $action 'count', 'info'
 * @param string $type 'new_comments', 'unvalidated_comments', 'new_elements', 'updated_categories', 'new_users'
 * @param ?string $start (mysql datetime format)
 * @param ?string $end (mysql datetime format)
 * @return int|array|null int for action count, array for info
 */
function custom_notification_query(
    string $action,
    string $type,
    ?string $start = null,
    ?string $end = null
): int|array|null {
    global $user;

    switch ($type) {
        case 'new_comments':

            $query = <<<SQL
                FROM comments AS c
                INNER JOIN image_category AS ic ON c.image_id = ic.image_id
                WHERE 1 = 1

                SQL;
            if ($start !== null && $start !== '' && $start !== '0') {
                $query .= <<<SQL
                    AND c.validation_date > '{$start}'

                    SQL;
            }

            if ($end !== null && $end !== '' && $end !== '0') {
                $query .= <<<SQL
                    AND c.validation_date <= '{$end}'

                    SQL;
            }

            $query .= get_std_sql_where_restrict_filter('AND');
            break;

        case 'unvalidated_comments':

            $query = <<<SQL
                FROM comments
                WHERE 1 = 1

                SQL;
            if ($start !== null && $start !== '' && $start !== '0') {
                $query .= <<<SQL
                    AND date > '{$start}'

                    SQL;
            }

            if ($end !== null && $end !== '' && $end !== '0') {
                $query .= <<<SQL
                    AND date <= '{$end}'

                    SQL;
            }

            $query .= <<<SQL
                AND validated = 'false'

                SQL;
            break;

        case 'new_elements':

            $query = <<<SQL
                FROM images
                INNER JOIN image_category AS ic ON image_id = id
                WHERE 1 = 1

                SQL;
            if ($start !== null && $start !== '' && $start !== '0') {
                $query .= <<<SQL
                    AND date_available > '{$start}'

                    SQL;
            }

            if ($end !== null && $end !== '' && $end !== '0') {
                $query .= <<<SQL
                    AND date_available <= '{$end}'

                    SQL;
            }

            $query .= get_std_sql_where_restrict_filter('AND', 'id');
            break;

        case 'updated_categories':

            $query = <<<SQL
                FROM images
                INNER JOIN image_category AS ic ON image_id = id
                WHERE 1 = 1

                SQL;
            if ($start !== null && $start !== '' && $start !== '0') {
                $query .= <<<SQL
                    AND date_available > '{$start}'

                    SQL;
            }

            if ($end !== null && $end !== '' && $end !== '0') {
                $query .= <<<SQL
                    AND date_available <= '{$end}'

                    SQL;
            }

            $query .= get_std_sql_where_restrict_filter('AND', 'id');
            break;

        case 'new_users':

            $query = <<<SQL
                FROM user_infos
                WHERE 1 = 1

                SQL;
            if ($start !== null && $start !== '' && $start !== '0') {
                $query .= <<<SQL
                    AND registration_date > '{$start}'

                    SQL;
            }

            if ($end !== null && $end !== '' && $end !== '0') {
                $query .= <<<SQL
                    AND registration_date <= '{$end}'

                    SQL;
            }

            break;

        default:
            return null; // stop and return nothing
    }

    switch ($action) {
        case 'count':

            switch ($type) {
                case 'new_comments':
                    $field_id = 'c.id';
                    break;
                case 'unvalidated_comments':
                    $field_id = 'id';
                    break;
                case 'new_elements':
                    $field_id = 'image_id';
                    break;
                case 'updated_categories':
                    $field_id = 'category_id';
                    break;
                case 'new_users':
                    $field_id = 'user_id';
                    break;
            }

            $query = <<<SQL
                SELECT COUNT(DISTINCT {$field_id}) {$query};
                SQL;
            [$count] = pwg_db_fetch_row(pwg_query($query));
            return (int) $count;
            break;

        case 'info':

            switch ($type) {
                case 'new_comments':
                    $field_id = 'c.id';
                    break;
                case 'unvalidated_comments':
                    $field_id = 'id';
                    break;
                case 'new_elements':
                    $field_id = 'image_id';
                    break;
                case 'updated_categories':
                    $field_id = 'category_id';
                    break;
                case 'new_users':
                    $field_id = 'user_id';
                    break;
            }

            $query = <<<SQL
                SELECT DISTINCT {$field_id} {$query};
                SQL;
            $infos = query2array($query);
            return $infos;
            break;

        default:
            return null; // stop and return nothing
    }
}

/**
 * Returns number of new comments between two dates.
 *
 * @param ?string $start (mysql datetime format)
 * @param ?string $end (mysql datetime format)
 */
function nb_new_comments(
    ?string $start = null,
    ?string $end = null
): array|int {
    return custom_notification_query('count', 'new_comments', $start, $end);
}

/**
 * Returns new comments between two dates.
 *
 * @param ?string $start (mysql datetime format)
 * @param ?string $end (mysql datetime format)
 * @return int[] comment ids
 */
function new_comments(
    ?string $start = null,
    ?string $end = null
): array|int {
    return custom_notification_query('info', 'new_comments', $start, $end);
}

/**
 * Returns number of unvalidated comments between two dates.
 *
 * @param ?string $start (mysql datetime format)
 * @param ?string $end (mysql datetime format)
 */
function nb_unvalidated_comments(
    ?string $start = null,
    ?string $end = null
): array|int {
    return custom_notification_query('count', 'unvalidated_comments', $start, $end);
}

/**
 * Returns number of new photos between two dates.
 *
 * @param ?string $start (mysql datetime format)
 * @param ?string $end (mysql datetime format)
 */
function nb_new_elements(
    ?string $start = null,
    ?string $end = null
): array|int {
    return custom_notification_query('count', 'new_elements', $start, $end);
}

/**
 * Returns new photos between two dates.es
 *
 * @param ?string $start (mysql datetime format)
 * @param ?string $end (mysql datetime format)
 * @return int[] photos ids
 */
function new_elements(
    ?string $start = null,
    ?string $end = null
): array|int {
    return custom_notification_query('info', 'new_elements', $start, $end);
}

/**
 * Returns number of updated categories between two dates.
 *
 * @param ?string $start (mysql datetime format)
 * @param ?string $end (mysql datetime format)
 */
function nb_updated_categories(
    ?string $start = null,
    ?string $end = null
): array|int {
    return custom_notification_query('count', 'updated_categories', $start, $end);
}

/**
 * Returns updated categories between two dates.
 *
 * @param ?string $start (mysql datetime format)
 * @param ?string $end (mysql datetime format)
 * @return int[] categories ids
 */
function updated_categories(
    ?string $start = null,
    ?string $end = null
): array|int {
    return custom_notification_query('info', 'updated_categories', $start, $end);
}

/**
 * Returns number of new users between two dates.
 *
 * @param ?string $start (mysql datetime format)
 * @param ?string $end (mysql datetime format)
 */
function nb_new_users(
    ?string $start = null,
    ?string $end = null
): array|int {
    return custom_notification_query('count', 'new_users', $start, $end);
}

/**
 * Returns new users between two dates.
 *
 * @param ?string $start (mysql datetime format)
 * @param ?string $end (mysql datetime format)
 * @return int[] user ids
 */
function new_users(
    ?string $start = null,
    ?string $end = null
): array|int {
    return custom_notification_query('info', 'new_users', $start, $end);
}

/**
 * Returns if there was new activity between two dates.
 *
 * Takes in account: number of new comments, number of new elements, number of
 * updated categories. Administrators are also informed about: number of
 * unvalidated comments, number of new users.
 * @todo number of unvalidated elements
 *
 * @param ?string $start (mysql datetime format)
 * @param ?string $end (mysql datetime format)
 */
function news_exists(
    ?string $start = null,
    ?string $end = null
): bool {
    return nb_new_comments($start, $end) > 0 || nb_new_elements($start, $end) > 0 || nb_updated_categories($start, $end) > 0 || is_admin() && nb_unvalidated_comments($start, $end) > 0 || is_admin() && nb_new_users($start, $end) > 0;
}

/**
 * Formats a news line and adds it to the array (e.g. '5 new elements')
 */
function add_news_line(
    array &$news,
    int $count,
    string $singular_key,
    string $plural_key,
    string $url = '',
    bool $add_url = false
): void {
    if ($count > 0) {
        $line = l10n_dec($singular_key, $plural_key, $count);
        if ($add_url && ($url !== '' && $url !== '0')) {
            $line = '<a href="' . $url . '">' . $line . '</a>';
        }

        $news[] = $line;
    }
}

/**
 * Returns new activity between two dates.
 *
 * Takes in account: number of new comments, number of new elements, number of
 * updated categories. Administrators are also informed about: number of
 * unvalidated comments, number of new users.
 * @todo number of unvalidated elements
 *
 * @param ?string $start (mysql datetime format)
 * @param ?string $end (mysql datetime format)
 * @param bool $exclude_img_cats if true, no info about new images/categories
 * @param bool $add_url add HTML link around news
 */
function news(
    ?string $start = null,
    ?string $end = null,
    bool $exclude_img_cats = false,
    bool $add_url = false,
    ?string $auth_key = null
): array {
    $news = [];

    $add_url_params = [];
    if (isset($auth_key)) {
        $add_url_params['auth'] = $auth_key;
    }

    if (! $exclude_img_cats) {
        add_news_line(
            $news,
            nb_new_elements($start, $end),
            '%d new photo',
            '%d new photos',
            add_url_params(make_index_url([
                'section' => 'recent_pics',
            ]), $add_url_params),
            $add_url
        );

        add_news_line(
            $news,
            nb_updated_categories($start, $end),
            '%d album updated',
            '%d albums updated',
            add_url_params(make_index_url([
                'section' => 'recent_cats',
            ]), $add_url_params),
            $add_url
        );
    }

    add_news_line(
        $news,
        nb_new_comments($start, $end),
        '%d new comment',
        '%d new comments',
        add_url_params(get_root_url() . 'comments.php', $add_url_params),
        $add_url
    );

    if (is_admin()) {
        add_news_line(
            $news,
            nb_unvalidated_comments($start, $end),
            '%d comment to validate',
            '%d comments to validate',
            get_root_url() . 'admin.php?page=comments',
            $add_url
        );

        add_news_line(
            $news,
            nb_new_users($start, $end),
            '%d new user',
            '%d new users',
            get_root_url() . 'admin.php?page=user_list',
            $add_url
        );
    }

    return $news;
}

/**
 * Returns information about recently published elements grouped by post date.
 *
 * @param int $max_dates maximum number of recent dates
 * @param int $max_elements maximum number of elements per date
 * @param int $max_cats maximum number of categories per date
 */
function get_recent_post_dates(
    int $max_dates,
    int $max_elements,
    int $max_cats
): array {
    global $conf, $user, $persistent_cache;

    $cache_key = $persistent_cache->make_key('recent_posts' . $user['id'] . $user['cache_update_time'] . $max_dates . $max_elements . $max_cats);
    if ($persistent_cache->get($cache_key, $cached)) {
        return $cached;
    }

    $where_sql = get_std_sql_where_restrict_filter('WHERE', 'i.id', true);

    $query = <<<SQL
        SELECT date_available, COUNT(DISTINCT id) AS nb_elements, COUNT(DISTINCT category_id) AS nb_cats
        FROM images i INNER JOIN image_category AS ic ON id = image_id
        {$where_sql}
        GROUP BY date_available
        ORDER BY date_available DESC
        LIMIT {$max_dates};
        SQL;
    $dates = query2array($query);
    $counter = count($dates);

    for ($i = 0; $i < $counter; $i++) {
        if ($max_elements > 0) { // get some thumbnails ...
            $randomFunction = DB_RANDOM_FUNCTION;
            $query = <<<SQL
                SELECT DISTINCT i.*
                FROM images i
                INNER JOIN image_category AS ic ON id = image_id
                {$where_sql}
                    AND date_available = '{$dates[$i]['date_available']}'
                ORDER BY {$randomFunction}
                LIMIT {$max_elements};
                SQL;
            $dates[$i]['elements'] = query2array($query);
        }

        if ($max_cats > 0) {// get some categories ...
            $query = <<<SQL
                SELECT DISTINCT c.uppercats, COUNT(DISTINCT i.id) AS img_count
                FROM images i
                INNER JOIN image_category AS ic ON i.id = image_id
                INNER JOIN categories c ON c.id = category_id
                {$where_sql}
                    AND date_available = '{$dates[$i]['date_available']}'
                GROUP BY category_id, c.uppercats
                ORDER BY img_count DESC
                LIMIT {$max_cats};
                SQL;
            $dates[$i]['categories'] = query2array($query);
        }
    }

    $persistent_cache->set($cache_key, $dates);
    return $dates;
}

/**
 * Returns information about recently published elements grouped by post date.
 * Same as get_recent_post_dates() but parameters as an indexed array.
 * @see get_recent_post_dates()
 */
function get_recent_post_dates_array(
    array $args
): array {
    return get_recent_post_dates(
        (empty($args['max_dates']) ? 3 : $args['max_dates']),
        (empty($args['max_elements']) ? 3 : $args['max_elements']),
        (empty($args['max_cats']) ? 3 : $args['max_cats'])
    );
}

/**
 * Returns HTML description about recently published elements grouped by post date.
 * @todo clean up HTML output, currently messy and invalid !
 *
 * @param array $date_detail returned value of get_recent_post_dates()
 */
function get_html_description_recent_post_date(
    array $date_detail,
    ?string $auth_key = null
): string {
    global $conf;

    $add_url_params = [];
    if (isset($auth_key)) {
        $add_url_params['auth'] = $auth_key;
    }

    $description = '<ul>';

    $description .=
          '<li>'
          . l10n_dec('%d new photo', '%d new photos', $date_detail['nb_elements'])
          . ' ('
          . '<a href="' . add_url_params(make_index_url([
              'section' => 'recent_pics',
          ]), $add_url_params) . '">'
            . l10n('Recent photos') . '</a>'
          . ')'
          . '</li><br>';

    foreach ($date_detail['elements'] as $element) {
        $tn_src = DerivativeImage::thumb_url($element);
        $description .= '<a href="' .
          add_url_params(
              make_picture_url(
                  [
                      'image_id' => $element['id'],
                      'image_file' => $element['file'],
                  ]
              ),
              $add_url_params
          )
          . '"><img src="' . $tn_src . '"></a>';
    }

    $description .= '...<br>';

    $description .=
          '<li>'
          . l10n_dec('%d album updated', '%d albums updated', $date_detail['nb_cats'])
          . '</li>';

    $description .= '<ul>';
    foreach ($date_detail['categories'] as $cat) {
        $description .=
              '<li>'
              . get_cat_display_name_cache($cat['uppercats'], '', false, null, $auth_key)
              . ' (' .
              l10n_dec('%d new photo', '%d new photos', $cat['img_count']) . ')'
              . '</li>';
    }

    $description .= '</ul>';

    $description .= '</ul>';

    return $description;
}

/**
 * Returns title about recently published elements grouped by post date.
 *
 * @param array $date_detail returned value of get_recent_post_dates()
 */
function get_title_recent_post_date(
    array $date_detail
): string {
    global $lang;

    $title = l10n_dec('%d new photo', '%d new photos', $date_detail['nb_elements']);

    if (preg_match('/^\d+-(\d+)-(\d+) /', (string) $date_detail['date_available'], $matches)) {
        $title .= ' (' . $lang['month'][(int) $matches[1]] . ' ' . $matches[2] . ')';
    }

    return $title;
}
