<?php
/*
Plugin Name: ComicPress Manager
Plugin URI: http://claritycomic.com/comicpress-manager/
Description: Manage the comics within a <a href="http://www.mindfaucet.com/comicpress/">ComicPress</a> theme installation.
Version: 0.6.0
Author: John Bintz
Author URI: http://www.coswellproductions.org/wordpress/

Copyright 2008 John Bintz  (email : jcoswell@coswellproductions.org)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

// Select the level of access you want editors on your site to have.

$access_level = 10; // Administrator only
//$access_level = 5;  // Editors only
//$access_level = 2;  // Authors & Editors

define("CPM_SCALE_NONE", 0);
define("CPM_SCALE_IMAGEMAGICK", 1);
define("CPM_SCALE_GD", 1);

class ComicPressConfig {
  /**
   * This array stores the config that is read from disk.
   * The only parameters you should change, if you wish, are the
   * default_post_time and the default_post_content.
   */
  var $properties = array(
    // change these to something you like better...
    'default_post_time' => "12:00 am",
    'default_post_content' => "{category} for {date} - {title}",
    'default_post_tags' => "",
    'archive_generate_thumbnails' => true,
    'rss_generate_thumbnails'     => true,
    'thumbnail_quality'           => 80,

    // leave these alone!
    'comic_folder' => '',
    'comiccat'     => '',
    'blogcat'      => '',
    'rss_comic_folder' => '',
    'archive_comic_folder' => '',
    'archive_comic_width' => '',
    'blog_postcount' => ''
  );

  var $warnings, $messages, $errors;
  var $config_method, $config_filepath, $can_write_config, $path, $plugin_path;
  var $comic_files, $blog_category_info, $comic_category_info;
  var $scale_method_cache;

  var $separate_thumbs_directory_defined = array('rss' => null, 'archive' => null);
  var $thumbs_directory_writable = array('rss' => null, 'archive' => null);

  function get_scale_method() {
    if (!isset($this->scale_method_cache)) {
      $this->scale_method_cache = CPM_SCALE_NONE;
      if (($result = `which convert`) !== "") {
        $this->scale_method_cache = CPM_SCALE_IMAGEMAGICK;
      }
      if (extension_loaded("gd")) {
        $this->scale_method_cache = CPM_SCALE_GD;
      }
    }
    return $this->scale_method_cache;
  }
}

$cpm_config = new ComicPressConfig();

wp_enqueue_script('prototype');
add_action("admin_menu", "cpm_add_pages");
add_action("edit_form_advanced", "cpm_show_comic");

/**
 * Add pages to the admin interface.
 */
function cpm_add_pages() {
  global $access_level;

  if (!isset($access_level)) { $access_level = 10; }
  
  add_menu_page("ComicPress Manager", "ComicPress", $access_level, __FILE__, "cpm_manager_index");
  add_submenu_page(__FILE__, "ComicPress Manager", "Upload", $access_level, __FILE__, 'cpm_manager_index');
  add_submenu_page(__FILE__, "ComicPress Manager", "Import", $access_level, 'import', 'cpm_manager_import');
  add_submenu_page(__FILE__, "ComicPress Manager", "Generate Thumbnails", $access_level, 'thumbnails', 'cpm_manager_thumbnails');
  add_submenu_page(__FILE__, "ComicPress Manager", "Delete", $access_level, 'delete', 'cpm_manager_delete');
  add_submenu_page(__FILE__, "ComicPress Manager", "Config", $access_level, 'config', 'cpm_manager_config');
}

