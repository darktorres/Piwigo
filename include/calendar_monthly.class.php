<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

require_once PHPWG_ROOT_PATH . 'include/calendar_base.class.php';

/**
 * Monthly calendar style (composed of years/months and days)
 */
class CalendarMonthly extends CalendarBase
{
    /**
     * Initialize the calendar.
     */
    #[\Override]
    public function initialize(
        string $inner_sql
    ): void {
        parent::initialize($inner_sql);
        global $lang;
        $this->calendar_levels = [
            [
                'sql' => pwg_db_get_year($this->date_field),
                'labels' => null,
            ],
            [
                'sql' => pwg_db_get_month($this->date_field),
                'labels' => $lang['month'],
            ],
            [
                'sql' => pwg_db_get_dayofmonth($this->date_field),
                'labels' => null,
            ],
        ];
    }

    /**
     * Generate navigation bars for category page.
     *
     * @return boolean false indicates that thumbnails where not included
     */
    #[\Override]
    public function generate_category_content(): bool
    {
        global $conf, $page;

        $view_type = $page['chronology_view'];
        if ($view_type == CAL_VIEW_CALENDAR) {
            global $template;
            $tpl_var = [];
            //case A: no year given - display all years+months
            if (count($page['chronology_date']) == 0 && $this->build_global_calendar($tpl_var)) {
                $template->assign('chronology_calendar', $tpl_var);
                return true;
            }

            //case B: year given - display all days in given year
            if (count($page['chronology_date']) == 1 && $this->build_year_calendar($tpl_var)) {
                $template->assign('chronology_calendar', $tpl_var);
                $this->build_nav_bar(CYEAR);
                // years
                return true;
            }

            if (count($page['chronology_date']) == 2) {//case C: year+month given - display a nice month calendar
                if ($this->build_month_calendar($tpl_var)) {
                    $template->assign('chronology_calendar', $tpl_var);
                }

                $this->build_next_prev();
                return true;
            }
        }

        if ($view_type == CAL_VIEW_LIST || count($page['chronology_date']) == 3) {
            if (count($page['chronology_date']) == 0) {
                $this->build_nav_bar(CYEAR); // years
            }

            if (count($page['chronology_date']) == 1) {
                $this->build_nav_bar(CMONTH); // month
            }

            if (count($page['chronology_date']) == 2) {
                $day_labels = range(1, $this->get_all_days_in_month(
                    $page['chronology_date'][CYEAR],
                    $page['chronology_date'][CMONTH]
                ));
                array_unshift($day_labels, 0);
                unset($day_labels[0]);
                $this->build_nav_bar(CDAY, $day_labels); // days
            }

            $this->build_next_prev();
        }

        return false;
    }

    /**
     * Returns a sql WHERE subquery for the date field.
     *
     * @param int $max_levels (e.g. 2=only year and month)
     */
    #[\Override]
    public function get_date_where(
        int $max_levels = 3
    ): string {
        global $page;

        $date = $page['chronology_date'];
        while (count($date) > $max_levels) {
            array_pop($date);
        }

        $res = '';
        if (isset($date[CYEAR]) && $date[CYEAR] !== 'any') {
            $b = $date[CYEAR] . '-';
            $e = $date[CYEAR] . '-';
            if (isset($date[CMONTH]) && $date[CMONTH] !== 'any') {
                $b .= sprintf('%02d-', $date[CMONTH]);
                $e .= sprintf('%02d-', $date[CMONTH]);
                if (isset($date[CDAY]) && $date[CDAY] !== 'any') {
                    $b .= sprintf('%02d', $date[CDAY]);
                    $e .= sprintf('%02d', $date[CDAY]);
                } else {
                    $b .= '01';
                    $e .= $this->get_all_days_in_month($date[CYEAR], $date[CMONTH]);
                }
            } else {
                $b .= '01-01';
                $e .= '12-31';
                if (isset($date[CMONTH]) && $date[CMONTH] !== 'any') {
                    $res .= ' AND ' . $this->calendar_levels[CMONTH]['sql'] . '=' . $date[CMONTH];
                }

                if (isset($date[CDAY]) && $date[CDAY] !== 'any') {
                    $res .= ' AND ' . $this->calendar_levels[CDAY]['sql'] . '=' . $date[CDAY];
                }
            }

            $res = " AND {$this->date_field} BETWEEN '{$b}' AND '{$e} 23:59:59'" . $res;
        } else {
            $res = ' AND ' . $this->date_field . ' IS NOT NULL';
            if (isset($date[CMONTH]) && $date[CMONTH] !== 'any') {
                $res .= ' AND ' . $this->calendar_levels[CMONTH]['sql'] . '=' . $date[CMONTH];
            }

            if (isset($date[CDAY]) && $date[CDAY] !== 'any') {
                $res .= ' AND ' . $this->calendar_levels[CDAY]['sql'] . '=' . $date[CDAY];
            }
        }

        return $res;
    }

