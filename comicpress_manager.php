<?php
/*
Plugin Name: ComicPress Manager
Plugin URI: http://claritycomic.com/comicpress-manager/
Description: Manage the comics within a <a href="http://www.mindfaucet.com/comicpress/">ComicPress</a> theme installation.
Version: 0.9.5
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
    'default_override_title' => '',
    'default_post_tags' => "",
    'archive_generate_thumbnails' => true,
    'rss_generate_thumbnails'     => true,
    'thumbnail_quality'           => 80,

    // ...and leave these alone!
    'comic_folder' => 'comics',
    'comiccat'     => '1',
    'blogcat'      => '2',
    'rss_comic_folder' => 'comics',
    'archive_comic_folder' => 'comics',
    'archive_comic_width' => '380',
    'blog_postcount' => '10'
  );

  // if you have a non-standard WP setup, you'll probably need to set
  // the absolute path to the folder where your site's index.php file
  // is located.  By default, this is:
  //
  // $_SERVER['DOCUMENT_ROOT'] . parse_url(get_bloginfo('url'), PHP_URL_PATH)

  var $comics_site_root = null;

  var $warnings, $messages, $errors, $detailed_warnings, $show_config_editor;
  var $config_method, $config_filepath, $path, $plugin_path;
  var $comic_files, $blog_category_info, $comic_category_info;
  var $scale_method_cache, $can_write_config;

  var $separate_thumbs_folder_defined = array('rss' => null, 'archive' => null);
  var $thumbs_folder_writable = array('rss' => null, 'archive' => null);

  function get_scale_method() {
    if (!isset($this->scale_method_cache)) {
      $this->scale_method_cache = CPM_SCALE_NONE;
      if (($result = @shell_exec("which convert")) !== "") {
        $this->scale_method_cache = CPM_SCALE_IMAGEMAGICK;
      }
      if (extension_loaded("gd")) {
        $this->scale_method_cache = CPM_SCALE_GD;
      }
    }
    return $this->scale_method_cache;
  }
}

$default_comicpress_config_file = explode("\n", '<?' . 'php' . <<<ENDPHP

//COMIC CATEGORY - the WordPress ID of your comic category (default "1").
\$comiccat = "1";

//BLOG CATEGORY - the WordPress ID of your blog category (default "2").
\$blogcat = "2";

//COMIC FOLDER - the folder your comics files are located in (default "comics")
\$comic_folder = "comics";

//RSS COMIC FOLDER - the folder your comic files are in for the RSS feed (default "comics").
\$rss_comic_folder = "comics";

//ARCHIVE COMIC FOLDER - the folder your comic files are in for your archive pages (default "comics").
\$archive_comic_folder = "comics";

//ARCHIVE COMIC WIDTH - the width your comics will appear on archive or search results (default "380").
\$archive_comic_width = "380";

//BLOG POSTCOUNT - the number of blog entries to appear on the home page (default "10").
\$blog_postcount = "10";

ENDPHP
. '?>');

$cpm_config = new ComicPressConfig();

add_action("admin_menu", "cpm_add_pages");
add_action("edit_form_advanced", "cpm_show_comic");
add_action("add_category_form_pre", "cpm_comicpress_categories_warning");

function cpm_comicpress_categories_warning() {
  if (count(get_all_category_ids()) < 2) {
    echo '<div style="margin: 10px; padding: 5px; background-color: #440008; color: white; border: solid #a00 1px">';
    echo __("Remember, you need at least two categories defined in order to use ComicPress.", 'comicpress-manager');
    echo '</div>';
  }
}

/**
 * Add pages to the admin interface and load necessary JavaScript libraries.
 * Also read in the configuration and handle any POST actions.
 */
function cpm_add_pages() {
  global $access_level;

  wp_enqueue_script('editor');
  wp_enqueue_script('wp_tiny_mce');
  wp_enqueue_script('prototype');

  if (!isset($access_level)) { $access_level = 10; }

  $plugin_title = __("ComicPress Manager", 'comicpress-manager');

  add_menu_page($plugin_title, __("ComicPress", 'comicpress-manager'), $access_level, __FILE__, "cpm_manager_index");
  add_submenu_page(__FILE__, $plugin_title, __("Upload", 'comicpress-manager'), $access_level, __FILE__, 'cpm_manager_index');
  add_submenu_page(__FILE__, $plugin_title, __("Import", 'comicpress-manager'), $access_level, __FILE__ . '-import', 'cpm_manager_import');
  add_submenu_page(__FILE__, $plugin_title, __("Generate Thumbnails", 'comicpress-manager'), $access_level, __FILE__ . '-thumbnails', 'cpm_manager_thumbnails');
  add_submenu_page(__FILE__, $plugin_title, __("Change Dates", 'comicpress-manager'), $access_level, __FILE__ . '-dates', 'cpm_manager_dates');
  add_submenu_page(__FILE__, $plugin_title, __("Delete", 'comicpress-manager'), $access_level, __FILE__ . '-delete', 'cpm_manager_delete');
  add_submenu_page(__FILE__, $plugin_title, __("Config", 'comicpress-manager'), $access_level, __FILE__ . '-config', 'cpm_manager_config');

  cpm_read_information_and_check_config();
  cpm_handle_actions();
}

/**
 * Show the Post Editor.
 * @param integer $width The width in pixels of the text editor widget.
 */
function cpm_post_editor($width = 435) {
  global $cpm_config; ?>

  <span class="form-title"><?php _e("Category:", 'comicpress-manager') ?></span>
  <span class="form-field"><?php echo generate_comic_categories_options('category') ?></span>

  <span class="form-title"><?php _e("Time to post:", 'comicpress-manager') ?></span>
  <span class="form-field"><input type="text" name="time" value="<?php echo $cpm_config->properties['default_post_time'] ?>" size="10" /></span>

  <span class="form-title"><label for="publish"><?php _e("Publish post:", 'comicpress-manager') ?></label></span>
  <span class="form-field"><input id="publish" type="checkbox" name="publish" value="yes" checked /></span>

  <span class="form-title"><label for="no-duplicate-check"><?php _e("Don't check for duplicate posts:", 'comicpress-manager') ?></label></span>
  <span class="form-field"><input id="no-duplicate-check" type="checkbox" name="no_duplicate_check" value="yes" /></span>

  <?php
    if ($cpm_config->get_scale_method() != CPM_SCALE_NONE) {
      $thumbnail_writes = array();
      foreach ($cpm_config->separate_thumbs_folder_defined as $type => $value) {
        if ($value) {
          if ($cpm_config->thumbs_folder_writable[$type]) {
            if ($cpm_config->properties[$type . "_generate_thumbnails"] !== false) {
              $thumbnail_writes[] = $type;
            }
          }
        }
      }

      if (count($thumbnail_writes) > 0) { ?>
        <div id="thumbnail-write-holder">
          (<em><?php printf("You'll be writing thumbnails to: %s", implode(", ", $thumbnail_writes)) ?></em>)
        </div>

        <span class="form-title"><label for="no-thumbnails"><?php _e("Don't generate thumbnails:", 'comicpress-manager') ?></label></span>
        <span class="form-field"><input onclick="hide_show_div_on_checkbox('thumbnail-write-holder', this, true)" type="checkbox" name="no-thumbnails" id="no-thumbnails" value="yes" /></span>
      <?php } else { ?>
        <div id="thumbnail-write-holder">
          (<em><?php _e("You won't be generating any thumbnails", 'comicpress-manager') ?></em>)
        </div>
      <?php }
    }
  ?>

  <span class="form-title"><label for="override-title"><?php _e("Specify a title for all posts:", 'comicpress-manager') ?></label></span>
  <span class="form-field"><input onclick="hide_show_div_on_checkbox('override-title-holder', this)" type="checkbox" id="override-title" name="override-title" value="yes" /></span>

  <div id="override-title-holder">
    <span class="form-title"><?php _e("Title to use:", 'comicpress-manager') ?></span>
    <span class="form-field"><input type="text" name="override-title-to-use" value="<?php echo $cpm_config->properties['default_override_title'] ?>" /></span>
  </div>

  <span class="form-title"><?php _e("Tags:", 'comicpress-manager') ?></span>
  <span class="form-field"><input type="text" name="tags" value="<?php echo $cpm_config->properties['default_post_tags'] ?>" /></span>

  <?php cpm_show_post_body_template($width) ?>
<?php }

/**
 * Wrap the help text and activity content in the CPM page style.
 * @param string $help_content The content to show in the Help box.
 * @param string $activity_content The content to show in the Activity box.
 */
function cpm_wrap_content($help_content, $activity_content) {
  cpm_write_global_styles_scripts(); ?>

<div class="wrap">  
  <div id="cpm-container">
    <?php if (cpm_handle_warnings()) { ?>
      <!-- Header -->
      <?php cpm_show_manager_header() ?>

      <div id="cpm-left-column">
        <?php cpm_show_comicpress_details() ?>
        <?php if (!is_null($help_content)) { ?>
          <div id="comicpress-help">
            <h2 style="padding-right:0;"><?php _e("Help!", 'comicpress-manager') ?></h2>
            <?php echo $help_content ?>
          </div>
        <?php } ?>
      </div>

      <div id="cpm-right-column">
        <!-- Delete a comic and a post -->
        <div class="activity-box"><?php echo $activity_content ?></div>
      </div>
      <?php cpm_show_footer() ?>
  <?php } ?>
  </div>
</div>
<?php }

/**
 * The main manager screen.
 */
