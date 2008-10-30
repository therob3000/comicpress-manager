<?php

/**
 * Functions that are tested by test_comicpress_manager.php live here,
 * to reduce the amount of WordPress simulation necessary for testing.
 */

class ComicPressConfig {
  /**
    * This array stores the config that is read from disk.
    * The only parameters you should change, if you wish, are the
    * default_post_time and the default_post_content.
    */
  var $properties = array(
    // Leave these alone! These values should be read from your comicpress-config.php file.
    // If your values from comicpress-config.php are not being read, then something is wrong in your config.
    'comic_folder' => 'comics',
    'comiccat'     => '1',
    'blogcat'      => '2',
    'rss_comic_folder' => 'comics',
    'archive_comic_folder' => 'comics',
    'archive_comic_width' => '380',
    'blog_postcount' => '10'
  );

  var $warnings, $messages, $errors, $detailed_warnings, $show_config_editor;
  var $config_method, $config_filepath, $path, $plugin_path;
  var $comic_files, $blog_category_info, $comic_category_info;
  var $scale_method_cache, $identify_method_cache, $can_write_config;
  var $need_calendars = false;

  var $separate_thumbs_folder_defined = array('rss' => null, 'archive' => null);
  var $thumbs_folder_writable = array('rss' => null, 'archive' => null);

  function get_scale_method() {
    if (!isset($this->scale_method_cache)) {
      $this->scale_method_cache = CPM_SCALE_NONE;
      $result = @shell_exec("which convert") . @shell_exec("which identify");
      if (!empty($result)) {
        $this->scale_method_cache = CPM_SCALE_IMAGEMAGICK;
      } else {
        if (extension_loaded("gd")) {
          $this->scale_method_cache = CPM_SCALE_GD;
        }
      }
    }
    $this->scale_method_cache = CPM_SCALE_GD;
    return $this->scale_method_cache;
  }
}

function cpm_get_home_url() {
  if (function_exists('get_current_site')) { // WPMU
    return preg_replace('#/$#', '', "http://" . get_current_site()->domain . get_current_site()->path);
  } else {
    return get_option('home');
  }
}

function cpm_get_admin_url() {
  if (function_exists('get_current_site')) { // WPMU
    return cpm_get_home_url();
  } else {
    return get_option('siteurl');
  }
}

function cpm_calculate_document_root() {
  global $cpm_attempted_document_roots;
  $cpm_attempted_document_roots = array();

  $document_root = null;

  $parsed_url = parse_url(cpm_get_home_url());

  $translated_script_filename = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME']);

  foreach (array('SCRIPT_NAME', 'SCRIPT_URL') as $var_to_try) {
    $root_to_try = substr($translated_script_filename, 0, -strlen($_SERVER[$var_to_try]))  . $parsed_url['path'];
    $cpm_attempted_document_roots[] = $root_to_try;

    if (file_exists($root_to_try . '/index.php')) {
      $document_root = $root_to_try;
      break;
    }
  }

  if (is_null($document_root)) { $document_root = $_SERVER['DOCUMENT_ROOT'] . $parsed_url['path']; }

  return $document_root;
}

function cpm_get_cpm_document_root() {
  if (!defined('CPM_DOCUMENT_ROOT')) {
    define('CPM_DOCUMENT_ROOT', cpm_calculate_document_root());
    define("CPM_STRLEN_REALPATH_DOCUMENT_ROOT", strlen(realpath(CPM_DOCUMENT_ROOT)));
  }
}

function cpm_transform_date_string($string, $replacements) {
  if (!is_array($replacements)) { return false; }
  if (!is_string($string)) { return false; }

  $transformed_string = $string;
  foreach (array("Y", "m", "d") as $required_key) {
    if (!isset($replacements[$required_key])) { return false; }
    $transformed_string = preg_replace('#(?<![\\\])' . $required_key . '#', $replacements[$required_key], $transformed_string);
  }

  $transformed_string = str_replace('\\', '', $transformed_string);
  return $transformed_string;
}

function cpm_generate_example_date($example_date) {
  return cpm_transform_date_string($example_date, array('Y' => "YYYY", 'm' => "MM", 'd' => "DD"));
}

function cpm_build_comic_uri($filename, $base_dir = null) {
  if (($realpath_result = realpath($filename)) !== false) {
    $filename = $realpath_result;
  }
  if (!is_null($base_dir)) {
    $filename = substr($filename, strlen($base_dir));
  }
  $parts = explode('/', str_replace('\\', '/', $filename));
  if (count($parts) < 2) { return false; }

  $parsed_url = parse_url(get_bloginfo('url'));

  return $parsed_url['path'] . '/' . implode('/', array_slice($parts, -2, 2));
}

/**
 * Breakdown the name of a comic file into a date and proper title.
 */
