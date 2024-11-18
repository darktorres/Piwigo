<?php

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

namespace Piwigo\inc;

use Piwigo\inc\dblayer\functions_mysqli;

/**
 * Monthly calendar style (composed of years/months and days)
 */
class CalendarMonthly extends CalendarBase
{
    /**
     * Initialize the calendar.
     * @param string $inner_sql
     */
    public function initialize($inner_sql)
    {
        parent::initialize($inner_sql);
        global $lang;
        $this->calendar_levels = [
            [
                'sql' => functions_mysqli::pwg_db_get_year($this->date_field),
                'labels' => null,
            ],
            [
                'sql' => functions_mysqli::pwg_db_get_month($this->date_field),
                'labels' => $lang['month'],
            ],
            [
                'sql' => functions_mysqli::pwg_db_get_dayofmonth($this->date_field),
                'labels' => null,
            ],
        ];
    }

    /**
     * Generate navigation bars for category page.
     *
     * @return bool false indicates that thumbnails where not included
     */
    public function generate_category_content()
    {
        global $conf, $page;

        $view_type = $page['chronology_view'];
        if ($view_type == functions_calendar::CAL_VIEW_CALENDAR) {
            global $template;
            $tpl_var = [];
            if (count($page['chronology_date']) == 0) {//case A: no year given - display all years+months
                if ($this->build_global_calendar($tpl_var)) {
                    $template->assign('chronology_calendar', $tpl_var);
                    return true;
                }
            }

            if (count($page['chronology_date']) == 1) {//case B: year given - display all days in given year
                if ($this->build_year_calendar($tpl_var)) {
                    $template->assign('chronology_calendar', $tpl_var);
                    $this->build_nav_bar(CalendarBase::CYEAR); // years
                    return true;
                }
            }

            if (count($page['chronology_date']) == 2) {//case C: year+month given - display a nice month calendar
                if ($this->build_month_calendar($tpl_var)) {
                    $template->assign('chronology_calendar', $tpl_var);
                }

                $this->build_next_prev();
                return true;
            }
        }

        if ($view_type == functions_calendar::CAL_VIEW_LIST or count($page['chronology_date']) == 3) {
            if (count($page['chronology_date']) == 0) {
                $this->build_nav_bar(CalendarBase::CYEAR); // years
            }

            if (count($page['chronology_date']) == 1) {
                $this->build_nav_bar(CalendarBase::CMONTH); // month
            }

            if (count($page['chronology_date']) == 2) {
                $day_labels = range(1, $this->get_all_days_in_month(
                    $page['chronology_date'][CalendarBase::CYEAR],
                    $page['chronology_date'][CalendarBase::CMONTH]
                ));
                array_unshift($day_labels, 0);
                unset($day_labels[0]);
                $this->build_nav_bar(CalendarBase::CDAY, $day_labels); // days
            }

            $this->build_next_prev();
        }

        return false;
    }

    /**
     * Returns a sql WHERE subquery for the date field.
     *
     * @param int $max_levels (e.g. 2=only year and month)
     * @return string
     */
    public function get_date_where($max_levels = 3)
    {
        global $page;

        $date = $page['chronology_date'];
        while (count($date) > $max_levels) {
            array_pop($date);
        }

        $res = '';
        if (isset($date[CalendarBase::CYEAR]) and $date[CalendarBase::CYEAR] !== 'any') {
            $b = $date[CalendarBase::CYEAR] . '-';
            $e = $date[CalendarBase::CYEAR] . '-';
            if (isset($date[CalendarBase::CMONTH]) and $date[CalendarBase::CMONTH] !== 'any') {
                $b .= sprintf('%02d-', $date[CalendarBase::CMONTH]);
                $e .= sprintf('%02d-', $date[CalendarBase::CMONTH]);
                if (isset($date[CalendarBase::CDAY]) and $date[CalendarBase::CDAY] !== 'any') {
                    $b .= sprintf('%02d', $date[CalendarBase::CDAY]);
                    $e .= sprintf('%02d', $date[CalendarBase::CDAY]);
                } else {
                    $b .= '01';
                    $e .= $this->get_all_days_in_month($date[CalendarBase::CYEAR], $date[CalendarBase::CMONTH]);
                }
            } else {
                $b .= '01-01';
                $e .= '12-31';
                if (isset($date[CalendarBase::CMONTH]) and $date[CalendarBase::CMONTH] !== 'any') {
                    $res .= ' AND ' . $this->calendar_levels[CalendarBase::CMONTH]['sql'] . '=' . $date[CalendarBase::CMONTH];
                }

                if (isset($date[CalendarBase::CDAY]) and $date[CalendarBase::CDAY] !== 'any') {
                    $res .= ' AND ' . $this->calendar_levels[CalendarBase::CDAY]['sql'] . '=' . $date[CalendarBase::CDAY];
                }
            }

            $res = " AND {$this->date_field} BETWEEN '{$b}' AND '{$e} 23:59:59'" . $res;
        } else {
            $res = ' AND ' . $this->date_field . ' IS NOT NULL';
            if (isset($date[CalendarBase::CMONTH]) and $date[CalendarBase::CMONTH] !== 'any') {
                $res .= ' AND ' . $this->calendar_levels[CalendarBase::CMONTH]['sql'] . '=' . $date[CalendarBase::CMONTH];
            }

            if (isset($date[CalendarBase::CDAY]) and $date[CalendarBase::CDAY] !== 'any') {
                $res .= ' AND ' . $this->calendar_levels[CalendarBase::CDAY]['sql'] . '=' . $date[CalendarBase::CDAY];
            }
        }

        return $res;
    }

