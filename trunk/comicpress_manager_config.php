<?php

// Select the level of access you want editors on your site to have.

$access_level = 10; // Administrator only
//$access_level = 5;  // Editors only
//$access_level = 2;  // Authors & Editors

define("CPM_SCALE_NONE", 0);
define("CPM_SCALE_IMAGEMAGICK", 1);
define("CPM_SCALE_GD", 2);

// if you know what you're doing, you can improve performance by setting this option to true
define("CPM_SKIP_CHECKS", false);

// if you've hacked on ComicPress to support a different date format, change it here
define("CPM_DATE_FORMAT", "Y-m-d");

// if you want the older ComicPress Manager warnings, set this to false
define("CPM_WP_STYLE_WARNINGS", true);

// if you don't want to check uploaded files against GD to see if they're valid images,
// set this to false
define("CPM_DO_GD_FILETYPE_CHECKS", true);

// if you don't want to see the ComicPress News Dashboard widget, set this to false
define("CPM_SHOW_DASHBOARD_RSS_FEED", true);

$cpm_config_properties = array(
  // change these to something you like better...

  'default_post_time' => "12:00 am",
  'default_post_content' => "",
  'default_override_title' => '',
  'default_post_tags' => "",
  'archive_generate_thumbnails' => true,
  'rss_generate_thumbnails'     => true,
  'thumbnail_quality'           => 80
);

// if you have a non-standard WP setup, you'll probably need to set
// the absolute path to the folder where your site's index.php file
// is located.  By default, this is:
//
// $_SERVER['DOCUMENT_ROOT'] . parse_url(get_bloginfo('url'), PHP_URL_PATH)

$cpm_config_comics_site_root = null;

// define('CPM_DOCUMENT_ROOT', '/your/very/nonstandard/setup/path')

?>