function cpm_breakdown_comic_filename($filename, $allow_override = false) {
  $pattern = CPM_DATE_FORMAT;
  if ($allow_override) {
    if (isset($_POST['upload-date-format']) && !empty($_POST['upload-date-format'])) { $pattern = $_POST['upload-date-format']; }
  }

  $pattern = cpm_transform_date_string($pattern, array("Y" => '[0-9]{4,4}',
                                                       "m" => '[0-9]{2,2}',
                                                       "d" => '[0-9]{2,2}'));

  if (preg_match("/^(${pattern})(.*)\.[^\.]+$/", $filename, $matches) > 0) {
    list($all, $date, $title) = $matches;

    if (strtotime($date) === false) { return false; }
    $converted_title = ucwords(trim(preg_replace('/[\-\_]/', ' ', $title)));

    return compact('date', 'title', 'converted_title');
  } else {
    return false;
  }
}

function cpm_steal_private_info() {
  // this one's for you, Tyler. :)
  // http://twitter.com/johncoswell/statuses/970351937
}

/**
 * Generate a hash for passing to wp_insert_post()
 * @param string $filename_date The post date.
 * @param string $filename_converted_title The title of the comic.
 * @return array The post information or false if the date is invalid.
 */
function generate_post_hash($filename_date, $filename_converted_title) {
  if (isset($_POST['time']) && !empty($_POST['time'])) {
    $filename_date .= " " . $_POST['time'];
  }
  if (($timestamp = strtotime($filename_date)) !== false) {
    if ($filename_converted_title == "") {
      $filename_converted_title = strftime("%m/%d/%Y", $timestamp);
    }

    $category_name = get_cat_name($_POST['category']);

    $post_content = "";
    if (isset($_POST['content']) && !empty($_POST['content'])) {
      $post_content = $_POST['content'];
      $post_content = preg_replace('/\{date\}/', date('F j, Y', $timestamp), $post_content);
      $post_content = preg_replace('/\{title\}/', $filename_converted_title, $post_content);
      $post_content = preg_replace('/\{category\}/', $category_name, $post_content);
    }

    $override_title = $_POST['override-title-to-use'];
    $tags = $_POST['tags'];
    if (get_magic_quotes_gpc()) {
      $override_title = stripslashes($override_title);
      $tags = stripslashes($tags);
    }

    $post_title    = (isset($_POST['override-title']) && !empty($_POST['override-title'])) ? $override_title : $filename_converted_title;
    $post_date     = date('Y-m-d H:i:s', $timestamp);
    $post_category = array($_POST['category']);
    if (isset($_POST['additional-categories'])) {
      if (is_array($_POST['additional-categories'])) {
        $post_category = array_merge($post_category, array_intersect(get_all_category_ids(), $_POST['additional-categories']));
      }
    }
    $post_status   = isset($_POST['publish']) ? "publish" : "draft";
    $tags_input    = $tags;

    return compact('post_content', 'post_title', 'post_date', 'post_category', 'post_status', 'tags_input');
  }

  return false;
}

/**
 * Retrieve posts from the WordPress database.
 */
function cpm_query_posts() {
  global $cpm_config;
  $query_posts_string = "posts_per_page=999999&cat=";
  if (is_array($cpm_config->properties['comiccat'])) {
    $query_posts_string .= implode(",", $cpm_config->properties['comiccat']);
  } else {
    $query_posts_string .= $cpm_config->properties['comiccat'];
  }

  return query_posts($query_posts_string);
}

/**
 * Get the absolute filepath to the comic folder.
 */
function get_comic_folder_path() {
  global $cpm_config, $blog_id;

  $path = CPM_DOCUMENT_ROOT . '/' . $cpm_config->properties['comic_folder'];
  if (function_exists('get_current_site')) {
    $path .= (!empty($blog_id) ? "/${blog_id}" : "");
  }

  return $path;
}

/**
 * Find all the valid comics in the comics folder.
 * If CPM_SKIP_CHECKS is enabled, comic file validity is not checked, improving speed.
 * @return array The list of valid comic files in the comic folder.
 */
function cpm_read_comics_folder() {
  global $cpm_config;

  $glob_results = glob(get_comic_folder_path() . "/*");
  if ($glob_results === false) {
    $cpm_config->warnings[] = "FYI: glob({$cpm_config->path}/*) returned false. This can happen on some PHP installations if you have no files in your comic directory.";
    return array(); 
  }

  if (CPM_SKIP_CHECKS) {
    return $glob_results;
  } else {
    $files = array();
    foreach ($glob_results as $file) {
      if (cpm_breakdown_comic_filename(pathinfo($file, PATHINFO_BASENAME)) !== false) {
        $files[] = $file;
      }
    }
    return $files;
  }
}

?>