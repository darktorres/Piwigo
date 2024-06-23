<?php

declare(strict_types=1);

namespace Piwigo\inc;

// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// |                                                                       |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

// Default settings
const PHPWG_VERSION = '13.7.0';
const PHPWG_DEFAULT_LANGUAGE = 'en_UK';

// this constant is only used in the upgrade process, the true default theme
// is the theme of user "guest", which is initialized with column user_infos.theme
// default value (see file install/piwigo_structure-mysql.sql)
const PHPWG_DEFAULT_TEMPLATE = 'modus';

define('PHPWG_THEMES_PATH', $conf['themes_dir'] . '/');
defined('PWG_COMBINED_DIR') || define('PWG_COMBINED_DIR', $conf['data_location'] . 'combined/');
defined('PWG_DERIVATIVE_DIR') || define('PWG_DERIVATIVE_DIR', $conf['data_location'] . 'i/');

// Required versions
const REQUIRED_PHP_VERSION = '8.3.7';

// Access codes
const ACCESS_FREE = 0;
const ACCESS_GUEST = 1;
const ACCESS_CLASSIC = 2;
const ACCESS_ADMINISTRATOR = 3;
const ACCESS_WEBMASTER = 4;
const ACCESS_CLOSED = 5;

// System activities
const ACTIVITY_SYSTEM_CORE = 1;
const ACTIVITY_SYSTEM_PLUGIN = 2;
const ACTIVITY_SYSTEM_THEME = 3;

// Sanity checks
const PATTERN_ID = '/^\d+$/';
const PATTERN_ORDER = '/^(rand(om)?|[a-z_]+(\s+(asc|desc))?)(\s*,\s*(rand(om)?|[a-z_]+(\s+(asc|desc))?))*$/i';

// Table names
if (! defined('CATEGORIES_TABLE')) {
    define('CATEGORIES_TABLE', sprintf('`%scategories`', $prefixeTable));
}

if (! defined('COMMENTS_TABLE')) {
    define('COMMENTS_TABLE', sprintf('`%scomments`', $prefixeTable));
}

if (! defined('CONFIG_TABLE')) {
    define('CONFIG_TABLE', sprintf('`%sconfig`', $prefixeTable));
}

if (! defined('FAVORITES_TABLE')) {
    define('FAVORITES_TABLE', sprintf('`%sfavorites`', $prefixeTable));
}

if (! defined('GROUP_ACCESS_TABLE')) {
    define('GROUP_ACCESS_TABLE', sprintf('`%sgroup_access`', $prefixeTable));
}

if (! defined('GROUPS_TABLE')) {
    define('GROUPS_TABLE', sprintf('`%sgroups`', $prefixeTable));
}

if (! defined('HISTORY_TABLE')) {
    define('HISTORY_TABLE', sprintf('`%shistory`', $prefixeTable));
}

if (! defined('HISTORY_SUMMARY_TABLE')) {
    define('HISTORY_SUMMARY_TABLE', sprintf('`%shistory_summary`', $prefixeTable));
}

if (! defined('IMAGE_CATEGORY_TABLE')) {
    define('IMAGE_CATEGORY_TABLE', sprintf('`%simage_category`', $prefixeTable));
}

if (! defined('IMAGES_TABLE')) {
    define('IMAGES_TABLE', sprintf('`%simages`', $prefixeTable));
}

if (! defined('SESSIONS_TABLE')) {
    define('SESSIONS_TABLE', sprintf('`%ssessions`', $prefixeTable));
}

if (! defined('SITES_TABLE')) {
    define('SITES_TABLE', sprintf('`%ssites`', $prefixeTable));
}

if (! defined('USER_ACCESS_TABLE')) {
    define('USER_ACCESS_TABLE', sprintf('`%suser_access`', $prefixeTable));
}

if (! defined('USER_GROUP_TABLE')) {
    define('USER_GROUP_TABLE', sprintf('`%suser_group`', $prefixeTable));
}

if (! defined('USERS_TABLE')) {
    define(
        'USERS_TABLE',
        isset($conf['users_table']) ? sprintf('`%s`', $conf['users_table']) : sprintf('`%susers`', $prefixeTable)
    );
}

if (! defined('USER_INFOS_TABLE')) {
    define('USER_INFOS_TABLE', sprintf('`%suser_infos`', $prefixeTable));
}

if (! defined('USER_FEED_TABLE')) {
    define('USER_FEED_TABLE', sprintf('`%suser_feed`', $prefixeTable));
}

if (! defined('RATE_TABLE')) {
    define('RATE_TABLE', sprintf('`%srate`', $prefixeTable));
}

if (! defined('USER_AUTH_KEYS_TABLE')) {
    define('USER_AUTH_KEYS_TABLE', sprintf('`%suser_auth_keys`', $prefixeTable));
}

if (! defined('USER_CACHE_TABLE')) {
    define('USER_CACHE_TABLE', sprintf('`%suser_cache`', $prefixeTable));
}

if (! defined('USER_CACHE_CATEGORIES_TABLE')) {
    define('USER_CACHE_CATEGORIES_TABLE', sprintf('`%suser_cache_categories`', $prefixeTable));
}

if (! defined('CADDIE_TABLE')) {
    define('CADDIE_TABLE', sprintf('`%scaddie`', $prefixeTable));
}

if (! defined('UPGRADE_TABLE')) {
    define('UPGRADE_TABLE', sprintf('`%supgrade`', $prefixeTable));
}

if (! defined('SEARCH_TABLE')) {
    define('SEARCH_TABLE', sprintf('`%ssearch`', $prefixeTable));
}

if (! defined('USER_MAIL_NOTIFICATION_TABLE')) {
    define('USER_MAIL_NOTIFICATION_TABLE', sprintf('`%suser_mail_notification`', $prefixeTable));
}

if (! defined('TAGS_TABLE')) {
    define('TAGS_TABLE', sprintf('`%stags`', $prefixeTable));
}

if (! defined('IMAGE_TAG_TABLE')) {
    define('IMAGE_TAG_TABLE', sprintf('`%simage_tag`', $prefixeTable));
}

if (! defined('PLUGINS_TABLE')) {
    define('PLUGINS_TABLE', sprintf('`%splugins`', $prefixeTable));
}

if (! defined('OLD_PERMALINKS_TABLE')) {
    define('OLD_PERMALINKS_TABLE', sprintf('`%sold_permalinks`', $prefixeTable));
}

if (! defined('THEMES_TABLE')) {
    define('THEMES_TABLE', sprintf('`%sthemes`', $prefixeTable));
}

if (! defined('LANGUAGES_TABLE')) {
    define('LANGUAGES_TABLE', sprintf('`%slanguages`', $prefixeTable));
}

if (! defined('IMAGE_FORMAT_TABLE')) {
    define('IMAGE_FORMAT_TABLE', sprintf('`%simage_format`', $prefixeTable));
}

if (! defined('ACTIVITY_TABLE')) {
    define('ACTIVITY_TABLE', sprintf('`%sactivity`', $prefixeTable));
}

if (! defined('LOUNGE_TABLE')) {
    define('LOUNGE_TABLE', sprintf('`%slounge`', $prefixeTable));
}