function cpm_post_editor($width = 435) {
  global $cpm_config; ?>
  <span class="form-title">Category:</span>
  <span class="form-field"><?php echo generate_comic_categories_options('category') ?></span>

  <span class="form-title">Time to post:</span>
  <span class="form-field"><input type="text" name="time" value="<?php echo $cpm_config->properties['default_post_time'] ?>" size="10" /></span>

  <span class="form-title">Publish post:</span>
  <span class="form-field"><input type="checkbox" name="publish" value="yes" checked /></span>

  <span class="form-title">Don't check for duplicate posts:</span>
  <span class="form-field"><input type="checkbox" name="no_duplicate_check" value="yes" /></span>

  <?php
    if ($cpm_config->get_scale_method() != CPM_SCALE_NONE) {
      $thumbnail_writes = array();
      foreach ($cpm_config->separate_thumbs_directory_defined as $type => $value) {
        if ($value) {
          if ($cpm_config->thumbs_directory_writable[$type]) {
            if ($cpm_config->properties[$type . "_generate_thumbnails"] !== false) {
              $thumbnail_writes[] = $type;
            }
          }
        }
      }

      if (count($thumbnail_writes) > 0) { ?>
        <div id="thumbnail-write-holder">
          (<em>You'll be writing thumbnails to: <?php echo implode(", ", $thumbnail_writes) ?></em>)
        </div>

        <span class="form-title">Don't generate thumbnails:</span>
        <span class="form-field"><input onclick="hide_show_div_on_checkbox('thumbnail-write-holder', this, true)" type="checkbox" name="no-thumbnails" value="yes" /></span>
      <?php } else { ?>
        <div id="thumbnail-write-holder">
          (<em>You won't be generating any thumbnails</em>)
        </div>
      <?php }
    }
  ?>

  <span class="form-title">Specify a title for all posts:</span>
  <span class="form-field"><input onclick="hide_show_div_on_checkbox('override-title-holder', this)" type="checkbox" id="override-title" name="override-title" value="yes" /></span>

  <div id="override-title-holder">
    <span class="form-title">Title to use:</span>
    <span class="form-field"><input type="text" name="override-title-to-use" /></span>
  </div>

  <span class="form-title">Tags:</span>
  <span class="form-field"><input type="text" name="tags" value="<?php echo $cpm_config->properties['default_post_tags'] ?>" /></span>

  <?php cpm_show_post_body_template($width) ?>
<?php }

/**
 * The main manager screen.
 */
function cpm_manager_index() {
  global $cpm_config;

  cpm_read_information();
  check_comicpress_config();
  cpm_handle_actions();

  cpm_write_global_styles_scripts();
  ?>
  
<div class="wrap">  
  <div id="cpm-container">

    <?php if (cpm_handle_warnings()) { ?>
      <!-- Header -->
      <?php cpm_show_manager_header() ?>

      <div id="cpm-left-column">
        <?php cpm_show_comicpress_details($cpm_config->blog_category_info, $cpm_config->comic_category_info) ?>
        <!-- Help -->
        <div id="comicpress-help">
          <h2 style="padding-right:0;">Help!</h2>
          <p>
            <strong>ComicPress Manager manages your comics and your time.</strong> It makes uploading new comics, importing comics from a non-ComicPress setup, and batch uploading a lot of comics at once, very fast and configurable.
          </p>

          <p>
            <strong>ComicPress Manager also manages yours and your Website's sanity.</strong> It can check for misconfigured ComicPress setups, for incorrectly-named files (remember, it's <em>YYYY-MM-DD-single-comic-title.ext</em>), and for when you might be duplicating a post.
            You will also be shown which comic will appear with which blog post in the Post editor.
          </p>
          
          <p>
            <strong>Single comic titles</strong> are generated from the incoming filename.  If you've named your file <strong>2008-01-01-my-new-years-day.jpg</strong> and create a new post for the file, the post title will be <strong>My New Years Day</strong>.  This default should handle the majority of cases.  If a comic file does not have a title, the date
              in <strong>MM/DD/YYYY</strong> format will be used.
          </p>

          <p>
            <strong>Upload image files</strong> lets you upload multiple comics at a time, and add a default
            post body for each comic.
          </p>

          <p>
            <?php if (extension_loaded('zip')) { ?>
              <strong>Upload a Zip file and create new posts</strong> combines the file transfer and post creation steps into one action, allowing you to quickly add new comics to your site.
            <?php } else { ?>
              The <strong>Upload a Zip file and create new posts</strong> options is not available to you because you do not have the PHP <strong>zip</strong> extension installed.
            <?php } ?>
          </p>
        </div>
      </div>

      <!-- Upload a single comic -->
      <div id="cpm-right-column">
        <div class="activity-box">
          <h2 style="padding-right:0;">Upload Image
            <?php if (extension_loaded('zip')) { echo "&amp; Zip"; } ?>
          Files</h2>
          <h3>&mdash; any existing files with the same name will be overwritten</h3>

          <form action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="multiple-upload-file" />
            <div id="multiple-file-upload">
            </div>
            <div style="text-align: center">
              [<a href="#" onclick="add_file_upload(); return false">Add file to upload</a>]
            </div>

            Generate new posts for each uploaded file: <input id="multiple-new-post-checkbox" type="checkbox" name="new_post" value="yes" checked />
            <div id="multiple-new-post-holder"><?php cpm_post_editor(420) ?></div>
            <br /><input type="submit" value="Upload Image <?php if (extension_loaded('zip')) { echo "&amp; Zip"; } ?> Files" style="width: 445px" />
          </form>
        </div>
      </div>
      <?php cpm_show_footer() ?>
    </div>
    <?php } ?>
  </div>
</div>

  <?php
}

/**
 * The delete dialog.
 */
function cpm_manager_delete() {
  global $cpm_config;

  cpm_read_information();
  check_comicpress_config();
  cpm_handle_actions();

  cpm_write_global_styles_scripts();
  ?>
  
<div class="wrap">  
  <div id="cpm-container">

    <?php if (cpm_handle_warnings()) { ?>
      <!-- Header -->
      <?php cpm_show_manager_header() ?>
      
      <div id="cpm-left-column">
        <?php cpm_show_comicpress_details($cpm_config->blog_category_info, $cpm_config->comic_category_info) ?>
        <div id="comicpress-help">
          <h2 style="padding-right:0;">Help!</h2>
          <p>
            <strong>Delete a comic file and the associated post, if found</strong> lets you delete a comic file and the post that goes with it.  Any thumbnails associated with the comic file will also be deleted.
          </p>
        </div>      </div>

      <div id="cpm-right-column">
        <!-- Delete a comic and a post -->
        <div class="activity-box">
          <h2 style="padding-right:0;">Delete A Comic File &amp; Post (if found)</h2>

          <?php if (count($cpm_config->comic_files) > 0) { ?>
            <form action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post" onsubmit="return confirm('Are you sure?')">
              <input type="hidden" name="action" value="delete-comic-and-post" />

              Comic to delete:<br />
                <select style="width: 445px" id="delete-comic-dropdown" name="comic" align="absmiddle" onchange="change_image_preview()">
                  <?php foreach ($cpm_config->comic_files as $file) { ?>
                    <option value="<?php echo substr($file, strlen($_SERVER['DOCUMENT_ROOT'])) ?>"><?php echo pathinfo($file, PATHINFO_BASENAME) ?></option>
                  <?php } ?>
                </select><br />
              <div id="image-preview" style="text-align: center"></div>
              <p><strong>NOTE:</strong> If more than one possible post is found, neither the posts nor the comic file will be deleted.  ComicPress Manager cannot safely resolve such a conflict.</p>
              <input type="submit" value="Delete comic and post" style="width: 445px" />
            </form>
          <?php } else { ?>
            <p>You haven't uploaded any comics yet.</p>
          <?php } ?>
        </div>
      </div>
      <?php cpm_show_footer() ?>
  <?php } ?>
  </div>
</div>
<?php
}

/**
 * The generate thumbnails dialog.
 */
function cpm_manager_thumbnails() {
  global $cpm_config;

  cpm_read_information();
  check_comicpress_config();
  cpm_handle_actions();

  cpm_write_global_styles_scripts();
  ?>
  
<div class="wrap">  
  <div id="cpm-container">

    <?php if (cpm_handle_warnings()) { ?>
      <!-- Header -->
      <?php cpm_show_manager_header() ?>
      
      <div id="cpm-left-column">
        <?php cpm_show_comicpress_details($cpm_config->blog_category_info, $cpm_config->comic_category_info) ?>
        <div id="comicpress-help">
          <h2 style="padding-right:0;">Help!</h2>
          <p>
            <strong>Generate thumbnails</strong> lets you regenerate thumbnails for comic files.  This is useful if an import is not functioning because it is taking too long, or if you've changed your size or quality settings for thumbnails.
          </p>
        </div>
      </div>

      <div id="cpm-right-column">
        <!-- Generate thumbnails -->
        <div class="activity-box">
          <h2 style="padding-right:0;">Generate Thumbnails</h2>

          <?php
            $ok_to_generate_thumbs = false;

            if ($cpm_config->get_scale_method() != CPM_SCALE_NONE) {
              foreach ($cpm_config->thumbs_directory_writable as $type => $value) {
                if ($value) {
                  if ($cpm_config->separate_thumbs_directory_defined[$type] !== false) {
                    if ($cpm_config->properties[$type . "_generate_thumbnails"] == true) {
                      $ok_to_generate_thumbs = true; break;
                    }
                  }
                }
              }
            }

            if ($ok_to_generate_thumbs) {
              if (count($cpm_config->comic_files) > 0) { ?>
                <form action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post">
                  <input type="hidden" name="action" value="generate-thumbnails" />

                  Thumbnails to regenerate (<em>to select multiple comics, [Ctrl]-click on Windows &amp; Linux, [Command]-click on Mac OS X</em>):<br />
                    <select style="height: auto; width: 445px" id="select-comics-dropdown" name="comics[]" size="<?php echo min(count($cpm_config->comic_files), 30) ?>" multiple>
                      <?php foreach ($cpm_config->comic_files as $file) { ?>
                        <option value="<?php echo substr($file, strlen($_SERVER['DOCUMENT_ROOT'])) ?>"><?php echo pathinfo($file, PATHINFO_BASENAME) ?></option>
                      <?php } ?>
                    </select>
                  <input type="submit" value="Generate Thumbnails for Selected Comics" style="width: 445px" />
                </form>
              <?php } else { ?>
                <p>You haven't uploaded any comics yet.</p>
              <?php }
            } else { ?>
              <p><strong>You either aren't able or are unwilling to generate any thumbnails for your comics.</strong>
              This may be caused by a configuration error.</p>
            <?php }
          ?>
        </div>
      </div>
      <?php cpm_show_footer() ?>
  <?php } ?>
  </div>
</div>
<?php
}

/**
 * The import dialog.
 */
function cpm_manager_import() {
  global $cpm_config;

  cpm_read_information();
  check_comicpress_config();
  cpm_handle_actions();

  cpm_write_global_styles_scripts();
  ?>
  
<div class="wrap">  
  <div id="cpm-container">

    <?php if (cpm_handle_warnings()) { ?>
      <!-- Header -->
      <?php cpm_show_manager_header() ?>
      
      <div id="cpm-left-column">
        <?php cpm_show_comicpress_details($cpm_config->blog_category_info, $cpm_config->comic_category_info) ?>
        <div id="comicpress-help">
          <h2 style="padding-right:0;">Help!</h2>
          <p>
            <strong>Create missing posts for uploaded comics</strong> is for when you upload a lot of comics to your comic folder and want to generate generic posts for all of the new comics, or for when you're migrating from another system to ComicPress.
          </p>
        
          <p>
            <strong>Generating thumbnails on an import is a slow process.</strong>  Some Webhosts will limit the amount of time a script can run.  If your import process is failing with thumbnail generation enabled, disable thumbnail generation, perform your import, and then visit the <a href="?page=thumbnails">Thumbnail Generation page</a> to complete the thumbnail generation process.
          </p>
        </div>
      </div>

      <div id="cpm-right-column">
        <!-- Create missing posts for uploaded comics -->
        <div class="activity-box">
          <h2 style="padding-right:0;">Create Missing Posts For Uploaded Comics</h2>
          <h3>&mdash; acts as a batch import process</h3>

          <a href="#" onclick="return false" id="count-missing-posts-clicker">Count the number of missing posts</a> (may take a while): <span id="missing-posts-display"></span>

          <div id="create-missing-posts-holder">
            <form action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post" style="margin-top: 10px">
              <input type="hidden" name="action" value="create-missing-posts" />

              <?php cpm_post_editor() ?>

              <input type="submit" value="Create posts" style="width: 445px" />
            </form>
          </div>
        </div>
      </div>
      <?php cpm_show_footer() ?>
  <?php } ?>
  </div>
</div>
<?php
}

/**
 * The config editor dialog.
 */
function cpm_manager_config() {
  global $cpm_config;

  cpm_read_information();
  check_comicpress_config();
  cpm_handle_actions();

  cpm_write_global_styles_scripts();
  ?>
  
<div class="wrap">  
  <div id="cpm-container">

    <?php if (cpm_handle_warnings()) { ?>
      <!-- Header -->
      <?php cpm_show_manager_header() ?>
      
      <div id="cpm-left-column">
        <?php cpm_show_comicpress_details($cpm_config->blog_category_info, $cpm_config->comic_category_info) ?>
      </div>

      <div id="cpm-right-column">
        <!-- Edit the config -->
        <div class="activity-box">
          <h2 style="padding-right:0;">Edit ComicPress Config</h2>
           <?php if ($cpm_config->separate_thumbs_directory_defined_config) {
            echo cpm_manager_edit_config();
          } else { ?>
            <strong>You are unable to edit your configuration.</strong>
          <?php } ?>
        </div>
      </div>
      <?php cpm_show_footer() ?>
  <?php } ?>
  </div>
</div>
<?php
}
/**
 * Show the header.
 */
function cpm_show_manager_header() { ?>
  <?php if (!is_null($cpm_config->comic_category_info)) { ?>
    <h2>Managing &#8216;<?php echo $cpm_config->comic_category_info['name'] ?>&#8217;</h2>
  <?php } else { ?>
    <h2>Managing ComicPress</h2>
  <?php } ?>
<?php }

/**
 * Show the comic in the Post editor.
 */
function cpm_show_comic() {
  global $post, $cpm_config;

  read_current_theme_comicpress_config();

  if (($comic = find_comic_by_date(strtotime($post->post_date))) !== false) {
    $ok = false;
    $post_categories = wp_get_post_categories($post->ID);

    $comic_uri = substr(realpath($comic), strlen(realpath($_SERVER['DOCUMENT_ROOT'])));
    
    if (isset($cpm_config->properties['comiccat'])) {
      if (is_array($cpm_config->properties['comiccat'])) {
        $ok = count(array_intersect($cpm_config->properties['comiccat'], $post_categories)) > 0;
      } else {
        $ok = (in_array($cpm_config->properties['comiccat'], $post_categories));
      }
    }
    
    if ($ok) {
      ?>
        <script type="text/javascript">
  function show_comic() {
    Element.clonePosition('comic-hover', 'comic-icon', { setWidth: false, setHeight: false });
    $('comic-hover').show();
  }

  function hide_comic() {
    $('comic-hover').hide();
  }
        </script>
        <div id="comicdiv" class="postbox">
          <h3>Comic For This Post</h3>
          <div class="inside" style="overflow: auto">
            <div id="comic-hover" style="border: solid black 1px; position: absolute; display: none" onmouseout="hide_comic()">
              <img height="400" src="<?php echo $comic_uri ?>" />
            </div>
            <a href="#" onclick="return false" onmouseover="show_comic()"><img id="comic-icon" src="<?php echo $comic_uri ?>" height="100" align="right" /></a>
            <p>The comic that will be shown with this post is <strong><a target="comic_window" href="<?php echo $comic_uri ?>"><?php echo preg_replace('#^.*/([^\/]*)$#', '\1', $comic_uri) ?></a></strong>.  Mouse over the icon to the right to see a larger version of the image.</p>
          </div>
        </div>
      <?php
    } else {
      ?>
      <div id="comicdiv" class="postbox">
        <h3>Comic For This Post</h3>
        <div class="inside" style="overflow: auto">
          <p>The comic <strong><a target="comic_window" href="<?php echo $comic_uri ?>"><?php echo preg_replace('#^.*/([^\/]*)$#', '\1', $comic_uri) ?></a></strong> was found for this date, but this post is not in the ComicPress comics category.</p>
        </div>
      </div>
      <?php
    }
  }
}

/**
 * Get the absolute filepath to the comic folder.
 */
function get_comic_folder_path() {
  global $cpm_config;
  
  return ABSPATH . $cpm_config->properties['comic_folder'];
}

/**
 * Find a comic file by date.
 */
function find_comic_by_date($timestamp) {
  if (count($files = glob(get_comic_folder_path() . '/' . date('Y-m-d', $timestamp) . '*')) > 0) {
    return $files[0];
  }
  return false;
}

/**
 * Breakdown the name of a comic file into a date and proper title.
 */
function breakdown_comic_filename($filename) {
  if (preg_match('/^([0-9]{4,4}-[0-9]{2,2}-[0-9]{2,2})(.*)\.[^\.]+$/', $filename, $matches) > 0) {
    list($all, $date, $title) = $matches;

    if (strtotime($date) === false) { return false; }
    $converted_title = ucwords(trim(preg_replace('/[\-\_]/', ' ', $title)));

    return compact('date', 'converted_title');
  } else {
    return false;
  }
}

/**
 * Generate &lt;option&gt; elements for all comic categories.
 */
function generate_comic_categories_options($form_name) {
  global $cpm_config;

  $number_of_categories = 0;
  $first_category = null;

  ob_start();
  foreach (get_all_category_ids() as $cat_id) {
    $ok = false;

    if (is_array($cpm_config->properties['comiccat'])) {
      $ok = in_array($cat_id, $cpm_config->properties['comiccat']);
    } else {
      $ok = ($cat_id == $cpm_config->properties['comiccat']);
    }

    if ($ok) {
      $number_of_categories++;
      $category = get_category($cat_id);
      if (is_null($first_category)) { $first_category = $category; }
      ?>
      <option
        value="<?php echo $category->cat_ID ?>"
        <?php if (!is_null($cpm_config->comic_category_info)) {
          echo ($cpm_config->properties['comiccat'] == $cat_id) ? " selected" : "";
        } ?>
        ><?php echo $category->cat_name; ?></option>
    <?php }
  }
  $output = ob_get_clean();

  if ($number_of_categories == 1) {
    return "<input type=\"hidden\" name=\"${form_name}\" value=\"{$first_category->cat_ID}\" />" . $first_category->cat_name;
  } else {
    return "<select name=\"${form_name}\">" . $output . "</select>";
  }
}

/**
 * Get the path to the currently used config.
 */
function get_functions_php_filepath() {
  $current_theme_info = get_theme(get_current_theme());

  foreach (array("comicpress-config.php", "functions.php") as $possible_file) {
    foreach ($current_theme_info['Template Files'] as $filename) {
      if (preg_match('/' . preg_quote($possible_file, '/') . '$/', $filename) > 0) {
        return ABSPATH . '/' . $filename;
      }
    }
  }
  return null;
}

/**
 * Read the ComicPress config from a file.
 */
function read_current_theme_comicpress_config() {
  $current_theme_info = get_theme(get_current_theme());

  $method = null;

  $config_json_file = ABSPATH . '/' . $current_theme_info['Template Dir'] . '/config.json';

  if (file_exists($config_json_file)) {
    read_comicpress_config_json($config_json_file);
    $method = "config.json";
  }

  if (is_null($method)) {
    if (!is_null($filepath = get_functions_php_filepath())) {
      read_comicpress_config_functions_php($filepath);
      $method = basename($filepath);
    }
  }

  return $method;
}

/**
 * Read the ComicPress config from a functions.php file.
 * Note: this isn't super-robust, but should cover basic use cases.
 */
function read_comicpress_config_functions_php($filepath) {
  global $cpm_config;
  
  $file = file_get_contents($filepath);

  $variable_values = array();

  foreach (array_keys($cpm_config->properties) as $variable) {
    if (preg_match("#\\$${variable}\ *\=\ *([^\;]*)\;#", $file, $matches) > 0) {
      $variable_values[$variable] = preg_replace('#"#', '', $matches[1]);
    }
  }

  $cpm_config->properties = array_merge($cpm_config->properties, $variable_values);
}

/**
 * See if we can write to the config directory.
 */
function can_write_comicpress_config($filepath) {
  $perm_check_filename = $filepath . '-' . md5(rand());
  if (@touch($perm_check_filename) === true) {
    @unlink($perm_check_filename);
    return true;
  }
  return false;
}

/**
 * Write the current ComicPress Config to disk.
 */
function write_comicpress_config_functions_php($filepath) {
  global $cpm_config;

  $file_lines = file($filepath, FILE_IGNORE_NEW_LINES);

  for ($i = 0; $i < count($file_lines); $i++) {
    foreach (array_keys($cpm_config->properties) as $variable) {
      if (preg_match("#\\$${variable}\ *\=\ *([^\;]*)\;#", $file_lines[$i], $matches) > 0) {
        $file_lines[$i] = '$' . $variable . ' = "' . $cpm_config->properties[$variable] . '";';
      }
    }
  }

  if (can_write_comicpress_config($filepath)) {
    if (@rename($filepath, $filepath . '.' . time())) {
      if (@file_put_contents($filepath, implode("\n", $file_lines)) !== false) {
        @chmod($filepath, 0664);
        return true;
      } else {
        return false;
      }
    } else {
      return false;
    }
  } else {
    return false;
  }
}

/**
 * Read the ComicPress config from config.json.
 */
function read_comicpress_config_json($filepath) {
  global $cpm_config;

  $config = json_decode(file_get_contents($filepath), true);

  $cpm_config->properties = array_merge($cpm_config->properties, $config);
}

/**
 * Check the current ComicPress Config.
 */
function check_comicpress_config() {
  global $cpm_config;

  $cpm_config->errors = array();
  $cpm_config->warnings = array();
  $cpm_config->messages = array();

  // quick check to see if the theme is ComicPress.
  // this needs to be made more robust.
  if (preg_match('/ComicPress/', get_current_theme()) == 0) {
    $cpm_config->warnings[] = "The current theme isn't the ComicPress theme.  If you've renamed the theme, ignore this warning.";
  }

  foreach (array(
    array('comic folder', 'comic_folder', true, ""),
    array('RSS feed folder', 'rss_comic_folder', false, 'rss'),
    array('archive folder', 'archive_comic_folder', false, 'archive')) as $folder_info) {
      list ($name, $property, $is_fatal, $thumb_type) = $folder_info;
      $path = ABSPATH . '/' . $cpm_config->properties[$property];
      if (!file_exists($path)) {
        $cpm_config->errors[] = "The ${name} <strong>" . $cpm_config->properties[$property] . "</strong> does not exist.";
      } else {
        do {
          $tmp_filename = "test-" . md5(rand());
        } while (file_exists($path . '/' . $tmp_filename));

        if (!@touch($path . '/' . $tmp_filename)) {
          $message = "The ${name} <strong>" . $cpm_config->properties[$property] . "</strong> is not writable by the Webserver process.";
          if ($is_fatal) {
            $cpm_config->errors[] = $message;
          } else {
            $cpm_config->warnings[] = $message;
          }

          if ($thumb_type != "") {
            $cpm_config->thumbs_directory_writable[$thumb_type] = false;
          }
        } else {
          @unlink($path . '/' . $tmp_filename);
          if ($thumb_type != "") {
            $cpm_config->thumbs_directory_writable[$thumb_type] = true;
          }
        }
      }
  }

  foreach ($cpm_config->separate_thumbs_directory_defined as $type => $value) {
    if (!$value) {
      $cpm_config->warnings[] = "The ${type} folder and the comics folder are the same.  You won't be able to generate ${type} thumbnails until you change this.";
    }
  }

  if ($cpm_config->get_scale_method() == CPM_SCALE_NONE) {
    $cpm_config->warnings[] = "No image resize methods are installed (GD or ImageMagick).  You are unable to generate thumbnails automatically.";
  }

  // ensure the defined comic category exists
  if (is_null($cpm_config->properties['comiccat'])) {
    // all non-blog categories are comic categories
    $cpm_config->comic_category_info = array(
      'name' => "All other categories",
    );
    $cpm_config->properties['comiccat'] = array_diff(get_all_category_ids(), array($cpm_config->properties['blogcat']));

    if (count($cpm_config->properties['comiccat']) == 1) {
      $cpm_config->properties['comiccat'] = $cpm_config->properties['comiccat'][0];
      $cpm_config->comic_category_info = get_object_vars(get_category($cpm_config->properties['comiccat']));
    }
  } else {
    // one comic category is specified
    if (is_null($cpm_config->comic_category_info = get_category($cpm_config->properties['comiccat']))) {
      $cpm_config->warnings[] = "The requested category ID for your comic, <strong>" . $cpm_config->properties['comiccat'] . "</strong>, doesn't exist!";
    } else {
      $cpm_config->comic_category_info = get_object_vars($cpm_config->comic_category_info);
    }
  }

  // ensure the defined blog category exists
  // TODO: multiple blog categories
  if (is_null($cpm_config->blog_category_info = get_category($cpm_config->properties['blogcat']))) {
    $cpm_config->errors[] = "The requested category ID for your blog, <strong>" . $cpm_config->properties['blogcat'] . "</strong>, doesn't exist!";
  } else {
    $cpm_config->blog_category_info = get_object_vars($cpm_config->blog_category_info);
  }

  // a quick note if you have no comics uploaded.
  // could be a sign of something more serious.
  if (count($cpm_config->comic_files = glob($cpm_config->path . "/*")) == 0) {
    $cpm_config->warnings[] = "Your comics directory is empty!";
  }

  return compact('path', 'errors', 'warnings', 'messages', 'comic_files', 'comic_category_info', 'blog_category_info');
}

function generate_view_edit_post_links($post_info) {
  return "<a href=\"" . $post_info['guid'] . "\">View the post</a> or <a href=\"post.php?action=edit&amp;post=" . $post_info['ID'] . "\">edit the post</a>.";
}

/**
 * Generate a hash for passing to wp_insert_post()
 */
function generate_post_hash($filename_date, $filename_converted_title) {
  if (isset($_POST['time'])) {
    $filename_date .= " " . $_POST['time'];
  }
  if (($timestamp = strtotime($filename_date)) !== false) {
    if ($filename_converted_title == "") {
      $filename_converted_title = strftime("%m/%d/%Y", $timestamp);
    }

    $category_name = get_cat_name($_POST['category']);

    $post_content = "";
    if (isset($_POST['post-text'])) {
      $post_content = $_POST['post-text'];
      $post_content = preg_replace('/\{date\}/', date('F j, Y', $timestamp), $post_content);
      $post_content = preg_replace('/\{title\}/', $filename_converted_title, $post_content);
      $post_content = preg_replace('/\{category\}/', $category_name, $post_content);
    }

    $post_title    = (isset($_POST['override-title'])) ? $_POST['override-title-to-use'] : $filename_converted_title;
    $post_date     = date('Y-m-d H:i:s', $timestamp);
    $post_category = array($_POST['category']);
    $post_status   = isset($_POST['publish']) ? "publish" : "draft";
    $tags_input    = $_POST['tags'];

    return compact('post_content', 'post_title', 'post_date', 'post_category', 'post_status', 'tags_input');
  }

  return false;
}

function cpm_write_thumbnail($input, $target_filename, $do_rebuild = false) {
  global $cpm_config;

  $write_targets = array();
  foreach ($cpm_config->separate_thumbs_directory_defined as $type => $value) {
    if ($value) {
      if ($cpm_config->thumbs_directory_writable[$type]) {
        $write_targets[] = ABSPATH . $cpm_config->properties[$type . "_comic_folder"] . '/' . $target_filename;
      }
    }
  }

  if (count($write_targets) > 0) {
    if (!$do_rebuild) {
      if (file_exists($input)) {
        if (file_exists($target)) {
          if (filemtime($input) > filemtime($target)) {
            $do_rebuild = true;
          }
        } else {
          $do_rebuild = true;
        }
      }
    }

    if ($do_rebuild) {
      switch ($cpm_config->get_scale_method()) {
        case CPM_SCALE_NONE:
          return;
        case CPM_SCALE_IMAGEMAGICK:
          foreach ($write_targets as $target) {
            $convert_to_jpeg_thumb = escapeshellcmd(
              "convert \"${input}\" -filter Lanczos -resize " . $cpm_config->properties['archive_comic_width'] . "x -quality " . $cpm_config->properties['thumbnail_quality'] . " \"${target}\"");

            exec($convert_to_jpeg_thumb);
          }
          return true;
        case CPM_SCALE_GD:
          list ($width, $height) = getimagesize($input);
          $archive_comic_height = ($cpm_config->properties['archive_comic_width'] * $height) / $width;

          $pathinfo = pathinfo($input);

          $thumb_image = imagecreatetruecolor($cpm_config->properties['archive_comic_width'], $archive_comic_height);
          switch(strtolower($pathinfo['extension'])) {
            case "jpg":
            case "jpeg":
              $comic_image = imagecreatefromjpeg($input);
              break;
            case "gif":
              $comic_image = imagecreatefromgif($input);
              break;
            case "png":
              $comic_image = imagecreatefrompng($input);
              break;
          }

          imagecopyresampled($thumb_image, $comic_image, 0, 0, 0, 0, $cpm_config->properties['archive_comic_width'], $archive_comic_height, $width, $height);

          foreach ($write_targets as $target) {
            imagejpeg($thumb_image, $target, $cpm_config->properties['thumbnail_quality']);
          }

          return true;
      }
    }
  }

  return false;
}

/**
 * Handle uploading a file.
 */
function handle_file_upload($key, $path) {
  global $cpm_config;

  if (is_uploaded_file($_FILES[$key]['tmp_name'])) {
    if ($_FILES[$key]['error'] != 0) {
      switch ($_FILES[$key]['error']) {
        case UPLOAD_ERR_INI_SIZE:
          $cpm_config->warnings[] = "<strong>The file you uploaded was too large.</strong>  The max allowed filesize for uploads to your server is " . ini_get('upload_max_filesize') . ".";
          break;
        default:
          $cpm_config->warnings[] = "<strong>There was an error in uploading.</strong>  The <a href='http://php.net/manual/en/features.file-upload.errors.php'>PHP upload error code</> was " . $_FILES[$key]['error'] . ".";
          break;
      }
    } else {
      if (strpos($_FILES[$key]['name'], ".zip") !== false) {
        $invalid_files = array();
        $posts_created = array();
        $duplicate_posts = array();

        if (is_resource($zip = zip_open($_FILES[$key]['tmp_name']))) {
          while ($zip_entry = zip_read($zip)) {
            $comic_file = zip_entry_name($zip_entry);
            if (($result = breakdown_comic_filename($comic_file)) !== false) {
              extract($result, EXTR_PREFIX_ALL, 'filename');
              $target_path = $cpm_config->path . '/' . zip_entry_name($zip_entry);
              if (zip_entry_open($zip, $zip_entry, "r")) {
                file_put_contents($target_path,
                                  zip_entry_read($zip_entry,
                                                 zip_entry_filesize($zip_entry)));

                if (!isset($_POST['no-thumbnails'])) {
                  $wrote_thumbnail = cpm_write_thumbnail($target_path, zip_entry_name($zip_entry));
                }

                zip_entry_close($zip_entry);

                if (($post_hash = generate_post_hash($filename_date, $filename_converted_title)) !== false) {
                  extract($post_hash);
                  $ok_to_create_post = true;
                  if (!isset($_POST['no_duplicate_check'])) {
                    $ok_to_create_post = (($post_id = post_exists($post_title, $post_content, $post_date)) == 0);
                  }

                  if ($ok_to_create_post) {
                    if (!is_null($post_id = wp_insert_post($post_hash))) {
                      $posts_created[] = get_post($post_id, ARRAY_A);
                    }
                  } else {
                    $duplicate_posts[] = $comic_file;
                  }
                } else {
                  $cpm_config->warnings[] = "There was an error in the post time for ${comic_file}.";
                }
                if ($wrote_thumbnail) {
                  $cpm_config->messages[] = "Wrote thumbnail for " . zip_entry_name($zip_entry) . ".";
                }
              }
            } else {
              $invalid_files[] = $comic_file;
            }
          }
          zip_close($zip);
        }

        if (count($posts_created) > 0) {
          $post_links = array();
          foreach ($posts_created as $comic_post) {
            $post_links[] = "<li>(" . $comic_post['post_date'] . ") " . generate_view_edit_post_links($comic_post);
          }

          $cpm_config->messages[] = "New posts created.  View them from the links below: <ul>" . implode("", $post_links) . "</ul>";
        } else {
          $cpm_config->messages[] = "No new posts created.";
        }

        if (count($invalid_files) > 0) {
          $cpm_config->messages[] = "The following filenames were invalid: " . implode(", ", $invalid_files);
        }

        if (count($duplicate_posts) > 0) {
          $cpm_config->messages[] = "The following files would have created duplicate posts: " . implode(", ", $duplicate_posts);
        }
      } else {
        if (($result = breakdown_comic_filename($_FILES[$key]['name'])) !== false) {
          extract($result, EXTR_PREFIX_ALL, "filename");
          move_uploaded_file($_FILES[$key]['tmp_name'], $cpm_config->path . '/' . $_FILES[$key]['name']);

          if (!isset($_POST['no-thumbnails'])) {
            $wrote_thumbnail = cpm_write_thumbnail($cpm_config->path . '/' . $_FILES[$key]['name'], $_FILES[$key]['name']);
          }

          if (isset($_POST['new_post'])) {
            if (($post_hash = generate_post_hash($filename_date, $filename_converted_title)) !== false) {
              extract($post_hash);

              $ok_to_create_post = true;
              if (!isset($_POST['no_duplicate_check'])) {
                $ok_to_create_post = (($post_id = post_exists($post_title, $post_content, $post_date)) == 0);
              }

              if ($ok_to_create_post) {
                if (!is_null($post_id = wp_insert_post($post_hash))) {
                  $post_info = get_post($post_id, ARRAY_A);
                  $cpm_config->messages[] = "Post created for " . $_FILES[$key]['name'] . ". " . generate_view_edit_post_links($post_info);
                }
              } else {
                $post_info = get_post($post_id, ARRAY_A);

                $cpm_config->messages[] = "File " . $_FILES[$key]['name'] . " uploaded, but a post for this comic already exists. " . generate_view_edit_post_links($post_info);
              }
            } else {
              $cpm_config->warnings[] = "There was an error in the post time for filename <strong>" . $_FILES[$key]['name'] . " - " .  $_POST['time'] . "</strong>";
            }
          } else {
            $cpm_config->messages[] = "Comic " . $_FILES[$key]['name'] . " uploaded.";
          }
          
          if ($wrote_thumbnail) {
            $cpm_config->messages[] = "Wrote thumbnail for " . $_FILES[$key]['name'] . ".";
          }
        } else {
          $cpm_config->warnings[] = "There was an error in the filename <strong>" . $_FILES[$key]['name'] . "</strong>";
        }
      }
    }
  }
}

/**
 * Show the Post Body Template.
 */
function cpm_show_post_body_template($width = 435) {
  global $cpm_config; ?>

  Post body template:<br />
  <textarea name="post-text" rows="4" style="width: <?= $width ?>px"><?php echo $cpm_config->properties['default_post_content'] ?></textarea>
  <br />
  (<em>Available wildcards:</em>)
  <ul>
    <li><strong>{category}</strong>: The name of the category</li>
    <li><strong>{date}</strong>: The date of the comic (ex: <em><?php echo date("F j, Y", time()) ?></em>)</li>
    <li><strong>{title}</strong>: The title of the comic</li>
  </ul>
  <?php
}

/**
 * Write all of the styles and scripts.
 */
function cpm_write_global_styles_scripts() {
  ?> <script type="text/javascript">
/**
 * hide/show the new post holder box depending on the status of the checkbox.
 */
function hide_show_new_post_holder(which) {
  if ($(which + '-checkbox').checked) {
    $(which + '-holder').show();
  } else {
    $(which + '-holder').hide();
  }
}

function hide_show_div_on_checkbox(div, checkbox, flip_behavior) {
  if ($(checkbox) && $(div)) {
    ok = (flip_behavior) ? !$(checkbox).checked : $(checkbox).checked;
    if (ok) {
      $(div).show();
    } else {
      $(div).hide();
    }
  }
}

/**
 * Show the preview image for deleting an image.
 */
function change_image_preview() {
  var which = $F('delete-comic-dropdown');
  $('image-preview').innerHTML = '<img src="' + which + '" width="420" />';
}

var current_file_index = 0;

/**
 * Add a file upload field.
 */
function add_file_upload() {
  var field  = "<div id=\"upload-holder-" + current_file_index + "\">";
      field += "File: <input type=\"file\" name=\"upload-" + current_file_index + "\" />";
      field += " [<a href=\"#\" onclick=\"Element.remove('upload-holder-" + current_file_index + "')\">remove</a>]";
      field += "</div>";
  Element.insert('multiple-file-upload', { bottom: field });
  current_file_index++;
}

// page startup code
Event.observe(window, 'load', function() {
  if ($('multiple-new-post-checkbox')) {
    Event.observe('multiple-new-post-checkbox', 'click', function() { hide_show_new_post_holder("multiple-new-post") });
    hide_show_new_post_holder("multiple-new-post");
    add_file_upload();

    hide_show_div_on_checkbox('override-title-holder', 'override-title');
    hide_show_div_on_checkbox('thumbnail-write-holder', 'no-thumbnails', true);
  }

  
  if ($('count-missing-posts-clicker')) {
    hide_show_div_on_checkbox('override-title-holder', 'override-title');
    hide_show_div_on_checkbox('thumbnail-write-holder', 'no-thumbnails', true);

    Event.observe('count-missing-posts-clicker', 'click', function() {
      $('missing-posts-display').innerHTML = "...counting...";

      new Ajax.Request('<?php echo $_SERVER['REQUEST_URI'] ?>',
                       {
                         parameters: {
                           action: "count-missing-posts"
                         },
                         onSuccess: function(transport) {
                           if (transport.responseText.match(/missing-posts>(.*)<\/missing-posts/)) {
                             $('missing-posts-display').innerHTML = RegExp.$1;
                           }
                           if (RegExp.$1 == 0) {
                             $('create-missing-posts-holder').innerHTML = "<p><strong>You're not missing any posts!</strong></p>";
                           }
                         }
                       }
                      );
      return false;
    });
  }

  if ($('image-preview')) { change_image_preview(); }

  // just in case...

  $('cpm-right-column').style.minHeight = $('cpm-left-column').offsetHeight + "px";
});
  </script>
  <style type="text/css">
div#cpm-container {
  padding: 10px;
  padding-bottom: 0
}

div#cpm-container h1 {
  margin-top: 0;
  margin-bottom: 5px;
}

