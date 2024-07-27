--
-- Table structure for table `activity`
--
DROP TABLE IF EXISTS activity;

CREATE TABLE activity (
    activity_id SERIAL PRIMARY KEY,
    object VARCHAR(255) NOT NULL,
    object_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    performed_by INT NOT NULL,
    session_idx VARCHAR(255) NOT NULL,
    ip_address VARCHAR(50),
    occured_on TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    details VARCHAR(255),
    user_agent VARCHAR(255)
);

--
-- Table structure for table `caddie`
--
DROP TABLE IF EXISTS caddie;

CREATE TABLE caddie (
    user_id INT NOT NULL DEFAULT 0,
    element_id INT NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, element_id)
);

--
-- Table structure for table `categories`
--
DROP TABLE IF EXISTS categories;

CREATE TABLE categories (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL DEFAULT '',
    id_uppercat INT,
    comment TEXT,
    dir VARCHAR(255),
    rank_column INT,
    status VARCHAR(7) NOT NULL DEFAULT 'public',
    site_id INT,
    visible VARCHAR(5) NOT NULL DEFAULT 'true',
    representative_picture_id INT,
    uppercats VARCHAR(255) NOT NULL DEFAULT '',
    commentable VARCHAR(5) NOT NULL DEFAULT 'true',
    global_rank VARCHAR(255),
    image_order VARCHAR(128),
    permalink VARCHAR(64),
    lastmodified TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (permalink)
);

CREATE INDEX idx_id_uppercat ON categories (id_uppercat);
CREATE INDEX idx_lastmodified ON categories (lastmodified);

CREATE INDEX category_ft ON categories USING GIN (to_tsvector('english', name), to_tsvector('english', comment));

--
-- Table structure for table `comments`
--
DROP TABLE IF EXISTS comments;

CREATE TABLE comments (
    id SERIAL PRIMARY KEY,
    image_id INT NOT NULL DEFAULT 0,
    date TIMESTAMP NOT NULL DEFAULT '1970-01-01 00:00:00',
    author VARCHAR(255),
    email VARCHAR(255),
    author_id INT,
    anonymous_id VARCHAR(45) NOT NULL,
    website_url VARCHAR(255),
    content TEXT,
    validated VARCHAR(5) NOT NULL DEFAULT 'false',
    validation_date TIMESTAMP
);

CREATE INDEX idx_validation_date ON comments (validation_date);
CREATE INDEX idx_image_id ON comments (image_id);

--
-- Table structure for table `config`
--
DROP TABLE IF EXISTS config;

CREATE TABLE config (
    param VARCHAR(40) NOT NULL PRIMARY KEY,
    value TEXT,
    comment VARCHAR(255)
) WITH (OIDS=FALSE);

COMMENT ON TABLE config IS 'configuration table';

--
-- Table structure for table `favorites`
--
DROP TABLE IF EXISTS favorites;

CREATE TABLE favorites (
    user_id INT NOT NULL DEFAULT 0,
    image_id INT NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, image_id)
);

--
-- Table structure for table `group_access`
--
DROP TABLE IF EXISTS group_access;

CREATE TABLE group_access (
    group_id INT NOT NULL DEFAULT 0,
    cat_id INT NOT NULL DEFAULT 0,
    PRIMARY KEY (group_id, cat_id)
);

--
-- Table structure for table `groups`
--
DROP TABLE IF EXISTS groups_table;

CREATE TABLE groups_table (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL DEFAULT '',
    is_default VARCHAR(5) NOT NULL DEFAULT 'false',
    lastmodified TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (name)
);

CREATE INDEX idx_lastmodified2 ON groups_table (lastmodified);

--
-- Table structure for table `history`
--
DROP TABLE IF EXISTS history;

