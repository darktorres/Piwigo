<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Event handler for method invocation security check. Should return a PwgError
 * if the preconditions are not satisfied for method invocation.
 */
function ws_isInvokeAllowed(
    bool $res,
    string $methodName,
    array $params
): PwgError|bool {
    global $conf;

    if (str_starts_with($methodName, 'reflection.')) { // OK for reflection
        return $res;
    }

    if (! is_autorize_status(ACCESS_GUEST) && ! str_starts_with($methodName, 'pwg.session.')) {
        return new PwgError(401, 'Access denied');
    }

    return $res;
}

/**
 * returns a "standard" (for our web service) array of SQL where clauses that
 * filters the images (images table only)
 */
function ws_std_image_sql_filter(
    array $params,
    string $tbl_name = ''
): array {
    $clauses = [];
    if (is_numeric($params['f_min_rate'])) {
        $clauses[] = "{$tbl_name}rating_score >= {$params['f_min_rate']}";
    }

    if (is_numeric($params['f_max_rate'])) {
        $clauses[] = "{$tbl_name}rating_score <= {$params['f_max_rate']}";
    }

    if (is_numeric($params['f_min_hit'])) {
        $clauses[] = "{$tbl_name}hit >= {$params['f_min_hit']}";
    }

    if (is_numeric($params['f_max_hit'])) {
        $clauses[] = "{$tbl_name}hit <= {$params['f_max_hit']}";
    }

    if (isset($params['f_min_date_available'])) {
        $clauses[] = "{$tbl_name}date_available >= '{$params['f_min_date_available']}'";
    }

    if (isset($params['f_max_date_available'])) {
        $clauses[] = "{$tbl_name}date_available < '{$params['f_max_date_available']}'";
    }

    if (isset($params['f_min_date_created'])) {
        $clauses[] = "{$tbl_name}date_creation >= '{$params['f_min_date_created']}'";
    }

    if (isset($params['f_max_date_created'])) {
        $clauses[] = "{$tbl_name}date_creation < '{$params['f_max_date_created']}'";
    }

    if (is_numeric($params['f_min_ratio'])) {
        $clauses[] = "{$tbl_name}width/{$tbl_name}height >= {$params['f_min_ratio']}";
    }

    if (is_numeric($params['f_max_ratio'])) {
        $clauses[] = "{$tbl_name}width/{$tbl_name}height <= {$params['f_max_ratio']}";
    }

    if (is_numeric($params['f_max_level'])) {
        $clauses[] = "{$tbl_name}level <= {$params['f_max_level']}";
    }

    return $clauses;
}

/**
 * returns a "standard" (for our web service) ORDER BY SQL clause for images
 */
function ws_std_image_sql_order(
    array $params,
    string $tbl_name = ''
): string {
    $ret = '';
    if (empty($params['order'])) {
        return $ret;
    }

    $matches = [];
    preg_match_all(
        '/([a-z_]+) *(?:(asc|desc)(?:ending)?)? *(?:, *|$)/i',
        (string) $params['order'],
        $matches
    );
    $counter = count($matches[1]);
    for ($i = 0; $i < $counter; $i++) {
        switch ($matches[1][$i]) {
            case 'date_created':
                $matches[1][$i] = 'date_creation';
                break;
            case 'date_posted':
                $matches[1][$i] = 'date_available';
                break;
            case 'rand': case 'random':
                $matches[1][$i] = DB_RANDOM_FUNCTION;
                break;
        }

        $sortable_fields = ['id', 'file', 'name', 'hit', 'rating_score',
            'date_creation', 'date_available', DB_RANDOM_FUNCTION];
        if (in_array($matches[1][$i], $sortable_fields)) {
            if ($ret !== '' && $ret !== '0') {
                $ret .= ', ';
            }

            if ($matches[1][$i] !== DB_RANDOM_FUNCTION) {
                $ret .= $tbl_name;
            }

            $ret .= $matches[1][$i];
            $ret .= ' ' . $matches[2][$i];
        }
    }

    return $ret;
}

/**
 * returns an array map of urls (thumb/element) for image_row - to be returned
 * in a standard way by different web service methods
 */
function ws_std_get_urls(
    array $image_row
): array {
    $ret = [];

    $ret['page_url'] = make_picture_url(
        [
            'image_id' => $image_row['id'],
            'image_file' => $image_row['file'],
        ]
    );

    $src_image = new SrcImage($image_row);

    $provide_download_url = false;

    if ($src_image->is_original() !== 0) {// we have a photo
        global $user;
        if ($user['enabled_high']) {
            $ret['element_url'] = $src_image->get_url();
            $provide_download_url = true;
        }
    } else {
        $ret['element_url'] = get_element_url($image_row);
        $provide_download_url = true;
    }

    $ret['download_url'] = null;
    if ($provide_download_url) {
        $ret['download_url'] = str_replace('&amp;', '&', get_action_url($image_row['id'], 'e', true));
    }

    $derivatives = DerivativeImage::get_all($src_image);
    $derivatives_arr = [];
    foreach ($derivatives as $type => $derivative) {
        $size = $derivative->get_size();
        if ($size == null) {
            $size = [null, null];
        }

        $derivatives_arr[$type] = [
            'url' => $derivative->get_url(),
            'width' => $size[0],
            'height' => $size[1],
        ];
    }

    $ret['derivatives'] = $derivatives_arr;
    return $ret;
}

/**
 * returns an array of image attributes that are to be encoded as XML attributes
 * instead of XML elements
 */
function ws_std_get_image_xml_attributes(): array
{
    return [
        'id', 'element_url', 'page_url', 'file', 'width', 'height', 'hit', 'date_available', 'date_creation',
    ];
}

function ws_std_get_category_xml_attributes(): array
{
    return [
        'id', 'url', 'nb_images', 'total_nb_images', 'nb_categories', 'date_last', 'max_date_last', 'status',
    ];
}

function ws_std_get_tag_xml_attributes(): array
{
    return [
        'id', 'name', 'url_name', 'counter', 'url', 'page_url',
    ];
}

/**
 * create a tree from a flat list of categories, no recursivity for high speed
 * @return mixed[]
 */
function categories_flatlist_to_tree(
    array $categories
): array {
    $tree = [];
    $key_of_cat = [];

    foreach ($categories as $key => &$node) {
        $key_of_cat[$node['id']] = $key;

        if (! isset($node['id_uppercat'])) {
            $tree[] = &$node;
        } else {
            if (! isset($categories[$key_of_cat[$node['id_uppercat']]]['sub_categories'])) {
                $categories[$key_of_cat[$node['id_uppercat']]]['sub_categories'] =
                  new PwgNamedArray([], 'category', ws_std_get_category_xml_attributes());
            }

            $categories[$key_of_cat[$node['id_uppercat']]]['sub_categories']->_content[] = &$node;
        }
    }

    return $tree;
}