div#cpm-container h2 {
  margin-top: 0;
  margin-bottom: 4px;
}

div#cpm-container h3 {
  margin-top: 0
}

div#cpm-container em {
  font-style: oblique
}

div#cpm-container div#cpm-left-column {
  position: absolute;
  width: 320px;
  margin-top: 5px;
}

div#cpm-container div#comicpress-details {
  border: solid #acb 1px;
  background-color: #dfe;
  padding: 10px;
}

div#cpm-container div#comicpress-help {
  border: solid #cab 1px;
  background-color: #fde;
  padding: 10px;
  margin-top: 10px;
}

div#cpm-container div#config-editor {
  border: solid #abc 1px;
  background-color: #fed;
  padding: 10px;
  margin-top: 10px;
}

div#cpm-container div#cpm-right-column {
 padding-left: 330px;
 width: 470px;
}

div#cpm-container div.activity-box {
  border: solid #999 1px;
  padding: 10px;
  margin-top: 10px;
}

div#cpm-container div.top-activity-box {
  margin-top: 0;
}

div#cpm-container div#cpm-footer {
  color: #777;
  margin-top: 10px;
  text-align: left
}

div#cpm-container div#new-post-holder,
div#cpm-container div#multiple-new-post-holder {
  border: solid #444 1px;
  padding: 5px;
  width: 435px;
  margin-top: 5px;
}