CREATE TABLE history (
    id SERIAL PRIMARY KEY,
    date DATE NOT NULL DEFAULT '1970-01-01',
    time TIME NOT NULL DEFAULT '00:00:00',
    user_id INT NOT NULL DEFAULT 0,
    IP VARCHAR(15) NOT NULL DEFAULT '',
    section VARCHAR(50),
    category_id INT,
    tag_ids VARCHAR(50),
    image_id INT,
    image_type VARCHAR(6),
    format_id INT,
    auth_key_id INT
);

--
-- Table structure for table `history_summary`
--
DROP TABLE IF EXISTS history_summary;

CREATE TABLE history_summary (
    year INT NOT NULL DEFAULT 0,
    month INT,
    day INT,
    hour INT,
    nb_pages INT,
    history_id_from INT,
    history_id_to INT,
    UNIQUE (year, month, day, hour)
);

--
-- Table structure for table `image_category`
--
DROP TABLE IF EXISTS image_category;

CREATE TABLE image_category (
    image_id INT NOT NULL DEFAULT 0,
    category_id INT NOT NULL DEFAULT 0,
    rank_column INT,
    PRIMARY KEY (image_id, category_id)
);

CREATE INDEX idx_category_id ON image_category (category_id);

--
-- Table structure for table `image_format`
--
DROP TABLE IF EXISTS image_format;

CREATE TABLE image_format (
    format_id SERIAL PRIMARY KEY,
    image_id INT NOT NULL DEFAULT 0,
    ext VARCHAR(255) NOT NULL,
    filesize INT
);

--
-- Table structure for table `image_tag`
--
DROP TABLE IF EXISTS image_tag;

CREATE TABLE image_tag (
    image_id INT NOT NULL DEFAULT 0,
    tag_id INT NOT NULL DEFAULT 0,
    PRIMARY KEY (image_id, tag_id)
);

CREATE INDEX idx_tag_id ON image_tag (tag_id);

--
-- Table structure for table `images`
--
DROP TABLE IF EXISTS images;

