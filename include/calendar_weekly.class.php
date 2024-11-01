<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

include_once(PHPWG_ROOT_PATH . 'include/calendar_base.class.php');

/**
 * Weekly calendar style (composed of years/week in years and days in week)
 */
class CalendarWeekly extends CalendarBase
{
    /**
     * Initialize the calendar
     */
    #[\Override]
    public function initialize(
        string $inner_sql
    ): void {
        parent::initialize($inner_sql);
        global $lang, $conf;
        $week_no_labels = [];
        for ($i = 1; $i <= 53; $i++) {
            $week_no_labels[$i] = l10n('Week %d', $i);
            //$week_no_labels[$i] = $i;
        }

        $this->calendar_levels = [
            [
                'sql' => pwg_db_get_year($this->date_field),
                'labels' => null,
            ],
            [
                'sql' => pwg_db_get_week($this->date_field) . '+1',
                'labels' => $week_no_labels,
            ],
            [
                'sql' => pwg_db_get_dayofweek($this->date_field) . '-1',
                'labels' => $lang['day'],
            ],
        ];
        //Comment next lines for week starting on Sunday or if MySQL version<4.0.17
        //WEEK(date,5) = "0-53 - Week 1=the first week with a Monday in this year"
        if ($conf['week_starts_on'] == 'monday') {
            $this->calendar_levels[CWEEK]['sql'] = pwg_db_get_week($this->date_field, 5) . '+1';
            $this->calendar_levels[CDAY]['sql'] = pwg_db_get_weekday($this->date_field);
            $this->calendar_levels[CDAY]['labels'][] = array_shift($this->calendar_levels[CDAY]['labels']);
        }
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

        if (count($page['chronology_date']) == 0) {
            $this->build_nav_bar(CYEAR); // years
        }

        if (count($page['chronology_date']) == 1) {
            $this->build_nav_bar(CWEEK, []); // week nav bar 1-53
        }

        if (count($page['chronology_date']) == 2) {
            $this->build_nav_bar(CDAY); // days nav bar Mon-Sun
        }

        $this->build_next_prev();
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
            $y = $date[CYEAR];
            $res = " AND {$this->date_field} BETWEEN '{$y}-01-01' AND '{$y}-12-31 23:59:59'";
        }

        if (isset($date[CWEEK]) && $date[CWEEK] !== 'any') {
            $res .= ' AND ' . $this->calendar_levels[CWEEK]['sql'] . '=' . $date[CWEEK];
        }

        if (isset($date[CDAY]) && $date[CDAY] !== 'any') {
            $res .= ' AND ' . $this->calendar_levels[CDAY]['sql'] . '=' . $date[CDAY];
        }

        if ($res === '' || $res === '0') {
            $res = ' AND ' . $this->date_field . ' IS NOT NULL';
        }

        return $res;
    }
}