    /**
     * Returns an array with all the days in a given month.
     *
     * @param int $year
     * @param int $month
     * @return int
     */
    protected function get_all_days_in_month($year, $month)
    {
        $md = [
            1 => 31,
            28,
            31,
            30,
            31,
            30,
            31,
            31,
            30,
            31,
            30,
            31,
        ];

        if (is_numeric($year) and $month == 2) {
            $nb_days = $md[2];
            if (($year % 4 == 0) and (($year % 100 != 0) or ($year % 400 != 0))) {
                $nb_days++;
            }
        } elseif (is_numeric($month)) {
            $nb_days = $md[$month];
        } else {
            $nb_days = 31;
        }

        return $nb_days;
    }

    /**
     * Build global calendar and assign the result in _$tpl_var_
     *
     * @param array $tpl_var
     * @return bool
     */
    protected function build_global_calendar(&$tpl_var)
    {
        global $page;

        assert(count($page['chronology_date']) == 0);

        $period = functions_mysqli::pwg_db_get_date_YYYYMM($this->date_field);
        $year = functions_mysqli::pwg_db_get_year($this->date_field);
        $month = functions_mysqli::pwg_db_get_month($this->date_field);
        $query = <<<SQL
            SELECT {$period} AS period, COUNT(DISTINCT id) AS count
            {$this->inner_sql}
            {$this->get_date_where()}
            GROUP BY period
            ORDER BY {$year} DESC, {$month} ASC;
            SQL;

        $result = functions_mysqli::pwg_query($query);
        $items = [];
        while ($row = functions_mysqli::pwg_db_fetch_assoc($result)) {
            $y = substr($row['period'], 0, 4);
            $m = (int) substr($row['period'], 4, 2);
            if (! isset($items[$y])) {
                $items[$y] = [
                    'nb_images' => 0,
                    'children' => [],
                ];
            }

            $items[$y]['children'][$m] = $row['count'];
            $items[$y]['nb_images'] += $row['count'];
        }

        //echo ('<pre>'. var_export($items, true) . '</pre>');
        if (count($items) == 1) {// only one year exists so bail out to year view
            list($y) = array_keys($items);
            $page['chronology_date'][CalendarBase::CYEAR] = $y;
            return false;
        }

        global $lang;
        foreach ($items as $year => $year_data) {
            $chronology_date = [$year];
            $url = functions_url::duplicate_index_url([
                'chronology_date' => $chronology_date,
            ]);

            $nav_bar = $this->get_nav_bar_from_items(
                $chronology_date,
                $year_data['children'],
                false,
                false,
                $lang['month']
            );

            $tpl_var['calendar_bars'][] =
              [
                  'U_HEAD' => $url,
                  'NB_IMAGES' => $year_data['nb_images'],
                  'HEAD_LABEL' => $year,
                  'items' => $nav_bar,
              ];
        }

        return true;
    }

    /**
     * Build year calendar and assign the result in _$tpl_var_
     *
     * @param array $tpl_var
     * @return bool
     */
    protected function build_year_calendar(&$tpl_var)
    {
        global $page;

        assert(count($page['chronology_date']) == 1);

        $period = functions_mysqli::pwg_db_get_date_MMDD($this->date_field);
        $query = <<<SQL
            SELECT {$period} AS period, COUNT(DISTINCT id) AS count
            {$this->inner_sql}
            {$this->get_date_where()}
            GROUP BY period
            ORDER BY period ASC;
            SQL;

        $result = functions_mysqli::pwg_query($query);
        $items = [];
        while ($row = functions_mysqli::pwg_db_fetch_assoc($result)) {
            $m = (int) substr($row['period'], 0, 2);
            $d = substr($row['period'], 2, 2);
            if (! isset($items[$m])) {
                $items[$m] = [
                    'nb_images' => 0,
                    'children' => [],
                ];
            }

            $items[$m]['children'][$d] = $row['count'];
            $items[$m]['nb_images'] += $row['count'];
        }

        if (count($items) == 1) { // only one month exists so bail out to month view
            list($m) = array_keys($items);
            $page['chronology_date'][CalendarBase::CMONTH] = $m;
            return false;
        }

        global $lang;
        foreach ($items as $month => $month_data) {
            $chronology_date = [$page['chronology_date'][CalendarBase::CYEAR], $month];
            $url = functions_url::duplicate_index_url([
                'chronology_date' => $chronology_date,
            ]);

            $nav_bar = $this->get_nav_bar_from_items(
                $chronology_date,
                $month_data['children'],
                false
            );

            $tpl_var['calendar_bars'][] =
              [
                  'U_HEAD' => $url,
                  'NB_IMAGES' => $month_data['nb_images'],
                  'HEAD_LABEL' => $lang['month'][$month],
                  'items' => $nav_bar,
              ];
        }

        return true;
    }