CREATE TABLE images (
    id SERIAL PRIMARY KEY,
    file VARCHAR(255) NOT NULL DEFAULT '',
    date_available TIMESTAMP NOT NULL DEFAULT '1970-01-01 00:00:00',
    date_creation TIMESTAMP,
    name VARCHAR(255),
    comment TEXT,
    author VARCHAR(255),
    hit INT NOT NULL DEFAULT 0,
    filesize INT,
    width SMALLINT,
    height SMALLINT,
    coi CHAR(4),
    representative_ext VARCHAR(4),
    date_metadata_update DATE,
    rating_score FLOAT,
    path VARCHAR(600) NOT NULL DEFAULT '',
    storage_category_id INT,
    level SMALLINT NOT NULL DEFAULT 0,
    md5sum CHAR(32),
    added_by INT NOT NULL DEFAULT 0,
    rotation SMALLINT,
    latitude DOUBLE PRECISION,
    longitude DOUBLE PRECISION,
    lastmodified TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_storage_category_id ON images (storage_category_id);
CREATE INDEX idx_date_available ON images (date_available);
CREATE INDEX idx_rating_score ON images (rating_score);
CREATE INDEX idx_hit ON images (hit);
CREATE INDEX idx_date_creation ON images (date_creation);
CREATE INDEX idx_latitude ON images (latitude);
CREATE INDEX idx_path ON images (path);
CREATE INDEX idx_lastmodified3 ON images (lastmodified);

CREATE INDEX image_ft ON images USING GIN (to_tsvector('english', name), to_tsvector('english', comment));

--
-- Table structure for table `languages`
--
DROP TABLE IF EXISTS languages;

CREATE TABLE languages (
    id VARCHAR(64) PRIMARY KEY,
    version VARCHAR(64) NOT NULL DEFAULT '0',
    name VARCHAR(64)
);

--
-- Table structure for table `lounge`
--
DROP TABLE IF EXISTS lounge;

CREATE TABLE lounge (
    image_id INT NOT NULL DEFAULT 0,
    category_id INT NOT NULL DEFAULT 0,
    PRIMARY KEY (image_id, category_id)
);

--
-- Table structure for table `old_permalinks`
--
DROP TABLE IF EXISTS old_permalinks;

CREATE TABLE old_permalinks (
    cat_id INT NOT NULL DEFAULT 0,
    permalink VARCHAR(64) PRIMARY KEY,
    date_deleted TIMESTAMP NOT NULL DEFAULT '1970-01-01 00:00:00',
    last_hit TIMESTAMP,
    hit INT NOT NULL DEFAULT 0
);

--
-- Table structure for table `plugins`
--
DROP TABLE IF EXISTS plugins;

CREATE TABLE plugins (
    id VARCHAR(64) PRIMARY KEY,
    state VARCHAR(8) NOT NULL DEFAULT 'inactive',
    version VARCHAR(64) NOT NULL DEFAULT '0'
);

--
-- Table structure for table `rate`
--
DROP TABLE IF EXISTS rate;

CREATE TABLE rate (
    user_id INT NOT NULL DEFAULT 0,
    element_id INT NOT NULL DEFAULT 0,
    anonymous_id VARCHAR(45) NOT NULL DEFAULT '',
    rate INT NOT NULL DEFAULT 0,
    date DATE NOT NULL DEFAULT '1970-01-01',
    PRIMARY KEY (element_id, user_id, anonymous_id)
);

--
-- Table structure for table `search`
--
DROP TABLE IF EXISTS search;

CREATE TABLE search (
    id SERIAL PRIMARY KEY,
    last_seen DATE,
    rules TEXT
);

--
-- Table structure for table `sessions`
--
DROP TABLE IF EXISTS sessions;

CREATE TABLE sessions (
    id VARCHAR(255) PRIMARY KEY,
    data TEXT NOT NULL,
    expiration TIMESTAMP NOT NULL DEFAULT '1970-01-01 00:00:00'
);

--
-- Table structure for table `sites`
--
DROP TABLE IF EXISTS sites;

CREATE TABLE sites (
    id SERIAL PRIMARY KEY,
    galleries_url VARCHAR(255) NOT NULL DEFAULT '',
    UNIQUE (galleries_url)
);

--
-- Table structure for table `tags`
--
DROP TABLE IF EXISTS tags;

CREATE TABLE tags (
    id SERIAL PRIMARY KEY,
    name VARCHAR(255) NOT NULL DEFAULT '',
    url_name VARCHAR(255) NOT NULL DEFAULT '',
    lastmodified TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_url_name ON tags (url_name);
CREATE INDEX idx_lastmodified4 ON tags (lastmodified);

CREATE INDEX tag_name_ft ON tags USING GIN (to_tsvector('english', name));

--
-- Table structure for table `themes`
--
DROP TABLE IF EXISTS themes;

CREATE TABLE themes (
    id VARCHAR(64) PRIMARY KEY,
    version VARCHAR(64) NOT NULL DEFAULT '0',
    name VARCHAR(64)
);

--
-- Table structure for table `upgrade`
--
DROP TABLE IF EXISTS upgrade;

CREATE TABLE upgrade (
    id VARCHAR(20) PRIMARY KEY,
    applied TIMESTAMP NOT NULL DEFAULT '1970-01-01 00:00:00',
    description VARCHAR(255)
);

--
-- Table structure for table `user_access`
--
DROP TABLE IF EXISTS user_access;

CREATE TABLE user_access (
    user_id INT NOT NULL DEFAULT 0,
    cat_id INT NOT NULL DEFAULT 0,
    PRIMARY KEY (user_id, cat_id)
);

--
-- Table structure for table `user_auth_keys`
--
DROP TABLE IF EXISTS user_auth_keys;

CREATE TABLE user_auth_keys (
    auth_key_id SERIAL PRIMARY KEY,
    auth_key VARCHAR(255) NOT NULL,
    user_id INT NOT NULL,
    created_on TIMESTAMP NOT NULL,
    duration INT,
    expired_on TIMESTAMP NOT NULL
);

--
-- Table structure for table `user_cache`
--
DROP TABLE IF EXISTS user_cache;

CREATE TABLE user_cache (
    user_id INT PRIMARY KEY,
    need_update VARCHAR(5) NOT NULL DEFAULT 'true',
    cache_update_time INT NOT NULL DEFAULT 0,
    forbidden_categories TEXT,
    nb_total_images INT,
    last_photo_date TIMESTAMP,
    nb_available_tags INT,
    nb_available_comments INT,
    image_access_type VARCHAR(6) NOT NULL DEFAULT 'NOT IN',
    image_access_list TEXT
);

--
-- Table structure for table `user_cache_categories`
--
DROP TABLE IF EXISTS user_cache_categories;

CREATE TABLE user_cache_categories (
    user_id INT NOT NULL DEFAULT 0,
    cat_id INT NOT NULL DEFAULT 0,
    date_last TIMESTAMP,
    max_date_last TIMESTAMP,
    nb_images INT NOT NULL DEFAULT 0,
    count_images INT DEFAULT 0,
    nb_categories INT DEFAULT 0,
    count_categories INT DEFAULT 0,
    user_representative_picture_id INT,
    PRIMARY KEY (user_id, cat_id)
);

--
-- Table structure for table `user_feed`
--
DROP TABLE IF EXISTS user_feed;

CREATE TABLE user_feed (
    id VARCHAR(50) PRIMARY KEY,
    user_id INT NOT NULL DEFAULT 0,
    last_check TIMESTAMP
);

--
-- Table structure for table `user_group`
--
DROP TABLE IF EXISTS user_group;

CREATE TABLE user_group (
    user_id INT NOT NULL DEFAULT 0,
    group_id INT NOT NULL DEFAULT 0,
    PRIMARY KEY (group_id, user_id)
);

--
-- Table structure for table `user_infos`
--
-- Create enum type for status
CREATE TYPE status_enum AS ENUM (
    'webmaster',
    'admin',
    'normal',
    'generic',
    'guest'
);

DROP TABLE IF EXISTS user_infos;

CREATE TABLE user_infos (
    user_id INT PRIMARY KEY,
    nb_image_page INT NOT NULL DEFAULT 15,
    status status_enum NOT NULL DEFAULT 'guest',
    language VARCHAR(50) NOT NULL DEFAULT 'en_UK',
    expand VARCHAR(5) NOT NULL DEFAULT 'false',
    show_nb_comments VARCHAR(5) NOT NULL DEFAULT 'false',
    show_nb_hits VARCHAR(5) NOT NULL DEFAULT 'false',
    recent_period INT NOT NULL DEFAULT 7,
    theme VARCHAR(255) NOT NULL DEFAULT 'modus',
    registration_date TIMESTAMP NOT NULL DEFAULT '1970-01-01 00:00:00',
    enabled_high VARCHAR(5) NOT NULL DEFAULT 'true',
    level SMALLINT NOT NULL DEFAULT 0,
    activation_key VARCHAR(255),
    activation_key_expire TIMESTAMP,
    last_visit TIMESTAMP,
    last_visit_from_history VARCHAR(5) NOT NULL DEFAULT 'false',
    lastmodified TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    preferences TEXT
);

CREATE INDEX idx_lastmodified5 ON user_infos (lastmodified);

--
-- Table structure for table `user_mail_notification`
--
DROP TABLE IF EXISTS user_mail_notification;

CREATE TABLE user_mail_notification (
    user_id INT PRIMARY KEY,
    check_key VARCHAR(16) NOT NULL DEFAULT '',
    enabled VARCHAR(5) NOT NULL DEFAULT 'false',
    last_send TIMESTAMP,
    UNIQUE (check_key)
);

--
-- Table structure for table `users`
--
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(100) NOT NULL DEFAULT '',
    password VARCHAR(255),
    mail_address VARCHAR(255),
    UNIQUE (username)
);
