<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

// +-----------------------------------------------------------------------+
// |                          define and include                           |
// +-----------------------------------------------------------------------+

define('PHPWG_ROOT_PATH', './');
require_once PHPWG_ROOT_PATH . 'include/common.inc.php';

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_GUEST);

// +-----------------------------------------------------------------------+
// |                     generate random element list                      |
// +-----------------------------------------------------------------------+

$filters_and_forbidden = get_sql_condition_FandF(
    [
        'forbidden_categories' => 'category_id',
        'visible_categories' => 'category_id',
        'visible_images' => 'id',
    ],
    'WHERE'
);
$min_ = min(50, $conf['top_number'], $user['nb_image_page']);
$query = "SELECT id FROM images INNER JOIN image_category AS ic ON id = ic.image_id {$filters_and_forbidden} ORDER BY " . DB_RANDOM_FUNCTION . " LIMIT {$min_};";

// +-----------------------------------------------------------------------+
// |                                redirect                               |
// +-----------------------------------------------------------------------+

redirect(make_index_url([
    'list' => query2array($query, null, 'id'),
]));
