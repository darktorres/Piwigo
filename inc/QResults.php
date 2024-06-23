<?php

namespace Piwigo\inc;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

require_once __DIR__ . '/functions_search.inc.php';

/**
 * Structure of results being filled from different tables
 */
class QResults
{
    public $all_tags;

    public $tag_ids;

    public $tag_iids;

    public $all_cats;

    public $cat_ids;

    public $cat_iids;

    public $images_iids;

    public $iids;
}