div#image-preview {
  margin: 5px;
  padding: 5px;
  background-color: #777;
  border: solid black 2px
}

form#config-editor {
  overflow: hidden
}

form#config-editor span.config-title {
  width: 130px;
  display: block;
  position: absolute;
}

form#config-editor span.config-field {
  width: 145px;
  margin-left: 130px;
  display: block;
}

form#config-editor input.update-config {
  clear: both;
  width: 295px;
}

form span.form-title {
  width: 210px;
  display: block;
  position: absolute;
  height: 30px;
  font-weight: bold
}

form span.form-field {
  width: 200px;
  display: block;
  margin-left: 220px;
  height: 30px;
}

form span.form-field input {
  font-size: 12px;
  border: solid black 1px;
  padding: 1px;
}

#cpm-container h2 {
  padding-right: 0;
}

#comicpress-details h3 {
  color: #7f958a;
  border-bottom: 1px solid #acb;
}

#comicpress-help h2 {
  color: #b17f98;
  border-color: #cab;
}
</style>

<!--[if lte IE 6]>
<style type="text/css">
div#cpm-container div#cpm-left-column { margin-top: 0 }
</style>
<![endif]-->
<?php }

/**
 * Read information about the current installation.
 */
function cpm_read_information() {
  global $cpm_config;

  $cpm_config->config_method = read_current_theme_comicpress_config();
  $cpm_config->config_filepath = get_functions_php_filepath();
  $cpm_config->separate_thumbs_directory_defined_config = can_write_comicpress_config($cpm_config->config_filepath);

  $cpm_config->path = get_comic_folder_path();
  $cpm_config->plugin_path = substr(__FILE__, strlen($_SERVER['DOCUMENT_ROOT']));

  foreach (array_keys($cpm_config->separate_thumbs_directory_defined) as $type) {
    $cpm_config->separate_thumbs_directory_defined[$type] = ($cpm_config->properties['comic_folder'] != $cpm_config->properties[$type . '_comic_folder']);
  }
}

