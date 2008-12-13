<?php

  // the list of all CPM configuration options that are saved to wp_options

  $configuration_options = array(
    'Basic Configuration',
    array(
      'id' => 'cpm-default-post-time',
      'name' => "Default Post Time",
      'type' => 'text',
      'size' => 8,
      'default' => '12:00am',
      'message' => "Default time for comic posts to go live"
    ),
    array(
      'id' => 'cpm-default-post-content',
      'name' => "Default Post Content",
      'type' => 'textarea',
      'message' => "Default content for comic posts"
    ),
    array(
      'id' => 'cpm-default-override-title',
      'name' => "Default Post Title",
      'type' => 'text',
      'size' => 40,
      'message' => "Default title for comic posts"
    ),
    array(
      'id' => 'cpm-default-post-tags',
      'name' => "Default Post Tags",
      'type' => 'text',
      'size' => 40,
      'message' => "Default tags for comic posts"
    ),
    array(
      'id' => 'cpm-default-additional-categories',
      'name' => "Default Additional Categories",
      'type' => 'categories',
      'message' => "Additional default categories for comic posts"
    ),
    array(
      'id' => 'cpm-archive-generate-thumbnails',
      'name' => "Generate Archive Thumbnails?",
      'type' => 'checkbox',
      'default' => "1",
      'message' => "If checked and server is configured correctly, generate archive thumbnails"
    ),
    array(
      'id' => 'cpm-rss-generate-thumbnails',
      'name' => "Generate RSS Thumbnails?",
      'type' => 'checkbox',
      'default' => "1",
      'message' => "If checked and server is configured correctly, generate RSS thumbnails"
    ),
    array(
      'id' => 'cpm-thumbnail-quality',
      'name' => "Thumbnail Quality",
      'type' => 'text',
      'size' => 3,
      'default' => "80",
      'message' => "Quality of JPEG Thumbnails"
    ),
    "Advanced Configuration",
    array(
      "id" => "cpm-skip-checks",
      "name" => "Skip Checks?",
      "type" => "checkbox",
      'default' => "1",
      "message" => "if you know your configuration is correct, enable this to improve performance"
    ),
    array(
      "id" => "cpm-date-format",
      "name" => "ComicPress Manager Date Format",
      "type" => "text",
      "size" => 12,
      'default' => "Y-m-d",
      "message" => "if you've hacked on ComicPress to support a different date format, change it here"
    ),
    array(
      "id" => "cpm-perform-gd-check",
      "name" => "Check uploaded files using GD?",
      "type" => "checkbox",
      'default' => "1",
      "message" => "enable this to check if uploaded files are valid images using the GD library"
    ),
    array(
      "id" => "cpm-enable-dashboard-rss-feed",
      "name" => "Enable the ComicPress RSS Feed on the Dashboard?",
      "type" => "checkbox",
      'default' => "1",
      "message" => "enable this to get the latest ComicPress news on your Dashboard"
    ),
    array(
      "id" => "cpm-enable-quomicpress",
      "name" => "Enable the QuomicPress (Quick ComicPress) panel on the Dashboard?",
      "type" => "checkbox",
      'default' => "1",
      "message" => "enable this to use the QuomicPress (Quick ComicPress) posting box on your Dashboard"
    ),
    array(
      "id" => "cpm-upload-permissions",
      "name" => "Unix chmod permissions to assign to uploaded files?",
      "type" => "text",
      "size" => 5,
      'default' => "664",
      "message" => "if you're on a Unix-like operating system, and need to have files uploaded with specific permissions, enter them in here. Windows systems always upload with chmod 777"
    ),
    array(
      "id" => "cpm-document-root",
      "name" => "Specify a WordPress DOCUMENT_ROOT",
      "type" => "text",
      "size" => 30,
      "message" => "if ComicPress Manager isn't able to automatically find the path to your index.php file and comics folders, enter in the proper absolute path here"
    ),
  );

?>