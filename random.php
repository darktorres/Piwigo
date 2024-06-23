<?php

declare(strict_types=1);

namespace Piwigo;

use function Piwigo\inc\check_status;
use function Piwigo\inc\dbLayer\query2array;
use function Piwigo\inc\get_sql_condition_FandF;
use function Piwigo\inc\make_index_url;
use function Piwigo\inc\redirect;
use const Piwigo\inc\ACCESS_GUEST;
use const Piwigo\inc\DbLayer\DB_RANDOM_FUNCTION;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

// +-----------------------------------------------------------------------+
// |                          define and include                           |
// +-----------------------------------------------------------------------+

const PHPWG_ROOT_PATH = './';
include_once(PHPWG_ROOT_PATH . 'inc/common.inc.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_GUEST);

// +-----------------------------------------------------------------------+
// |                     generate random element list                      |
// +-----------------------------------------------------------------------+

$query = '
SELECT id
  FROM ' . IMAGES_TABLE . '
    INNER JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON id = ic.image_id
' . get_sql_condition_FandF(
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
    'list' => query2array($query, null, 'id'),
]));