    /**
     * Returns an array with all the days in a given month.
     */
    protected function get_all_days_in_month(
        int $year,
        int $month
    ): int {
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

        if (is_numeric($year) && $month == 2) {
            $nb_days = $md[2];
            if ($year % 4 == 0 && ($year % 100 != 0 || $year % 400 != 0)) {
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
     */
    protected function build_global_calendar(
        array &$tpl_var
    ): bool {
        global $page;

        assert(count($page['chronology_date']) == 0);
        $year_ = pwg_db_get_year($this->date_field);
        $month_ = pwg_db_get_month($this->date_field);
        $query =
        "SELECT ' . pwg_db_get_date_YYYYMM({$this->date_field}) . ' AS period, COUNT(distinct id) AS count {$this->inner_sql} {$this->get_date_where()}
         GROUP BY period ORDER BY {$year_} DESC, {$month_} ASC;";
        $result = pwg_query($query);
        $items = [];
        while ($row = pwg_db_fetch_assoc($result)) {
            $y = substr((string) $row['period'], 0, 4);
            $m = (int) substr((string) $row['period'], 4, 2);
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
            [$y] = array_keys($items);
            $page['chronology_date'][CYEAR] = $y;
            return false;
        }

        global $lang;
        foreach ($items as $year => $year_data) {
            $chronology_date = [$year];
            $url = duplicate_index_url([
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
     */
    protected function build_year_calendar(
        array &$tpl_var
    ): bool {
        global $page;

        assert(count($page['chronology_date']) == 1);
        $date_ = pwg_db_get_date_MMDD($this->date_field);
        $query = "SELECT {$date_} AS period, COUNT(DISTINCT id) AS count {$this->inner_sql} {$this->get_date_where()} GROUP BY period ORDER BY period ASC;";
        $result = pwg_query($query);
        $items = [];
        while ($row = pwg_db_fetch_assoc($result)) {
            $m = (int) substr((string) $row['period'], 0, 2);
            $d = substr((string) $row['period'], 2, 2);
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
            [$m] = array_keys($items);
            $page['chronology_date'][CMONTH] = $m;
            return false;
        }

        global $lang;
        foreach ($items as $month => $month_data) {
            $chronology_date = [$page['chronology_date'][CYEAR], $month];
            $url = duplicate_index_url([
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
     */
    protected function build_month_calendar(
        array &$tpl_var
    ): bool {
        global $page, $lang, $conf;

        $dayofmonth_ = pwg_db_get_dayofmonth($this->date_field);
        $query = "SELECT {$dayofmonth_} AS period, COUNT(DISTINCT id) AS count {$this->inner_sql} {$this->get_date_where()} GROUP BY period ORDER BY period ASC;";
        $items = [];
        $result = pwg_query($query);
        while ($row = pwg_db_fetch_assoc($result)) {
            $d = (int) $row['period'];
            $items[$d] = [
                'nb_images' => $row['count'],
            ];
        }

        foreach (array_keys($items) as $day) {
            $page['chronology_date'][CDAY] = $day;
            $dayofweek_ = pwg_db_get_dayofweek($this->date_field);
            $query =
            "SELECT id, file, representative_ext, path, width, height, rotation, {$dayofweek_} - 1 AS dow {$this->inner_sql} {$this->get_date_where()}
             ORDER BY " . DB_RANDOM_FUNCTION . '() LIMIT 1;';
            unset($page['chronology_date'][CDAY]);

            $row = pwg_db_fetch_assoc(pwg_query($query));
            $derivative = new DerivativeImage(IMG_SQUARE, new SrcImage($row));
            $items[$day]['derivative'] = $derivative;
            $items[$day]['file'] = $row['file'];
            $items[$day]['dow'] = $row['dow'];
        }

        if ($items !== []) {
            [$known_day] = array_keys($items);
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

            [$cell_width, $cell_height] = ImageStdParams::get_by_type(IMG_SQUARE)->sizing->ideal_size;

            $tpl_weeks = [];
            $tpl_crt_week = [];

            //fill the empty days in the week before first day of this month
            for ($i = 0; $i < $first_day_dow; $i++) {
                $tpl_crt_week[] = [];
            }

            for ($day = 1;
                $day <= $this->get_all_days_in_month(
                    $page['chronology_date'][CYEAR],
                    $page['chronology_date'][CMONTH]
                );
                $day++) {
                $dow = ($first_day_dow + $day - 1) % 7;
                if ($dow == 0 && $day != 1) {
                    $tpl_weeks[] = $tpl_crt_week; // add finished week to week list
                    $tpl_crt_week = []; // start new week
                }

                if (! isset($items[$day])) {// empty day
                    $tpl_crt_week[] =
                      [
                          'DAY' => $day,
                      ];
                } else {
                    $url = duplicate_index_url(
                        [
                            'chronology_date' =>
                              [
                                  $page['chronology_date'][CYEAR],
                                  $page['chronology_date'][CMONTH],
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
