-- initial configuration for Piwigo

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'activate_comments',
    'false',
    'Global parameter for usage of comments system'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'allow_user_customization',
    'true',
    'allow users to customize their gallery?'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'allow_user_registration',
    'false',
    'allow visitors to register?'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  ('blk_menubar', '', 'Menubar options');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  ('c13y_ignore', NULL, 'List of ignored anomalies');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'comments_author_mandatory',
    'false',
    'Comment author is mandatory'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'comments_email_mandatory',
    'false',
    'Comment email is mandatory'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'comments_enable_website',
    'true',
    'Enable "website" field on add comment form'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'comments_forall',
    'false',
    'even guest not registered can post comments'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'comments_order',
    'ASC',
    'comments order on picture page and cie'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'comments_validation',
    'false',
    'administrators validate users comments before becoming visible'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  ('display_fromto', 'false', '');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'email_admin_on_comment',
    'false',
    'Send an email to the administrators when a valid comment is entered'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'email_admin_on_comment_deletion',
    'false',
    'Send an email to the administrators when a comment is deleted'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'email_admin_on_comment_edition',
    'false',
    'Send an email to the administrators when a comment is modified'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'email_admin_on_comment_validation',
    'true',
    'Send an email to the administrators when a comment requires validation'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'email_admin_on_new_user',
    'none',
    'Send an email to the administrators when a user registers'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'extents_for_templates',
    'a:0:{}',
    'Activated template-extension(s)'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'gallery_locked',
    'false',
    'Lock your gallery temporary for non admin users'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'gallery_title',
    '',
    'Title at top of each page and for RSS feed'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'history_admin',
    'false',
    'keep a history of administrator visits on your website'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'history_guest',
    'true',
    'keep a history of guest visits on your website'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  ('index_caddie_icon', 'true', '');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'index_created_date_icon',
    'true',
    'Display calendar by creation date icon'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  ('index_edit_icon', 'true', '');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  ('index_flat_icon', 'false', 'Display flat icon');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'index_new_icon',
    'true',
    'Display new icons next albums and pictures'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'index_posted_date_icon',
    'true',
    'Display calendar by posted date'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  ('index_search_in_set_action', 'true', '');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  ('index_search_in_set_button', 'false', '');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  ('index_sizes_icon', 'true', '');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'index_slideshow_icon',
    'true',
    'Display slideshow icon'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'index_sort_order_input',
    'true',
    'Display image order selection list'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'log',
    'true',
    'keep an history of visits on your website'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  ('mail_theme', 'clear', '');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'menubar_filter_icon',
    'false',
    'Display filter icon'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  ('mobile_theme', 'smartpocket', '');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'nb_categories_page',
    '12',
    'Param for categories pagination'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'nb_comment_page',
    '10',
    'number of comments to display on each page'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'nbm_complementary_mail_content',
    '',
    'Complementary mail content for notification by mail'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'nbm_send_detailed_content',
    'true',
    'Send detailed content for notification by mail'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'nbm_send_html_mail',
    'true',
    'Send mail on HTML format for notification by mail'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'nbm_send_mail_as',
    '',
    'Send mail as param value for notification by mail'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'nbm_send_recent_post_dates',
    'true',
    'Send recent post by dates for notification by mail'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'obligatory_user_mail_address',
    'false',
    'Mail address is obligatory for users'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'order_by',
    'ORDER BY date_creation DESC',
    'default photo order'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'order_by_inside_category',
    'ORDER BY date_creation DESC',
    'default photo order inside category'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  ('original_resize', 'false', '');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  ('original_resize_maxheight', '2016', '');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  ('original_resize_maxwidth', '2016', '');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  ('original_resize_quality', '95', '');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'page_banner',
    '',
    'html displayed on the top each page of your gallery'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  ('picture_caddie_icon', 'true', '');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'picture_download_icon',
    'true',
    'Display download icon on picture page'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  ('picture_edit_icon', 'true', '');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'picture_favorite_icon',
    'true',
    'Display favorite icon on picture page'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'picture_information',
    'a:11:{s:6:"author";b:1;s:10:"created_on";b:1;s:9:"posted_on";b:1;s:10:"dimensions";b:0;s:4:"file";b:0;s:8:"filesize";b:0;s:4:"tags";b:1;s:10:"categories";b:1;s:6:"visits";b:1;s:12:"rating_score";b:1;s:13:"privacy_level";b:1;}',
    'Information displayed on picture page'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'picture_menu',
    'false',
    'Show menubar on picture page'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'picture_metadata_icon',
    'true',
    'Display metadata icon on picture page'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'picture_navigation_icons',
    'true',
    'Display navigation icons on picture page'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'picture_navigation_thumb',
    'true',
    'Display navigation thumbnails on picture page'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  ('picture_representative_icon', 'true', '');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  ('picture_sizes_icon', 'true', '');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'picture_slideshow_icon',
    'true',
    'Display slideshow icon on picture page'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'rate',
    'true',
    'Rating pictures feature is enabled'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'rate_anonymous',
    'true',
    'Rating pictures feature is also enabled for visitors'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  ('show_mobile_app_banner_in_admin', 'true', '');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  ('show_mobile_app_banner_in_gallery', 'false', '');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'updates_ignored',
    'a:3:{s:7:"plugins";a:0:{}s:6:"themes";a:0:{}s:9:"languages";a:0:{}}',
    'Extensions ignored for update'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  ('upload_detect_duplicate', 'true', '');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'user_can_delete_comment',
    'false',
    'administrators can allow user delete their own comments'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'user_can_edit_comment',
    'false',
    'administrators can allow user edit their own comments'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'week_starts_on',
    'monday',
    'Monday may not be the first day of the week'
  );