function cpm_manager_index() {
  global $cpm_config;

  $example_date = CPM_DATE_FORMAT;
  $example_date = preg_replace('#Y#', 'YYYY', $example_date);
  $example_date = preg_replace('#m#', 'MM', $example_date);
  $example_date = preg_replace('#d#', 'DD', $example_date);

  $example_real_date = date(CPM_DATE_FORMAT);

  ob_start(); ?>
    <p>
      <strong>
        <?php _e("ComicPress Manager manages your comics and your time.", 'comicpress-manager') ?>
      </strong>
      <?php _e("It makes uploading new comics, importing comics from a non-ComicPress setup, and batch uploading a lot of comics at once, very fast and configurable.", 'comicpress-manager') ?>
    </p>

    <p>
      <strong>
        <?php _e("ComicPress Manager also manages yours and your Website's sanity.", 'comicpress-manager') ?>
      </strong>

      <?php printf(__("It can check for misconfigured ComicPress setups, for incorrectly-named files (remember, it's <em>%s-single-comic-title.ext</em>) and for when you might be duplicating a post. You will also be shown which comic will appear with which blog post in the Post editor.", 'comicpress-manager'), $example_date) ?>
    </p>

    <p>
      <?php printf(__("<strong>Single comic titles</strong> are generated from the incoming filename.  If you've named your file <strong>%s-my-new-years-day.jpg</strong> and create a new post for the file, the post title will be <strong>My New Years Day</strong>.  This default should handle the majority of cases.  If a comic file does not have a title, the date in <strong>MM/DD/YYYY</strong> format will be used.", 'comicpress-manager'), $example_real_date) ?>
    </p>

    <p>
      <?php _e("<strong>Upload image files</strong> lets you upload multiple comics at a time, and add a default post body for each comic.", 'comicpress-manager') ?>
      <?php if (extension_loaded('zip')) { ?>
        <?php _e("You can <strong>upload a Zip file and create new posts</strong> from the files contained within the Zip file.", 'comicpress-manager') ?>
      <?php } else { ?>
        <?php _e("<strong>You can't upload a Zip file</strong> because you do not have the PHP <strong>zip</strong> extension installed.", 'comicpress-manager') ?>
      <?php } ?>
    </p>

    <p>
      <?php _e("Has ComicPress Manager saved you time and sanity?  <strong>Donate a few bucks to show your appreciation!</strong>", 'comicpress-manager') ?>
      <span style="display: block; text-align: center">
        <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
        <input type="hidden" name="cmd" value="_s-xclick">
        <input type="image" src="https://www.paypal.com/en_US/i/btn/x-click-but11.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
        <img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
        <input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----MIIHVwYJKoZIhvcNAQcEoIIHSDCCB0QCAQExggEwMIIBLAIBADCBlDCBjjELMAkGA1UEBhMCVVMxCzAJBgNVBAgTAkNBMRYwFAYDVQQHEw1Nb3VudGFpbiBWaWV3MRQwEgYDVQQKEwtQYXlQYWwgSW5jLjETMBEGA1UECxQKbGl2ZV9jZXJ0czERMA8GA1UEAxQIbGl2ZV9hcGkxHDAaBgkqhkiG9w0BCQEWDXJlQHBheXBhbC5jb20CAQAwDQYJKoZIhvcNAQEBBQAEgYByxAq7QPX6OfmfNgRELmuKJ+NHyr/nPUSHHc3tR8cSqNXnlOY6rRszKk2kFsYb0Yfl/uHMcZrqC4hkmTcabF6+aEjx/mumiW0g7uthf2kremO7SN4Ex0FVI+wgiEGB7zAzKSSNlv8v78yNLKk0q1rWNIjDTq+EjgMT/eKlll5dLDELMAkGBSsOAwIaBQAwgdQGCSqGSIb3DQEHATAUBggqhkiG9w0DBwQID4pJSyI4PY6AgbBqgnbCpYdKYbtlCsPi2zXiBbnweGefLMbtsS0jzVhEyjXnCBJnk9F2Ue+6euJgg9HjUjCvWjYr3Tf4HUKDlYK6CIWtQrUFmcC5ZMDPoCLqM4gziZmOSqLHohfB8ETOL3CHLhIAFDxaAygsoHTIAH0BT6bGGwdVC1UAGixQgf6cqiw+FlzrVbViu+GqgiSsPfKq5TLyoPPu2c3FmJpXdgyIpvOepfd+H9Oub4WBju1lQaCCA4cwggODMIIC7KADAgECAgEAMA0GCSqGSIb3DQEBBQUAMIGOMQswCQYDVQQGEwJVUzELMAkGA1UECBMCQ0ExFjAUBgNVBAcTDU1vdW50YWluIFZpZXcxFDASBgNVBAoTC1BheVBhbCBJbmMuMRMwEQYDVQQLFApsaXZlX2NlcnRzMREwDwYDVQQDFAhsaXZlX2FwaTEcMBoGCSqGSIb3DQEJARYNcmVAcGF5cGFsLmNvbTAeFw0wNDAyMTMxMDEzMTVaFw0zNTAyMTMxMDEzMTVaMIGOMQswCQYDVQQGEwJVUzELMAkGA1UECBMCQ0ExFjAUBgNVBAcTDU1vdW50YWluIFZpZXcxFDASBgNVBAoTC1BheVBhbCBJbmMuMRMwEQYDVQQLFApsaXZlX2NlcnRzMREwDwYDVQQDFAhsaXZlX2FwaTEcMBoGCSqGSIb3DQEJARYNcmVAcGF5cGFsLmNvbTCBnzANBgkqhkiG9w0BAQEFAAOBjQAwgYkCgYEAwUdO3fxEzEtcnI7ZKZL412XvZPugoni7i7D7prCe0AtaHTc97CYgm7NsAtJyxNLixmhLV8pyIEaiHXWAh8fPKW+R017+EmXrr9EaquPmsVvTywAAE1PMNOKqo2kl4Gxiz9zZqIajOm1fZGWcGS0f5JQ2kBqNbvbg2/Za+GJ/qwUCAwEAAaOB7jCB6zAdBgNVHQ4EFgQUlp98u8ZvF71ZP1LXChvsENZklGswgbsGA1UdIwSBszCBsIAUlp98u8ZvF71ZP1LXChvsENZklGuhgZSkgZEwgY4xCzAJBgNVBAYTAlVTMQswCQYDVQQIEwJDQTEWMBQGA1UEBxMNTW91bnRhaW4gVmlldzEUMBIGA1UEChMLUGF5UGFsIEluYy4xEzARBgNVBAsUCmxpdmVfY2VydHMxETAPBgNVBAMUCGxpdmVfYXBpMRwwGgYJKoZIhvcNAQkBFg1yZUBwYXlwYWwuY29tggEAMAwGA1UdEwQFMAMBAf8wDQYJKoZIhvcNAQEFBQADgYEAgV86VpqAWuXvX6Oro4qJ1tYVIT5DgWpE692Ag422H7yRIr/9j/iKG4Thia/Oflx4TdL+IFJBAyPK9v6zZNZtBgPBynXb048hsP16l2vi0k5Q2JKiPDsEfBhGI+HnxLXEaUWAcVfCsQFvd2A1sxRr67ip5y2wwBelUecP3AjJ+YcxggGaMIIBlgIBATCBlDCBjjELMAkGA1UEBhMCVVMxCzAJBgNVBAgTAkNBMRYwFAYDVQQHEw1Nb3VudGFpbiBWaWV3MRQwEgYDVQQKEwtQYXlQYWwgSW5jLjETMBEGA1UECxQKbGl2ZV9jZXJ0czERMA8GA1UEAxQIbGl2ZV9hcGkxHDAaBgkqhkiG9w0BCQEWDXJlQHBheXBhbC5jb20CAQAwCQYFKw4DAhoFAKBdMBgGCSqGSIb3DQEJAzELBgkqhkiG9w0BBwEwHAYJKoZIhvcNAQkFMQ8XDTA4MDYwMTEzNDcyMlowIwYJKoZIhvcNAQkEMRYEFAMHkZ9xatPkArDvEp3aZKB6lMpkMA0GCSqGSIb3DQEBAQUABIGAGoThKy0P1SIGjL4UkrOo/10KdiSf752IrDXepM9Ob8Qwm+JNV6jGbvz2pLg//2mDCiAPapSkxvoxymRZmT2E23M2KgSC6rNC0qcRnI25Fo3siDS44uGIW+HXWGVbKaYt2JVwBVj2682Z4NVnht17SsqQ98mlhInTUooh2pGBmmE=-----END PKCS7-----
        ">
        </form>
      </span>
    </p>
  <?php $help_content = ob_get_clean();

  ob_start(); ?>

  <h2 style="padding-right:0;">
    <?php if (extension_loaded('zip')) {
      _e("Upload Image &amp; Zip Files", 'comicpress-manager');
    } else {
      _e("Upload Image Files", 'comicpress-manager');
    } ?>
  </h2>
  <h3>&mdash; <?php _e("any existing files with the same name will be overwritten", 'comicpress-manager') ?></h3>

  <form onsubmit="$('submit').disabled=true" action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="multiple-upload-file" />
    <div id="multiple-file-upload">
    </div>
    <div style="text-align: center">
      [<a href="#" onclick="add_file_upload(); return false"><?php _e("Add file to upload", 'comicpress-manager') ?></a>]
    </div>

    <p>
      <?php _e("Destination for uploaded files:", 'comicpress-manager') ?>
      <select name="upload-destination" id="upload-destination">
        <option value="comic"><?php _e("Comics folder", 'comicpress-manager') ?></option>
        <option value="archive_comic"><?php _e("Archive folder", 'comicpress-manager') ?></option>
        <option value="rss_comic"><?php _e("RSS feed folder", 'comicpress-manager') ?></option>
      </select>
    </p>

    <?php if (count($cpm_config->comic_files) > 0) { ?>
      <div id="overwrite-existing-holder">
        <input type="checkbox" name="overwrite-existing-file-selector-checkbox" id="overwrite-existing-file-selector-checkbox" value="yes"
        <?php if (isset($_GET['replace'])) { echo "checked"; } ?> /> <label for="overwrite-existing-file-selector-checkbox"><?php _e("(<em>for single file uploads only</em>) Overwrite an existing file:", 'comicpress-manager') ?></label>
        <div id="overwrite-existing-file-selector-holder">
          <select name="overwrite-existing-file-choice">
            <?php foreach ($cpm_config->comic_files as $file) {
              $basename = pathinfo($file, PATHINFO_BASENAME);
              ?>
              <option value="<?php echo $basename ?>"
              <?php echo ($_GET['replace'] == $basename) ? "selected" : "" ?>><?php echo $basename ?></option>
            <?php } ?>
          </select>
        </div>
      </div>
    <?php } ?>

    <div id="upload-destination-holder">
      <p>
         <input id="multiple-new-post-checkbox" type="checkbox" name="new_post" value="yes" checked /> <label for="multiple-new-post-checkbox"><?php _e("Generate new posts for each uploaded file:", 'comicpress-manager') ?></label>
      </p>
      <div id="multiple-new-post-holder">
        <?php cpm_post_editor(420) ?>

        <div id="specify-date-holder">
          <div>
            <?php _e("(<em>for single file uploads only, can accept any date format parseable by <a href=\"http://us.php.net/strtotime\" target=\"php\">strtotime()</a></em>)", 'comicpress-manager') ?>
          </div>
          <span class="form-title"><?php _e("Use this date if missing from filename:", 'comicpress-manager') ?></span>
          <span class="form-field"><input type="text" id="override-date" name="override-date" /></span>
        </div>
      </div>
    </div>
    <br /><input id="submit" type="submit" value="<?php
      if (extension_loaded("zip")) {
        _e("Upload Image &amp; Zip Files", 'comicpress-manager');
      } else {
        _e("Upload Image Files", 'comicpress-manager');
      }
    ?>" style="width: 520px" />
  </form>
  <script type="text/javascript">
    Calendar.setup({
      inputField: "override-date",
      ifFormat: "%Y-%m-%d",
      button: "override-date"
    });
  </script>

  <?php

  $activity_content = ob_get_clean();

  cpm_wrap_content($help_content, $activity_content);
}

/**
 * The delete dialog.
 */
function cpm_manager_delete() {
  global $cpm_config;

  $help_content = __("<p><strong>Delete a comic file and the associated post, if found</strong> lets you delete a comic file and the post that goes with it.  Any thumbnails associated with the comic file will also be deleted.</p>", 'comicpress-manager');

  ob_start(); ?>
  
  <h2 style="padding-right:0;"><?php _e("Delete A Comic File &amp; Post (if found)", 'comicpress-manager') ?></h2>

  <?php if (count($cpm_config->comic_files) > 0) { ?>
    <form onsubmit="$('submit').disabled=true" action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post" onsubmit="return confirm('<?php _e("Are you sure?", 'comicpress-manager') ?>')">
      <input type="hidden" name="action" value="delete-comic-and-post" />

      <?php _e("Comic to delete:", 'comicpress-manager') ?><br />
        <select style="width: 445px" id="delete-comic-dropdown" name="comic" align="absmiddle" onchange="change_image_preview()">
          <?php foreach ($cpm_config->comic_files as $file) { ?>
            <option value="<?php echo substr($file, strlen($_SERVER['DOCUMENT_ROOT'])) ?>"><?php echo pathinfo($file, PATHINFO_BASENAME) ?></option>
          <?php } ?>
        </select><br />
      <div id="image-preview" style="text-align: center"></div>
      <p>
        <?php _e("<strong>NOTE:</strong> If more than one possible post is found, neither the posts nor the comic file will be deleted.  ComicPress Manager cannot safely resolve such a conflict.", 'comicpress-manager') ?>
      </p>
      <input type="submit" id="submit" value="<?php _e("Delete comic and post", 'comicpress-manager') ?>" style="width: 520px" />
    </form>
  <?php } else { ?>
    <p><?php _e("You haven't uploaded any comics yet.", 'comicpress-manager') ?></p>
  <?php } ?>
  <div id="editorcontainer" style="display: none"></div>

  <?php

  $activity_content = ob_get_clean();

  cpm_wrap_content($help_content, $activity_content);
}

/**
 * The generate thumbnails dialog.
 */
function cpm_manager_thumbnails() {
  global $cpm_config;

  $help_content = __("<p><strong>Generate thumbnails</strong> lets you regenerate thumbnails for comic files.  This is useful if an import is not functioning because it is taking too long, or if you've changed your size or quality settings for thumbnails.</p>", 'comicpress-manager');

  ob_start(); ?>
  
  <h2 style="padding-right:0;"><?php _e("Generate Thumbnails", 'comicpress-manager') ?></h2>

  <?php
    $ok_to_generate_thumbs = false;

    if ($cpm_config->get_scale_method() != CPM_SCALE_NONE) {
      foreach ($cpm_config->thumbs_folder_writable as $type => $value) {
        if ($value) {
          if ($cpm_config->separate_thumbs_folder_defined[$type] !== false) {
            if ($cpm_config->properties[$type . "_generate_thumbnails"] == true) {
              $ok_to_generate_thumbs = true; break;
            }
          }
        }
      }
    }

    if ($ok_to_generate_thumbs) {
      if (count($cpm_config->comic_files) > 0) { ?>
        <form onsubmit="$('submit').disabled=true" action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post">
          <input type="hidden" name="action" value="generate-thumbnails" />

          <p><?php printf(__("You'll be generating thumbnails that are %s pixels wide.", 'comicpress-manager'), $cpm_config->properties['archive_comic_width']) ?></p>

          <?php _e("Thumbnails to regenerate (<em>to select multiple comics, [Ctrl]-click on Windows &amp; Linux, [Command]-click on Mac OS X</em>):", 'comicpress-manager') ?>
          <br />
            <select style="height: auto; width: 445px" id="select-comics-dropdown" name="comics[]" size="<?php echo min(count($cpm_config->comic_files), 30) ?>" multiple>
              <?php foreach ($cpm_config->comic_files as $file) { ?>
                <option value="<?php echo substr($file, strlen($_SERVER['DOCUMENT_ROOT'])) ?>"><?php echo pathinfo($file, PATHINFO_BASENAME) ?></option>
              <?php } ?>
            </select>
          <input type="submit" id="submit" value="<?php _e("Generate Thumbnails for Selected Comics", 'comicpress-manager') ?>" style="width: 520px" />
        </form>
      <?php } else { ?>
        <p><?php _e("You haven't uploaded any comics yet.", 'comicpress-manager') ?></p>
      <?php }
    } else { ?>
      <p>
        <?php _e("<strong>You either aren't able or are unwilling to generate any thumbnails for your comics.</strong> This may be caused by a configuration error.", 'comicpress-manager') ?>
      </p>
    <?php }
  ?>
  <div id="editorcontainer" style="display: none"></div>

  <?php

  $activity_content = ob_get_clean();

  cpm_wrap_content($help_content, $activity_content);
}

