<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/** URL keyword for list view */
define('CAL_VIEW_LIST', 'list');
/** URL keyword for calendar view */
define('CAL_VIEW_CALENDAR', 'calendar');

/**
 * Initialize _$page_ and _$template_ vars for calendar view.
 */
function initialize_calendar(): void
{
    global $page, $conf, $user, $template, $persistent_cache, $filter;

    //------------------ initialize the condition on items to take into account ---
    $inner_sql = ' FROM images';

    if ($page['section'] == 'categories') { // we will regenerate the items by including subcats elements
        $page['items'] = [];
        $inner_sql .= "\nINNER JOIN image_category ON id = image_id";

        if (isset($page['category'])) {
            $sub_ids = array_diff(
                get_subcat_ids([$page['category']['id']]),
                explode(',', (string) $user['forbidden_categories'])
            );

            if ($sub_ids === []) {
                return; // nothing to do
            }

            $imploded_ids = implode(',', $sub_ids);
            $inner_sql .= "\nWHERE category_id IN ({$imploded_ids})";
            $inner_sql .= "\n" . get_sql_condition_FandF(
                [
                    'visible_images' => 'id',
                ],
                'AND',
                false
            );
        } else {
            $inner_sql .= "\n" . get_sql_condition_FandF(
                [
                    'forbidden_categories' => 'category_id',
                    'visible_categories' => 'category_id',
                    'visible_images' => 'id',
                ],
                'WHERE',
                true
            );
        }
    } else {
        if (empty($page['items'])) {
            return; // nothing to do
        }

        $imploded_items = implode(',', $page['items']);
        $inner_sql .= "\nWHERE id IN ({$imploded_items})";
    }

    //-------------------------------------- initialize the calendar parameters ---
    pwg_debug('start initialize_calendar');

    $fields = [
        // Created
        'created' => [
            'label' => l10n('Creation date'),
        ],
        // Posted
        'posted' => [
            'label' => l10n('Post date'),
        ],
    ];

    $styles = [
        // Monthly style
        'monthly' => [
            'include' => 'calendar_monthly.class.php',
            'view_calendar' => true,
            'classname' => 'CalendarMonthly',
        ],
        // Weekly style
        'weekly' => [
            'include' => 'calendar_weekly.class.php',
            'view_calendar' => false,
            'classname' => 'CalendarWeekly',
        ],
    ];

    $views = [CAL_VIEW_LIST, CAL_VIEW_CALENDAR];

    // Retrieve calendar field
    if (! isset($fields[$page['chronology_field']])) {
        fatal_error('bad chronology field');
    }

    // Retrieve style
    if (! isset($styles[$page['chronology_style']])) {
        $page['chronology_style'] = 'monthly';
    }

    $cal_style = $page['chronology_style'];
    $classname = $styles[$cal_style]['classname'];

    require PHPWG_ROOT_PATH . 'include/' . $styles[$cal_style]['include'];
    $calendar = new $classname();

    // Retrieve view

    if (! isset($page['chronology_view']) || ! in_array($page['chronology_view'], $views)) {
        $page['chronology_view'] = CAL_VIEW_LIST;
    }

    if ($page['chronology_view'] == CAL_VIEW_CALENDAR && ! $styles[$cal_style]['view_calendar']) {

        $page['chronology_view'] = CAL_VIEW_LIST;
    }

    // perform a sanity check on $requested
    if (! isset($page['chronology_date'])) {
        $page['chronology_date'] = [];
    }

    while (count($page['chronology_date']) > 3) {
        array_pop($page['chronology_date']);
    }

    $any_count = 0;
    $counter = count($page['chronology_date']);
    for ($i = 0; $i < $counter; $i++) {
        if ($page['chronology_date'][$i] == 'any') {
            if ($page['chronology_view'] == CAL_VIEW_CALENDAR) {// we don't allow any in calendar view
                while ($i < count($page['chronology_date'])) {
                    array_pop($page['chronology_date']);
                }

                break;
            }

            $any_count++;
        } elseif ($page['chronology_date'][$i] == '') {
            while ($i < count($page['chronology_date'])) {
                array_pop($page['chronology_date']);
            }
        } else {
            $page['chronology_date'][$i] = (int) $page['chronology_date'][$i];
        }
    }

    if ($any_count == 3) {
        array_pop($page['chronology_date']);
    }

    $calendar->initialize($inner_sql);

    //echo ('<pre>'. var_export($calendar, true) . '</pre>');

    $must_show_list = true; // true until calendar generates its own display
    if (script_basename() !== 'picture') { // basename without file extention
        if ($calendar->generate_category_content()) {
            $page['items'] = [];
            $must_show_list = false;
        }

        $page['comment'] = '';
        $template->assign('FILE_CHRONOLOGY_VIEW', 'month_calendar.tpl');

        foreach ($styles as $style => $style_data) {
            foreach ($views as $view) {
                if ($style_data['view_calendar'] || $view !== CAL_VIEW_CALENDAR) {
                    $selected = false;

                    if ($style !== $cal_style) {
                        $chronology_date = [];
                        if (isset($page['chronology_date'][0])) {
                            $chronology_date[] = $page['chronology_date'][0];
                        }
                    } else {
                        $chronology_date = $page['chronology_date'];
                    }

                    $url = duplicate_index_url(
                        [
                            'chronology_style' => $style,
                            'chronology_view' => $view,
                            'chronology_date' => $chronology_date,
                        ]
                    );

                    if ($style === $cal_style && $view == $page['chronology_view']) {
                        $selected = true;
                    }

                    $template->append(
                        'chronology_views',
                        [
                            'VALUE' => $url,
                            'CONTENT' => l10n('chronology_' . $style . '_' . $view),
                            'SELECTED' => $selected,
                        ]
                    );
                }
            }
        }

        $url = duplicate_index_url(
            [],
            ['start', 'chronology_date']
        );
        $calendar_title = '<a href="' . $url . '">'
            . $fields[$page['chronology_field']]['label'] . '</a>';
        $calendar_title .= $calendar->get_display_name();
        $template->assign(
            'chronology',
            [
                'TITLE' => $calendar_title,
            ]
        );
    } // end category calling

    if ($must_show_list) {
        if (isset($page['super_order_by'])) {
            $order_by = $conf['order_by'];
        } else {
            $order = count($page['chronology_date']) == 0 || in_array('any', $page['chronology_date']) ? ' DESC, ' : ' ASC, ';

            // TODO: fix duplicated ORDER BY => 'ORDER BY date_creation DESC' => 'ORDER BY date_creation DESC, date_creation DESC'
            $order_by = str_replace(
                'ORDER BY ',
                "ORDER BY {$calendar->date_field}{$order}",
                $conf['order_by']
            );
        }

        if ($page['section'] == 'categories' && ! isset($page['category'])
          && (count($page['chronology_date']) == 0 || $page['chronology_date'][0] == 'any' && count($page['chronology_date']) == 1)
        ) {
            $cache_key = $persistent_cache->make_key($user['id'] . $user['cache_update_time']
              . $calendar->date_field . $order_by);
        }

        if (! isset($cache_key) || ! $persistent_cache->get($cache_key, $page['items'])) {
            $query = <<<SQL
                SELECT DISTINCT id, date_creation
                {$calendar->inner_sql}
                {$calendar->get_date_where()}
                {$order_by};
                SQL;

            $page['items'] = query2array($query, null, 'id');
            if (isset($cache_key)) {
                $persistent_cache->set($cache_key, $page['items']);
            }
        }
    }

    pwg_debug('end initialize_calendar');
}
