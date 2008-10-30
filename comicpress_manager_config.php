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

// if you need ot use a different level of permissions when uploading/modifying
// files, change it here
//
// for Windows users, your permissions will automatically be set to 0777 (writable).
// there is no easy way in PHP to modify permissions on an NTFS filesystem (and no
// permissions to speak of on FAT32!)

if (strpos(PHP_OS, "WIN") !== false) {
  // for Windows users
  define("CPM_FILE_UPLOAD_CHMOD", 0777);
} else {
  // for Unix (Linux, Mac OS X, *BSD) users
  // define("CPM_FILE_UPLOAD_CHMOD", 0644); // writable by webserver process only      (rare)
  define("CPM_FILE_UPLOAD_CHMOD", 0664);    // writable by owner and any group members (common)
  // define("CPM_FILE_UPLOAD_CHMOD", 0666); // writable by anyone                      (rare)
}

$cpm_config_properties = array(
  // change these to something you like better...

  'default_post_time' => "12:00 am",
  'default_post_content' => "",
  'default_override_title' => '',
  'default_post_tags' => "",
  'default_additional_categories' => array(12),
  'archive_generate_thumbnails' => true,
  'rss_generate_thumbnails'     => true,
  'thumbnail_quality'           => 80
);

// CPM_DOCUMENT_ROOT override
//
// If you are having issues with your comics folders or your WordPress site root
// not being found, then you will need to override the auto-calaculated
// CPM_DOCUMENT_ROOT with the absolute path to your site root (where the WordPress
// index.php is located, and most likely the same location as your comics folders).
// Please make sure there is no / at the end of the path!

// define('CPM_DOCUMENT_ROOT', '/your/very/nonstandard/setup/path')

?>