/**
 * Handle any warnings that have been invoked.
 */
function cpm_handle_warnings() {
  global $cpm_config;

    // display informative messages to the use
    // TODO: remove separate arrays and tag messages based on an enum value
    foreach (array(
      array($cpm_config->messages, "The operation you just performed returned the following:"),
      array($cpm_config->warnings, "Your configuration has some potential problems:"),
      array($cpm_config->errors,   "The following problems were found in your configuration:")
    ) as $info) {
      list($messages, $header) = $info;
      if (count($messages) > 0) { ?>
        <h2 style="padding-right:0;"><?php echo $header ?></h2>
        <ul>
          <?php foreach ($messages as $message) { ?>
            <li><?php echo $message ?></li>
          <?php } ?>
        </ul>
      <?php }
    }

    // errors are fatal.
    if (count($cpm_config->errors) > 0) {
      $current_theme_info = get_theme(get_current_theme());
      ?>
      <p>You must fix the problems above before you can proceed with managing your ComicPress installation.</p>
      <p><strong>Details:</strong></p>
      <ul>
        <li><strong>Current ComicPress theme directory:</strong> <?php echo $current_theme_info['Template Dir'] ?></li>
        <li><strong>Available categories:</strong>
          <ul>
            <?php foreach (get_categories() as $category) { ?>
              <li><strong><?php echo $category->category_nicename ?></strong> - <?php echo $category->cat_ID ?></li>
            <?php } ?>
          </ul>
        </li>
      </ul>
      <?php

      if ($cpm_config->config_method == "comicpress-config.php") {
        if ($cpm_config->separate_thumbs_directory_defined_config) {
          echo cpm_manager_edit_config();
        } else { ?>
          <p>
            <strong>You cannot update your comicpress-config.php file through the ComicPress Manager interface.</strong> Check to make sure the permissions on <?= $current_theme_info['Template Dir'] ?> and the comicpress-config.php are set so that the Webserver can write to them.
          </p>
        <?php }
      }

      return false;
    }
  return true;
}

