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

define('PHPWG_ROOT_PATH', './');
require_once(__DIR__ . '/inc/common.inc.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
FunctionsUser::check_status(ACCESS_GUEST);

if (empty($_GET['q'])) {
    redirect(make_index_url());
}

$search = [];
$search['q'] = $_GET['q'];

$query = '
SElECT id FROM ' . SEARCH_TABLE . '
  WHERE rules = \'' . addslashes(serialize($search)) . '\'
;';
$search_id = Mysqli::query2array($query, null, 'id');
if (! empty($search_id)) {
    $search_id = $search_id[0];
    $query = '
UPDATE ' . SEARCH_TABLE . '
  SET last_seen=NOW()
  WHERE id=' . $search_id;
    Mysqli::pwg_query($query);
} else {
    $query = '
INSERT INTO ' . SEARCH_TABLE . '
  (rules, last_seen)
  VALUES
  (\'' . addslashes(serialize($search)) . '\', NOW() )
;';
    Mysqli::pwg_query($query);
    $search_id = Mysqli::pwg_db_insert_id();
}

redirect(
    make_index_url(
        [
            'section' => 'search',
            'search' => $search_id,
        ]
    )
);
