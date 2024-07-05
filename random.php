<?php

namespace Piwigo;

use Piwigo\inc\dblayer\Mysqli;
use Piwigo\inc\FunctionsUser;
use function Piwigo\inc\make_index_url;
use function Piwigo\inc\redirect;

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
require_once(__DIR__ . '/inc/common.inc.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
FunctionsUser::check_status(ACCESS_GUEST);

// +-----------------------------------------------------------------------+
// |                     generate random element list                      |
// +-----------------------------------------------------------------------+

$query = '
SELECT id
  FROM ' . IMAGES_TABLE . '
    INNER JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON id = ic.image_id
' . FunctionsUser::get_sql_condition_FandF(
    [
        'forbidden_categories' => 'category_id',
        'visible_categories' => 'category_id',
        'visible_images' => 'id',
    ],
    'WHERE'
) . '
  ORDER BY ' . DB_RANDOM_FUNCTION . '()
  LIMIT ' . min(50, $conf['top_number'], $user['nb_image_page']) . '
;';

// +-----------------------------------------------------------------------+
// |                                redirect                               |
// +-----------------------------------------------------------------------+

redirect(make_index_url([
    'list' => Mysqli::query2array($query, null, 'id'),
]));