/**
 * Handle all actions.
 */
function cpm_handle_actions() {
  global $cpm_config;

  $get_posts_string = "numberposts=9999&post_status=&category=";
  if (is_array($cpm_config->properties['comiccat'])) {
    $get_posts_string .= implode(",", $cpm_config->properties['comiccat']);
  } else {
    $get_posts_string .= $cpm_config->properties['comiccat'];
  }
  
  //
  // take actions based upon $_POST['action']
  //
  if (isset($_POST['action'])) {
    switch (strtolower($_POST['action'])) {
      // upload a single comic file
      case "multiple-upload-file":
        foreach ($_FILES as $name => $info) {
          if (strpos($name, "upload-") !== false) {
            handle_file_upload($name, $cpm_config->path);
          }
        }
        break;
      // count the number of missing posts
      case "count-missing-posts":
        // TODO: handle different comic categories differently, this is still too geared
        // toward one blog/one comic...
        // TODO: handle DST issues!
        $all_post_dates = array();

        foreach (get_posts($get_posts_string) as $comic_post) {
          $all_post_dates[] = date("Y-m-d", strtotime($comic_post->post_date));
        }
        $all_post_dates = array_unique($all_post_dates);

        $missing_comic_count = 0;
        foreach ($cpm_config->comic_files as $comic_file) {
          $comic_file = pathinfo($comic_file, PATHINFO_BASENAME);
          if (($result = breakdown_comic_filename($comic_file)) !== false) {
            if (!in_array($result['date'], $all_post_dates)) {
              var_dump($comic_file);
              $missing_comic_count++;
            }
          }
        }

        echo "<missing-posts>${missing_comic_count}</missing-posts>";
        // AJAX call, exit after complete
        return;
        break;
      // create all missing posts
      case "create-missing-posts":
        $all_post_dates = array();
        foreach (get_posts($get_posts_string) as $comic_post) {
          $all_post_dates[] = date("Y-m-d", strtotime($comic_post->post_date));
        }
        $all_post_dates = array_unique($all_post_dates);

        $posts_created = array();

        foreach ($cpm_config->comic_files as $comic_file) {
          $comic_file = pathinfo($comic_file, PATHINFO_BASENAME);
          if (($result = breakdown_comic_filename($comic_file)) !== false) {
            extract($result, EXTR_PREFIX_ALL, 'filename');

            if (!in_array($result['date'], $all_post_dates)) {
              if (($post_hash = generate_post_hash($filename_date, $filename_converted_title)) !== false) {
                if (!is_null($post_id = wp_insert_post($post_hash))) {
                  $posts_created[] = get_post($post_id, ARRAY_A);

                  if (!isset($_POST['no-thumbnails'])) {
                    if (cpm_write_thumbnail($cpm_config->path . '/' . $comic_file, $comic_file)) {
                      $cpm_config->messages[] = "Wrote thumbnail for " . $comic_file . ".";
                    }
                  }
                }
              } else {
                $cpm_config->warnings[] = "There was an error in the post time for ${comic_file}.";
              }
            }
          }
        }

        if (count($posts_created) > 0) {
          $post_links = array();
          foreach ($posts_created as $comic_post) {
            $post_links[] = "<li>(" . $comic_post['post_date'] . ") " . generate_view_edit_post_links($comic_post);
          }

          $cpm_config->messages[] = "New posts created.  View them from the links below: <ul>" . implode("", $post_links) . "</ul>";
        } else {
          $cpm_config->messages[] = "No new posts created.";
        }
        break;
      case "delete-comic-and-post":
        $comic_file = pathinfo($_POST['comic'], PATHINFO_BASENAME);

        if (file_exists($cpm_config->path . '/' . $comic_file)) {
          if (($result = breakdown_comic_filename($comic_file)) !== false) {
            extract($result, EXTR_PREFIX_ALL, 'filename');

            $all_possible_posts = array();
            foreach (get_posts($get_posts_string) as $comic_post) {
              if (date("Y-m-d", strtotime($comic_post->post_date)) == $filename_date) {
                $all_possible_posts[] = $comic_post->ID;
              }
            }

            if (count($all_possible_posts) > 1) {
              $cpm_config->messages[] = "There are multiple posts (" . implode(", ", $all_possible_posts) . ") with the date ${filename_date} in the comic categories.  Please manually delete the posts.";
            } else {
              $delete_targets = array($cpm_config->path . '/' . $comic_file);
              foreach ($cpm_config->thumbs_directory_writable as $type => $value) {
                $delete_targets[] = ABSPATH . $cpm_config->properties[$type . "_comic_folder"] . '/' . $comic_file;
              }
              foreach ($delete_targets as $target) { @unlink($target); }

              if (count($all_possible_posts) == 0) {
                $cpm_config->messages[] = "<strong>${comic_file} deleted</strong>.  No matching posts found.  Any associated thumbnails were also deleted.";
              } else {
                wp_delete_post($all_possible_posts[0]);
                $cpm_config->messages[] = "<strong>${comic_file} and post " . $all_possible_posts[0] . " deleted.</strong>  Any associated thumbnails were also deleted.";
              }
              $cpm_config->comic_files = glob($cpm_config->path . "/*");
            }
          }
        }
        break;
      case "update-config":
        if ($cpm_config->config_method == "comicpress-config.php") {
          foreach (array_keys($cpm_config->properties) as $property) {
            if (isset($_POST[$property])) {
              $cpm_config->properties[$property] = $_POST[$property];
            }
          }

          if (!is_null($cpm_config->config_filepath)) {
            if (write_comicpress_config_functions_php($cpm_config->config_filepath)) {
              $cpm_config->config_method = read_current_theme_comicpress_config();
              $cpm_config->path = get_comic_folder_path();
              $cpm_config->plugin_path = substr(__FILE__, strlen($_SERVER['DOCUMENT_ROOT']));

              cpm_read_information();

              check_comicpress_config();

              $cpm_config->messages[] = "Configuration updated and original config backed up.";
            } else {
              $relative_path = substr(realpath($filepath), strlen(realpath($_SERVER['DOCUMENT_ROOT'])));
              $cpm_config->warnings[] = "<strong>Configuration not updated</strong>, check the permissions of ${relative_path} and the theme folder.  They should be writable by the Webserver process.";
            }
          }
        }
        break;
      case "generate-thumbnails":
        foreach ($_POST['comics'] as $comic) {
          $comic_file = pathinfo($comic, PATHINFO_BASENAME);

          if (cpm_write_thumbnail($cpm_config->path . '/' . $comic_file, $comic_file)) {
            $cpm_config->messages[] = "Wrote thumbnail for " . $comic_file . ".";
          }
        }
        break;
    }
  }
}