/**
 * The change dates dialog.
 */
function cpm_manager_dates() {
  global $cpm_config;

  $comic_format_date_string = date(CPM_DATE_FORMAT);

  $start_date = date("Y-m-d");
  $end_date = substr(pathinfo(end($cpm_config->comic_files), PATHINFO_BASENAME), 0, strlen($comic_format_date_string));
  $end_date = date("Y-m-d", strtotime($end_date));

  if (isset($_POST['start-date'])) {
    $target_start_date = strtotime($_POST['start-date']);
    if (($target_start_date != -1) && ($target_start_date !== false)) {
      $start_date = date("Y-m-d", $target_start_date);
    } else {
      $cpm_config->warnings[] = $_POST['start-date'] . " is an invalid date.  Resetting to ${start_date}";
    }
  
    $target_end_date = strtotime($_POST['end-date']);
    if (($target_end_date != -1) && ($target_end_date !== false)) {
      $end_date = date("Y-m-d", $target_end_date);
    } else {
      $cpm_config->warnings[] = $_POST['end-date'] . " is an invalid date.  Resetting to ${end_date}";
    }
  }

  if (strtotime($end_date) < strtotime($start_date)) {
    list($start_date, $end_date) = array($end_date, $start_date);
  }

  $visible_comic_files = array();
  $visible_comic_files_md5 = array();

  $start_date_timestamp = strtotime($start_date);
  $end_date_timestamp = strtotime($end_date);

  foreach ($cpm_config->comic_files as $file) {
    $filename = pathinfo($file, PATHINFO_BASENAME);
    $result = breakdown_comic_filename($filename);
    $result_date_timestamp = strtotime($result['date']);

    if (($result_date_timestamp >= $start_date_timestamp) && ($result_date_timestamp <= $end_date_timestamp)) {
      $visible_comic_files[] = $file;
      $visible_comic_files_md5[] = "\"" . md5($file) . "\"";
    }
  }

  $help_content = __("<p><strong>Change post &amp; comic dates</strong> lets you change the comic file names and post dates for any and every comic published. You will only be able to move a comic file and its associated post if there is no comic or post that exists on the destination date, as ComicPress Manager cannot automatically resolve such conflicts.</p>", 'comicpress-manager');

  $help_content .= __("<p><strong>This is a potentialy dangerous and resource-intensive operation.</strong> Back up your database and comics/archive/RSS folders before performing large move operations.  Additionally, if you experience script timeouts while moving large numbers of posts, you may have to move posts & comic files by hand rather than through ComicPress Manager.</p>", 'comicpress-manager');

  ob_start();
  
  ?>
  
  <h2 style="padding-right:0;"><?php _e("Change Post &amp; Comic Dates", 'comicpress-manager') ?></h2>
  <h3>&mdash; <?php _e("date changes will affect comics that are associated or not associated with posts", 'comicpress-manager') ?></h3>

  <?php if (count($cpm_config->comic_files) > 0) { ?>
    <form action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post">
      <?php printf(__('Show comics between %1$s and %2$s', 'comicpress-manager'),
                      "<input type=\"text\" id=\"start-date\" name=\"start-date\" size=\"12\" value=\"${start_date}\" />",
                      "<input type=\"text\" id=\"end-date\" name=\"end-date\" size=\"12\" value=\"${end_date}\" />") ?>

      <input type="submit" value="<?php _e("Filter", 'comicpress-manager') ?>" />
    </form>

    <script type="text/javascript">
      var comic_files_keys = [ <?php echo implode(", ", $visible_comic_files_md5); ?> ];

      function reschedule_posts(start) {
        var start_processing = false;
        var interval = null;
        var current_date = null;
        var current_interval = 0;
        for (var i = 0, l = comic_files_keys.length; i < l; ++i) {
          if (start_processing) {
            top.console.log(interval[current_interval]);
            current_date += (interval[current_interval] * 86400 * 1000);
            current_interval = (current_interval + 1) % interval.length;

            var date_obj = new Date(current_date);

            var month_string = ("00" + date_obj.getMonth().toString());
                month_string = month_string.substr(month_string.length - 2, 2);

            var day_string = ("00" + date_obj.getDate().toString());
                day_string = day_string.substr(day_string.length - 2, 2);

            date_string = date_obj.getFullYear() + "-" + month_string + "-" + day_string;

            $('dates[' + comic_files_keys[i] + ']').value = date_string;
            $('holder-' + comic_files_keys[i]).style.backgroundColor = "#ddd";
          }
          if (comic_files_keys[i] == start) {
            start_processing = true;
            interval = prompt("<?php _e("How many days between posts?  Separate multiple intervals with commas (Ex: MWF is 2,2,3):", 'comicpress-manager') ?>", "7");

            if (interval !== null) {
              var all_valid = true;
              var parts = interval.split(",");
              for (var j = 0, jl = parts.length; j < jl; ++j) {
                if (!parts[j].toString().match(/^\d+$/)) { all_valid = false; break; }
              }

              if (all_valid) {
                interval = parts;
                date_parts = $F('dates[' + comic_files_keys[i] + ']').split("-");
                current_date = Date.UTC(date_parts[0], date_parts[1], date_parts[2]) + 86400 * 1000;
              } else {
                alert(interval + " <?php _e("is a valid interval", 'comicpress-manager') ?>");
                break;
              }
            } else {
              break;
            }
          }
        }
      }
    </script>

    <form onsubmit="$('submit').disabled=true" action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post">
      <input type="hidden" name="action" value="change-dates" />
      <input type="hidden" name="start-date" value="<?php echo $start_date ?>" />
      <input type="hidden" name="end-date" value="<?php echo $end_date ?>" />

      <?php
      $field_to_setup = array();
      foreach ($visible_comic_files as $file) {
        $filename = pathinfo($file, PATHINFO_BASENAME);
        $result = breakdown_comic_filename($filename);

        $key = md5($file);
        $fields_to_setup[] = "'dates[${key}]'";
        ?>
        <div id="holder-<?php echo $key ?>" style="border-bottom: solid #666 1px; padding-bottom: 3px; margin-bottom: 3px">
          <span class="form-title"><?php echo $filename ?></span>
          <span class="form-field"><input size="12" onchange="$('holder-<?php echo $key ?>').style.backgroundColor=(this.value != '<?php echo $result['date'] ?>' ? '#ddd' : '')" type="text" name="dates[<?php echo $key ?>]" id="dates[<?php echo $key ?>]" value="<?php echo $result['date'] ?>" />
            [<a title="<?php printf(__("Reset date to %s", 'comicpress-manager'), $result['date']) ?>" href="#" onclick="$('holder-<?php echo $key ?>').style.backgroundColor=''; $('dates[<?php echo $key ?>]').value = '<?php echo $result['date'] ?>'; return false">R</a> | <a title="<?php _e("Re-schedule posts from this date at a daily interval", 'comicpress-manager') ?>" href="#" onclick="reschedule_posts('<?php echo $key ?>'); return false">I</a>]
          </span>
        </div>
      <?php } ?>
      <script type="text/javascript">
        var fields_to_setup = [ 'start-date', 'end-date', <?php echo implode(", ", $fields_to_setup) ?> ];

        for (var i = 0, len = fields_to_setup.length; i < len; ++i) {
          var format = (i < 2) ? "%Y-%m-%d" : "<?php echo preg_replace('/([a-zA-Z])/', '%\1', CPM_DATE_FORMAT) ?>";
          Calendar.setup({
            inputField: fields_to_setup[i],
            ifFormat: format,
            button: fields_to_setup[i]
          });
        }
      </script>
      <input type="submit" id="submit" value="<?php _e("Change Dates", 'comicpress-manager') ?>" style="width: 520px" />
    </form>
  <?php } else { ?>
    <p><?php _e("You haven't uploaded any comics yet.", 'comicpress-manager') ?></p>
  <?php } ?>
  <div id="editorcontainer" style="display: none"></div>

  <?php

  $activity_content = ob_get_clean();

  cpm_wrap_content($help_content, $activity_content);
}

/**
 * The import dialog.
 */
function cpm_manager_import() {
  global $cpm_config;

  ob_start(); ?>
    <p>
      <?php _e("<strong>Create missing posts for uploaded comics</strong> is for when you upload a lot of comics to your comic folder and want to generate generic posts for all of the new comics, or for when you're migrating from another system to ComicPress.", 'comicpress-manager') ?>
    </p>

    <p>
      <?php
        $link_text = __("Thumbnail Generation page", 'comicpress-manager');
        $link = "<a href=\"?page=" . substr(__FILE__, strlen(ABSPATH . '/' . PLUGINDIR)) . "-thumbnails\">${link_text}</a>";

        printf(__("<strong>Generating thumbnails on an import is a slow process.</strong>  Some Webhosts will limit the amount of time a script can run.  If your import process is failing with thumbnail generation enabled, disable thumbnail generation, perform your import, and then visit the %s to complete the thumbnail generation process.", 'comicpress-manager'), $link);
      ?>
    </p>
  <?php $help_content = ob_get_clean();

  ob_start(); ?>
  
  <h2 style="padding-right:0;"><?php _e("Create Missing Posts For Uploaded Comics", 'comicpress-manager') ?></h2>
  <h3>&mdash; <?php _e("acts as a batch import process", 'comicpress-manager') ?></h3>

  <a href="#" onclick="return false" id="count-missing-posts-clicker"><?php _e("Count the number of missing posts", 'comicpress-manager') ?></a> (<?php _e("may take a while", 'comicpress-manager') ?>): <span id="missing-posts-display"></span>

  <div id="create-missing-posts-holder">
    <form onsubmit="$('submit').disabled=true" action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post" style="margin-top: 10px">
      <input type="hidden" name="action" value="create-missing-posts" />

      <?php cpm_post_editor() ?>

      <input type="submit" id="submit" value="<?php _e("Create posts", 'comicpress-manager') ?>" style="width: 520px" />
    </form>
  </div>

  <?php

  $activity_content = ob_get_clean();

  cpm_wrap_content($help_content, $activity_content);
}

/**
 * The config editor dialog.
 */
