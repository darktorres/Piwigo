<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

/** level of year view */
define('CYEAR', 0);
/** level of week view in weekly view */
define('CWEEK', 1);
/** level of month view in monthly view */
define('CMONTH', 1);
/** level of day view */
define('CDAY', 2);

/**
 * Base class for monthly and weekly calendar styles
 */
abstract class CalendarBase
{
    /**
     * db column on which this calendar works
     */
    public $date_field;

    /**
     * used for queries (INNER JOIN or normal)
     */
    public $inner_sql;

    /**
     * used to store db fields
     */
    public $calendar_levels;

    /**
     * Generate navigation bars for category page.
     *
     * @return boolean false indicates that thumbnails where not included
     */
    abstract public function generate_category_content();

    /**
     * Returns a SQL WHERE subquery for the date field.
     *
     * @param int $max_levels (e.g. 2=only year and month)
     * @return string
     */
    abstract public function get_date_where($max_levels = 3);

    /**
     * Initialize the calendar.
     *
     * @param string $inner_sql
     */
    public function initialize($inner_sql)
    {
        global $page;
        if ($page['chronology_field'] == 'posted') {
            $this->date_field = 'date_available';
        } else {
            $this->date_field = 'date_creation';
        }
        $this->inner_sql = $inner_sql;
    }

    /**
     * Returns the calendar title (with HTML).
     *
     * @return string
     */
    public function get_display_name()
    {
        global $conf, $page;
        $res = '';

        for ($i = 0; $i < count($page['chronology_date']); $i++) {
            $res .= $conf['level_separator'];
            if (isset($page['chronology_date'][$i + 1])) {
                $chronology_date = array_slice($page['chronology_date'], 0, $i + 1);
                $url = duplicate_index_url(
                    [
                        'chronology_date' => $chronology_date,
                    ],
                    ['start']
                );
                $res .=
                  '<a href="' . $url . '">'
                  . $this->get_date_component_label($i, $page['chronology_date'][$i])
                  . '</a>';
            } else {
                $res .=
                  '<span class="calInHere">'
                  . $this->get_date_component_label($i, $page['chronology_date'][$i])
                  . '</span>';
            }
        }
        return $res;
    }

    /**
     * Returns a display name for a date component optionally using labels.
     *
     * @return string
     */
    protected function get_date_component_label($level, $date_component)
    {
        $label = $date_component;
        if (isset($this->calendar_levels[$level]['labels'][$date_component])) {
            $label = $this->calendar_levels[$level]['labels'][$date_component];
        } elseif ($date_component === 'any') {
            $label = l10n('All');
        }
        return $label;
    }

    /**
     * Gets a nice display name for a date to be shown in previous/next links
     *
     * @param string $date
     * @return string
     */
    protected function get_date_nice_name($date)
    {
        $date_components = explode('-', $date);
        $res = '';
        for ($i = count($date_components) - 1; $i >= 0; $i--) {
            if ($date_components[$i] !== 'any') {
                $label = $this->get_date_component_label($i, $date_components[$i]);
                if ($res != '') {
                    $res .= ' ';
                }
                $res .= $label;
            }
        }
        return $res;
    }

    /**
     * Creates a calendar navigation bar.
     *
     * @param array $date_components
     * @param array $items - hash of items to put in the bar (e.g., 2005,2006)
     * @param bool $show_any - adds any link to the end of the bar
     * @param bool $show_empty - shows all labels even those without items
     * @param array $labels - optional labels for items (e.g. Jan,Feb,...)
     * @return string
     */
    protected function get_nav_bar_from_items(
        $date_components,
        $items,
        $show_any,
        $show_empty = false,
        $labels = null
    ) {
        global $conf, $page, $template;

        $nav_bar_datas = [];

        if ($conf['calendar_show_empty'] and $show_empty and ! empty($labels)) {
            foreach ($labels as $item => $label) {
                if (! isset($items[$item])) {
                    $items[$item] = -1;
                }
            }
            ksort($items);
        }

        foreach ($items as $item => $nb_images) {
            $label = $item;
            if (isset($labels[$item])) {
                $label = $labels[$item];
            }
            if ($nb_images == -1) {
                $tmp_datas = [
                    'LABEL' => $label,
                ];
            } else {
                $url = duplicate_index_url(
                    [
                        'chronology_date' => array_merge($date_components, [$item]),
                    ],
                    ['start']
                );
                $tmp_datas = [
                    'LABEL' => $label,
                    'URL' => $url,
                ];
            }
            if ($nb_images > 0) {
                $tmp_datas['NB_IMAGES'] = $nb_images;
            }
            $nav_bar_datas[] = $tmp_datas;

        }

        if ($conf['calendar_show_any'] and $show_any and count($items) > 1 and
              count($date_components) < count($this->calendar_levels) - 1) {
            $url = duplicate_index_url(
                [
                    'chronology_date' => array_merge($date_components, ['any']),
                ],
                ['start']
            );
            $nav_bar_datas[] = [
                'LABEL' => l10n('All'),
                'URL' => $url,
            ];
        }

        return $nav_bar_datas;
    }