/**
 * Show the details of the current setup.
 */
function cpm_show_comicpress_details() {
  global $cpm_config;

  ?>
    <!-- ComicPress details -->
    <div id="comicpress-details">
      <h2 style="padding-right: 0">ComicPress Details</h2>
      <ul style="padding-left: 30px">
        <li><strong>Configuration method:</strong>
          <?php if ($cpm_config->config_method == "comicpress-config.php") {
            if ($cpm_config->separate_thumbs_directory_defined_config) {
              ?><a href="?page=config"><?php echo $cpm_config->config_method ?></a> (click to edit)
            <?php } else { ?>
              <?php echo $cpm_config->config_method ?> (unable to edit, check permissions)
            <?php } ?>
          <?php } else { ?>
            <?php echo $cpm_config->config_method ?>
          <?php } ?>
        </li>
        <li><strong>Comics folder:</strong> <?php echo $cpm_config->properties['comic_folder'] ?><br />
            (<?php echo count($cpm_config->comic_files) ?> comic<?php echo (count($cpm_config->comic_files) != 1) ? "s" : "" ?> in folder)</li>
        <li><strong>Archive folder:</strong> <?php echo $cpm_config->properties['archive_comic_folder'] ?>
          <?php if (
            ($cpm_config->get_scale_method() != CPM_SCALE_NONE) &&
            ($cpm_config->properties['archive_generate_thumbnails'] !== false) &&
            ($cpm_config->separate_thumbs_directory_defined['archive']) &&
            ($cpm_config->thumbs_directory_writable['archive'])
          ) { ?>
            (<em>generating</em>)
          <?php } ?>
        </li>
        <li><strong>RSS feed folder:</strong> <?php echo $cpm_config->properties['rss_comic_folder'] ?>
          <?php if (
            ($cpm_config->get_scale_method() != CPM_SCALE_NONE) &&
            ($cpm_config->properties['rss_generate_thumbnails'] !== false) &&
            ($cpm_config->separate_thumbs_directory_defined['rss']) &&
            ($cpm_config->thumbs_directory_writable['rss'])
          ) { ?>
            (<em>generating</em>)
          <?php } ?>
        </li>
        <li><strong>Comic categor<?php echo (is_array($cpm_config->properties['comiccat']) && count($cpm_config->properties['comiccat']) != 1) ? "ies" : "y" ?>:</strong>
          <?php if (is_array($cpm_config->properties['comiccat'])) { ?>
            <ul>
              <?php foreach ($cpm_config->properties['comiccat'] as $cat_id) { ?>
                <li><a href="<?php echo get_category_link($cat_id) ?>"><?php echo get_cat_name($cat_id) ?></a> (ID <?php echo $cat_id ?>)</li>
              <?php } ?>
            </ul>
          <?php } else { ?>
            <a href="<?php echo get_category_link($cpm_config->properties['comiccat']) ?>"><?php echo $cpm_config->comic_category_info['name'] ?></a> (ID <?php echo $cpm_config->properties['comiccat'] ?>)
          <?php } ?>
        </li>
        <li><strong>Blog category:</strong> <a href="<?php echo get_category_link($cpm_config->properties['blogcat']) ?>" ?>
            <?php echo $cpm_config->blog_category_info['name'] ?></a> (ID <?php echo $cpm_config->properties['blogcat'] ?>)</li>
        <li><strong>PHP Version:</strong> <?= phpversion() ?>
            <?php if (substr(phpversion(), 0, 3) < 5.2) { ?>
              (<a href="http://gophp5.org/hosts">upgrade strongly recommended</a>)
            <?php } ?>
        </li>
        <li>
          <strong>Theme Directory:</strong> <?php $theme_info = get_theme(get_current_theme()); echo $theme_info['Template'] ?>
        </li>
      </ul>
    </div>
  <?php
}


