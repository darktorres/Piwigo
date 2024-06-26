<?php

declare(strict_types=1);

namespace Piwigo;

use function Piwigo\inc\check_status;
use function Piwigo\inc\dbLayer\pwg_db_insert_id;
use function Piwigo\inc\dbLayer\pwg_query;
use function Piwigo\inc\dbLayer\query2array;
use function Piwigo\inc\make_index_url;
use function Piwigo\inc\redirect;
use const Piwigo\inc\ACCESS_GUEST;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

const PHPWG_ROOT_PATH = './';
include_once(PHPWG_ROOT_PATH . 'inc/common.inc.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
check_status(ACCESS_GUEST);

if (empty($_GET['q'])) {
    redirect(make_index_url());
}

$search = [];
$search['q'] = $_GET['q'];

$query = '
SElECT id FROM ' . SEARCH_TABLE . '
  WHERE rules = \'' . addslashes(serialize($search)) . '\'
;';
$search_id = query2array($query, null, 'id');
if ($search_id !== []) {
    $search_id = $search_id[0];
    $query = '
UPDATE ' . SEARCH_TABLE . '
  SET last_seen=NOW()
  WHERE id=' . $search_id;
    pwg_query($query);
} else {
    $query = '
INSERT INTO ' . SEARCH_TABLE . '
  (rules, last_seen)
  VALUES
  (\'' . addslashes(serialize($search)) . '\', NOW() )
;';
    pwg_query($query);
    $search_id = pwg_db_insert_id();
}

redirect(
    make_index_url(
        [
            'section' => 'search',
            'search' => $search_id,
        ]
    )
);
