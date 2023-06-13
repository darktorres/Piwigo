--
-- Table structure for table `piwigo_activity`
--
DROP TABLE IF EXISTS `piwigo_activity`;

CREATE TABLE `piwigo_activity` (
  `activity_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `object` varchar(255) NOT NULL,
  `object_id` int UNSIGNED NOT NULL,
  `action` varchar(255) NOT NULL,
  `performed_by` int UNSIGNED NOT NULL,
  `session_idx` varchar(255) NOT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `occured_on` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `details` varchar(255) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`activity_id`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_caddie`
--
DROP TABLE IF EXISTS `piwigo_caddie`;

CREATE TABLE `piwigo_caddie` (
  `user_id` int UNSIGNED NOT NULL DEFAULT '0',
  `element_id` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`, `element_id`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_categories`
--
DROP TABLE IF EXISTS `piwigo_categories`;

CREATE TABLE `piwigo_categories` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `id_uppercat` int UNSIGNED DEFAULT NULL,
  `comment` text,
  `dir` varchar(255) DEFAULT NULL,
  `rank` int UNSIGNED DEFAULT NULL,
  `status` enum('public', 'private') NOT NULL DEFAULT 'public',
  `site_id` int UNSIGNED DEFAULT NULL,
  `visible` enum('true', 'false') NOT NULL DEFAULT 'true',
  `representative_picture_id` int UNSIGNED DEFAULT NULL,
  `uppercats` varchar(255) NOT NULL DEFAULT '',
  `commentable` enum('true', 'false') NOT NULL DEFAULT 'true',
  `global_rank` varchar(255) DEFAULT NULL,
  `image_order` varchar(128) DEFAULT NULL,
  `permalink` varchar(64) DEFAULT NULL,
  `lastmodified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `categories_i3` (`permalink`),
  KEY `categories_i2` (`id_uppercat`),
  KEY `lastmodified` (`lastmodified`),
  FULLTEXT KEY `category_ft` (`name`, `comment`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_comments`
--
DROP TABLE IF EXISTS `piwigo_comments`;

CREATE TABLE `piwigo_comments` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `image_id` int UNSIGNED NOT NULL DEFAULT '0',
  `date` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `author` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `author_id` int UNSIGNED DEFAULT NULL,
  `anonymous_id` varchar(45) NOT NULL,
  `website_url` varchar(255) DEFAULT NULL,
  `content` longtext,
  `validated` enum('true', 'false') NOT NULL DEFAULT 'false',
  `validation_date` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `comments_i2` (`validation_date`),
  KEY `comments_i1` (`image_id`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_config`
--
DROP TABLE IF EXISTS `piwigo_config`;

CREATE TABLE `piwigo_config` (
  `param` varchar(40) NOT NULL DEFAULT '',
  `value` text,
  `comment` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`param`)
) ENGINE = InnoDB COMMENT = 'configuration table';

--
-- Table structure for table `piwigo_favorites`
--
DROP TABLE IF EXISTS `piwigo_favorites`;

CREATE TABLE `piwigo_favorites` (
  `user_id` int UNSIGNED NOT NULL DEFAULT '0',
  `image_id` int UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`, `image_id`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_group_access`
--
DROP TABLE IF EXISTS `piwigo_group_access`;

CREATE TABLE `piwigo_group_access` (
  `group_id` int UNSIGNED NOT NULL DEFAULT '0',
  `cat_id` int UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`group_id`, `cat_id`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_groups`
--
DROP TABLE IF EXISTS `piwigo_groups`;

CREATE TABLE `piwigo_groups` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `is_default` enum('true', 'false') NOT NULL DEFAULT 'false',
  `lastmodified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `groups_ui1` (`name`),
  KEY `lastmodified` (`lastmodified`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_history`
--
DROP TABLE IF EXISTS `piwigo_history`;

CREATE TABLE `piwigo_history` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `date` date NOT NULL DEFAULT '1970-01-01',
  `time` time NOT NULL DEFAULT '00:00:00',
  `user_id` int UNSIGNED NOT NULL DEFAULT '0',
  `IP` varchar(15) NOT NULL DEFAULT '',
  `section` enum(
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
  `category_id` int DEFAULT NULL,
  `tag_ids` varchar(50) DEFAULT NULL,
  `image_id` int DEFAULT NULL,
  `image_type` enum('picture', 'high', 'other') DEFAULT NULL,
  `format_id` int UNSIGNED DEFAULT NULL,
  `auth_key_id` int UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_history_summary`
--
DROP TABLE IF EXISTS `piwigo_history_summary`;

CREATE TABLE `piwigo_history_summary` (
  `year` int NOT NULL DEFAULT '0',
  `month` int DEFAULT NULL,
  `day` int DEFAULT NULL,
  `hour` int DEFAULT NULL,
  `nb_pages` int DEFAULT NULL,
  `history_id_from` int UNSIGNED DEFAULT NULL,
  `history_id_to` int UNSIGNED DEFAULT NULL,
  UNIQUE KEY history_summary_ymdh (`year`, `month`, `day`, `hour`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_image_category`
--
DROP TABLE IF EXISTS `piwigo_image_category`;

CREATE TABLE `piwigo_image_category` (
  `image_id` int UNSIGNED NOT NULL DEFAULT '0',
  `category_id` int UNSIGNED NOT NULL DEFAULT '0',
  `rank` int UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`image_id`, `category_id`),
  KEY `image_category_i1` (`category_id`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_image_format`
--
DROP TABLE IF EXISTS `piwigo_image_format`;

CREATE TABLE `piwigo_image_format` (
  `format_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `image_id` int UNSIGNED NOT NULL DEFAULT '0',
  `ext` varchar(255) NOT NULL,
  `filesize` int UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`format_id`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_image_tag`
--
DROP TABLE IF EXISTS `piwigo_image_tag`;

CREATE TABLE `piwigo_image_tag` (
  `image_id` int UNSIGNED NOT NULL DEFAULT '0',
  `tag_id` int UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`image_id`, `tag_id`),
  KEY `image_tag_i1` (`tag_id`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_images`
--
DROP TABLE IF EXISTS `piwigo_images`;

CREATE TABLE `piwigo_images` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `file` varchar(255) NOT NULL DEFAULT '',
  `date_available` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `date_creation` datetime DEFAULT NULL,
  `name` varchar(255) DEFAULT NULL,
  `comment` text,
  `author` varchar(255) DEFAULT NULL,
  `hit` int UNSIGNED NOT NULL DEFAULT '0',
  `filesize` int UNSIGNED DEFAULT NULL,
  `width` smallint(9) UNSIGNED DEFAULT NULL,
  `height` smallint(9) UNSIGNED DEFAULT NULL,
  `coi` char(4) DEFAULT NULL COMMENT 'center of interest',
  `representative_ext` varchar(4) DEFAULT NULL,
  `date_metadata_update` date DEFAULT NULL,
  `rating_score` float DEFAULT NULL,
  `path` varchar(600) NOT NULL DEFAULT '',
  `storage_category_id` int UNSIGNED DEFAULT NULL,
  `level` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `md5sum` char(32) DEFAULT NULL,
  `added_by` int UNSIGNED NOT NULL DEFAULT '0',
  `rotation` tinyint UNSIGNED DEFAULT NULL,
  `latitude` double DEFAULT NULL,
  `longitude` double DEFAULT NULL,
  `lastmodified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `images_i1` (`storage_category_id`),
  KEY `images_i2` (`date_available`),
  KEY `images_i3` (`rating_score`),
  KEY `images_i4` (`hit`),
  KEY `images_i5` (`date_creation`),
  KEY `images_i6` (`latitude`),
  KEY `images_i7` (`path`),
  KEY `lastmodified` (`lastmodified`),
  FULLTEXT KEY `image_ft` (`name`, `comment`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_languages`
--
DROP TABLE IF EXISTS `piwigo_languages`;

CREATE TABLE `piwigo_languages` (
  `id` varchar(64) NOT NULL DEFAULT '',
  `version` varchar(64) NOT NULL DEFAULT '0',
  `name` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_lounge`
--
DROP TABLE IF EXISTS `piwigo_lounge`;

CREATE TABLE `piwigo_lounge` (
  `image_id` int UNSIGNED NOT NULL DEFAULT '0',
  `category_id` int UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`image_id`, `category_id`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_old_permalinks`
--
DROP TABLE IF EXISTS `piwigo_old_permalinks`;

CREATE TABLE `piwigo_old_permalinks` (
  `cat_id` int UNSIGNED NOT NULL DEFAULT '0',
  `permalink` varchar(64) NOT NULL DEFAULT '',
  `date_deleted` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `last_hit` datetime DEFAULT NULL,
  `hit` int UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`permalink`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_plugins`
--
DROP TABLE IF EXISTS `piwigo_plugins`;

CREATE TABLE `piwigo_plugins` (
  `id` varchar(64) NOT NULL DEFAULT '',
  `state` enum('inactive', 'active') NOT NULL DEFAULT 'inactive',
  `version` varchar(64) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_rate`
--
DROP TABLE IF EXISTS `piwigo_rate`;

CREATE TABLE `piwigo_rate` (
  `user_id` int UNSIGNED NOT NULL DEFAULT '0',
  `element_id` int UNSIGNED NOT NULL DEFAULT '0',
  `anonymous_id` varchar(45) NOT NULL DEFAULT '',
  `rate` int UNSIGNED NOT NULL DEFAULT '0',
  `date` date NOT NULL DEFAULT '1970-01-01',
  PRIMARY KEY (`element_id`, `user_id`, `anonymous_id`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_search`
--
DROP TABLE IF EXISTS `piwigo_search`;

CREATE TABLE `piwigo_search` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `last_seen` date DEFAULT NULL,
  `rules` text,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_sessions`
--
DROP TABLE IF EXISTS `piwigo_sessions`;

CREATE TABLE `piwigo_sessions` (
  `id` varchar(255) NOT NULL DEFAULT '',
  `data` mediumtext NOT NULL,
  `expiration` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_sites`
--
DROP TABLE IF EXISTS `piwigo_sites`;

CREATE TABLE `piwigo_sites` (
  `id` int NOT NULL AUTO_INCREMENT,
  `galleries_url` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `sites_ui1` (`galleries_url`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_tags`
--
DROP TABLE IF EXISTS `piwigo_tags`;

CREATE TABLE `piwigo_tags` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `url_name` varchar(255) NOT NULL DEFAULT '',
  `lastmodified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `tags_i1` (`url_name`),
  KEY `lastmodified` (`lastmodified`),
  FULLTEXT KEY `tag_name_ft` (`name`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_themes`
--
DROP TABLE IF EXISTS `piwigo_themes`;

CREATE TABLE `piwigo_themes` (
  `id` varchar(64) NOT NULL DEFAULT '',
  `version` varchar(64) NOT NULL DEFAULT '0',
  `name` varchar(64) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_upgrade`
--
DROP TABLE IF EXISTS `piwigo_upgrade`;

CREATE TABLE `piwigo_upgrade` (
  `id` varchar(20) NOT NULL DEFAULT '',
  `applied` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_user_access`
--
DROP TABLE IF EXISTS `piwigo_user_access`;

CREATE TABLE `piwigo_user_access` (
  `user_id` int UNSIGNED NOT NULL DEFAULT '0',
  `cat_id` int UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`user_id`, `cat_id`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_user_auth_keys`
--
DROP TABLE IF EXISTS `piwigo_user_auth_keys`;

CREATE TABLE `piwigo_user_auth_keys` (
  `auth_key_id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `auth_key` varchar(255) NOT NULL,
  `user_id` int UNSIGNED NOT NULL,
  `created_on` datetime NOT NULL,
  `duration` int UNSIGNED DEFAULT NULL,
  `expired_on` datetime NOT NULL,
  PRIMARY KEY (`auth_key_id`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_user_cache`
--
DROP TABLE IF EXISTS `piwigo_user_cache`;

CREATE TABLE `piwigo_user_cache` (
  `user_id` int UNSIGNED NOT NULL DEFAULT '0',
  `need_update` enum('true', 'false') NOT NULL DEFAULT 'true',
  `cache_update_time` int UNSIGNED NOT NULL DEFAULT 0,
  `forbidden_categories` mediumtext,
  `nb_total_images` int UNSIGNED DEFAULT NULL,
  `last_photo_date` datetime DEFAULT NULL,
  `nb_available_tags` int DEFAULT NULL,
  `nb_available_comments` int DEFAULT NULL,
  `image_access_type` enum('NOT IN', 'IN') NOT NULL DEFAULT 'NOT IN',
  `image_access_list` mediumtext DEFAULT NULL,
  PRIMARY KEY (`user_id`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_user_cache_categories`
--
DROP TABLE IF EXISTS `piwigo_user_cache_categories`;

CREATE TABLE `piwigo_user_cache_categories` (
  `user_id` int UNSIGNED NOT NULL DEFAULT '0',
  `cat_id` int UNSIGNED NOT NULL DEFAULT '0',
  `date_last` datetime DEFAULT NULL,
  `max_date_last` datetime DEFAULT NULL,
  `nb_images` int UNSIGNED NOT NULL DEFAULT '0',
  `count_images` int UNSIGNED DEFAULT '0',
  `nb_categories` int UNSIGNED DEFAULT '0',
  `count_categories` int UNSIGNED DEFAULT '0',
  `user_representative_picture_id` int UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`user_id`, `cat_id`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_user_feed`
--
DROP TABLE IF EXISTS `piwigo_user_feed`;

CREATE TABLE `piwigo_user_feed` (
  `id` varchar(50) NOT NULL DEFAULT '',
  `user_id` int UNSIGNED NOT NULL DEFAULT '0',
  `last_check` datetime DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_user_group`
--
DROP TABLE IF EXISTS `piwigo_user_group`;

CREATE TABLE `piwigo_user_group` (
  `user_id` int UNSIGNED NOT NULL DEFAULT '0',
  `group_id` int UNSIGNED NOT NULL DEFAULT '0',
  PRIMARY KEY (`group_id`, `user_id`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_user_infos`
--
DROP TABLE IF EXISTS `piwigo_user_infos`;

CREATE TABLE `piwigo_user_infos` (
  `user_id` int UNSIGNED NOT NULL DEFAULT '0',
  `nb_image_page` int UNSIGNED NOT NULL DEFAULT '15',
  `status` enum(
    'webmaster',
    'admin',
    'normal',
    'generic',
    'guest'
  ) NOT NULL DEFAULT 'guest',
  `language` varchar(50) NOT NULL DEFAULT 'en_UK',
  `expand` enum('true', 'false') NOT NULL DEFAULT 'false',
  `show_nb_comments` enum('true', 'false') NOT NULL DEFAULT 'false',
  `show_nb_hits` enum('true', 'false') NOT NULL DEFAULT 'false',
  `recent_period` int UNSIGNED NOT NULL DEFAULT '7',
  `theme` varchar(255) NOT NULL DEFAULT 'modus',
  `registration_date` datetime NOT NULL DEFAULT '1970-01-01 00:00:00',
  `enabled_high` enum('true', 'false') NOT NULL DEFAULT 'true',
  `level` tinyint UNSIGNED NOT NULL DEFAULT '0',
  `activation_key` varchar(255) DEFAULT NULL,
  `activation_key_expire` datetime DEFAULT NULL,
  `last_visit` datetime DEFAULT NULL,
  `last_visit_from_history` enum('true', 'false') NOT NULL DEFAULT 'false',
  `lastmodified` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `preferences` TEXT DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  KEY `lastmodified` (`lastmodified`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_user_mail_notification`
--
DROP TABLE IF EXISTS `piwigo_user_mail_notification`;

CREATE TABLE `piwigo_user_mail_notification` (
  `user_id` int UNSIGNED NOT NULL DEFAULT '0',
  `check_key` varchar(16) NOT NULL DEFAULT '',
  `enabled` enum('true', 'false') NOT NULL DEFAULT 'false',
  `last_send` datetime DEFAULT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `user_mail_notification_ui1` (`check_key`)
) ENGINE = InnoDB;

--
-- Table structure for table `piwigo_users`
--
DROP TABLE IF EXISTS `piwigo_users`;

CREATE TABLE `piwigo_users` (
  `id` int UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL DEFAULT '',
  `password` varchar(255) DEFAULT NULL,
  `mail_address` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_ui1` (`username`)
) ENGINE = InnoDB;