<?php
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

use Piwigo\admin\inc\functions_history;
use Piwigo\inc\functions;
use Piwigo\inc\functions_url;
use Piwigo\inc\functions_user;

if (!defined('PHPWG_ROOT_PATH'))
{
  die ('Hacking attempt!');
}

include_once(PHPWG_ROOT_PATH.'admin/inc/functions.php');
include_once(PHPWG_ROOT_PATH.'admin/inc/functions_history.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

functions_user::check_status(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// | Refresh summary from details                                          |
// +-----------------------------------------------------------------------+

functions_history::history_summarize();

// +-----------------------------------------------------------------------+
// | Display statistics header                                             |                                                                                            
// +-----------------------------------------------------------------------+

$template->set_filename('stats', 'stats.tpl');

// TabSheet initialization
functions_history::history_tabsheet();

$base_url = functions_url::get_root_url().'admin.php?page=history';

$template->assign(
  array(
    'U_HELP' => functions_url::get_root_url().'admin/popuphelp.php?page=history',
    'F_ACTION' => $base_url,
    )
  );

// +-----------------------------------------------------------------------+
// | Send data to template                                                 |
// +-----------------------------------------------------------------------+

$actual_date = new DateTime();
$actual_date->add(new DateInterval('PT1S'));

$first_date = new DateTime();
$last_hours = functions::set_missing_values(
  'hour',
  functions::get_last(72, 'hour'), 
  $first_date->sub(new DateInterval("P3D")),
  $actual_date
);

$first_date = new DateTime();
$last_days = functions::set_missing_values(
  'day',
  functions::get_last(90, 'day'), 
  $first_date->sub(new DateInterval("P90D")),
  $actual_date
);

$first_date = new DateTime();
$last_months = functions::set_missing_values(
  'month',
  functions::get_last(60, 'month'), 
  $first_date->sub(new DateInterval("P60M")),
  $actual_date
);

if (count(functions::get_last(60, 'year')) > 1 ) 
{
  $last_years = functions::set_missing_values(
    'year',
    functions::get_last(60, 'year')
  );
} else {
  $last_year_date = new DateTime();
  $last_years = functions::set_missing_values(
    'year', 
    functions::get_last(60, 'year'),
    $last_year_date->sub(new DateInterval('P1Y')),
    new DateTime()
  );
}

ksort($lang['month']);

$template->assign(array(
  'compareYears' => functions::get_month_of_last_years($conf['stat_compare_year_displayed']),
  'monthStats' => functions::get_month_stats(),
  'lastHours' => $last_hours,
  'lastDays' => $last_days,
  'lastMonths' => $last_months,
  'lastYears' => $last_years,
  'langCode' => strval($user['language']),
  'month_labels' => join('~', $lang['month']),
  'ADMIN_PAGE_TITLE' => functions::l10n('History'),
));

$template->assign_var_from_handle('ADMIN_CONTENT', 'stats');
?>