function cpm_manager_config() {
  global $cpm_config;

  ob_start(); ?>

  <h2 style="padding-right:0;"><?php _e("Edit ComicPress Config", 'comicpress-manager') ?></h2>
  <?php if (!$cpm_config->can_write_config) { ?>
    <p>
      <?php _e("<strong>You won't be able to automatically update your configuration.</strong>
      After submitting, you will be shown the code to paste into comicpress-config.php.
      If you want to enable automatic updating, check the permissions of your
      theme folder and comicpress-config.php file.", 'comicpress-manager') ?>
    </p>
  <?php }
  echo cpm_manager_edit_config();
  ?>
  <div id="editorcontainer" style="display: none"></div>
  
  <?php

  $activity_content = ob_get_clean();

  cpm_wrap_content(null, $activity_content);
}

/**
 * Show the header.
 */
function cpm_show_manager_header() {
  global $cpm_config; ?>
  <h2>
  <?php if (!is_null($cpm_config->comic_category_info)) { ?>
    <?php printf(__("Managing &#8216;%s&#8217;", 'comicpress-manager'), $cpm_config->comic_category_info['name']) ?>
  <?php } else { ?>
    <?php _e("Managing ComicPress", 'comicpress-manager') ?>
  <?php } ?>
 </h2>
<?php }

/**
 * Show the comic in the Post editor.
 */
function cpm_show_comic() {
  global $post, $cpm_config;

  read_current_theme_comicpress_config();
  cpm_get_comic_site_root();

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

    $comic_filename = preg_replace('#^.*/([^\/]*)$#', '\1', $comic_uri);
    $link = "<strong><a target=\"comic_window\" href=\"${comic_uri}\">${comic_filename}</a></strong>";

    if ($ok) {
      $date_root = substr($comic_filename, 0, 10);
      $thumbnails_found = array();
      foreach (array('rss', 'archive') as $type) {
        if ($com_config->separate_thumbs_folder_defined[$type]) {
          if (count($files = glob($cpm_config->comics_site_root . '/' . $cpm_config->properties[$type . "_comic_folder"] . '/' . $date_root . "*")) > 0) {
            $thumbnails_found[$type] = substr(realpath(array_shift($files)), strlen(realpath($_SERVER['DOCUMENT_ROOT'])));
          }
        }
      }
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
          <h3><?php _e("Comic For This Post", 'comicpress-manager') ?></h3>
          <div class="inside" style="overflow: auto">
            <div id="comic-hover" style="border: solid black 1px; position: absolute; display: none" onmouseout="hide_comic()">
              <img height="400" src="<?php echo $comic_uri ?>" />
            </div>
            <a href="#" onclick="return false" onmouseover="show_comic()"><img id="comic-icon" src="<?php echo $comic_uri ?>" height="100" align="right" /></a>
            <p>
              <?php printf(__("The comic that will be shown with this post is %s.", 'comicpress-manager'), $link) ?>
              <?php _e("Mouse over the icon to the right to see a larger version of the image.", 'comicpress-manager') ?>
              <a href="admin.php?page=<?php echo substr(__FILE__, strlen(ABSPATH . '/' . PLUGINDIR)) ?>&replace=<?php echo $comic_filename ?>"><?php _e('Replace this image with another', 'comicpress-manager') ?></a>.
            </p>

            <?php if (count($thumbnails_found) > 0) { ?>
              <p><?php _e("The following thumbnails for this comic were also found:", 'comicpress-manager') ?>
                <?php foreach ($thumbnails_found as $type => $file) { ?>
                  <a target="comic_window" href="<?php echo $file ?>"><?php echo $type ?></a>
                <?php } ?>
              </p>
            <?php } ?>
          </div>
        </div>
      <?php
    } else {
      if (is_array($cpm_config->properties['comiccat'])) {
        $comic_cat_name_list = array();
        foreach ($cpm_config->properties['comiccat'] as $cat_id) {
          $comic_cat_name_list[] = get_cat_name($cat_id);
        }
        $comic_cat_names = implode(", ", $comic_cat_name_list);
      } else {
        $comic_cat_names = get_cat_name($cpm_config->properties['comiccat']);
      }
      ?>
      <div id="comicdiv" class="postbox">
        <h3><?php _e("Comic For This Post", 'comicpress-manager') ?></h3>
        <div class="inside" style="overflow: auto">
          <p>
            <?php printf(__('The comic %1$s was found for this date, but this post is not in the ComicPress comics category.  Switch the category to <strong>%2$s</strong> to allow this post to appear as a comic post.', 'comicpress-manager'), $link, $comic_cat_names) ?>
          </p>
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

  return $cpm_config->comics_site_root . '/' . $cpm_config->properties['comic_folder'];
}

/**
 * Find a comic file by date.
 */
function find_comic_by_date($timestamp) {
  if (count($files = glob(get_comic_folder_path() . '/' . date(CPM_DATE_FORMAT, $timestamp) . '*')) > 0) {
    return $files[0];
  }
  return false;
}

/**
 * Breakdown the name of a comic file into a date and proper title.
 * TESTED
 */
function breakdown_comic_filename($filename) {
  $pattern = CPM_DATE_FORMAT;
  $pattern = preg_replace('#Y#', '[0-9]{4,4}', $pattern);
  $pattern = preg_replace('#m#', '[0-9]{2,2}', $pattern);
  $pattern = preg_replace('#d#', '[0-9]{2,2}', $pattern);

  if (preg_match("/^(${pattern})(.*)\.[^\.]+$/", $filename, $matches) > 0) {
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
 * TESTED
 */
function get_functions_php_filepath() {
  $current_theme_info = get_theme(get_current_theme());

  $template_files = glob(TEMPLATEPATH . '/*');

  foreach (array("comicpress-config.php", "functions.php") as $possible_file) {
    foreach ($template_files as $file) {
      if (pathinfo($file, PATHINFO_BASENAME) == $possible_file) {
        return $file;
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

  if (!file_exists($filepath)) { $cpm_config->warnings[] = "file not found: ${filepath}"; return; }

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
 * See if we can write to the config folder.
 */
function can_write_comicpress_config($filepath) {
  $perm_check_filename = $filepath . '-' . md5(rand());
  if (@touch($perm_check_filename) === true) {
    $move_check_filename = $perm_check_filename . '-' . md5(rand());
    if (@rename($perm_check_filename, $move_check_filename)) {
      @unlink($move_check_filename);
      return true;
    } else {
      @unlink($perm_check_filename);
      return false;
    }
  }
  return false;
}

/**
 * Write the current ComicPress Config to disk.
 */
function write_comicpress_config_functions_php($filepath, $just_show_config = false, $use_default_file = false) {
  global $cpm_config, $default_comicpress_config_file;

  if ($use_default_file) {
    $file_lines = $default_comicpress_config_file;
  } else {
    $file_lines = file($filepath, FILE_IGNORE_NEW_LINES);
  }

  $folders_separate_from_comic_folder = array('rss_comic_folder', 'archive_comic_folder');

  $properties_written = array();

  for ($i = 0; $i < count($file_lines); $i++) {
    foreach (array_keys($cpm_config->properties) as $variable) {
      if (!in_array($variable, $properties_written)) {
        if (preg_match("#\\$${variable}\ *\=\ *([^\;]*)\;#", $file_lines[$i], $matches) > 0) {
          $value = $cpm_config->properties[$variable];
          if (in_array($variable, $folders_separate_from_comic_folder)) {
            if (!isset($_POST[$variable . "-checkbox"])) {
              $value = $cpm_config->properties['comic_folder'];
            }
          }
          $file_lines[$i] = '$' . $variable . ' = "' . $value . '";';
          $properties_written[] = $variable;
        }
      }
    }
  }

  $file_output = implode("\n", $file_lines);

  if (!$just_show_config) {
    if (can_write_comicpress_config($filepath)) {
      $target_filepath = $filepath . '.' . time();
      $temp_filepath = $target_filepath . '-tmp';
      if (@file_put_contents($temp_filepath, $file_output) !== false) {
        if (file_exists($temp_filepath)) {
          @chmod($temp_filepath, 0664);
          if (@rename($filepath, $target_filepath)) {
            if (@rename($temp_filepath, $filepath)) {
              return array($target_filepath);
            } else {
              @unlink($temp_filepath);
              @rename($target_filepath, $filepath);
              return $file_output;
            }
          } else {
            @unlink($temp_filepath);
            return $file_output;
          }
        } else {
          return $file_output;
        }
      } else {
        return $file_output;
      }
    } else {
      return $file_output;
    }
  }

  return $file_output;
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
 * Generate links to view or edit a particular post.
 * @param array $post_info The post information to use.
 * @return string The view & edit post links for the post.
 */
function generate_view_edit_post_links($post_info) {
  $view_post_link = sprintf("<a href=\"{$post_info['guid']}\">%s</a>", __("View post", 'comicpress-manager'));
  $edit_post_link = sprintf("<a href=\"post.php?action=edit&amp;post={$post_info['ID']}\">%s</a>", __("Edit post", 'comicpress-manager'));

  return $view_post_link . ' | ' . $edit_post_link;
}

/**
 * Generate a hash for passing to wp_insert_post()
 * @param string $filename_date The post date.
 * @param string $filename_converted_title The title of the comic.
 * @return array The post information or false if the date is invalid.
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
    if (isset($_POST['content'])) {
      $post_content = $_POST['content'];
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

/**
 * Write a thumbnail image to the thumbnail folders.
 * @param string $input The input image filename.
 * @param string $target_filename The filename for the thumbnails.
 * @param boolean $do_rebuild If true, force rebuilding thumbnails.
 * @return mixed True if successful, false if not, null if unable to write.
 */
function cpm_write_thumbnail($input, $target_filename, $do_rebuild = false) {
  global $cpm_config;

  $write_targets = array();
  foreach ($cpm_config->separate_thumbs_folder_defined as $type => $value) {
    if ($value) {
      if ($cpm_config->thumbs_folder_writable[$type]) {
        $target = $cpm_config->comics_site_root . '/' . $cpm_config->properties[$type . "_comic_folder"] . '/' . $target_filename;

        if (!in_array($target, $write_targets)) {
          $write_targets[] = $target;
        }
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
          return null;
        case CPM_SCALE_IMAGEMAGICK:
          $ok = true;
          foreach ($write_targets as $target) {
            $convert_to_jpeg_thumb = escapeshellcmd(
              "convert \"${input}\" -filter Lanczos -resize " . $cpm_config->properties['archive_comic_width'] . "x -quality " . $cpm_config->properties['thumbnail_quality'] . " \"${target}\"");

            exec($convert_to_jpeg_thumb);

            if (!file_exists($target)) { $ok = false; }
          }
          return $ok;
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
            default:
              return false;
          }

          imagecopyresampled($thumb_image, $comic_image, 0, 0, 0, 0, $cpm_config->properties['archive_comic_width'], $archive_comic_height, $width, $height);

          $ok = true;

          foreach ($write_targets as $target) {
            imagejpeg($thumb_image, $target, $cpm_config->properties['thumbnail_quality']);
            if (!file_exists($target)) { $ok = false; }
          }

          return $ok;
      }
    }
  }

  return null;
}

/**
 * Handle uploading a set of files.
 * @param array $files A list of valid $_FILES keys to process.
 */
function cpm_handle_file_uploads($files) {
  global $cpm_config;

  $posts_created = array();
  $duplicate_posts = array();
  $files_uploaded = array();
  $thumbnails_written = array();
  $invalid_filenames = array();
  $thumbnails_not_written = array();

  $target_root = $cpm_config->comics_site_root . '/' . $cpm_config->properties[$_POST['upload-destination'] . "_folder"];
  $write_thumbnails = !isset($_POST['no-thumbnails']) && ($_POST['upload-destination'] == "comic");
  $new_post = isset($_POST['new_post']) && ($_POST['upload-destination'] == "comic");

  foreach ($files as $key) {
    if (is_uploaded_file($_FILES[$key]['tmp_name'])) {
      if ($_FILES[$key]['error'] != 0) {
        switch ($_FILES[$key]['error']) {
          case UPLOAD_ERR_INI_SIZE:
            $cpm_config->warnings[] = sprintf(__("<strong>The file you uploaded was too large.</strong>  The max allowed filesize for uploads to your server is %s.", 'comicpress-manager'), ini_get('upload_max_filesize'));
            break;
          default:
            $cpm_config->warnings[] = sprintf(__("<strong>There was an error in uploading.</strong>  The <a href='http://php.net/manual/en/features.file-upload.errors.php'>PHP upload error code</a> was %s.", 'comicpress-manager'), $_FILES[$key]['error']);
            break;
        }
      } else {
        if (strpos($_FILES[$key]['name'], ".zip") !== false) {
          $invalid_files = array();

          if (extension_loaded("zip")) {
            if (is_resource($zip = zip_open($_FILES[$key]['tmp_name']))) {
              while ($zip_entry = zip_read($zip)) {
                $comic_file = zip_entry_name($zip_entry);
                if (($result = breakdown_comic_filename($comic_file)) !== false) {
                  extract($result, EXTR_PREFIX_ALL, 'filename');
                  $target_path = $target_root . '/' . zip_entry_name($zip_entry);
                  if (zip_entry_open($zip, $zip_entry, "r")) {
                    $temp_path = $target_path . '-' . md5(rand());
                    file_put_contents($temp_path,
                                      zip_entry_read($zip_entry,
                                                     zip_entry_filesize($zip_entry)));

                    $file_ok = true;
                    if (extension_loaded("gd") && CPM_DO_GD_FILETYPE_CHECKS) {
                      $file_ok = (getimagesize($temp_path) !== false);
                    }

                    if ($file_ok) {
                      @rename($temp_path, $target_path);
                      $files_uploaded[] = zip_entry_name($zip_entry);
                    } else {
                      @unlink($temp_path);
                      $invalid_filenames[] = zip_entry_name($zip_entry);
                    }

                    zip_entry_close($zip_entry);
                  }
                } else {
                  $invalid_filenames[] = $comic_file;
                }
              }
              zip_close($zip);
            }
          } else {
            $cpm_config->warnings[] = sprintf(__("The Zip extension is not installed. %s was not processed.", 'comicpress-manager'), $_FILES[$key]['name']);
          }
        } else {
          $target_filename = $_FILES[$key]['name'];
          $result = breakdown_comic_filename($target_filename);

          if ($result == false) { // bad file, can we get a date attached?
            if (count($files) == 1) {
              if (isset($_POST['overwrite-existing-file-selector-checkbox'])) {
                $original_filename = $target_filename;
                $target_filename = $_POST['overwrite-existing-file-choice'];
                $new_post = false;
                $result = breakdown_comic_filename($target_filename);
                $cpm_config->messages[] = sprintf(__('Uploaded file <strong>%1$s</strong> renamed to <strong>%2$s</strong>.', 'comicpress-manager'), $original_filename, $target_filename);
              } else {
                $date = strtotime($_POST['override-date']);
                if (($date !== false) && ($date !== -1)) {
                  $target_filename = date(CPM_DATE_FORMAT, $date) . '-' . $target_filename;
                  $cpm_config->messages[] = sprintf(__('Uploaded file %1$s renamed to %2$s.', 'comicpress-manager'), $_FILES[$key]['name'], $target_filename);
                  $result = breakdown_comic_filename($target_filename);
                } else {
                  if (preg_match('/\S/', $_POST['override-date']) > 0) {
                    $cpm_config->warnings[] = sprintf(__("Provided override date %s is not parseable by strtotime().", 'comicpress-manager'), $_POST['override-date']);
                  }
                }
              }
            }
          }

          $comic_file = $_FILES[$key]['name'];
          if ($result !== false) {
            extract($result, EXTR_PREFIX_ALL, "filename");

            $file_ok = true;
            if (extension_loaded("gd") && CPM_DO_GD_FILETYPE_CHECKS) {
              $file_ok = (getimagesize($_FILES[$key]['tmp_name']) !== false);
            }

            if ($file_ok) {
              move_uploaded_file($_FILES[$key]['tmp_name'], $target_root . '/' . $target_filename);

              $files_uploaded[] = $target_filename;
            } else {
              $invalid_filenames[] = $comic_file;
            }
          } else {
            $invalid_filenames[] = $comic_file;
          }
        }
      }
    }
  }

  foreach ($files_uploaded as $target_filename) {
    $target_path = $target_root . '/' . $target_filename;
    if ($write_thumbnails) {
      $wrote_thumbnail = cpm_write_thumbnail($target_path, $target_filename);
    }

    if ($new_post) {
      extract(breakdown_comic_filename($target_filename), EXTR_PREFIX_ALL, "filename");
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
          $duplicate_posts[] = array(get_post($post_id, ARRAY_A), $target_filename);
        }
      } else {
        $invalid_filenames[] = $target_filename;
      }
    }

    if (!is_null($wrote_thumbnail)) {
      if ($wrote_thumbnail) {
        $thumbnails_written[] = $target_filename;
      } else {
        $thumbnails_not_written[] = $target_filename;
      }
    }
  }

  cpm_display_operation_messages(compact('invalid_filenames', 'files_uploaded', 'thumbnails_written',
                                         'thumbnails_not_written', 'posts_created', 'duplicate_posts'));
}

function cpm_display_operation_messages($info) {
  global $cpm_config;
  extract($info);
  
  if (count($invalid_filenames) > 0) {
    $cpm_config->messages[] = __("<strong>The following filenames or filetypes were invalid:</strong> ", 'comicpress-manager') . implode(", ", $invalid_filenames);
  }

  if (count($files_uploaded) > 0) {
    $cpm_config->messages[] = __("<strong>The following files were uploaded:</strong> ", 'comicpress-manager') . implode(", ", $files_uploaded);
  }

  if (count($thumbnails_written) > 0) {
    $cpm_config->messages[] = __("<strong>Thumbnails were written for the following files:</strong> ", 'comicpress-manager') . implode(", ", $thumbnails_written);
  }
  
  if (count($thumbnails_not_written) > 0) {
    $cpm_config->messages[] = __("<strong>Thumbnails were not written for the following files.</strong>  Check the permissions on the rss &amp; archive folders: ", 'comicpress-manager') . implode(", ", $thumbnails_not_written);
  }

  if (count($posts_created) > 0) {
    $post_links = array();
    foreach ($posts_created as $comic_post) {
      $post_links[] = "<li><strong>" . $comic_file . "</strong> (" . $comic_post['post_date'] . ") " . generate_view_edit_post_links($comic_post) . "</li>";
    }

    $cpm_config->messages[] = __("<strong>New posts created.</strong>  View them from the links below:", 'comicpress-manager') . " <ul>" . implode("", $post_links) . "</ul>";
  } else {
    if (count($files_uploaded) > 0) {
      $cpm_config->messages[] = __("<strong>No new posts created.</strong>", 'comicpress-manager');
    }
  }

  if (count($duplicate_posts) > 0) {
    $post_links = array();
    foreach ($duplicate_posts as $info) {
      list($comic_post, $comic_file) = $info;
      $post_links[] = "<li><strong>" . $comic_file . "</strong> (" . $comic_post['post_date'] . ") " . generate_view_edit_post_links($comic_post) . "</li>";
    }

    $cpm_config->messages[] = __("<strong>The following files would have created duplicate posts.</strong> View them from the links below: ", 'comicpress-manager') . "<ul>" . implode("", $post_links) . "</ul>";
  }
}

/**
 * Show the Post Body Template.
 * @param integer $width The width of the editor in pixels.
 */
function cpm_show_post_body_template($width = 435) {
  global $cpm_config; ?>

  <strong>Post body template:</strong>
  <div id="title"></div>
  <div id="<?php echo user_can_richedit() ? 'postdivrich' : 'postdiv' ?>" class="postarea">
    <?php the_editor($cpm_config->properties['default_post_content']) ?>
  </div>

  <br />
  (<em><?php _e("Available wildcards:", 'comicpress-manager') ?></em>)
  <ul>
    <li><strong>{category}</strong>: <?php _e("The name of the category", 'comicpress-manager') ?></li>
    <li><strong>{date}</strong>: <?php printf(__("The date of the comic (ex: <em>%s</em>)", 'comicpress-manager'), date("F j, Y", time())) ?></li>
    <li><strong>{title}</strong>: <?php _e("The title of the comic", 'comicpress-manager') ?></li>
  </ul>
  <?php
}

/**
 * Write all of the styles and scripts.
 */
function cpm_write_global_styles_scripts() {
  $plugin_url_root = substr(realpath(dirname(__FILE__)), strlen(realpath($_SERVER['DOCUMENT_ROOT']))); ?>

  <script type="text/javascript">
var messages = {
  'add_file_upload_file': "<?php _e("File:", 'comicpress-manager') ?>",
  'add_file_upload_remove': "<?php _e("remove", 'comicpress-manager') ?>",
  'count_missing_posts_none_missing': "<?php _e("You're not missing any posts!", 'comicpress-manager') ?>",
  'count_missing_posts_counting': "<?php _e("counting", 'comicpress-manager') ?>"
};

var ajax_request_uri = "<?php echo $_SERVER['REQUEST_URI'] ?>";
  </script>
  <script type="text/javascript" src="<?php echo $plugin_url_root . '/comicpress_script.js' ?>"> </script>
  <style type="text/css">@import url(<?php echo $plugin_url_root . '/comicpress_styles.css' ?>);</style>
  <style type="text/css">@import url(<?php echo $plugin_url_root ?>/jscalendar-1.0/calendar-blue.css);</style>
  <script type="text/javascript" src="<?php echo $plugin_url_root ?>/jscalendar-1.0/calendar.js"></script>
  <script type="text/javascript" src="<?php echo $plugin_url_root ?>/jscalendar-1.0/lang/calendar-en.js"></script>
  <script type="text/javascript" src="<?php echo $plugin_url_root ?>/jscalendar-1.0/calendar-setup.js"></script>

<!--[if lte IE 6]>
<style type="text/css">
div#cpm-container div#cpm-left-column { margin-top: 0 }
</style>
<![endif]-->
<?php }

/**
 * Find all the valid comics in the comics folder.
 * If CPM_SKIP_CHECKS is enabled, comic file validity is not checked, improving speed.
 * @return array The list of valid comic files in the comic folder.
 */
function cpm_read_comics_folder() {
  global $cpm_config;

  if (CPM_SKIP_CHECKS) {
    return glob($cpm_config->path . "/*");
  } else {
    $files = array();
    foreach (glob($cpm_config->path . "/*") as $file) {
      if (breakdown_comic_filename(pathinfo($file, PATHINFO_BASENAME)) !== false) {
        $files[] = $file;
      }
    }
    return $files;
  }
}

/**
 * Read information about the current installation.
 */
function cpm_read_information_and_check_config() {
  global $cpm_config;

  $cpm_config->config_method = read_current_theme_comicpress_config();
  $cpm_config->config_filepath = get_functions_php_filepath();
  $cpm_config->can_write_config = can_write_comicpress_config($cpm_config->config_filepath);

  cpm_get_comic_site_root();

  $cpm_config->path = get_comic_folder_path();
  $cpm_config->plugin_path = substr(__FILE__, strlen($_SERVER['DOCUMENT_ROOT']));

  foreach (array_keys($cpm_config->separate_thumbs_folder_defined) as $type) {
    $cpm_config->separate_thumbs_folder_defined[$type] = ($cpm_config->properties['comic_folder'] != $cpm_config->properties[$type . '_comic_folder']);
  }

  $cpm_config->errors = array();
  $cpm_config->warnings = array();
  $cpm_config->detailed_warnings = array();
  $cpm_config->messages = array();
  $cpm_config->show_config_editor = true;

  $folders = array(
    array('comic folder', 'comic_folder', true, ""),
    array('RSS feed folder', 'rss_comic_folder', false, 'rss'),
    array('archive folder', 'archive_comic_folder', false, 'archive'));

  if (CPM_SKIP_CHECKS) {
    // if the user knows what they're doing, disabling all of the checks improves performance
    
    foreach ($folders as $folder_info) {
      list ($name, $property, $is_fatal, $thumb_type) = $folder_info;
      $path = $cpm_config->comics_site_root . '/' . $cpm_config->properties[$property];
      if ($thumb_type != "") {
        $cpm_config->thumbs_folder_writable[$thumb_type] = true;
      }
    }
    $cpm_config->comic_category_info = get_object_vars(get_category($cpm_config->properties['comiccat']));
    $cpm_config->blog_category_info = get_object_vars(get_category($cpm_config->properties['blogcat']));
    $cpm_config->comic_files = cpm_read_comics_folder();
  } else {
    // quick check to see if the theme is ComicPress.
    // this needs to be made more robust.
    if (preg_match('/ComicPress/', get_current_theme()) == 0) {
      $cpm_config->detailed_warnings[] = __("The current theme isn't the ComicPress theme.  If you've renamed the theme, ignore this warning.", 'comicpress-manager');
    }

    // is the site root configured properly?
    if (!file_exists($cpm_config->comics_site_root)) {
      $cpm_config->errors[] = sprintf(__('The comics site root <strong>%s</strong> does not exist. Check your <a href="options-general.php">WordPress address and address settings</a>.', 'comicpress-manager'), $cpm_config->comics_site_root);
    }

    if (!file_exists($cpm_config->comics_site_root . '/index.php')) {
      $cpm_config->errors[] = sprintf(__('The comics site root <strong>%s</strong> does not contain a WordPress index.php file. Check your <a href="options-general.php">WordPress address and address settings</a>.', 'comicpress-manager'), $cpm_config->comics_site_root);
    }

    // folders that are the same as the comics folder won't be written to
    $all_the_same = array();
    foreach ($cpm_config->separate_thumbs_folder_defined as $type => $value) {
      if (!$value) { $all_the_same[] = $type; }
    }

    if (count($all_the_same) > 0) {
      $cpm_config->detailed_warnings[] = sprintf(__("The <strong>%s</strong> folders and the comics folder are the same.  You won't be able to generate thumbnails until you change these folders.", 'comicpress-manager'), implode(", ", $all_the_same));
    }

    // check the existence and writability of all image folders
    foreach ($folders as $folder_info) {
      list ($name, $property, $is_fatal, $thumb_type) = $folder_info;
      if (($thumb_type == "") || ($cpm_config->separate_thumbs_folder_defined[$thumb_type] == true)) {
        $path = $cpm_config->comics_site_root . '/' . $cpm_config->properties[$property];
        if (!file_exists($path)) {
          $cpm_config->errors[] = sprintf(__('The %1$s <strong>%2$s</strong> does not exist.  Did you create it within the <strong>%3$s</strong> folder?' , 'comicpress-manager'), $name, $cpm_config->properties[$property], substr(realpath($cpm_config->comics_site_root), strlen(realpath($_SERVER['DOCUMENT_ROOT']))));
        } else {
          do {
            $tmp_filename = "test-" . md5(rand());
          } while (file_exists($path . '/' . $tmp_filename));

          $ok_to_warn = true;
          if ($thumb_type != "") {
            $ok_to_warn = $cpm_config->properties[$thumb_type . "_generate_thumbnails"];
          }

          if ($ok_to_warn) {
            if (!@touch($path . '/' . $tmp_filename)) {
              $message = sprintf(__('The %1$s <strong>%2$s</strong> is not writable by the Webserver.', 'comicpress-manager'), $name, $cpm_config->properties[$property]);
              if ($is_fatal) {
                $cpm_config->errors[] = $message;
              } else {
                $cpm_config->warnings[] = $message;
              }

              if ($thumb_type != "") {
                $cpm_config->thumbs_folder_writable[$thumb_type] = false;
              }
            } else {
              @unlink($path . '/' . $tmp_filename);
              if ($thumb_type != "") {
                $cpm_config->thumbs_folder_writable[$thumb_type] = true;
              }
            }
          }
        }
      }
    }

    // to generate thumbnails, a supported image processor is needed
    if ($cpm_config->get_scale_method() == CPM_SCALE_NONE) {
      $cpm_config->detailed_warnings[] = __("No image resize methods are installed (GD or ImageMagick).  You are unable to generate thumbnails automatically.", 'comicpress-manager');
      $cpm_config->properties['archive_generate_thumbnails'] = false;
      $cpm_config->properties['rss_generate_thumbnails'] = false;
    }

    // are there enough categories created?
    if (count(get_all_category_ids()) < 2) {
      $cpm_config->errors[] = __("You need to define at least two categories, a blog category and a comics category, to use ComicPress.  Visit <a href=\"categories.php\">Manage -> Categories</a> and create at least two categories, then return here to continue your configuration.", 'comicpress-manager');
      $cpm_config->show_config_editor = false;
    } else {
      // ensure the defined comic category exists
      if (is_null($cpm_config->properties['comiccat'])) {
        // all non-blog categories are comic categories
        $cpm_config->comic_category_info = array(
          'name' => __("All other categories", 'comicpress-manager'),
        );
        $cpm_config->properties['comiccat'] = array_diff(get_all_category_ids(), array($cpm_config->properties['blogcat']));

        if (count($cpm_config->properties['comiccat']) == 1) {
          $cpm_config->properties['comiccat'] = $cpm_config->properties['comiccat'][0];
          $cpm_config->comic_category_info = get_object_vars(get_category($cpm_config->properties['comiccat']));
        }
      } else {
        if (!is_numeric($cpm_config->properties['comiccat'])) {
          // the property is non-numeric
          $cpm_config->errors[] = __("The comic category needs to be defined as a number, not an alphanumeric string.", 'comicpress-manager');
        } else {
          // one comic category is specified
          if (is_null($cpm_config->comic_category_info = get_category($cpm_config->properties['comiccat']))) {
            $cpm_config->errors[] = sprintf(__("The requested category ID for your comic, <strong>%s</strong>, doesn't exist!", 'comicpress-manager'), $cpm_config->properties['comiccat']);
          } else {
            $cpm_config->comic_category_info = get_object_vars($cpm_config->comic_category_info);
          }
        }
      }

      // ensure the defined blog category exists
      // TODO: multiple blog categories
      if (!is_numeric($cpm_config->properties['blogcat'])) {
        // the property is non-numeric
        $cpm_config->errors[] = __("The blog category needs to be defined as a number, not an alphanumeric string.", 'comicpress-manager');
      } else {
        if (is_null($cpm_config->blog_category_info = get_category($cpm_config->properties['blogcat']))) {
          $cpm_config->errors[] = sprintf(__("The requested category ID for your blog, <strong>%s</strong>, doesn't exist!", 'comicpress-manager'), $cpm_config->properties['blogcat']);
        } else {
          $cpm_config->blog_category_info = get_object_vars($cpm_config->blog_category_info);
        }

        if (!is_array($cpm_config->properties['blogcat']) && !is_array($cpm_config->properties['comiccat'])) {
          if ($cpm_config->properties['blogcat'] == $cpm_config->properties['comiccat']) {
            $cpm_config->warnings[] = __("Your comic and blog categories are the same.  This will cause browsing problems for visitors to your site.", 'comicpress-manager');
          }
        }
      }
    }

    // a quick note if you have no comics uploaded.
    // could be a sign of something more serious.
    if (count($cpm_config->comic_files = cpm_read_comics_folder()) == 0) {
      $cpm_config->detailed_warnings[] = __("Your comics folder is empty!", 'comicpress-manager');
    }
  }
}

/**
 * Get the comic site root. This can differ from the location where the WordPress
 * installation lives.
 */
function cpm_get_comic_site_root() {
  global $cpm_config;
  if (is_null($cpm_config->comics_site_root)) {
    if (($parsed_url = parse_url(get_bloginfo('url'))) !== false) {
      $cpm_config->comics_site_root = $_SERVER['DOCUMENT_ROOT'] . $parsed_url['path'];
    }
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
      array(
        $cpm_config->messages,
        __("The operation you just performed returned the following:", 'comicpress-manager'),
        'messages'),
      array(
        $cpm_config->warnings,
        __("The following warnings were generated:", 'comicpress-manager'),
        'warnings'),
      array(
        $cpm_config->errors,
        __("The following problems were found in your configuration:", 'comicpress-manager'),
        'errors')
    ) as $info) {
      list($messages, $header, $style) = $info;
      if (count($messages) > 0) {
        if (count($messages) == 1) {
          $output = $messages[0];
        } else {
          ob_start(); ?>

          <ul>
            <?php foreach ($messages as $message) { ?>
              <li><?php echo $message ?></li>
            <?php } ?>
          </ul>

          <?php $output = ob_get_clean();
        }

        if (CPM_WP_STYLE_WARNINGS) { ?>
          <div id="cpm-<?php echo $style ?>"><?php echo $output ?></div>
        <?php } else { ?>
          <h2 style="padding-right:0;"><?php echo $header ?></h2>
        <?php } ?>
      <?php }
    }

    // errors are fatal.
    if (count($cpm_config->errors) > 0) {
      $current_theme_info = get_theme(get_current_theme());
      ?>
      <p><?php _e("You must fix the problems above before you can proceed with managing your ComicPress installation.", 'comicpress-manager') ?></p>

      <?php if ($cpm_config->show_config_editor) { ?>
        <p><strong><?php _e("Details:", 'comicpress-manager') ?></strong></p>
        <ul>
          <li><strong><?php _e("Current ComicPress theme folder:", 'comicpress-manager') ?></strong> <?php echo $current_theme_info['Template Dir'] ?></li>
          <li><strong><?php _e("Available categories:", 'comicpress-manager') ?></strong>
            <table id="categories-table">
              <tr>
                <th><?php _e("Category Name", 'comicpress-manager') ?></th>
                <th><?php _e("ID #", 'comicpress-manager') ?></th>
              </tr>
              <?php foreach (get_all_category_ids() as $category_id) {
                $category = get_category($category_id);
                ?>
                <tr>
                  <td><?php echo $category->category_nicename ?></td>
                  <td align="center"><?php echo $category->cat_ID ?></td>
                </tr>
              <?php } ?>
            </table>
          </li>
        </ul>
      <?php }

      $update_automatically = true;

      $available_backup_files = array();
      foreach (glob(dirname($cpm_config->config_filepath) . '/comicpress-config.php.*') as $file) {
        if (preg_match('#\.([0-9]+)$#', $file, $matches) > 0) {
          list($all, $time) = $matches;
          $available_backup_files[] = $time;
        }
      }

      arsort($available_backup_files);

      if ($cpm_config->config_method == "comicpress-config.php") {
        if (!$cpm_config->can_write_config) {
          $update_automatically = false;
        }
      } else {
        if (count($available_backup_files) > 0) {
          if (!$cpm_config->can_write_config) {
            $update_automatically = false;
          }
        } else {
          $update_automatically = false;
        }
      }

      if (!$update_automatically) { ?>
        <p>
          <?php printf(__("<strong>You won't be able to update your comicpress-config.php or functions.php file directly through the ComicPress Manager interface.</strong> Check to make sure the permissions on %s and comicpress-config.php are set so that the Webserver can write to them.  Once you submit, you'll be given a block of code to paste into the comicpress-config.php file.", 'comicpress-manager'), $current_theme_info['Template Dir']) ?>
        </p>
      <?php } else {
        if (count($available_backup_files) > 0) { ?>
          <p>
            <?php _e("<strong>Some backup comicpress-config.php files were found in your theme directory.</strong>  You can choose to restore one of these backup files, or you can go ahead and create a new configuration below.", 'comicpress-manager') ?>
          </p>

          <form action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post">
            <input type="hidden" name="action" value="restore-backup" />
            <strong><?php _e("Restore from backup dated:", 'comicpress-manager') ?></strong>
              <select name="backup-file-time">
                <?php foreach($available_backup_files as $time) { ?>
                  <option value="<?php echo $time ?>">
                    <?php echo date("r", $time) ?>
                  </option>
                <?php } ?>
              </select>
            <input type="submit" value="<?php _e("Restore", 'comicpress-manager') ?>" />
          </form>
          <hr />
        <?php }
      }

      if ($cpm_config->show_config_editor) {
        echo cpm_manager_edit_config();
      }

      ?>

      <hr />

      <strong><?php _e('Debug info', 'comicpress-manager') ?></strong> (<em><?php _e("this data is sanitized to protect your server's configuration", 'comicpress-manager') ?></em>)

      <?php echo cpm_show_debug_info(false);

      return false;
    }
  return true;
}

function cpm_available_backup_files_sort($a, $b) {
  if ($a[1] == $b[1]) return 0;
  return ($a[1] > $b[1]) ? -1 : 1;
}

/**
 * Handle all actions.
 */
function cpm_handle_actions() {
  global $cpm_config;

  $get_posts_string = "numberposts=999999&post_status=&category=";
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
        if (strtotime($_POST['time']) === false) {
          $cpm_config->warnings[] = sprintf(__('<strong>There was an error in the post time (%1$s)</strong>.  The time is not parseable by strtotime().', 'comicpress-manager'), $_POST['time']);
        } else {
          $files_to_handle = array();

          foreach ($_FILES as $name => $info) {
            if (strpos($name, "upload-") !== false) {
              if (is_uploaded_file($_FILES[$name]['tmp_name'])) {
                $files_to_handle[] = $name;
              }
            }
          }

          if (count($files_to_handle) > 0) {
            cpm_handle_file_uploads($files_to_handle);

            $cpm_config->comic_files = cpm_read_comics_folder();
          } else {
            $cpm_config->warnings[] = __("<strong>You didn't upload any files!</strong>", 'comicpress-manager');
          }
        }
        break;
      // count the number of missing posts
      case "count-missing-posts":
        // TODO: handle different comic categories differently, this is still too geared
        // toward one blog/one comic...
        $all_post_dates = array();

        foreach (get_posts($get_posts_string) as $comic_post) {
          $all_post_dates[] = date(CPM_DATE_FORMAT, strtotime($comic_post->post_date));
        }
        $all_post_dates = array_unique($all_post_dates);

        $missing_comic_count = 0;
        foreach ($cpm_config->comic_files as $comic_file) {
          $comic_file = pathinfo($comic_file, PATHINFO_BASENAME);
          if (($result = breakdown_comic_filename($comic_file)) !== false) {
            if (!in_array($result['date'], $all_post_dates)) {
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
          $all_post_dates[] = date(CPM_DATE_FORMAT, strtotime($comic_post->post_date));
        }
        $all_post_dates = array_unique($all_post_dates);

        $posts_created = array();
        $thumbnails_written = array();
        $thumbnails_not_written = array();
        $invalid_filenames = array();

        if (strtotime($_POST['time']) === false) {
          $cpm_config->warnings[] = sprintf(__('<strong>There was an error in the post time (%1$s)</strong>.  The time is not parseable by strtotime().', 'comicpress-manager'), $_POST['time']);
        } else {
          foreach ($cpm_config->comic_files as $comic_file) {
            $comic_file = pathinfo($comic_file, PATHINFO_BASENAME);
            if (($result = breakdown_comic_filename($comic_file)) !== false) {
              extract($result, EXTR_PREFIX_ALL, 'filename');

              if (!in_array($result['date'], $all_post_dates)) {
                if (($post_hash = generate_post_hash($filename_date, $filename_converted_title)) !== false) {
                  if (!is_null($post_id = wp_insert_post($post_hash))) {
                    $posts_created[] = get_post($post_id, ARRAY_A);

                    if (!isset($_POST['no-thumbnails'])) {
                      $wrote_thumbnail = cpm_write_thumbnail($cpm_config->path . '/' . $comic_file, $comic_file);
                      if (!is_null($wrote_thumbnail)) {
                        if ($wrote_thumbnail) {
                          $thumbnails_written[] = $comic_file;
                        } else {
                          $thumbnails_not_written[] = $comic_file;
                        }
                      }
                    }
                  }
                } else {
                  $invalid_filenames[] = $comic_file;
                }
              }
            }
          }
        }

        cpm_display_operation_messages(compact('invalid_filenames', 'thumbnails_written',
                                               'thumbnails_not_written', 'posts_created'));
        break;
      // delete a comic and associated thumbnails and post
      case "delete-comic-and-post":
        $comic_file = pathinfo($_POST['comic'], PATHINFO_BASENAME);

        if (file_exists($cpm_config->path . '/' . $comic_file)) {
          if (($result = breakdown_comic_filename($comic_file)) !== false) {
            extract($result, EXTR_PREFIX_ALL, 'filename');

            $all_possible_posts = array();
            foreach (get_posts($get_posts_string) as $comic_post) {
              if (date(CPM_DATE_FORMAT, strtotime($comic_post->post_date)) == $filename_date) {
                $all_possible_posts[] = $comic_post->ID;
              }
            }

            if (count($all_possible_posts) > 1) {
              $cpm_config->messages[] = sprintf(
                __('There are multiple posts (%1$s) with the date %2$s in the comic categories. Please manually delete the posts.', 'comicpress-manager'),
                implode(", ", $all_possible_posts),
                $filename_date
              );

            } else {
              $delete_targets = array($cpm_config->path . '/' . $comic_file);
              foreach ($cpm_config->thumbs_folder_writable as $type => $value) {
                $delete_targets[] = $cpm_config->comics_site_root . '/' . $cpm_config->properties[$type . "_comic_folder"] . '/' . $comic_file;
              }
              foreach ($delete_targets as $target) { @unlink($target); }

              if (count($all_possible_posts) == 0) {
                $cpm_config->messages[] = sprintf(__("<strong>%s deleted.</strong>  No matching posts found.  Any associated thumbnails were also deleted.", 'comicpress-manager'), $comic_file);
              } else {
                wp_delete_post($all_possible_posts[0]);
                $cpm_config->messages[] = sprintf(__('<strong>%1$s and post %2$s deleted.</strong>  Any associated thumbnails were also deleted.', 'comicpress-manager'), $comic_file, $all_possible_posts[0]);
              }
              $cpm_config->comic_files = cpm_read_comics_folder();
            }
          }
        }
        break;
      // update the comicpress-config.php file
      case "update-config":
        $do_write = false;
        $use_default_file = false;

        if ($cpm_config->config_method == "comicpress-config.php") {
          $do_write = !isset($_POST['just-show-config']);
        } else {
          $use_default_file = true;
        }

        $original_properties = $cpm_config->properties;
        foreach (array_keys($cpm_config->properties) as $property) {
          if (isset($_POST[$property])) {
            $cpm_config->properties[$property] = $_POST[$property];
          }
        }

        if (!$do_write) {
          $file_output = write_comicpress_config_functions_php($cpm_config->config_filepath, true, $use_default_file);
          $cpm_config->properties = $original_properties;
          if ($use_default_file) {
            $cpm_config->messages[] = __("<strong>No comicpress-config.php file was found in your theme folder.</strong> Using default configuration file.", 'comicpress-manager');
          }
          $cpm_config->messages[] = __("<strong>Your configuration:</strong>", 'comicpress-manager') . "<pre class=\"code-block\">" . htmlentities($file_output) . "</pre>";
        } else {
          if (!is_null($cpm_config->config_filepath)) {
            if (is_array($file_output = write_comicpress_config_functions_php($cpm_config->config_filepath))) {
              $cpm_config->config_method = read_current_theme_comicpress_config();
              $cpm_config->path = get_comic_folder_path();
              $cpm_config->plugin_path = substr(__FILE__, strlen($_SERVER['DOCUMENT_ROOT']));

              cpm_read_information_and_check_config();

              $backup_file = pathinfo($file_output[0], PATHINFO_BASENAME);

              $cpm_config->messages[] = sprintf(__("<strong>Configuration updated and original config backed up to %s.</strong> Rename this file to comicpress-config.php if you are having problems.", 'comicpress-manager'), $backup_file);

            } else {
              $relative_path = substr(realpath($cpm_config->config_filepath), strlen(realpath($_SERVER['DOCUMENT_ROOT'])));
              $cpm_config->warnings[] = sprintf(__("<strong>Configuration not updated</strong>, check the permissions of %s and the theme folder.  They should be writable by the Webserver process. Alternatively, copy and paste the following code into your comicpress-config.php file:", 'comicpress-manager'), $relative_path) . "<pre class=\"code-block\">" . htmlentities($file_output) . "</pre>";

              $cpm_config->properties = $original_properties;
            }
          }
        }
        break;
      // restore from a backup
      case "restore-backup":
        $config_dirname = dirname($cpm_config->config_filepath);
        if (is_numeric($_POST['backup-file-time'])) {
          if (file_exists($config_dirname . '/comicpress-config.php.' . $_POST['backup-file-time'])) {
            if ($cpm_config->can_write_config) {
              if (@copy($config_dirname . '/comicpress-config.php.' . $_POST['backup-file-time'],
                        $config_dirname . '/comicpress-config.php') !== false) {

                cpm_read_information_and_check_config();

                $cpm_config->messages[] = sprintf(__("<strong>Restored %s</strong>.  Check to make sure your site is functioning correctly.", 'comicpress-manager'), 'comicpress-config.php.' . $_POST['backup-file-time']);
              } else {
                $cpm_config->warnings[] = sprintf(__("<strong>Could not restore %s</strong>.  Check the permissions of your theme folder and try again.", 'comicpress-manager'), 'comicpress-config.php.' . $_POST['backup-file-time']);
              }
            }
          }
        }
        break;
      // generate thumbnails for the requested files
      case "generate-thumbnails":
        foreach ($_POST['comics'] as $comic) {
          $comic_file = pathinfo($comic, PATHINFO_BASENAME);

          $wrote_thumbnail = cpm_write_thumbnail($cpm_config->path . '/' . $comic_file, $comic_file, true);

          if (!is_null($wrote_thumbnail)) {
            if ($wrote_thumbnail) {
              $cpm_config->messages[] = sprintf(__("<strong>Wrote thumbnail for %s.</strong>", 'comicpress-manager'), $comic_file);
            } else {
              $cpm_config->warnings[] = sprintf(__("<strong>Could not write thumbnail for %s.</strong> Check the permissions on the thumbnail directories.", 'comicpress-manager'), $comic_file);
            }
          }
        }
        break;
      // change the dates for a series of comics/posts
      case "change-dates":
        $comic_posts_to_date_shift = array();
        $comic_files_to_date_shift = array();
        $comic_post_target_date_counts = array();

        $wp_date_string_length  = strlen(date("Y-m-d"));
        $cpm_date_string_length = strlen(date(CPM_DATE_FORMAT));

        // find all comic files that will be shifted
        foreach ($cpm_config->comic_files as $comic_file) {
          $comic_filename = pathinfo($comic_file, PATHINFO_BASENAME);
          $filename_info = breakdown_comic_filename($comic_filename);
          $key = md5($comic_file);

          if (isset($_POST['dates'][$key])) {
            if ($_POST['dates'][$key] != $filename_info['date']) {
              $timestamp = strtotime($_POST['dates'][$key]);
              if (($timestamp !== false) && ($timestamp !== -1)) {
                $target_date = date(CPM_DATE_FORMAT, $timestamp);

                $new_comic_filename = $target_date . substr($comic_filename, $cpm_date_string_length);

                $comic_posts_to_date_shift[strtotime($filename_info['date'])] = $timestamp;
                if (!isset($comic_post_target_date_counts[$timestamp])) {
                  $comic_post_target_date_counts[$timestamp] = 0;
                }
                $comic_post_target_date_counts[$timestamp]++;

                if (!isset($comic_files_to_date_shift[$timestamp])) {
                  $comic_files_to_date_shift[$timestamp] = array($comic_filename, $new_comic_filename);
                }
              }
            }
          }
        }

        $comic_posts_to_change = array();

        $all_posts = get_posts($get_posts_string);

        // get the target dates for all files to move
        if (count($comic_posts_to_date_shift) > 0) {
          foreach ($all_posts as $comic_post) {
            $post_date_day = substr($comic_post->post_date, 0, $wp_date_string_length);
            $post_date_day_timestamp = strtotime($post_date_day);
            if (isset($comic_posts_to_date_shift[$post_date_day_timestamp])) {
              if ($comic_post_target_date_counts[$comic_posts_to_date_shift[$post_date_day_timestamp]] == 1) {
                $new_post_date = date("Y-m-d", $comic_posts_to_date_shift[$post_date_day_timestamp]) . substr($comic_post->post_date, $wp_date_string_length);
                $comic_posts_to_change[$comic_post->ID] = array($comic_post, $new_post_date);
              }
            }
          }
        }

        $final_post_day_counts = array();

        // intersect all existing and potential new posts, counting how many
        // posts occur on each day
        foreach ($all_posts as $comic_post) {
          if (isset($comic_posts_to_change[$comic_post->ID])) {
            $date_to_use = $comic_posts_to_change[$comic_post->ID][1];
          } else {
            $date_to_use = $comic_post->post_date;
          }

          $day_to_use = strtotime(substr($date_to_use, 0, $wp_date_string_length));
          if (!isset($final_post_day_counts[$day_to_use])) {
            $final_post_day_counts[$day_to_use] = 0;
          }
          $final_post_day_counts[$day_to_use]++;
        }

        $posts_moved = array();

        // move what can be moved
        foreach ($comic_posts_to_change as $id => $info) {
          list($comic_post, $new_post_date) = $info;
          $new_post_day = strtotime(substr($new_post_date, 0, $wp_date_string_length));
          if ($final_post_day_counts[$new_post_day] == 1) {
            $old_post_date = $comic_post->post_date;
            $comic_post->post_date = $new_post_date;
            wp_update_post($comic_post);
            $cpm_config->messages[] = sprintf(__('<strong>Post %1$s moved to %2$s.</strong>', 'comicpress-manager'), $id, date("Y-m-d", $new_post_day));
            $posts_moved[$new_post_day] = array($comic_post, $old_post_date);
          } else {
            $cpm_config->warnings[] = sprintf(__('<strong>Moving post %1$s to %2$s would cause two comic posts to exist on the same day.</strong>  This is not allowed in the automated process.', 'comicpress-manager'), $id, date("Y-m-d", $new_post_day));
          }
        }

        // try to move all the files, and roll back any changes to files and posts that fail
        foreach ($comic_post_target_date_counts as $target_date => $count) {
          if (!isset($final_post_day_counts[$target_date]) || ($final_post_day_counts[$target_date] == 1)) {
            if ($count > 1) {
              $cpm_config->warnings[] = sprintf(__("<strong>You are moving two comics to the same date: %s.</strong>  This is not allowed in the automated process.", 'comicpress-manager'), $target_date);
            } else {
              list($comic_filename, $new_comic_filename) = $comic_files_to_date_shift[$target_date];

              $roll_back_change = false;

              foreach (array(
                array(__('comic folder', 'comicpress-manager'), 'comic_folder', ""),
                array(__('RSS feed folder', 'comicpress-manager'), 'rss_comic_folder', "rss"),
                array(__('archive folder', 'comicpress-manager'), 'archive_comic_folder', "archive")) as $folder_info) {
                  list ($name, $property, $type) = $folder_info;

                  $do_move = true;
                  if ($type != "") {
                    if ($cpm_config->separate_thumbs_folder_defined[$type]) {
                      if ($cpm_config->thumbs_folder_writable[$type]) {
                        $do_move = ($cpm_config->properties[$type . "_generate_thumbnails"]);
                      }
                    }
                  }

                  if ($do_move) {
                    $path = $cpm_config->comics_site_root . '/' . $cpm_config->properties[$property];
                    if (!file_exists($path)) {
                      $cpm_config->errors[] = sprintf(__('The %1$s <strong>%2$s</strong> does not exist.', 'comicpress-manager'), $name, $cpm_config->properties[$property]);

                      $roll_back_change = true;
                    } else {
                      if (file_exists($path . '/' . $comic_filename)) {
                        if (@rename($path . '/' . $comic_filename, $path . '/' . $new_comic_filename)) {
                          $cpm_config->messages[] = sprintf(__('<strong>Rename %1$s file %2$s to %3$s.</strong>', 'comicpress-manager'), $name, $comic_filename, $new_comic_filename);
                        } else {
                          $cpm_config->warnings[] = sprintf(__('<strong>The renaming of %1$s to %2$s failed.</strong>  Check the permissions on %3$s', 'comicpress-manager'), $comic_filename, $new_comic_filename, $path);

                          $roll_back_change = true;
                        }
                      }
                    }
                  }
              }

              if ($roll_back_change) {
                foreach (array(
                  array(__('comic folder', 'comicpress-manager'), 'comic_folder',""),
                  array(__('RSS feed folder', 'comicpress-manager'), 'rss_comic_folder',"rss"),
                  array(__('archive folder', 'comicpress-manager'), 'archive_comic_folder',"archive")) as $folder_info) {
                    list ($name, $property) = $folder_info;

                    $do_move = true;
                    if ($type != "") {
                      if ($cpm_config->separate_thumbs_folder_defined[$type]) {
                        if ($cpm_config->thumbs_folder_writable[$type]) {
                          $do_move = ($cpm_config->properties[$type . "_generate_thumbnails"]);
                        }
                      }
                    }

                    if ($do_move) {
                      $path = $cpm_config->comics_site_root . '/' . $cpm_config->properties[$property];
                      if (file_exists($path . '/' . $new_comic_filename)) {
                        @rename($path . '/' . $new_comic_filename, $path . '/' . $comic_filename);
                        $cpm_config->messages[] = sprintf(__("<strong>Rolling back %s.</strong>", 'comicpress-manager'), $new_comic_filename);
                      }
                    }
                }

                if (isset($posts_moved[$target_date])) {
                  list($comic_post, $old_post_date) = $posts_moved[$target_date];
                  $comic_post->post_date = $old_post_date;
                  wp_update_post($comic_post);
                  $cpm_config->messages[] = sprintf(__('<strong>Rename error, rolling back post %1$s to %2$s.</strong>', 'comicpress-manager'), $comic_post->ID, $old_post_date);
                }
              }
            }
          }
        }

        $cpm_config->comic_files = cpm_read_comics_folder();

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
      <h2 style="padding-right: 0"><?php _e('ComicPress Details', 'comicpress-manager') ?></h2>
      <ul style="padding-left: 30px">
        <li><strong><?php _e("Configuration method:", 'comicpress-manager') ?></strong>
          <?php if ($cpm_config->config_method == "comicpress-config.php") { ?>
            <a href="?page=<?php echo substr(__FILE__, strlen(ABSPATH . '/' . PLUGINDIR)) ?>-config"><?php echo $cpm_config->config_method ?></a>
            <?php if ($cpm_config->can_write_config) { ?>
              <?php _e('(click to edit)', 'comicpress-manager') ?>
            <?php } else { ?>
              <?php _e('(click to edit, cannot update automatically)', 'comicpress-manager') ?>
            <?php } ?>
          <?php } else { ?>
            <?php echo $cpm_config->config_method ?>
          <?php } ?>
        </li>
        <li><strong><?php _e('Comics folder:', 'comicpress-manager') ?></strong> <?php echo $cpm_config->properties['comic_folder'] ?><br />
            <?php printf(__ngettext('(%d comic in folder)', '(%d comics in folder)', count($cpm_config->comic_files), 'comicpress-manager'), count($cpm_config->comic_files)) ?>
        </li>

        <li><strong><?php _e('Archive folder:', 'comicpress-manager') ?></strong> <?php echo $cpm_config->properties['archive_comic_folder'] ?>
          <?php if (
            ($cpm_config->get_scale_method() != CPM_SCALE_NONE) &&
            ($cpm_config->properties['archive_generate_thumbnails'] !== false) &&
            ($cpm_config->separate_thumbs_folder_defined['archive']) &&
            ($cpm_config->thumbs_folder_writable['archive'])
          ) { ?>
            (<em><?php _e('generating', 'comicpress-manager') ?></em>)
          <?php } else {
            $reasons = array();

            if ($cpm_config->get_scale_method() == CPM_SCALE_NONE) { $reasons[] = __("No scaling software", 'comicpress-manager'); }
            if ($cpm_config->properties['archive_generate_thumbnails'] === false) { $reasons[] = __("Generation disabled", 'comicpress-manager'); }
            if (!$cpm_config->separate_thumbs_folder_defined['archive']) { $reasons[] = __("Same as comics folder", 'comicpress-manager'); }
            if (!$cpm_config->thumbs_folder_writable['archive']) { $reasons[] = __("Not writable", 'comicpress-manager'); }
            ?>
            (<em style="cursor: help; text-decoration: underline" title="<?php echo implode(", ", $reasons) ?>">not generating</em>)
          <?php } ?>
        </li>
        <li><strong><?php _e('RSS feed folder:', 'comicpress-manager') ?></strong> <?php echo $cpm_config->properties['rss_comic_folder'] ?>
          <?php if (
            ($cpm_config->get_scale_method() != CPM_SCALE_NONE) &&
            ($cpm_config->properties['rss_generate_thumbnails'] !== false) &&
            ($cpm_config->separate_thumbs_folder_defined['rss']) &&
            ($cpm_config->thumbs_folder_writable['rss'])
          ) { ?>
            (<em>generating</em>)
          <?php } else {
            $reasons = array();

            if ($cpm_config->get_scale_method() == CPM_SCALE_NONE) { $reasons[] = __("No scaling software", 'comicpress-manager'); }
            if ($cpm_config->properties['rss_generate_thumbnails'] === false) { $reasons[] = __("Generation disabled", 'comicpress-manager'); }
            if (!$cpm_config->separate_thumbs_folder_defined['rss']) { $reasons[] = __("Same as comics folder", 'comicpress-manager'); }
            if (!$cpm_config->thumbs_folder_writable['rss']) { $reasons[] = __("Not writable", 'comicpress-manager'); }
            ?>
            (<em style="cursor: help; text-decoration: underline" title="<?php echo implode(", ", $reasons) ?>">not generating</em>)
          <?php } ?>
        </li>
        <li><strong>
          <?php
            if (is_array($cpm_config->properties['comiccat']) && count($cpm_config->properties['comiccat']) != 1) {
              _e("Comic categories:", 'comicpress-manager');
            } else {
              _e("Comic category:", 'comicpress-manager');
            }
          ?></strong>
          <?php if (is_array($cpm_config->properties['comiccat'])) { ?>
            <ul>
              <?php foreach ($cpm_config->properties['comiccat'] as $cat_id) { ?>
                <li><a href="<?php echo get_category_link($cat_id) ?>"><?php echo get_cat_name($cat_id) ?></a>
                <?php printf(__('(ID %s)', 'comicpress-manager'), $cat_id) ?></li>
              <?php } ?>
            </ul>
          <?php } else { ?>
            <a href="<?php echo get_category_link($cpm_config->properties['comiccat']) ?>"><?php echo $cpm_config->comic_category_info['name'] ?></a>
            <?php printf(__('(ID %s)', 'comicpress-manager'), $cpm_config->properties['comiccat']) ?>
          <?php } ?>
        </li>
        <li><strong><?php _e('Blog category:', 'comicpress-manager') ?></strong> <a href="<?php echo get_category_link($cpm_config->properties['blogcat']) ?>" ?>
            <?php echo $cpm_config->blog_category_info['name'] ?></a> <?php printf(__('(ID %s)', 'comicpress-manager'), $cpm_config->properties['blogcat']) ?></li>
        <li><strong><?php _e("PHP Version:", 'comicpress-manager') ?></strong> <?php echo phpversion() ?>
            <?php if (substr(phpversion(), 0, 3) < 5.2) { ?>
              (<a href="http://gophp5.org/hosts"><?php _e("upgrade strongly recommended", 'comicpress-manager') ?></a>)
            <?php } ?>
        </li>
        <li>
          <strong><?php _e('Theme folder:', 'comicpress-manager') ?></strong> <?php $theme_info = get_theme(get_current_theme()); echo $theme_info['Template'] ?>
        </li>
        <?php if (count($cpm_config->detailed_warnings) != 0) { ?>
           <li>
              <strong><?php _e('Additional, non-fatal warnings:', 'comicpress-manager') ?></strong>
              <ul>
                <?php foreach ($cpm_config->detailed_warnings as $warning) { ?>
                  <li><?php echo $warning ?></li>
                <?php } ?>
              </ul>
           </li>
        <?php } ?>
        <li>
          <strong><a href="#" onclick="Element.show('debug-info'); $('cpm-right-column').style.minHeight = $('cpm-left-column').offsetHeight + 'px'; return false"><?php _e('Show debug info', 'comicpress-manager') ?></a></strong> (<em><?php _e("this data is sanitized to protect your server's configuration", 'comicpress-manager') ?></em>)
          <?php echo cpm_show_debug_info() ?>
        </li>
      </ul>
    </div>
  <?php
}

function cpm_show_debug_info($display_none = true) {
  global $cpm_config;
  
  ob_start(); ?>
  <span id="debug-info" class="code-block" <?php echo $display_none ? "style=\"display: none\"" : "" ?>><?php
    $output_config = get_object_vars($cpm_config);
    $output_config['comic_files'] = count($cpm_config->comic_files) . " comic files";
    $output_config['config_filepath'] = substr(realpath($cpm_config->config_filepath), strlen(realpath($_SERVER['DOCUMENT_ROOT'])));
    $output_config['path'] = substr(realpath($cpm_config->path), realpath(strlen($_SERVER['DOCUMENT_ROOT'])));
    $output_config['zip_enabled'] = extension_loaded("zip");
    unset($output_config['comics_site_root']);

    clearstatcache();
    $output_config['folder_perms'] = array();

    foreach (array(
      'comic' => $cpm_config->comics_site_root . '/' . $cpm_config->properties['comic_folder'],
      'rss' => $cpm_config->comics_site_root . '/' . $cpm_config->properties['rss_comic_folder'],
      'archive' => $cpm_config->comics_site_root . '/' . $cpm_config->properties['archive_comic_folder'],
      'config' => $cpm_config->config_filepath
    ) as $key => $path) {
      if (($s = @stat($path)) !== false) {
        $output_config['folder_perms'][$key] = decoct($s[2]);
      } else {
        $output_config['folder_perms'][$key] = "folder does not exist";
      }
    }

    $new_output_config = array();
    foreach ($output_config as $key => $value) {
      if (is_string($value)) {
        $value = htmlentities($value);
      }
      $new_output_config[$key] = $value;
    }

    var_dump($new_output_config);
  ?></span>
  <?php

  return ob_get_clean();
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
      array(__("Comic category", 'comicpress-manager'), "comiccat", "category"),
      array(__("Blog category", 'comicpress-manager'), "blogcat", "category"),
      array(__("Comic Folder", 'comicpress-manager'), "comic_folder", "folder"),
      array(__("RSS Comic Folder", 'comicpress-manager'), "rss_comic_folder", "folder-optional"),
      array(__("Archive Comic Folder", 'comicpress-manager'), "archive_comic_folder", "folder-optional"),
      array(__("Archive Comic Width (px)", 'comicpress-manager'), "archive_comic_width", "integer"),
      array(__("# of <acronym title=\"Home Page\">HP</acronym> Blog Posts", 'comicpress-manager'), "blog_postcount", "integer")
    ) as $field_info) {
      list($title, $field, $type) = $field_info;

      switch($type) {
        case "category": ?>
          <span class="config-title"><?php echo $title ?>:</span>
          <span class="config-field"><select name="<?php echo $field ?>" title="<?php _e('All possible WordPress categories', 'comicpress-manager') ?>">
                           <?php foreach (get_all_category_ids() as $cat_id) {
                             $category = get_category($cat_id); ?>
                             <option value="<?php echo $category->cat_ID ?>"
                                     <?php echo ($cpm_config->properties[$field] == $cat_id) ? " selected" : "" ?>><?php echo $category->cat_name; ?></option>
                           <?php } ?>
                         </select></span>
          <?php break;
        case "folder":
        case "folder-optional": ?>
          <?php if ($type == "folder-optional") { ?>
            <input type="checkbox" name="<?php echo $field ?>-checkbox" id="<?php echo $field ?>-checkbox" value="yes"
                   <?php echo ($cpm_config->properties[$field] != $cpm_config->properties['comic_folder']) ? "checked" : "" ?> />
            <label for="<?php echo $field ?>-checkbox"><?php printf(__('Yes, I want a separate %s', 'comicpress-manager'), $title) ?></label><br />
            <div id="<?php echo $field ?>-holder">
          <?php } ?>
          <span class="config-title"><?php echo $title ?>:</span>
          <span class="config-field"><input type="text" id="<?php echo $field ?>" name="<?php echo $field ?>" size="15" value="<?php echo $cpm_config->properties[$field] ?>" />

          <a href="#" title="<?php _e('Click to move folder choice from right dropdown to text field', 'comicpress-manager') ?>" onclick="$('<?php echo $field ?>').value = $('<?php echo $field ?>-folder').options[$('<?php echo $field ?>-folder').selectedIndex].value; return false;">&lt;&lt;</a>

          <select title="<?php _e("List of possible folders at the root of your site", 'comicpress-manager') ?>" id="<?php echo $field ?>-folder">
            <?php foreach (glob($cpm_config->comics_site_root . '/*') as $file) {
              if (is_dir($file)) {
                $file = preg_replace("#/#", '', substr($file, strlen($cpm_config->comics_site_root))); ?>
                <option <?php echo ($file == $cpm_config->properties[$field]) ? " selected" : "" ?> value="<?php echo $file ?>"><?php echo $file ?></option>
              <?php }

            } ?>
          </select>

          </span>

          <?php if ($type == "folder-optional") { ?>
            </div>
            <script type="text/javascript">
              Event.observe('<?php echo $field ?>-checkbox', 'click', function() { hide_show_checkbox_holder("<?php echo $field ?>") });
              Event.observe(window, 'load', function() { hide_show_checkbox_holder("<?php echo $field ?>") } );
            </script>
          <?php } ?>

          <?php break;
        case "integer": ?>
          <span class="config-title"><?php echo $title ?>:</span>
          <span class="config-field"><input type="text" name="<?php echo $field ?>" size="20" value="<?php echo $cpm_config->properties[$field] ?>" />
          </span>
          <?php break;
      }
    } ?>
    <p>
      <?php _e("<strong>If you've designated a comics folder that does not exist on the server, you <em>will trigger errors.</em></strong>
      Create the folder first, then reload this page and use the dropdowns to select the target folder.", 'comicpress-manager') ?>
    </p>

    <p><input type="checkbox" name="just-show-config" id="just-show-config" value="yes" /> <label for="just-show-config"><?php _e("Don't try to write my config out; just display it", 'comicpress-manager') ?></label></p>
    <input class="update-config" type="submit" value="<?php _e("Update Config", 'comicpress-manager') ?>" style="width: 520px" />
  </form>

  <?php return ob_get_clean();
}

/**
 * Show the footer.
 */
function cpm_show_footer() { ?>
  <div id="cpm-footer">
    <?php _e('<a href="http://claritycomic.com/comicpress-manager/" target="_new">ComicPress Manager</a> is built for the <a href="http://www.mindfaucet.com/comicpress/" target="_new">ComicPress</a> theme', 'comicpress-manager') ?> |
    <?php _e('Copyright 2008 <a href="mailto:john@claritycomic.com?Subject=ComicPress Manager Comments">John Bintz</a>', 'comicpress-manager') ?> |
    <?php _e('Released under the GNU GPL', 'comicpress-manager') ?> |
    <?php _e('Version 0.9.5', 'comicpress-manager') ?> |
    <?php _e('Uses the <a target="_new" href="http://www.dynarch.com/projects/calendar/">Dynarch DHTML Calendar Widget</a>', 'comicpress-manager') ?>
  </div>
<?php }

?>