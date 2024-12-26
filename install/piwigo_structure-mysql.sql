--
-- Table structure for table `piwigo_activity`
--
DROP TABLE IF EXISTS `piwigo_activity`;

CREATE TABLE `piwigo_activity` (
  `activity_id` INT (11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `object` VARCHAR(255) NOT NULL,
  `object_id` INT (11) UNSIGNED NOT NULL,
  `action` VARCHAR(255) NOT NULL,
  `performed_by` mediumint (8) UNSIGNED NOT NULL,
  `session_idx` VARCHAR(255) NOT NULL,
  `ip_address` VARCHAR(50) DEFAULT NULL,
  `occured_on` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `details` VARCHAR(255) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`activity_id`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_caddie`
--
DROP TABLE IF EXISTS `piwigo_caddie`;

CREATE TABLE `piwigo_caddie` (
  `user_id` mediumint (8) UNSIGNED NOT NULL DEFAULT '0',
  `element_id` mediumint (8) NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`, `element_id`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_categories`
--
DROP TABLE IF EXISTS `piwigo_categories`;

CREATE TABLE `piwigo_categories` (
  `id` SMALLINT (5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL DEFAULT '',
  `id_uppercat` SMALLINT (5) UNSIGNED DEFAULT NULL,
  `comment` text,
  `dir` VARCHAR(255) DEFAULT NULL,
  `rank` SMALLINT (5) UNSIGNED DEFAULT NULL,
  `status` enum ('public', 'private') NOT NULL DEFAULT 'public',
  `site_id` tinyint (4) UNSIGNED DEFAULT NULL,
  `visible` enum ('true', 'false') NOT NULL DEFAULT 'true',
  `representative_picture_id` mediumint (8) UNSIGNED DEFAULT NULL,
  `uppercats` VARCHAR(255) NOT NULL DEFAULT '',
  `commentable` enum ('true', 'false') NOT NULL DEFAULT 'true',
  `global_rank` VARCHAR(255) DEFAULT NULL,
  `image_order` VARCHAR(128) DEFAULT NULL,
  `permalink` VARCHAR(64) BINARY DEFAULT NULL,
  `lastmodified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_i3` (`permalink`),
  KEY `categories_i2` (`id_uppercat`),
  KEY `lastmodified` (`lastmodified`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_comments`
--
DROP TABLE IF EXISTS `piwigo_comments`;

CREATE TABLE `piwigo_comments` (
  `id` INT (11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `image_id` mediumint (8) UNSIGNED NOT NULL DEFAULT '0',
  `date` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `author` VARCHAR(255) DEFAULT NULL,
  `email` VARCHAR(255) DEFAULT NULL,
  `author_id` mediumint (8) UNSIGNED DEFAULT NULL,
  `anonymous_id` VARCHAR(45) NOT NULL,
  `website_url` VARCHAR(255) DEFAULT NULL,
  `content` longtext,
  `validated` enum ('true', 'false') NOT NULL DEFAULT 'false',
  `validation_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `comments_i2` (`validation_date`),
  KEY `comments_i1` (`image_id`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_config`
--
DROP TABLE IF EXISTS `piwigo_config`;

CREATE TABLE `piwigo_config` (
  `param` VARCHAR(40) NOT NULL DEFAULT '',
  `value` text,
  `comment` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`param`)
) ENGINE = MYISAM COMMENT = 'configuration table';

--
-- Table structure for table `piwigo_favorites`
--
DROP TABLE IF EXISTS `piwigo_favorites`;

CREATE TABLE `piwigo_favorites` (
  `user_id` mediumint (8) UNSIGNED NOT NULL DEFAULT '0',
  `image_id` mediumint (8) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`, `image_id`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_group_access`
--
DROP TABLE IF EXISTS `piwigo_group_access`;

CREATE TABLE `piwigo_group_access` (
  `group_id` SMALLINT (5) UNSIGNED NOT NULL DEFAULT '0',
  `cat_id` SMALLINT (5) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`group_id`, `cat_id`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_groups`
--
DROP TABLE IF EXISTS `piwigo_groups`;

CREATE TABLE `piwigo_groups` (
  `id` SMALLINT (5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL DEFAULT '',
  `is_default` enum ('true', 'false') NOT NULL DEFAULT 'false',
  `lastmodified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `groups_ui1` (`name`),
  KEY `lastmodified` (`lastmodified`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_history`
--
DROP TABLE IF EXISTS `piwigo_history`;

CREATE TABLE `piwigo_history` (
  `id` INT (10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `date` DATE NOT NULL DEFAULT '1970-01-01',
  `time` TIME NOT NULL DEFAULT '00:00:00',
  `user_id` mediumint (8) UNSIGNED NOT NULL DEFAULT '0',
  `IP` VARCHAR(15) NOT NULL DEFAULT '',
  `section` enum (
    'categories',
    'tags',
    'search',
    'list',
    'favorites',
    'most_visited',
    'best_rated',
    'recent_pics',
    'recent_cats'
  ) DEFAULT NULL,
  `category_id` SMALLINT (5) DEFAULT NULL,
  `search_id` INT (10) UNSIGNED DEFAULT NULL,
  `tag_ids` VARCHAR(50) DEFAULT NULL,
  `image_id` mediumint (8) DEFAULT NULL,
  `image_type` enum ('picture', 'high', 'other') DEFAULT NULL,
  `format_id` INT (11) UNSIGNED DEFAULT NULL,
  `auth_key_id` INT (11) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_history_summary`
--
DROP TABLE IF EXISTS `piwigo_history_summary`;

CREATE TABLE `piwigo_history_summary` (
  `year` SMALLINT (4) NOT NULL DEFAULT '0',
  `month` tinyint (2) DEFAULT NULL,
  `day` tinyint (2) DEFAULT NULL,
  `hour` tinyint (2) DEFAULT NULL,
  `nb_pages` INT (11) DEFAULT NULL,
  `history_id_from` INT (10) UNSIGNED DEFAULT NULL,
  `history_id_to` INT (10) UNSIGNED DEFAULT NULL,
  UNIQUE KEY history_summary_ymdh (`year`, `month`, `day`, `hour`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_image_category`
--
DROP TABLE IF EXISTS `piwigo_image_category`;

CREATE TABLE `piwigo_image_category` (
  `image_id` mediumint (8) UNSIGNED NOT NULL DEFAULT '0',
  `category_id` SMALLINT (5) UNSIGNED NOT NULL DEFAULT '0',
  `rank` mediumint (8) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`image_id`, `category_id`),
  KEY `image_category_i1` (`category_id`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_image_format`
--
DROP TABLE IF EXISTS `piwigo_image_format`;

CREATE TABLE `piwigo_image_format` (
  `format_id` INT (11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `image_id` mediumint (8) UNSIGNED NOT NULL DEFAULT '0',
  `ext` VARCHAR(255) NOT NULL,
  `filesize` mediumint (9) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`format_id`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_image_tag`
--
DROP TABLE IF EXISTS `piwigo_image_tag`;

CREATE TABLE `piwigo_image_tag` (
  `image_id` mediumint (8) UNSIGNED NOT NULL DEFAULT '0',
  `tag_id` SMALLINT (5) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`image_id`, `tag_id`),
  KEY `image_tag_i1` (`tag_id`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_images`
--
DROP TABLE IF EXISTS `piwigo_images`;

CREATE TABLE `piwigo_images` (
  `id` mediumint (8) UNSIGNED NOT NULL AUTO_INCREMENT,
  `file` VARCHAR(255) BINARY NOT NULL DEFAULT '',
  `date_available` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `date_creation` datetime DEFAULT NULL,
  `name` VARCHAR(255) DEFAULT NULL,
  `comment` text,
  `author` VARCHAR(255) DEFAULT NULL,
  `hit` INT (10) UNSIGNED NOT NULL DEFAULT '0',
  `filesize` mediumint (9) UNSIGNED DEFAULT NULL,
  `width` SMALLINT (9) UNSIGNED DEFAULT NULL,
  `height` SMALLINT (9) UNSIGNED DEFAULT NULL,
  `coi` CHAR(4) DEFAULT NULL COMMENT 'center of interest',
  `representative_ext` VARCHAR(4) DEFAULT NULL,
  `date_metadata_update` DATE DEFAULT NULL,
  `rating_score` FLOAT (5, 2) UNSIGNED DEFAULT NULL,
  `path` VARCHAR(255) NOT NULL DEFAULT '',
  `storage_category_id` SMALLINT (5) UNSIGNED DEFAULT NULL,
  `level` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `md5sum` CHAR(32) DEFAULT NULL,
  `added_by` mediumint (8) UNSIGNED NOT NULL DEFAULT '0',
  `rotation` tinyint UNSIGNED DEFAULT NULL,
  `latitude` DOUBLE (8, 6) DEFAULT NULL,
  `longitude` DOUBLE (9, 6) DEFAULT NULL,
  `lastmodified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `images_i1` (`storage_category_id`),
  KEY `images_i2` (`date_available`),
  KEY `images_i3` (`rating_score`),
  KEY `images_i4` (`hit`),
  KEY `images_i5` (`date_creation`),
  KEY `images_i6` (`latitude`),
  KEY `images_i7` (`path`),
  KEY `lastmodified` (`lastmodified`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_languages`
--
DROP TABLE IF EXISTS `piwigo_languages`;

CREATE TABLE `piwigo_languages` (
  `id` VARCHAR(64) NOT NULL DEFAULT '',
  `version` VARCHAR(64) NOT NULL DEFAULT '0',
  `name` VARCHAR(64) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_lounge`
--
DROP TABLE IF EXISTS `piwigo_lounge`;

CREATE TABLE `piwigo_lounge` (
  `image_id` mediumint (8) UNSIGNED NOT NULL DEFAULT '0',
  `category_id` SMALLINT (5) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`image_id`, `category_id`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_old_permalinks`
--
DROP TABLE IF EXISTS `piwigo_old_permalinks`;

CREATE TABLE `piwigo_old_permalinks` (
  `cat_id` SMALLINT (5) UNSIGNED NOT NULL DEFAULT '0',
  `permalink` VARCHAR(64) BINARY NOT NULL DEFAULT '',
  `date_deleted` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `last_hit` datetime DEFAULT NULL,
  `hit` INT (10) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`permalink`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_plugins`
--
DROP TABLE IF EXISTS `piwigo_plugins`;

CREATE TABLE `piwigo_plugins` (
  `id` VARCHAR(64) BINARY NOT NULL DEFAULT '',
  `state` enum ('inactive', 'active') NOT NULL DEFAULT 'inactive',
  `version` VARCHAR(64) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_rate`
--
DROP TABLE IF EXISTS `piwigo_rate`;

CREATE TABLE `piwigo_rate` (
  `user_id` mediumint (8) UNSIGNED NOT NULL DEFAULT '0',
  `element_id` mediumint (8) UNSIGNED NOT NULL DEFAULT '0',
  `anonymous_id` VARCHAR(45) NOT NULL DEFAULT '',
  `rate` tinyint (2) UNSIGNED NOT NULL DEFAULT '0',
  `date` DATE NOT NULL DEFAULT '1970-01-01',
  PRIMARY KEY (`element_id`, `user_id`, `anonymous_id`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_search`
--
DROP TABLE IF EXISTS `piwigo_search`;

CREATE TABLE `piwigo_search` (
  `id` INT (10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `search_uuid` CHAR(23) DEFAULT NULL,
  `created_on` DATETIME DEFAULT NULL,
  `created_by` MEDIUMINT (8) UNSIGNED,
  `forked_from` INT (10) UNSIGNED,
  `rules` text,
  PRIMARY KEY (`id`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_sessions`
--
DROP TABLE IF EXISTS `piwigo_sessions`;

CREATE TABLE `piwigo_sessions` (
  `id` VARCHAR(255) BINARY NOT NULL DEFAULT '',
  `data` mediumtext NOT NULL,
  `expiration` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_sites`
--
DROP TABLE IF EXISTS `piwigo_sites`;

CREATE TABLE `piwigo_sites` (
  `id` tinyint (4) NOT NULL AUTO_INCREMENT,
  `galleries_url` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `sites_ui1` (`galleries_url`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_tags`
--
DROP TABLE IF EXISTS `piwigo_tags`;

CREATE TABLE `piwigo_tags` (
  `id` SMALLINT (5) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL DEFAULT '',
  `url_name` VARCHAR(255) BINARY NOT NULL DEFAULT '',
  `lastmodified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tags_i1` (`url_name`),
  KEY `lastmodified` (`lastmodified`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_themes`
--
DROP TABLE IF EXISTS `piwigo_themes`;

CREATE TABLE `piwigo_themes` (
  `id` VARCHAR(64) NOT NULL DEFAULT '',
  `version` VARCHAR(64) NOT NULL DEFAULT '0',
  `name` VARCHAR(64) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_upgrade`
--
DROP TABLE IF EXISTS `piwigo_upgrade`;

CREATE TABLE `piwigo_upgrade` (
  `id` VARCHAR(20) NOT NULL DEFAULT '',
  `applied` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `description` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_user_access`
--
DROP TABLE IF EXISTS `piwigo_user_access`;

CREATE TABLE `piwigo_user_access` (
  `user_id` mediumint (8) UNSIGNED NOT NULL DEFAULT '0',
  `cat_id` SMALLINT (5) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`, `cat_id`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_user_auth_keys`
--
DROP TABLE IF EXISTS `piwigo_user_auth_keys`;

CREATE TABLE `piwigo_user_auth_keys` (
  `auth_key_id` INT (11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `auth_key` VARCHAR(255) NOT NULL,
  `user_id` mediumint (8) UNSIGNED NOT NULL,
  `created_on` datetime NOT NULL,
  `duration` INT (11) UNSIGNED DEFAULT NULL,
  `expired_on` datetime NOT NULL,
  PRIMARY KEY (`auth_key_id`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_user_cache`
--
DROP TABLE IF EXISTS `piwigo_user_cache`;

CREATE TABLE `piwigo_user_cache` (
  `user_id` mediumint (8) UNSIGNED NOT NULL DEFAULT '0',
  `need_update` enum ('true', 'false') NOT NULL DEFAULT 'true',
  `cache_update_time` INT UNSIGNED NOT NULL DEFAULT 0,
  `forbidden_categories` mediumtext,
  `nb_total_images` mediumint (8) UNSIGNED DEFAULT NULL,
  `last_photo_date` datetime DEFAULT NULL,
  `nb_available_tags` INT (5) DEFAULT NULL,
  `nb_available_comments` INT (5) DEFAULT NULL,
  `image_access_type` enum ('NOT IN', 'IN') NOT NULL DEFAULT 'NOT IN',
  `image_access_list` mediumtext DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_user_cache_categories`
--
DROP TABLE IF EXISTS `piwigo_user_cache_categories`;

CREATE TABLE `piwigo_user_cache_categories` (
  `user_id` mediumint (8) UNSIGNED NOT NULL DEFAULT '0',
  `cat_id` SMALLINT (5) UNSIGNED NOT NULL DEFAULT '0',
  `date_last` datetime DEFAULT NULL,
  `max_date_last` datetime DEFAULT NULL,
  `nb_images` mediumint (8) UNSIGNED NOT NULL DEFAULT '0',
  `count_images` mediumint (8) UNSIGNED DEFAULT '0',
  `nb_categories` mediumint (8) UNSIGNED DEFAULT '0',
  `count_categories` mediumint (8) UNSIGNED DEFAULT '0',
  `user_representative_picture_id` mediumint (8) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`user_id`, `cat_id`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_user_feed`
--
DROP TABLE IF EXISTS `piwigo_user_feed`;

CREATE TABLE `piwigo_user_feed` (
  `id` VARCHAR(50) BINARY NOT NULL DEFAULT '',
  `user_id` mediumint (8) UNSIGNED NOT NULL DEFAULT '0',
  `last_check` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_user_group`
--
DROP TABLE IF EXISTS `piwigo_user_group`;

CREATE TABLE `piwigo_user_group` (
  `user_id` mediumint (8) UNSIGNED NOT NULL DEFAULT '0',
  `group_id` SMALLINT (5) UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`group_id`, `user_id`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_user_infos`
--
DROP TABLE IF EXISTS `piwigo_user_infos`;

CREATE TABLE `piwigo_user_infos` (
  `user_id` mediumint (8) UNSIGNED NOT NULL DEFAULT '0',
  `nb_image_page` SMALLINT (3) UNSIGNED NOT NULL DEFAULT '15',
  `status` enum (
    'webmaster',
    'admin',
    'normal',
    'generic',
    'guest'
  ) NOT NULL DEFAULT 'guest',
  `language` VARCHAR(50) NOT NULL DEFAULT 'en_UK',
  `expand` enum ('true', 'false') NOT NULL DEFAULT 'false',
  `show_nb_comments` enum ('true', 'false') NOT NULL DEFAULT 'false',
  `show_nb_hits` enum ('true', 'false') NOT NULL DEFAULT 'false',
  `recent_period` tinyint (3) UNSIGNED NOT NULL DEFAULT '7',
  `theme` VARCHAR(255) NOT NULL DEFAULT 'modus',
  `registration_date` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `enabled_high` enum ('true', 'false') NOT NULL DEFAULT 'true',
  `level` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `activation_key` VARCHAR(255) DEFAULT NULL,
  `activation_key_expire` datetime DEFAULT NULL,
  `last_visit` datetime DEFAULT NULL,
  `last_visit_from_history` enum ('true', 'false') NOT NULL DEFAULT 'false',
  `lastmodified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `preferences` TEXT DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  KEY `lastmodified` (`lastmodified`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_user_mail_notification`
--
DROP TABLE IF EXISTS `piwigo_user_mail_notification`;

CREATE TABLE `piwigo_user_mail_notification` (
  `user_id` mediumint (8) UNSIGNED NOT NULL DEFAULT '0',
  `check_key` VARCHAR(16) BINARY NOT NULL DEFAULT '',
  `enabled` enum ('true', 'false') NOT NULL DEFAULT 'false',
  `last_send` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `user_mail_notification_ui1` (`check_key`)
) ENGINE = MYISAM;

--
-- Table structure for table `piwigo_users`
--
DROP TABLE IF EXISTS `piwigo_users`;

CREATE TABLE `piwigo_users` (
  `id` mediumint (8) UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(100) BINARY NOT NULL DEFAULT '',
  `password` VARCHAR(255) DEFAULT NULL,
  `mail_address` VARCHAR(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_ui1` (`username`)
) ENGINE = MYISAM;
