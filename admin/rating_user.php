<?php

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

use Piwigo\admin\inc\tabsheet;
use Piwigo\inc\dblayer\functions_mysqli;
use Piwigo\inc\derivative_std_params;
use Piwigo\inc\DerivativeImage;
use Piwigo\inc\functions;
use Piwigo\inc\functions_url;
use Piwigo\inc\functions_user;
use Piwigo\inc\ImageStdParams;

defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

$tabsheet = new tabsheet();
$tabsheet->set_id('rating');
$tabsheet->select('rating_user');
$tabsheet->assign();

$filter_min_rates = 2;
if (isset($_GET['f_min_rates'])) {
    $filter_min_rates = (int) $_GET['f_min_rates'];
}

$consensus_top_number = $conf['top_number'];
if (isset($_GET['consensus_top_number'])) {
    $consensus_top_number = (int) $_GET['consensus_top_number'];
}

// build users
global $conf;
$query = 'SELECT DISTINCT
  u.' . $conf['user_fields']['id'] . ' AS id,
  u.' . $conf['user_fields']['username'] . ' AS name,
  ui.status
  FROM users AS u INNER JOIN user_infos AS ui
    ON u.' . $conf['user_fields']['id'] . ' = ui.user_id';

$users_by_id = [];
$result = functions_mysqli::pwg_query($query);
while ($row = functions_mysqli::pwg_db_fetch_assoc($result)) {
    $users_by_id[(int) $row['id']] = [
        'name' => $row['name'],
        'anon' => functions_user::is_autorize_status(ACCESS_CLASSIC, $row['status']) ? false : true,
    ];
}

$by_user_rating_model = [
    'rates' => [],
];
foreach ($conf['rate_items'] as $rate) {
    $by_user_rating_model['rates'][$rate] = [];
}

// by user aggregation
$image_ids = [];
$by_user_ratings = [];
$query = '
SELECT * FROM rate ORDER by date DESC';
$result = functions_mysqli::pwg_query($query);
while ($row = functions_mysqli::pwg_db_fetch_assoc($result)) {
    if (! isset($users_by_id[$row['user_id']])) {
        $users_by_id[$row['user_id']] = [
            'name' => '???' . $row['user_id'],
            'anon' => false,
        ];
    }

    $usr = $users_by_id[$row['user_id']];
    if ($usr['anon']) {
        $user_key = $usr['name'] . '(' . $row['anonymous_id'] . ')';
    } else {
        $user_key = $usr['name'];
    }

    $rating = &$by_user_ratings[$user_key];
    if ($rating === null) {
        $rating = $by_user_rating_model;
        $rating['uid'] = (int) $row['user_id'];
        $rating['aid'] = $usr['anon'] ? $row['anonymous_id'] : '';
        $rating['last_date'] = $rating['first_date'] = $row['date'];
    } else {
        $rating['first_date'] = $row['date'];
    }

    $rating['rates'][$row['rate']][] = [
        'id' => $row['element_id'],
        'date' => $row['date'],
    ];
    $image_ids[$row['element_id']] = 1;
    unset($rating);
}

// get image tn urls
$image_urls = [];
if (count($image_ids) > 0) {
    $query = 'SELECT id, name, file, path, representative_ext, level
  FROM images
  WHERE id IN (' . implode(',', array_keys($image_ids)) . ')';
    $result = functions_mysqli::pwg_query($query);
    $params = ImageStdParams::get_by_type(derivative_std_params::IMG_SQUARE);
    while ($row = functions_mysqli::pwg_db_fetch_assoc($result)) {
        $image_urls[$row['id']] = [
            'tn' => DerivativeImage::url($params, $row),
            'page' => functions_url::make_picture_url([
                'image_id' => $row['id'],
                'image_file' => $row['file'],
            ]),
        ];
    }
}

//all image averages
$query = 'SELECT element_id,
    AVG(rate) AS avg
  FROM rate
  GROUP BY element_id';