-- Plugin 'Admin Tools'

INSERT INTO
  piwigo_plugins (id, state, version)
VALUES
  ('AdminTools', 'active', '14.5.0');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'AdminTools',
    'a:3:{s:12:\"default_open\";b:1;s:15:\"closed_position\";s:4:\"left\";s:17:\"public_quick_edit\";b:1;}',
    NULL
  );

-- Plugin 'Take A Tour of Your Piwigo'

INSERT INTO
  piwigo_plugins (id, state, version)
VALUES
  ('TakeATour', 'active', '14.5.0');

-- Plugin 'Language Switch'

INSERT INTO
  piwigo_plugins (id, state, version)
VALUES
  ('language_switch', 'active', '14.5.0');

-- Plugin 'LocalFiles Editor'

INSERT INTO
  piwigo_plugins (id, state, version)
VALUES
  ('LocalFilesEditor', 'active', '14.5.0');

-- Plugin 'gdThumb'

INSERT INTO
  piwigo_plugins (id, state, version)
VALUES
  ('GDThumb', 'active', '1.0.26');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'gdThumb',
    'a:13:{s:6:\"height\";s:3:\"600\";s:6:\"margin\";s:2:\"10\";s:13:\"nb_image_page\";s:2:\"80\";s:9:\"big_thumb\";b:0;s:16:\"big_thumb_noinpw\";b:0;s:15:\"cache_big_thumb\";b:1;s:15:\"normalize_title\";s:3:\"off\";s:6:\"method\";s:6:\"resize\";s:16:\"thumb_mode_album\";s:13:\"bottom_static\";s:16:\"thumb_mode_photo\";s:4:\"hide\";s:14:\"thumb_metamode\";s:6:\"merged\";s:11:\"no_wordwrap\";b:0;s:13:\"thumb_animate\";b:0;}',
    NULL
  );

-- Plugin 'RV Thumb Scroller'

INSERT INTO
  piwigo_plugins (id, state, version)
VALUES
  ('rv_tscroller', 'active', '12.a');

-- Theme 'Modus'

INSERT INTO
  piwigo_themes (id, version, name)
VALUES
  ('modus', '14.5.0', 'modus');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'modus_theme',
    'a:5:{s:4:\"skin\";s:9:\"newspaper\";s:16:\"album_thumb_size\";i:250;s:17:\"index_photo_deriv\";s:6:\"2small\";s:22:\"index_photo_deriv_hdpi\";s:6:\"xsmall\";s:19:\"display_page_banner\";b:0;}',
    NULL
  );

-- Theme 'Elegant'

INSERT INTO
  piwigo_themes (id, version, name)
VALUES
  ('elegant', '14.5.0', 'elegant');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'elegant',
    'a:3:{s:11:\"p_main_menu\";s:2:\"on\";s:12:\"p_pict_descr\";s:2:\"on\";s:14:\"p_pict_comment\";s:3:\"off\";}',
    NULL
  );

-- Theme 'Smart Pocket'

INSERT INTO
  piwigo_themes (id, version, name)
VALUES
  ('smartpocket', '14.5.0', 'Smart Pocket');

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'smartpocket',
    'a:2:{s:4:\"loop\";b:1;s:8:\"autohide\";i:5000;}',
    NULL
  );

-- Theme 'Bootstrap Darkroom'

INSERT INTO
  piwigo_themes (id, version, name)
VALUES
  (
    'bootstrap_darkroom',
    '2.5.22',
    'Bootstrap Darkroom'
  );

INSERT INTO
  piwigo_config (param, value, comment)
VALUES
  (
    'bootstrap_darkroom',
    '{\"fluid_width\":true,\"fluid_width_col_xxl\":true,\"bootstrap_theme\":\"material-darkroom\",\"bootswatch_theme\":null,\"material_color\":null,\"navbar_main_style\":\"navbar-dark\",\"navbar_main_bg\":\"bg-dark\",\"navbar_contextual_style\":\"navbar-dark\",\"navbar_contextual_bg\":\"bg-primary\",\"slick_enabled\":true,\"slick_lazyload\":\"ondemand\",\"slick_infinite\":false,\"slick_centered\":false,\"page_header\":\"none\",\"page_header_full\":false,\"page_header_image\":null,\"page_header_both_navs\":true,\"picture_info\":\"cards\",\"photoswipe\":true,\"photoswipe_interval\":\"3500\",\"thumbnail_linkto\":\"photoswipe\",\"thumbnail_caption\":true,\"thumbnail_desc\":false,\"thumbnail_cat_desc\":\"simple\",\"category_wells\":\"never\",\"logo_image_enabled\":false,\"logo_image_path\":null,\"quicksearch_navbar\":true,\"cat_descriptions\":false,\"cat_nb_images\":true,\"social_enabled\":false,\"social_buttons\":false,\"social_twitter\":true,\"social_facebook\":true,\"social_pinterest\":true,\"social_vk\":true,\"comments_type\":\"piwigo\",\"comments_disqus_shortname\":null,\"tag_cloud_type\":\"basic\",\"custom_css\":null,\"conf_version\":20}',
    NULL
  );