    /**
     * Build month calendar and assign the result in _$tpl_var_
     *
     * @param array $tpl_var
     * @return bool
     */
    protected function build_month_calendar(&$tpl_var)
    {
        global $page, $lang, $conf;

        $period = functions_mysqli::pwg_db_get_dayofmonth($this->date_field);
        $query = <<<SQL
            SELECT {$period} AS period, COUNT(DISTINCT id) AS count
            {$this->inner_sql}
            {$this->get_date_where()}
            GROUP BY period
            ORDER BY period ASC;
            SQL;

        $items = [];
        $result = functions_mysqli::pwg_query($query);
        while ($row = functions_mysqli::pwg_db_fetch_assoc($result)) {
            $d = (int) $row['period'];
            $items[$d] = [
                'nb_images' => $row['count'],
            ];
        }

        foreach ($items as $day => $data) {
            $page['chronology_date'][CalendarBase::CDAY] = $day;
            $day_of_week = functions_mysqli::pwg_db_get_dayofweek($this->date_field);
            $random_function = functions_mysqli::DB_RANDOM_FUNCTION;
            $query = <<<SQL
                SELECT id, file, representative_ext, path, width, height, rotation, {$day_of_week} - 1 AS dow
                {$this->inner_sql}
                {$this->get_date_where()}
                ORDER BY {$random_function}()
                LIMIT 1;
                SQL;

            unset($page['chronology_date'][CalendarBase::CDAY]);

            $row = functions_mysqli::pwg_db_fetch_assoc(functions_mysqli::pwg_query($query));
            $derivative = new DerivativeImage(derivative_std_params::IMG_SQUARE, new SrcImage($row));
            $items[$day]['derivative'] = $derivative;
            $items[$day]['file'] = $row['file'];
            $items[$day]['dow'] = $row['dow'];
        }

        if (! empty($items)) {
            list($known_day) = array_keys($items);
            $known_dow = $items[$known_day]['dow'];
            $first_day_dow = ($known_dow - ($known_day - 1)) % 7;
            if ($first_day_dow < 0) {
                $first_day_dow += 7;
            }

            //first_day_dow = week day corresponding to the first day of this month
            $wday_labels = $lang['day'];

            if ($conf['week_starts_on'] == 'monday') {
                if ($first_day_dow == 0) {
                    $first_day_dow = 6;
                } else {
                    --$first_day_dow;
                }

                $wday_labels[] = array_shift($wday_labels);
            }

            list($cell_width, $cell_height) = ImageStdParams::get_by_type(derivative_std_params::IMG_SQUARE)->sizing->ideal_size;

            $tpl_weeks = [];
            $tpl_crt_week = [];

            //fill the empty days in the week before first day of this month
            for ($i = 0; $i < $first_day_dow; $i++) {
                $tpl_crt_week[] = [];
            }

            for ($day = 1;
                $day <= $this->get_all_days_in_month(
                    $page['chronology_date'][CalendarBase::CYEAR],
                    $page['chronology_date'][CalendarBase::CMONTH]
                );
                $day++) {
                $dow = ($first_day_dow + $day - 1) % 7;
                if ($dow == 0 and $day != 1) {
                    $tpl_weeks[] = $tpl_crt_week; // add finished week to week list
                    $tpl_crt_week = []; // start new week
                }

                if (! isset($items[$day])) {// empty day
                    $tpl_crt_week[] =
                      [
                          'DAY' => $day,
                      ];
                } else {
                    $url = functions_url::duplicate_index_url(
                        [
                            'chronology_date' =>
                              [
                                  $page['chronology_date'][CalendarBase::CYEAR],
                                  $page['chronology_date'][CalendarBase::CMONTH],
                                  $day,
                              ],
                        ]
                    );

                    $tpl_crt_week[] =
                      [
                          'DAY' => $day,
                          'DOW' => $dow,
                          'NB_ELEMENTS' => $items[$day]['nb_images'],
                          'IMAGE' => $items[$day]['derivative']->get_url(),
                          'U_IMG_LINK' => $url,
                          'IMAGE_ALT' => $items[$day]['file'],
                      ];
                }
            }

            //fill the empty days in the week after the last day of this month
            while ($dow < 6) {
                $tpl_crt_week[] = [];
                $dow++;
            }

            $tpl_weeks[] = $tpl_crt_week;

            $tpl_var['month_view'] =
                [
                    'CELL_WIDTH' => $cell_width,
                    'CELL_HEIGHT' => $cell_height,
                    'wday_labels' => $wday_labels,
                    'weeks' => $tpl_weeks,
                ];
        }

        return true;
    }
}