/**
 * Show the config editor.
 */
function cpm_manager_edit_config() {
  global $cpm_config;
  ob_start(); ?>

  <form action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post" id="config-editor">
    <input type="hidden" name="action" value="update-config" />

    <?php foreach (array(
      array("Comic category", "comiccat", "category"),
      array("Blog category", "blogcat", "category"),
      array("Comic Folder", "comic_folder", "directory"),
      array("RSS Comic Folder", "rss_comic_folder", "directory"),
      array("Archive Comic Folder", "archive_comic_folder", "directory"),
      array("Archive Comic Width", "archive_comic_width", "integer"),
      array("Blog Post Count", "blog_postcount", "integer")
    ) as $field_info) {
      list($title, $field, $type) = $field_info;

      switch($type) {
        case "category": ?>
          <span class="config-title"><?= $title ?>:</span>
          <span class="config-field"><select name="<?= $field ?>">
                           <?php foreach (get_all_category_ids() as $cat_id) {
                             $category = get_category($cat_id); ?>
                             <option value="<?php echo $category->cat_ID ?>"
                                     <?php echo ($cpm_config->properties[$field] == $cat_id) ? " selected" : "" ?>><?php echo $category->cat_name; ?></option>
                           <?php } ?>
                         </select></span>
          <?php break;
        case "directory":
        case "integer": ?>
          <span class="config-title"><?= $title ?>:</span>
          <span class="config-field"><input type="text" name="<?= $field ?>" size="20" value="<?php echo $cpm_config->properties[$field] ?>" /></span>
          <?php break;
      }
    } ?>
    <input class="update-config" type="submit" value="Update Config" />
  </form>

  <?php return ob_get_clean();
}

/**
 * Show the footer.
 */
function cpm_show_footer() { ?>
  <div id="cpm-footer">
    <a href="http://claritycomic.com/comicpress-manager/" target="_new">ComicPress Manager</a> is built for the <a href="http://www.mindfaucet.com/comicpress/" target="_new">ComicPress</a> theme | Copyright 2008 <a href="mailto:jcoswell@coswellproductions.org?Subject=ComicPress Manager Comments">John Bintz</a> | Released under the GNU GPL | Version 0.6
  </div>
<?php }

?>