    /**
     * Creates a calendar navigation bar for a given level.
     *
     * @param int $level - 0-year, 1-month/week, 2-day
     */
    protected function build_nav_bar($level, $labels = null)
    {
        global $template, $conf, $page;

        $query = <<<SQL
            SELECT DISTINCT({$this->calendar_levels[$level]['sql']}) AS period, COUNT(DISTINCT id) AS nb_images
            {$this->inner_sql}
            {$this->get_date_where($level)}
            GROUP BY period;
            SQL;

        $level_items = query2array($query, 'period', 'nb_images');

        if (count($level_items) == 1 and
             count($page['chronology_date']) < count($this->calendar_levels) - 1) {
            if (! isset($page['chronology_date'][$level])) {
                list($key) = array_keys($level_items);
                $page['chronology_date'][$level] = (int) $key;

                if ($level < count($page['chronology_date']) and
                     $level != count($this->calendar_levels) - 1) {
                    return;
                }
            }
        }

        $dates = $page['chronology_date'];
        while ($level < count($dates)) {
            array_pop($dates);
        }

        $nav_bar = $this->get_nav_bar_from_items(
            $dates,
            $level_items,
            true,
            true,
            isset($labels) ? $labels : $this->calendar_levels[$level]['labels']
        );

        $template->append(
            'chronology_navigation_bars',
            [
                'items' => $nav_bar,
            ]
        );
    }

    /**
     * Assigns the next/previous link to the template in regard to
     * the currently chosen date.
     */
    protected function build_next_prev()
    {
        global $template, $page;

        $prev = $next = null;
        if (empty($page['chronology_date'])) {
            return;
        }

        $sub_queries = [];
        $nb_elements = count($page['chronology_date']);
        for ($i = 0; $i < $nb_elements; $i++) {
            if ($page['chronology_date'][$i] === 'any') {
                $sub_queries[] = '\'any\'';
            } else {
                $sub_queries[] = pwg_db_cast_to_text($this->calendar_levels[$i]['sql']);
            }
        }
        $period = pwg_db_concat_ws($sub_queries, '-');
        $query = <<<SQL
            SELECT {$period} AS period
            {$this->inner_sql}
                AND {$this->date_field} IS NOT NULL
            GROUP BY period;
            SQL;

        $current = implode('-', $page['chronology_date']);
        $upper_items = query2array($query, null, 'period');

        usort($upper_items, 'version_compare');
        $upper_items_rank = array_flip($upper_items);
        if (! isset($upper_items_rank[$current])) {
            $upper_items[] = $current; // just in case (external link)
            usort($upper_items, 'version_compare');
            $upper_items_rank = array_flip($upper_items);
        }
        $current_rank = $upper_items_rank[$current];

        $tpl_var = [];

        if ($current_rank > 0) { // has previous
            $prev = $upper_items[$current_rank - 1];
            $chronology_date = explode('-', $prev);
            $tpl_var['previous'] =
              [
                  'LABEL' => $this->get_date_nice_name($prev),
                  'URL' => duplicate_index_url(
                      [
                          'chronology_date' => $chronology_date,
                      ],
                      ['start']
                  ),
              ];
        }

        if ($current_rank < count($upper_items) - 1) { // has next
            $next = $upper_items[$current_rank + 1];
            $chronology_date = explode('-', $next);
            $tpl_var['next'] =
              [
                  'LABEL' => $this->get_date_nice_name($next),
                  'URL' => duplicate_index_url(
                      [
                          'chronology_date' => $chronology_date,
                      ],
                      ['start']
                  ),
              ];
        }

        if (! empty($tpl_var)) {
            $existing = $template->smarty->getTemplateVars('chronology_navigation_bars');
            if (! empty($existing)) {
                $existing[sizeof($existing) - 1] = array_merge($existing[sizeof($existing) - 1], $tpl_var);
                $template->assign('chronology_navigation_bars', $existing);
            } else {
                $template->append('chronology_navigation_bars', $tpl_var);
            }
        }
    }
}