$all_img_sum = [];
$result = functions_mysqli::pwg_query($query);
while ($row = functions_mysqli::pwg_db_fetch_assoc($result)) {
    $all_img_sum[(int) $row['element_id']] = [
        'avg' => (float) $row['avg'],
    ];
}

$query = 'SELECT id
  FROM images
  ORDER by rating_score DESC
  LIMIT ' . $consensus_top_number;
$best_rated = array_flip(functions::array_from_query($query, 'id'));

// by user stats
foreach ($by_user_ratings as $id => &$rating) {
    $c = 0;
    $s = 0;
    $ss = 0;
    $consensus_dev = 0;
    $consensus_dev_top = 0;
    $consensus_dev_top_count = 0;
    foreach ($rating['rates'] as $rate => $rates) {
        $ct = count($rates);
        $c += $ct;
        $s += $ct * $rate;
        $ss += $ct * $rate * $rate;
        foreach ($rates as $id_date) {
            $dev = abs($rate - $all_img_sum[$id_date['id']]['avg']);
            $consensus_dev += $dev;
            if (isset($best_rated[$id_date['id']])) {
                $consensus_dev_top += $dev;
                $consensus_dev_top_count++;
            }
        }
    }

    $consensus_dev /= $c;
    if ($consensus_dev_top_count) {
        $consensus_dev_top /= $consensus_dev_top_count;
    }

    $var = ($ss - $s * $s / $c) / $c;
    $rating += [
        'id' => $id,
        'count' => $c,
        'avg' => $s / $c,
        'cv' => $s == 0 ? -1 : sqrt($var) / ($s / $c), // http://en.wikipedia.org/wiki/Coefficient_of_variation
        'cd' => $consensus_dev,
        'cdtop' => $consensus_dev_top_count ? $consensus_dev_top : '',
    ];
}

unset($rating);

// filter
foreach ($by_user_ratings as $id => $rating) {
    if ($rating['count'] <= $filter_min_rates) {
        unset($by_user_ratings[$id]);
    }
}

$order_by_index = 4;
if (isset($_GET['order_by']) and is_numeric($_GET['order_by'])) {
    $order_by_index = $_GET['order_by'];
}

$available_order_by = [
    [functions::l10n('Average rate'), '\Piwigo\inc\functions::avg_compare'],
    [functions::l10n('Number of rates'), '\Piwigo\inc\functions::count_compare'],
    [functions::l10n('Variation'), '\Piwigo\inc\functions::cv_compare'],
    [functions::l10n('Consensus deviation'), '\Piwigo\inc\functions::consensus_dev_compare'],
    [functions::l10n('Last'), '\Piwigo\inc\functions::last_rate_compare'],
];

for ($i = 0; $i < count($available_order_by); $i++) {
    $template->append(
        'order_by_options',
        $available_order_by[$i][0]
    );
}

$template->assign('order_by_options_selected', [$order_by_index]);

$x = uasort($by_user_ratings, $available_order_by[$order_by_index][1]);

$query = '
SELECT
    COUNT(*)
  FROM rate' .
';';
list($nb_elements) = functions_mysqli::pwg_db_fetch_row(functions_mysqli::pwg_query($query));

$template->assign([
    'F_ACTION' => functions_url::get_root_url() . 'admin.php',
    'F_MIN_RATES' => $filter_min_rates,
    'CONSENSUS_TOP_NUMBER' => $consensus_top_number,
    'available_rates' => $conf['rate_items'],
    'ratings' => $by_user_ratings,
    'image_urls' => $image_urls,
    'TN_WIDTH' => ImageStdParams::get_by_type(derivative_std_params::IMG_SQUARE)->sizing->ideal_size[0],
    'NB_ELEMENTS' => $nb_elements,
    'ADMIN_PAGE_TITLE' => functions::l10n('Rating'),
]);
$template->set_filename('rating', 'rating_user.tpl');
$template->assign_var_from_handle('ADMIN_CONTENT', 'rating');
