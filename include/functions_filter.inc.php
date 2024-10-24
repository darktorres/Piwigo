<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/**
 * Updates data of categories with filtered values
 */
function update_cats_with_filtered_data(
    array &$cats
): void {
    global $filter;

    if ($filter['enabled']) {
        $upd_fields = ['date_last', 'max_date_last', 'count_images', 'count_categories', 'nb_images'];

        foreach ($cats as $cat_id => $category) {
            foreach ($upd_fields as $upd_field) {
                $cats[$cat_id][$upd_field] = $filter['categories'][$category['id']][$upd_field];
            }
        }
    }
}
