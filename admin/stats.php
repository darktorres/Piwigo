<?php

declare(strict_types=1);

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

if (! defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

require_once PHPWG_ROOT_PATH . 'admin/include/functions.php';
require_once PHPWG_ROOT_PATH . 'admin/include/functions_history.inc.php';

// +-----------------------------------------------------------------------+
// | Functions                                                             |
// +-----------------------------------------------------------------------+
//Get the last unit of time for years, months, days and hours
/**
 * @return mixed[]
 */
function get_last(
    int $last_number = 60,
    string $type = 'year'
): array {
    $query = <<<SQL
        SELECT year, month, day, hour, nb_pages
        FROM history_summary

        SQL;

    if ($type === 'hour') {
        $query .= <<<SQL
            WHERE year IS NOT NULL
                AND month IS NOT NULL
                AND day IS NOT NULL
                AND hour IS NOT NULL
            ORDER BY year DESC, month DESC, day DESC, hour DESC
            LIMIT {$last_number}

            SQL;
    } elseif ($type === 'day') {
        $query .= <<<SQL
            WHERE year IS NOT NULL
                AND month IS NOT NULL
                AND day IS NOT NULL
                AND hour IS NULL
            ORDER BY year DESC, month DESC, day DESC
            LIMIT {$last_number}

            SQL;
    } elseif ($type === 'month') {
        $query .= <<<SQL
            WHERE year IS NOT NULL
                AND month IS NOT NULL
                AND day IS NULL
            ORDER BY year DESC, month DESC
            LIMIT {$last_number}

            SQL;
    } else {
        $query .= <<<SQL
            WHERE year IS NOT NULL
                AND month IS NULL
            ORDER BY year DESC
            LIMIT {$last_number}

            SQL;
    }

    $query .= ';';
    $result = pwg_query($query);

    $output = [];
    while ($row = pwg_db_fetch_assoc($result)) {
        $output[] = $row;
    }

    return $output;
}

function get_month_of_last_years(
    string|int $last = 'all'
): array {

    $query = <<<SQL
        SELECT year, month, day, hour, nb_pages
        FROM history_summary
        WHERE month IS NOT NULL
            AND day IS NULL
        ORDER BY year DESC, month DESC

        SQL;

    if ($last !== 'all') {
        $date = new DateTime();
        $limit = ($last - 1) * 12 + $date->format('n') - 1;
        $query .= " LIMIT {$limit}\n";
        $result = query2array($query . ';');
        $lastDate = $date->sub(new DateInterval('P' . ($last - 1) . 'Y' . ($date->format('n') - 1) . 'M'));
        return set_missing_values('month', $result, $lastDate, new DateTime());
    }

    if (count(query2array($query . ';')) > 1) {
        return set_missing_values('month', query2array($query . ';'));
    }

    $last_year_date = new DateTime();
    return set_missing_values(
        'month',
        query2array($query . ';'),
        $last_year_date->sub(new DateInterval('P1Y')),
        new DateTime()
    );

}

function get_month_stats(): array
{
    $result = [];
    $date = new DateTime();
    $date_last_month = clone $date;
    $date_last_year = clone $date;
    $months = [];

    $date_last_month->sub(new DateInterval('P1M'));
    $date_last_year->sub(new DateInterval('P1Y'));
    $query = <<<SQL
        SELECT year, month, day, hour, nb_pages
        FROM history_summary
        WHERE
        (
            (year = {$date->format('Y')} AND month = {$date->format('n')})
            OR (year = {$date_last_month->format('Y')} AND month = {$date_last_month->format('n')})
            OR (year = {$date_last_year->format('Y')} AND month = {$date_last_year->format('n')})
        )
            AND day IS NOT NULL
            AND hour IS NULL
        ORDER BY year DESC, month DESC;
        SQL;

    foreach (query2array($query) as $value) {
        $date = get_date_object($value);
        $months[$date->format('Y/m/1')][] = $value;
    }

    $actual_date = new DateTime();
    if (! isset($months[$actual_date->format('Y/m/1')])) {
        $months[$actual_date->format('Y/m/1')][] = [
            'year' => $actual_date->format('Y'),
            'month' => $actual_date->format('n'),
            'day' => null,
            'hour' => null,
            'nb_pages' => 0,
        ];
    }

    foreach ($months as $key => $val) {
        $lastDate = new DateTime($key);
        $lastDate = $lastDate->add(new DateInterval('P1M'));
        $lastDate = $lastDate->sub(new DateInterval('P1D'));
        if ($lastDate > new DateTime()) {
            $lastDate = new DateTime();
        }

        $result['month'][] = set_missing_values('day', $val, new DateTime($key), $lastDate);
    }

    $query = <<<SQL
        SELECT AVG(nb_pages)
        FROM history_summary
        WHERE
        (
            year = {$date->format('Y')} OR
            (year = ({$date->format('Y')} - 1) AND month > {$date->format('n')})
        )
            AND day IS NOT NULL
            AND hour IS NULL
        ORDER BY year DESC, month DESC;
        SQL;

    [$result['avg']] = pwg_db_fetch_row(pwg_query($query));

    return $result;
}

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

check_status(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// | Refresh summary from details                                          |
// +-----------------------------------------------------------------------+

history_summarize();

// +-----------------------------------------------------------------------+
// | Display statistics header                                             |
// +-----------------------------------------------------------------------+

$template->set_filename('stats', 'stats.tpl');

// TabSheet initialization
history_tabsheet();

$base_url = get_root_url() . 'admin.php?page=history';

$template->assign(
    [
        'U_HELP' => get_root_url() . 'admin/popuphelp.php?page=history',
        'F_ACTION' => $base_url,
    ]
);

// +-----------------------------------------------------------------------+
// | Set missing rows to 0                                                 |
// +-----------------------------------------------------------------------+
/**
 * @return float[]|int[]
 */
function set_missing_values(
    string $unit,
    array $data,
    ?DateTime $firstDate = null,
    ?DateTime $lastDate = null
): array {
    $limit = count($data);
    $result = [];
    $date = $firstDate == null ? get_date_object($data[count($data) - 1]) : $firstDate;

    $date_end = $lastDate == null ? get_date_object($data[0]) : $lastDate;

    //Declare variable according the unit
    if ($unit === 'year') {
        $date_format = 'Y';
        $date_add = 'P1Y';
    } elseif ($unit === 'month') {
        $date_format = 'Y-m';
        $date_add = 'P1M';
    } elseif ($unit === 'day') {
        $date_format = 'Y-m-d';
        $date_add = 'P1D';
    } elseif ($unit === 'hour') {
        $date_format = 'Y-m-d\TH:00';
        $date_add = 'PT1H';
    }

    //Fill an empty array with all the dates
    while ($date <= $date_end) {
        $result[$date->format($date_format)] = 0;
        $date->add(new DateInterval($date_add));
    }

    //Overload with database rows
    foreach ($data as $value) {
        $str = get_date_object($value)
            ->format($date_format);
        if (isset($result[$str])) {
            $result[$str] += $value['nb_pages'];
        }
    }

    return $result;
}

//Get a DateTime object for a database row
function get_date_object(
    array $row
): \DateTime {
    $date_string = $row['year'];
    if ($row['month'] != null) {
        $date_string = $date_string . '-' . $row['month'];
        if ($row['day'] != null) {
            $date_string = $date_string . '-' . $row['day'];
            if ($row['hour'] != null) {
                $date_string = $date_string . ' ' . $row['hour'] . ':00';
            }
        }
    } else {
        $date_string .= '-1';
    }

    return new DateTime($date_string);
}

// +-----------------------------------------------------------------------+
// | Send data to template                                                 |
// +-----------------------------------------------------------------------+

$actual_date = new DateTime();
$actual_date->add(new DateInterval('PT1S'));

$first_date = new DateTime();
$last_hours = set_missing_values(
    'hour',
    get_last(72, 'hour'),
    $first_date->sub(new DateInterval('P3D')),
    $actual_date
);

$first_date = new DateTime();
$last_days = set_missing_values(
    'day',
    get_last(90, 'day'),
    $first_date->sub(new DateInterval('P90D')),
    $actual_date
);

$first_date = new DateTime();
$last_months = set_missing_values(
    'month',
    get_last(60, 'month'),
    $first_date->sub(new DateInterval('P60M')),
    $actual_date
);

if (count(get_last(60, 'year')) > 1) {
    $last_years = set_missing_values(
        'year',
        get_last(60, 'year')
    );
} else {
    $last_year_date = new DateTime();
    $last_years = set_missing_values(
        'year',
        get_last(60, 'year'),
        $last_year_date->sub(new DateInterval('P1Y')),
        new DateTime()
    );
}

ksort($lang['month']);

$template->assign([
    'compareYears' => get_month_of_last_years($conf['stat_compare_year_displayed']),
    'monthStats' => get_month_stats(),
    'lastHours' => $last_hours,
    'lastDays' => $last_days,
    'lastMonths' => $last_months,
    'lastYears' => $last_years,
    'langCode' => strval($user['language']),
    'month_labels' => implode('~', $lang['month']),
    'ADMIN_PAGE_TITLE' => l10n('History'),
]);

$template->assign_var_from_handle('ADMIN_CONTENT', 'stats');
