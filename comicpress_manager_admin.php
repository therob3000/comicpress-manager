<?php

//harmonious @maxdb @hash @zip:rename

require_once('comicpress_manager_library.php');

add_action("admin_menu", "cpm_add_pages");
add_action("edit_form_advanced", "cpm_show_comic_caller");
add_action("add_category_form_pre", "cpm_comicpress_categories_warning");

$cpm_config = new ComicPressConfig();

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

//RSS COMIC WIDTH - the width your comics will appear in RSS feeds (default "380").
\$rss_comic_width = "380";

//BLOG POSTCOUNT - the number of blog entries to appear on the home page (default "10").
\$blog_postcount = "10";

ENDPHP
. '?>');

cpm_get_cpm_document_root();
cpm_initialize_options();

function cpm_comicpress_categories_warning() {
  if (count(get_all_category_ids()) < 2) {
    echo '<div style="margin: 10px; padding: 5px; background-color: #440008; color: white; border: solid #a00 1px">';
    echo __("Remember, you need at least two categories defined in order to use ComicPress.", 'comicpress-manager');
    echo '</div>';
  }
}

function cpm_get_plugin_path() {
  return PLUGINDIR . '/' . preg_replace('#^.*/([^\/]*)#', '\\1', dirname(plugin_basename(__FILE__)));
}

/**
 * Add pages to the admin interface and load necessary JavaScript libraries.
 * Also read in the configuration and handle any POST actions.
 */
function cpm_add_pages() {
  global $plugin_page, $access_level, $pagenow, $cpm_config, $wp_version;

  load_plugin_textdomain('comicpress-manager', cpm_get_plugin_path());

  $widget_options = get_option('dashboard_widget_options');
  if ( !$widget_options || !is_array($widget_options) )
    $widget_options = array();

  cpm_read_information_and_check_config();

  $do_enqueue_prototype = false;

  if (($pagenow == "post.php") && ($_REQUEST['action'] == "edit")) {
    $do_enqueue_prototype = true;
  }

  $filename = plugin_basename(__FILE__);

  if (strpos($plugin_page, pathinfo(__FILE__, PATHINFO_BASENAME)) !== false) {
    $editor_load_pages = array($filename, $filename . '-import');

    if (in_array($plugin_page, $editor_load_pages)) {
      wp_enqueue_script('editor');
      if (!function_exists('wp_tiny_mce')) {
        wp_enqueue_script('wp_tiny_mce');
      }
    }

    $do_enqueue_prototype = true;

    cpm_handle_actions();
  }

  if ($do_enqueue_prototype) {
    wp_enqueue_script('prototype');
  }

  if (!isset($access_level)) { $access_level = 10; }

  $plugin_title = __("ComicPress Manager", 'comicpress-manager');

  add_menu_page($plugin_title, __("ComicPress", 'comicpress-manager'), $access_level, $filename, "cpm_manager_index_caller");
  add_submenu_page($filename, $plugin_title, __("Upload", 'comicpress-manager'), $access_level, $filename, 'cpm_manager_index_caller');

  if (!function_exists('get_site_option')) {
    add_submenu_page($filename, $plugin_title, __("Import", 'comicpress-manager'), $access_level, $filename . '-import', 'cpm_manager_import_caller');
  }

  add_submenu_page($filename, $plugin_title, __("Status", 'comicpress-manager'), $access_level, $filename . '-status', 'cpm_manager_status_caller');
  add_submenu_page($filename, $plugin_title, __("Generate Thumbnails", 'comicpress-manager'), $access_level, $filename . '-thumbnails', 'cpm_manager_thumbnails_caller');
  add_submenu_page($filename, $plugin_title, __("Change Dates", 'comicpress-manager'), $access_level, $filename . '-dates', 'cpm_manager_dates_caller');
  add_submenu_page($filename, $plugin_title, __("Delete", 'comicpress-manager'), $access_level, $filename . '-delete', 'cpm_manager_delete_caller');
  add_submenu_page($filename, $plugin_title, __("ComicPress Config", 'comicpress-manager'), $access_level, $filename . '-config', 'cpm_manager_config_caller');
  add_submenu_page($filename, $plugin_title, __("Manager Config", 'comicpress-manager'), $access_level, $filename . '-cpm-config', 'cpm_manager_cpm_config_caller');

  if (cpm_option('cpm-enable-dashboard-rss-feed') == 1) {
    wp_register_sidebar_widget( 'dashboard_cpm', __("ComicPress News", "comicpress-manager"), 'cpm_dashboard_widget',
      array( 'all_link' => "http://mindfaucet.com/comicpress/", 'feed_link' => "http://feeds.feedburner.com/comicpress?format=xml", 'width' => 'half', 'class' => 'widget_rss' )
    );

    add_filter('wp_dashboard_widgets', 'cpm_add_dashboard_widget');
  }

  if (cpm_option('cpm-enable-quomicpress') == 1) {
    if (count($cpm_config->errors) == 0) {
      wp_register_sidebar_widget( 'dashboard_quomicpress', __("QuomicPress (Quick ComicPress)", "comicpress-manager"), 'cpm_quomicpress_widget',
        array( 'width' => 'half' )
      );

      add_filter('wp_dashboard_widgets', 'cpm_add_quomicpress_widget');
    }
  }

  add_submenu_page("post.php", $plugin_title, __("Comic", 'comicpress-manager'), $access_level, $filename . "-write-comic", 'cpm_manager_write_comic_caller');
}

/**
 * Add the ComicPress News dashboard widget.
 */
function cpm_add_dashboard_widget($widgets) {
	global $wp_registered_widgets;
	if (!isset($wp_registered_widgets['dashboard_cpm'])) {
		return $widgets;
	}
	array_splice($widgets, sizeof($widgets)-1, 0, 'dashboard_cpm');
	return $widgets;
}

/**
 * Write out the RSS widget for ComicPress Manager.
 */
function cpm_dashboard_widget($sidebar_args) {
  if (is_array($sidebar_args)) {
    extract($sidebar_args, EXTR_SKIP);
  }
  echo $before_widget . $before_title . $widget_name . $after_title;
  wp_widget_rss_output('http://feeds.feedburner.com/comicpress?format=xml', array('items' => 2, 'show_summary' => true));
	echo $after_widget;
}

/**
 * Add a dashboard widget.
 * Is there a better way to do this?
 */
function cpm_add_quomicpress_widget($widgets) {
  global $wp_registered_widgets;
  if (!isset($wp_registered_widgets['dashboard_quomicpress'])) {
    return $widgets;
  }
  array_splice($widgets, sizeof($widgets)-1, 0, 'dashboard_quomicpress');
  return $widgets;
}

function cpm_quomicpress_widget($sidebar_args) {
  if (is_array($sidebar_args)) {
    extract($sidebar_args, EXTR_SKIP);
  }
  echo $before_widget . $before_title . $widget_name . $after_title;
  include("pages/write_comic_post.php");
  cpm_manager_write_comic(plugin_basename(__FILE__), false);
  echo $after_widget;
}

/**
 * Create a list of checkboxes that can be used to select additional categories.
 */
function cpm_generate_additional_categories_checkboxes($override_name = null) {
  global $cpm_config;

  $additional_categories = array();

  foreach (get_all_category_ids() as $cat_id) {
    $ok = true;

    foreach (array('comiccat', 'blogcat') as $type) {
      if (is_array($cpm_config->properties[$type])) {
        if (in_array($cat_id, $cpm_config->properties[$type])) {
          $ok = false;
        }
      } else {
        if ($cat_id == $cpm_config->properties[$type]) {
          $ok = false;
        }
      }
    }

    if ($ok) {
       $category = get_category($cat_id);
       $additional_categories[strtolower($category->cat_name)] = $category;
    }
  }

  ksort($additional_categories);

  $name = (!empty($override_name)) ? $override_name : "additional-categories";
  $selected_additional_categories = explode(",", cpm_option("cpm-default-additional-categories"));

  $category_checkboxes = array();
  if (count($additional_categories) > 0) {
    foreach ($additional_categories as $category) {
      $checked = (in_array($category->cat_ID, $selected_additional_categories) ? "checked" : "");

      $category_checkboxes[] = "<input type=\"checkbox\" name=\"${name}[]\" value=\"" . $category->cat_ID . "\" ${checked} /> " . $category->cat_name . "<br />";
    }
  }
  return $category_checkboxes;
}

/**
 * Initialize ComicPress Manager options.
 */
function cpm_initialize_options() {
  global $cpm_config;

  include('cpm_configuration_options.php');

  foreach ($configuration_options as $option_info) {
    if (is_array($option_info)) {
      $result = cpm_option($option_info['id']);

      if ($result === false) {
        $default = (isset($option_info['default']) ? $option_info['default'] : "");
        update_option("comicpress-manager-" . $option_info['id'], $default);
      }
    }
  }
}

/**
 * Show the Post Editor.
 * @param integer $width The width in pixels of the text editor widget.
 */
function cpm_post_editor($width = 435) {
  global $cpm_config;

  $form_titles_and_fields = array();

  $form_titles_and_fields[] = array(
    __("Category:", 'comicpress-manager'),
    generate_comic_categories_options('category')
  );

  // see if there are additional categories that can be set besides the comic and blog categories

  if (count($category_checkboxes = cpm_generate_additional_categories_checkboxes()) > 0) {
    $form_titles_and_fields[] = array(
      __("Additional Categories:", 'comicpress-manager'),
      implode("\n", $category_checkboxes)
    );
  }

  $form_titles_and_fields[] = array(
    __("Time to post:", 'comicpress-manager'),
    "<input type=\"text\" name=\"time\" value=\"" . cpm_option('cpm-default-post-time') . "\" size=\"10\" />"
  );

  $form_titles_and_fields[] = array(
    '<label for="publish">' . __("Publish post:", 'comicpress-manager') . '</label>',
    '<input id="publish" type="checkbox" name="publish" value="yes" checked />'
  );

  $form_titles_and_fields[] = array(
    '<label for="no-duplicate-check">' . __("Don't check for duplicate posts:", 'comicpress-manager') . '</label>',
    '<input id="no-duplicate-check" type="checkbox" name="no_duplicate_check" value="yes" />'
  );

  if ($cpm_config->get_scale_method() != CPM_SCALE_NONE) {
    $thumbnail_writes = array();
    foreach ($cpm_config->separate_thumbs_folder_defined as $type => $value) {
      if ($value) {
        if ($cpm_config->thumbs_folder_writable[$type]) {
          if (cpm_option("cpm-${type}-generate-thumbnails") == 1) {
            $thumbnail_writes[] = $type;
          }
        }
      }
    }

    $thumb_write_holder = '<div id="thumbnail-write-holder">(<em>';
    if (count($thumbnail_writes) > 0) {
      $thumb_write_holder .= sprintf("You'll be writing thumbnails to: %s", implode(", ", $thumbnail_writes));
    } else {
      $thumb_write_holder .= __("You won't be generating any thumbnails", 'comicpress-manager');
    }
    $thumb_write_holder .= "</em>)</div>";

    $form_titles_and_fields[] = $thumb_write_holder;

    if (count($thumbnail_writes) > 0) {
      $form_titles_and_fields[] = array(
        '<label for="no-thumbnails">' . __("Don't generate thumbnails:", 'comicpress-manager') . '</label>',
        '<input onclick="hide_show_div_on_checkbox(\'thumbnail-write-holder\', this, true)" type="checkbox" name="no-thumbnails" id="no-thumbnails" value="yes" />'
      );
    }
  }

  $form_titles_and_fields[] = array(
    '<label for="override-title">' . __("Specify a title for all posts:", 'comicpress-manager') . '</label>',
    '<input onclick="hide_show_div_on_checkbox(\'override-title-holder\', this)" type="checkbox" id="override-title" name="override-title" value="yes" ' . ((cpm_option('cpm-default-override-title') !== "") ? "checked" : "") . " />"
  );

  $form_titles_and_fields[] = array(
    __("Title to use:", 'comicpress-manager'),
    '<input type="text" name="override-title-to-use" value="' . cpm_option('cpm-default-override-title') . '" />',
    'override-title-holder'
  );

  $form_titles_and_fields[] = '</div>';

  $form_titles_and_fields[] = array(
    __("Upload Date Format:", 'comicpress-manager'),
    '<input type="text" name="upload-date-format" />'
  );

  $form_titles_and_fields[] = array(
    __("Tags:", 'comicpress-manager'),
    '<input type="text" name="tags" value="' . cpm_option('cpm-default-post-tags') .'" />'
  );

  ?><table cellspacing="0"><?php

  foreach ($form_titles_and_fields as $form_title_and_field) {
    if (is_array($form_title_and_field)) {
      list($title, $field, $id) = $form_title_and_field; ?>
      <tr<?php echo (!empty($id) ? " id=\"$id\"" : "") ?>>
        <td valign="top" class="form-title"><?php echo $title ?></td>
        <td valign="top"><?php echo $field ?></td>
      </tr>
    <?php } else { ?>
      <tr><td colspan="2"><?php echo $form_title_and_field ?></td></tr>
    <?php } ?>
  <?php } ?>
  </table>

  <?php cpm_show_post_body_template($width) ?>
<?php }

/**
 * Wrap the help text and activity content in the CPM page style.
 * @param string $help_content The content to show in the Help box.
 * @param string $activity_content The content to show in the Activity box.
 */
function cpm_wrap_content($help_content, $activity_content) {
  global $wp_scripts;
  cpm_write_global_styles_scripts(); ?>

<div class="wrap">  
  <div id="cpm-container">
    <?php if (cpm_handle_warnings()) { ?>
      <!-- Header -->
      <?php cpm_show_manager_header() ?>

      <div style="overflow: hidden">
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
          <div class="activity-box"><?php echo $activity_content ?></div>
          <![if !IE]>
            <script type="text/javascript">prepare_comicpress_manager()</script>
          <![endif]>
        </div>
      </div>
    <?php cpm_show_footer() ?>
  <?php } ?>
  </div>
</div>
<?php }

function cpm_manager_page_caller($page) {
  if (!cpm_option('cpm-did-first-run')) {
    include("pages/comicpress_first_run.php");
    cpm_manager_first_run(__FILE__);
  } else {
    include("pages/comicpress_${page}.php");
    call_user_func("cpm_manager_${page}");
  }
}

/**
 * Wrappers around page calls to reduce the amount of code in _admin.php.
 */
function cpm_manager_index_caller() { cpm_manager_page_caller("index"); }
function cpm_manager_delete_caller() { cpm_manager_page_caller("delete"); }
function cpm_manager_thumbnails_caller() { cpm_manager_page_caller("thumbnails"); }
function cpm_manager_status_caller() { cpm_manager_page_caller("status"); }
function cpm_manager_dates_caller() { cpm_manager_page_caller("dates"); }
function cpm_manager_import_caller() { cpm_manager_page_caller("import"); }
function cpm_manager_config_caller() { cpm_manager_page_caller("config"); }
function cpm_manager_cpm_config_caller() { cpm_manager_page_caller("cpm_config"); }

function cpm_show_comic_caller() {
  include("pages/edit_post_show_comic.php");
  cpm_show_comic();
}

function cpm_manager_write_comic_caller() {
  include("pages/write_comic_post.php");
  cpm_manager_write_comic(plugin_basename(__FILE__));
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
 * Find all the thumbnails for a particular image root.
 */
function cpm_find_thumbnails($date_root) {
  global $cpm_config;

  $thumbnails_found = array();
  foreach (array('rss', 'archive') as $type) {
    if ($cpm_config->separate_thumbs_folder_defined[$type]) {
      $files = glob(CPM_DOCUMENT_ROOT . '/' . $cpm_config->properties[$type . "_comic_folder"] . '/' . $date_root . "*");
      if ($files === false) { $files = array(); }

      if (count($files) > 0) {
        $thumbnails_found[$type] = substr(realpath(array_shift($files)), CPM_STRLEN_REALPATH_DOCUMENT_ROOT);
      }
    }
  }

  return $thumbnails_found;
}

/**
 * Find a comic file by date.
 */
function find_comic_by_date($timestamp) {
  $files = glob(get_comic_folder_path() . '/' . date(CPM_DATE_FORMAT, $timestamp) . '*');
  if ($files === false) { $comic_date_files = array(); }
  if (count($files) > 0) {
    return $files[0];
  }
  return false;
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
 * Use file_put_contents or f-functions() as necessary.
 */
function file_write_contents($file, $data) {
  //harmonious file_put_contents
  if (function_exists('file_put_contents')) {
    return file_put_contents($file, $data);
  } else {
    if (($fh = fopen($file, "w")) !== false) {
      fwrite($fh, $data);
      fclose($fh);
    }
  }
  //harmonious_end
}

/**
 * Write the current ComicPress Config to disk.
 */
function write_comicpress_config_functions_php($filepath, $just_show_config = false, $use_default_file = false) {
  global $cpm_config, $default_comicpress_config_file;

  if ($use_default_file) {
    $file_lines = $default_comicpress_config_file;
  } else {
    $file_lines = array();
    foreach (file($filepath) as $line) {
      $file_lines[] = rtrim($line, "\r\n");
    }
  }

  $properties_written = array();

  for ($i = 0; $i < count($file_lines); $i++) {
    foreach (array_keys($cpm_config->properties) as $variable) {
      if (!in_array($variable, $properties_written)) {
        if (preg_match("#\\$${variable}\ *\=\ *([^\;]*)\;#", $file_lines[$i], $matches) > 0) {
          $value = $cpm_config->properties[$variable];
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
      if (@file_write_contents($temp_filepath, $file_output) !== false) {
        if (file_exists($temp_filepath)) {
          @chmod($temp_filepath, CPM_FILE_UPLOAD_CHMOD);
          if (@rename($filepath, $target_filepath)) {
            if (@rename($temp_filepath, $filepath)) {
              return array($target_filepath);
            } else {
              @unlink($temp_filepath);
              @rename($target_filepath, $filepath);
            }
          } else {
            @unlink($temp_filepath);
          }
        }
      }
    }
  }

  return $file_output;
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
 * Write a thumbnail image to the thumbnail folders.
 * @param string $input The input image filename.
 * @param string $target_filename The filename for the thumbnails.
 * @param boolean $do_rebuild If true, force rebuilding thumbnails.
 * @return mixed True if successful, false if not, null if unable to write.
 */
function cpm_write_thumbnail($input, $target_filename, $do_rebuild = false) {
  global $cpm_config;

  $target_format = pathinfo($target_filename, PATHINFO_EXTENSION);
  $files_created_in_operation = array();

  $write_targets = array();
  foreach ($cpm_config->separate_thumbs_folder_defined as $type => $value) {
    if ($value) {
      if ($cpm_config->thumbs_folder_writable[$type]) {

        $converted_target_filename = preg_replace('#\.[^\.]+$#', '', $target_filename) . '.' . $target_format;

        $target = CPM_DOCUMENT_ROOT . '/' . $cpm_config->properties[$type . "_comic_folder"] . '/' . $converted_target_filename;

        if (!in_array($target, $write_targets)) {
          $write_targets[$type] = $target;
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
          $unique_colors = exec("identify -format '%k' '${input}'");
          if (empty($unique_colors)) { $unique_colors = 256; }

          $ok = true;
          foreach ($write_targets as $type => $target) {
            $width_to_use =   (isset($cpm_config->properties["${type}_comic_width"]))
                            ? $cpm_config->properties["${type}_comic_width"]
                            : $cpm_config->properties['archive_comic_width'];

            $command = array("convert",
                             "\"${input}\"",
                             "-filter Lanczos",
                             "-resize " . $width_to_use . "x");

            $im_target = $target;

            switch(strtolower($target_format)) {
              case "jpg":
              case "jpeg":
                $command[] = "-quality " . cpm_option("cpm-thumbnail-quality");
                break;
              case "gif":
                $command[] = "-colors ${unique_colors}";
                break;
              case "png":
                if ($unique_colors <= 256) {
                  $im_target = "png8:${im_target}";
                  $command[] = "-colors ${unique_colors}";
                }
                $command[] = "-quality 100";
                break;
              default:
            }

            $command[] = "\"${im_target}\"";

            $convert_to_jpeg_thumb = escapeshellcmd(implode(" ", $command));

            exec($convert_to_jpeg_thumb);

            if (!file_exists($target)) {
              $ok = false;
            } else {
              @chmod($target, CPM_FILE_UPLOAD_CHMOD);
              $files_created_in_operation[] = $target;
            }
          }
          return $ok;
        case CPM_SCALE_GD:
          list ($width, $height) = getimagesize($input);

          if ($width > 0) {
            foreach ($write_targets as $type => $target) {
              $width_to_use =   (isset($cpm_config->properties["${type}_comic_width"]))
                              ? $cpm_config->properties["${type}_comic_width"]
                              : $cpm_config->properties['archive_comic_width'];

              $archive_comic_height = (int)(($width_to_use * $height) / $width);

              $pathinfo = pathinfo($input);

              $thumb_image = imagecreatetruecolor($width_to_use, $archive_comic_height);
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

              if ($is_palette = !imageistruecolor($comic_image)) {
                $number_of_colors = imagecolorstotal($comic_image); 
              }

              imagecopyresampled($thumb_image, $comic_image, 0, 0, 0, 0, $width_to_use, $archive_comic_height, $width, $height);

              $ok = true;

              switch(strtolower($target_format)) {
                case "jpg":
                case "jpeg":
                  if (imagetypes() & IMG_JPG) {
                    imagejpeg($thumb_image, $target, cpm_option("cpm-thumbnail-quality"));
                  } else {
                    return false;
                  }
                  break;
                case "gif":
                  if (imagetypes() & IMG_GIF) {
                    imagegif($thumb_image, $target);
                  } else {
                    return false;
                  }
                  break;
                case "png":
                  if (imagetypes() & IMG_PNG) {
                    if ($is_palette) {
                      imagetruecolortopalette($thumb_image, true, $number_of_colors);
                    }
                    imagepng($thumb_image, $target, 9);
                  } else {
                    return false;
                  }
                  break;
                default:
                  return false;
              }

              if (!file_exists($target)) {
                $ok = false;
              } else {
                @chmod($target, CPM_FILE_UPLOAD_CHMOD);
                $files_created_in_operation[] = $target;
              }
            }
          } else {
            $ok = false;
          }

          return ($ok) ? $files_created_in_operation :false ;
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
  $files_not_uploaded = array();
  $invalid_image_types = array();

  $target_root = CPM_DOCUMENT_ROOT . '/' . $cpm_config->properties[$_POST['upload-destination'] . "_folder"];
  $write_thumbnails = !isset($_POST['no-thumbnails']) && ($_POST['upload-destination'] == "comic");
  $new_post = isset($_POST['new_post']) && ($_POST['upload-destination'] == "comic");

  $ok_to_keep_uploading = true;
  $files_created_in_operation = array();

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

          //harmonious zip_open zip_entry_name zip_read zip_entry_read zip_entry_open zip_entry_filesize zip_entry_close zip_close
          if (extension_loaded("zip")) {
            if (is_resource($zip = zip_open($_FILES[$key]['tmp_name']))) {
              while ($zip_entry = zip_read($zip)) {
                $comic_file = zip_entry_name($zip_entry);
                if (($result = cpm_breakdown_comic_filename($comic_file, true)) !== false) {
                  extract($result, EXTR_PREFIX_ALL, 'filename');
                  $target_filename = zip_entry_name($zip_entry);
                  $target_path = $target_root . '/' . $target_filename;

                  if (isset($_POST['upload-date-format']) && !empty($_POST['upload-date-format'])) {
                    $target_filename = date(CPM_DATE_FORMAT, strtotime($result['date'])) .
                                        $result['title'] . '.' . pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION);
                  } 

                  if (zip_entry_open($zip, $zip_entry, "r")) {
                    $temp_path = $target_path . '-' . md5(rand());
                    file_write_contents($temp_path,
                                      zip_entry_read($zip_entry,
                                                     zip_entry_filesize($zip_entry)));

                    if (file_exists($temp_path)) {
                      $file_ok = true;
                      if (extension_loaded("gd") && (cpm_option('cpm-perform-gd-check') == 1)) {
                        $file_ok = (getimagesize($temp_path) !== false);
                      }

                      if ($file_ok) {
                        @rename($temp_path, $target_root . '/' . $target_filename);
                        $files_created_in_operation[] = $target_root . '/' . $target_filename;
                        $files_uploaded[] = zip_entry_name($zip_entry);
                      } else {
                        @unlink($temp_path);
                        $invalid_filenames[] = zip_entry_name($zip_entry);
                      }
                    } else {
                      $files_not_uploaded[] = $zip_entry;
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
          //harmonious_end
        } else {
          $target_filename = $_FILES[$key]['name'];
          if (get_magic_quotes_gpc()) {
            $target_filename = stripslashes($target_filename);
          }

          if (isset($_POST['overwrite-existing-file-selector-checkbox'])) {
            $original_filename = $target_filename;
            $target_filename = $_POST['overwrite-existing-file-choice'];

            $new_post = false;
            if (pathinfo($original_filename, PATHINFO_EXTENSION) == pathinfo($target_filename, PATHINFO_EXTENSION)) {
              $result = cpm_breakdown_comic_filename($target_filename);
              $cpm_config->messages[] = sprintf(__('Uploaded file <strong>%1$s</strong> renamed to <strong>%2$s</strong>.', 'comicpress-manager'), $original_filename, $target_filename);
            } else {
              $cpm_config->warnings[] = sprintf(__('<strong>Extensions of %1$s and %2$s don\'t match.</strong> Make sure you\'re replacing the file with one of the same type.', 'comicpress-manager'), $original_filename, $target_filename);
              $result = false;
            }
          } else {
            if (count($files) == 1) {
              $date = strtotime($_POST['override-date']);
              if (($date !== false) && ($date !== -1)) {
                $target_filename = date(CPM_DATE_FORMAT, $date) . '-' . $target_filename;
                $cpm_config->messages[] = sprintf(__('Uploaded file %1$s renamed to %2$s.', 'comicpress-manager'), $_FILES[$key]['name'], $target_filename);
                $result = cpm_breakdown_comic_filename($target_filename);
              } else {
                if (preg_match('/\S/', $_POST['override-date']) > 0) {
                  $cpm_config->warnings[] = sprintf(__("Provided override date %s is not parseable by strtotime().", 'comicpress-manager'), $_POST['override-date']);
                }
              }
            }
            $result = cpm_breakdown_comic_filename($target_filename, true);
            if ($result !== false) { // bad file, can we get a date attached?
              if (isset($_POST['upload-date-format']) && !empty($_POST['upload-date-format'])) {
                $target_filename = date(CPM_DATE_FORMAT, strtotime($result['date'])) .
                                   $result['title'] . '.' . pathinfo($_FILES[$key]['name'], PATHINFO_EXTENSION);
              }
            }
          }

          $comic_file = $_FILES[$key]['name'];
          if ($result !== false) {
            extract($result, EXTR_PREFIX_ALL, "filename");

            $did_filecheck = false;
            $file_ok = true;
            if (extension_loaded("gd") && (cpm_option('cpm-perform-gd-check') == 1)) {
              $file_ok = (getimagesize($_FILES[$key]['tmp_name']) !== false);
              $did_filecheck = true;
            }

            if ($file_ok) {
              @move_uploaded_file($_FILES[$key]['tmp_name'], $target_root . '/' . $target_filename);
              $files_created_in_operation[] = $target_root . '/' . $target_filename;

              if (file_exists($target_root . '/' . $target_filename)) {
                $files_uploaded[] = $target_filename;
              } else {
                $files_not_uploaded[] = $target_filename;
              }
            } else {
              if ($did_filecheck) {
                $invalid_image_types[] = $comic_file;
              } else {
                $invalid_filenames[] = $comic_file;
              }
            }
          } else {
            $invalid_filenames[] = $comic_file;
          }
        }
      }
    }
    if (function_exists('get_site_option')) {
      if (cpm_wpmu_is_over_storage_limit()) { $ok_to_keep_uploading = false; break; }
    }
  }

  if ($ok_to_keep_uploading) {
    foreach ($files_uploaded as $target_filename) {
      $target_path = $target_root . '/' . $target_filename;
      @chmod($target_path, CPM_FILE_UPLOAD_CHMOD);
      if ($write_thumbnails) {
        $wrote_thumbnail = cpm_write_thumbnail($target_path, $target_filename);
      }

      if (!is_null($wrote_thumbnail)) {
        if (is_array($wrote_thumbnail)) {
          $thumbnails_written[] = $target_filename;
          $files_created_in_operation = array_merge($files_created_in_operation, $wrote_thumbnail);
        } else {
          $thumbnails_not_written[] = $target_filename;
        }
      }
    }
    if (function_exists('get_site_option')) {
      if (cpm_wpmu_is_over_storage_limit()) { $ok_to_keep_uploading = false; break; }
    }
  }

  if ($ok_to_keep_uploading) {
    foreach ($files_uploaded as $target_filename) {
      if ($new_post) {
        extract(cpm_breakdown_comic_filename($target_filename), EXTR_PREFIX_ALL, "filename");
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
    }
    cpm_display_operation_messages(compact('invalid_filenames', 'files_uploaded', 'files_not_uploaded',
                                           'thumbnails_written', 'thumbnails_not_written', 'posts_created',
                                           'duplicate_posts', 'invalid_image_types'));
  } else {
    $cpm_config->messages = array();
    $cpm_config->warnings = array($cpm_config->wpmu_disk_space_message);

    foreach ($files_created_in_operation as $file) { @unlink($file); }
  }

  return array($posts_created, $duplicate_posts);
}

/**
 * Display messages when CPM operations are completed.
 */
function cpm_display_operation_messages($info) {
  global $cpm_config;
  extract($info);

  if (count($invalid_filenames) > 0) {
    $cpm_config->messages[] = __("<strong>The following filenames were invalid:</strong> ", 'comicpress-manager') . implode(", ", $invalid_filenames);
  }

  if (count($invalid_image_types) > 0) {
    $cpm_config->warnings[] = __("<strong>According to GD, the following files were invalid image files:</strong> ", 'comicpress-manager') . implode(", ", $invalid_image_types);
  }

  if (count($files_uploaded) > 0) {
    $cpm_config->messages[] = __("<strong>The following files were uploaded:</strong> ", 'comicpress-manager') . implode(", ", $files_uploaded);
  }

  if (count($files_not_uploaded) > 0) {
    $cpm_config->messages[] = __("<strong>The following files were not uploaded, or the permissions on the uploaded file do not allow reading the file.</strong> Check the permissions of both the target directory and the upload directory and try again: ", 'comicpress-manager') . implode(", ", $files_not_uploaded);
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
      $post_links[] = "<li><strong>" . $comic_post['post_title'] . "</strong> (" . $comic_post['post_date'] . ") " . generate_view_edit_post_links($comic_post) . "</li>";
    }

    $cpm_config->messages[] = __("<strong>New posts created.</strong>  View them from the links below:", 'comicpress-manager') . " <ul>" . implode("", $post_links) . "</ul>";
  } else {
    if (count($files_uploaded) > 0) {
      if (count($duplicate_posts) == 0) {
        $cpm_config->messages[] = __("<strong>No new posts created.</strong>", 'comicpress-manager');
      }
    }
  }

  if (count($duplicate_posts) > 0) {
    $post_links = array();
    foreach ($duplicate_posts as $info) {
      list($comic_post, $comic_file) = $info;
      $post_links[] = "<li><strong>" . $comic_post['post_title'] . "</strong> (" . $comic_post['post_date'] . ") " . generate_view_edit_post_links($comic_post) . "</li>";
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

  <?php if (function_exists('wp_tiny_mce')) { wp_tiny_mce(); } ?>

  <strong>Post body template:</strong>
  <div id="title"></div>
  <div id="<?php echo user_can_richedit() ? 'postdivrich' : 'postdiv' ?>" class="postarea">
    <?php the_editor(cpm_option('cpm-default-post-content')) ?>
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
 * Include a JavaScript file, preferring the minified version of the file if available.
 */
function cpm_include_javascript($name) {

  $js_path = realpath(ABSPATH . cpm_get_plugin_path() . '/js');
  $plugin_url_root = get_option('siteurl') . '/' . cpm_get_plugin_path();

  $regular_file = $name;
  $minified_file = 'minified-' . $name;

  $file_to_use = $regular_file;
  if (file_exists($js_path . '/' . $minified_file)) {
    if (filemtime($js_path . '/' . $minified_file) >= filemtime($js_path . '/' . $regular_file)) {
      $file_to_use = $minified_file;
    }
  }

  ?><script type="text/javascript" src="<?php echo $plugin_url_root ?>/js/<?php echo $file_to_use ?>"></script><?php
}

/**
 * Write all of the styles and scripts.
 */
function cpm_write_global_styles_scripts() {
  global $cpm_config, $blog_id;

  $plugin_url_root = get_option('siteurl') . '/' . cpm_get_plugin_path();

  $ajax_request_url = isset($_SERVER['URL']) ? $_SERVER['URL'] : $_SERVER['SCRIPT_URL'];
  ?>

  <script type="text/javascript">
var messages = {
  'add_file_upload_file': "<?php _e("File:", 'comicpress-manager') ?>",
  'add_file_upload_remove': "<?php _e("remove", 'comicpress-manager') ?>",
  'count_missing_posts_none_missing': "<?php _e("You're not missing any posts!", 'comicpress-manager') ?>",
  'failure_in_counting_posts': "<?php _e("There was a failure in counting. You may have too many comics/posts to analyze before your server times out.", 'comicpress-manager') ?>",
  'count_missing_posts_counting': "<?php _e("counting", 'comicpress-manager') ?>"
};

var ajax_request_uri = "<?php echo $plugin_url_root ?>/comicpress_manager_count_missing_entries.php?blog_id=<?php echo $blog_id ?>";
  </script>
  <?php cpm_include_javascript("comicpress_script.js") ?>
  <link rel="stylesheet" href="<?php echo $plugin_url_root . '/comicpress_styles.css' ?>" type="text/css" />
  <?php if ($cpm_config->need_calendars) { ?>
    <link rel="stylesheet" href="<?php echo $plugin_url_root ?>/jscalendar-1.0/calendar-blue.css" type="text/css" />
    <script type="text/javascript" src="<?php echo $plugin_url_root ?>/jscalendar-1.0/calendar.js"></script>
    <script type="text/javascript" src="<?php echo $plugin_url_root ?>/jscalendar-1.0/lang/calendar-en.js"></script>
    <script type="text/javascript" src="<?php echo $plugin_url_root ?>/jscalendar-1.0/calendar-setup.js"></script>
  <?php } ?>
  <!--[if IE]>
    <script type="text/javascript">Event.observe(window, 'load', function() { prepare_comicpress_manager() })</script>
  <![endif]-->

<!--[if lte IE 6]>
<style type="text/css">div#cpm-container div#cpm-left-column { margin-top: 0 }</style>
<![endif]-->
<?php }

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

        if ((strpos(PHP_OS, "WIN") !== false) && ($style == "warnings")) {
          $output .= __("<p><strong>If your error is permissions-related, you may have to set some Windows-specific permissions on your filesystem.</strong> Consult your Webhost for more information.</p>", 'comicpress-manager');
        }

        ?>
        <div id="cpm-<?php echo $style ?>"><?php echo $output ?></div>
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
      $found_backup_files = glob(dirname($cpm_config->config_filepath) . '/comicpress-config.php.*');
      if ($found_backup_files === false) { $found_backup_files = array(); }
      foreach ($found_backup_files as $file) {
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

          <form action="" method="post">
            <input type="hidden" name="action" value="restore-backup" />
            <strong><?php _e("Restore from backup dated:", 'comicpress-manager') ?></strong>
              <select name="backup-file-time">
                <?php foreach($available_backup_files as $time) { ?>
                  <option value="<?php echo $time ?>">
                    <?php echo date("r", $time) ?>
                  </option>
                <?php } ?>
              </select>
            <input type="submit" class="button" value="<?php _e("Restore", 'comicpress-manager') ?>" />
          </form>
          <hr />
        <?php }
      }

      if ($cpm_config->show_config_editor) {
        echo cpm_manager_edit_config();
      } ?>

      <hr />

      <strong><?php _e('Debug info', 'comicpress-manager') ?></strong> (<em><?php _e("this data is sanitized to protect your server's configuration", 'comicpress-manager') ?></em>)

      <?php echo cpm_show_debug_info(false);

      return false;
    }
  return true;
}

/**
 * Sort backup files by timestamp.
 */
function cpm_available_backup_files_sort($a, $b) {
  if ($a[1] == $b[1]) return 0;
  return ($a[1] > $b[1]) ? -1 : 1;
}

/**
 * Handle all actions.
 */
function cpm_handle_actions() {
  global $cpm_config;

  $valid_actions = array('multiple-upload-file', 'create-missing-posts', 'delete-comic-and-post',
                         'update-config', 'restore-backup', 'generate-thumbnails', 'change-dates',
                         'write-comic-post', 'update-cpm-config', 'do-first-run', 'skip-first-run');

  //
  // take actions based upon $_POST['action']
  //
  if (isset($_POST['action'])) {
    if (in_array($_POST['action'], $valid_actions)) {
      require_once('actions/comicpress_' . $_POST['action'] . '.php');
      call_user_func("cpm_action_" . str_replace("-", "_", $_POST['action']));
    }
  }
}

/**
 * Show the details of the current setup.
 */
function cpm_show_comicpress_details() {
  global $cpm_config;

  $all_comic_dates_ok = true;
  $all_comic_dates = array();
  foreach ($cpm_config->comic_files as $comic_file) {
    if (($result = cpm_breakdown_comic_filename(pathinfo($comic_file, PATHINFO_BASENAME))) !== false) {
      if (isset($all_comic_dates[$result['date']])) { $all_comic_dates_ok = false; break; }
      $all_comic_dates[$result['date']] = true;
    }
  }

  ?>
    <!-- ComicPress details -->
    <div id="comicpress-details">
      <h2 style="padding-right: 0"><?php _e('ComicPress Details', 'comicpress-manager') ?></h2>
      <ul style="padding-left: 30px; margin: 0">
        <li><strong><?php _e("Configuration method:", 'comicpress-manager') ?></strong>
          <?php if ($cpm_config->config_method == "comicpress-config.php") { ?>
            <a href="?page=<?php echo plugin_basename(__FILE__) ?>-config"><?php echo $cpm_config->config_method ?></a>
            <?php if ($cpm_config->can_write_config) { ?>
              <?php _e('(click to edit)', 'comicpress-manager') ?>
            <?php } else { ?>
              <?php _e('(click to edit, cannot update automatically)', 'comicpress-manager') ?>
            <?php } ?>
          <?php } else { ?>
            <?php echo $cpm_config->config_method ?>
          <?php } ?>
        </li>
        <?php if (function_exists('get_site_option')) { ?>
          <li><strong><?php _e("Available disk space:", 'comicpress-manager') ?></strong>
          <?php printf(__("%0.2f MB"), cpm_wpmu_get_available_disk_space() / 1048576) ?>
        <?php } ?>
        <li><strong><?php _e('Comics folder:', 'comicpress-manager') ?></strong> <?php echo $cpm_config->properties['comic_folder'] ?><br />
            <?php
              $too_many_comics_message = "";
              if (!$all_comic_dates_ok) {
                ob_start(); ?>
                  , <a href="?page=<?php echo plugin_basename(__FILE__) ?>-status"><em><?php _e("multiple files on the same date!", 'comicpress-manager') ?></em></a>
                <?php $too_many_comics_message = trim(ob_get_clean());
              } ?>

            <?php printf(__ngettext('(%d comic in folder%s)', '(%d comics in folder%s)', count($cpm_config->comic_files), 'comicpress-manager'), count($cpm_config->comic_files), $too_many_comics_message) ?>
        </li>

        <?php foreach (array('archive' => __('Archive folder:', 'comicpress-manager'),
                             'rss'     => __('RSS feed folder', 'comicpress-manager'))
                       as $type => $title) { ?>
          <li><strong><?php echo $title ?></strong> <?php echo $cpm_config->properties["${type}_comic_folder"] ?>
            <?php if (
              ($cpm_config->get_scale_method() != CPM_SCALE_NONE) &&
              (cpm_option("cpm-${type}-generate-thumbnails") == 1) &&
              ($cpm_config->separate_thumbs_folder_defined[$type]) &&
              ($cpm_config->thumbs_folder_writable[$type])
            ) { ?>
              (<em><?php _e('generating', 'comicpress-manager') ?></em>)
            <?php } else {
              $reasons = array();

              if ($cpm_config->get_scale_method() == CPM_SCALE_NONE) { $reasons[] = __("No scaling software", 'comicpress-manager'); }
              if (cpm_option("cpm-${type}-generate-thumbnails") == 0) { $reasons[] = __("Generation disabled", 'comicpress-manager'); }
              if (!$cpm_config->separate_thumbs_folder_defined[$type]) { $reasons[] = __("Same as comics folder", 'comicpress-manager'); }
              if (!$cpm_config->thumbs_folder_writable[$type]) { $reasons[] = __("Not writable", 'comicpress-manager'); }
              ?>
              (<em style="cursor: help; text-decoration: underline" title="<?php echo implode(", ", $reasons) ?>">not generating</em>)
            <?php } ?>
          </li>
        <?php } ?>

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

        <?php if (!function_exists('get_site_option')) { ?>
          <li><strong><?php _e("PHP Version:", 'comicpress-manager') ?></strong> <?php echo phpversion() ?>
              <?php if (substr(phpversion(), 0, 3) < 5.2) { ?>
                (<a href="http://gophp5.org/hosts"><?php _e("upgrade strongly recommended", 'comicpress-manager') ?></a>)
              <?php } ?>
          </li>
          <li>
            <strong><?php _e('Theme folder:', 'comicpress-manager') ?></strong>
            <?php $theme_info = get_theme(get_current_theme());
                  if (!empty($theme_info['Template'])) {
                    echo $theme_info['Template'];
                  } else {
                    echo __("<em>Something's misconfigured with your theme...</em>", 'comicpress-manager');
                  } ?>
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
        <?php } ?>
      </ul>
    </div>
  <?php
}

/**
 * Show site debug info.
 */
function cpm_show_debug_info($display_none = true) {
  global $cpm_config;

  ob_start(); ?>
  <span id="debug-info" class="code-block" <?php echo $display_none ? "style=\"display: none\"" : "" ?>><?php
    $output_config = get_object_vars($cpm_config);
    $output_config['comic_files'] = count($cpm_config->comic_files) . " comic files";
    $output_config['config_filepath'] = substr(realpath($cpm_config->config_filepath), CPM_STRLEN_REALPATH_DOCUMENT_ROOT);
    $output_config['path'] = substr(realpath($cpm_config->path), CPM_STRLEN_REALPATH_DOCUMENT_ROOT);
    $output_config['zip_enabled'] = extension_loaded("zip");

    clearstatcache();
    $output_config['folder_perms'] = array();

    foreach (array(
      'comic' => CPM_DOCUMENT_ROOT . '/' . $cpm_config->properties['comic_folder'],
      'rss' => CPM_DOCUMENT_ROOT . '/' . $cpm_config->properties['rss_comic_folder'],
      'archive' => CPM_DOCUMENT_ROOT . '/' . $cpm_config->properties['archive_comic_folder'],
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

  include('cp_configuration_options.php');

  ob_start(); ?>

  <form action="" method="post" id="config-editor">
    <input type="hidden" name="action" value="update-config" />

    <table class="form-table">
      <?php foreach ($comicpress_configuration_options as $field_info) {
        extract($field_info);

        $description = " <em>(" . $description . ")</em>";

        $config_id = (isset($field_info['variable_name'])) ? $field_info['variable_name'] : $field_info['id'];

        switch($type) {
          case "category": ?>
            <tr>
              <th scope="row"><?php echo $name ?>:</th>
              <td><select name="<?php echo $config_id ?>" title="<?php _e('All possible WordPress categories', 'comicpress-manager') ?>">
                             <?php foreach (get_all_category_ids() as $cat_id) {
                               $category = get_category($cat_id); ?>
                               <option value="<?php echo $category->cat_ID ?>"
                                       <?php echo ($cpm_config->properties[$config_id] == $cat_id) ? " selected" : "" ?>><?php echo $category->cat_name; ?></option>
                             <?php } ?>
                           </select><?php echo $description ?></td>
            </tr>
            <?php break;
          case "folder": ?>
            <tr>
              <th scope="row"><?php echo $name ?>:</th>
              <td class="config-field">
                <select title="<?php _e("List of possible folders at the root of your site", 'comicpress-manager') ?>" name="<?php echo $config_id ?>" id="<?php echo $config_id ?>">
                <?php 
                  $comic_site_root_files = glob(CPM_DOCUMENT_ROOT . '/*');
                  if ($comic_site_root_files === false) { $comic_site_root_files = array(); }

                  foreach ($comic_site_root_files as $file) {
                    if (is_dir($file)) {
                      $file = preg_replace("#/#", '', substr($file, strlen(CPM_DOCUMENT_ROOT))); ?>
                      <option <?php echo ($file == $cpm_config->properties[$config_id]) ? " selected" : "" ?> value="<?php echo $file ?>"><?php echo $file ?></option>
                    <?php }
                  } ?>
                </select><?php echo $description ?>
              </td>
            </tr>
            <?php break;
          case "integer": ?>
            <tr>
              <th scope="row"><?php echo $name ?>:</th>
              <td><input type="text" name="<?php echo $config_id ?>" size="20" value="<?php echo $cpm_config->properties[$config_id] ?>" /><?php echo $description ?></td>
            </tr>
            <?php break;
        }
      } ?>
    </table>

    <p>
      <?php _e("<strong>Create your comics, archive, or RSS folders first</strong>, then reload this page and use the dropdowns to select the target folder.", 'comicpress-manager') ?>
    </p>

    <?php if (!function_exists('get_site_option')) { ?>
      <?php if (!$cpm_config->is_wp_options) { ?>
        <p><input type="checkbox" name="just-show-config" id="just-show-config" value="yes" /> <label for="just-show-config"><?php _e("Don't try to write my config out; just display it", 'comicpress-manager') ?></label></p>
      <?php } ?>
    <?php } ?>

    <input class="button update-config" type="submit" value="<?php _e("Update Config", 'comicpress-manager') ?>" />
  </form>

  <?php return ob_get_clean();
}

/**
 * Show the footer.
 */
function cpm_show_footer() {
  $version_string = "";
  foreach (array('/', '/../') as $pathing) {
    if (($path = realpath(dirname(__FILE__) . $pathing . 'comicpress_manager.php')) !== false) {
      $info = get_plugin_data($path);
      $version_string = sprintf(__("Version %s |", 'comicpress-manager'), $info['Version']);
    }
  }

  ?>
  <div id="cpm-footer">
    <?php _e('<a href="http://claritycomic.com/comicpress-manager/" target="_new">ComicPress Manager</a> is built for the <a href="http://www.mindfaucet.com/comicpress/" target="_new">ComicPress</a> theme', 'comicpress-manager') ?> |
    <?php _e('Copyright 2008-2009 <a href="mailto:john@claritycomic.com?Subject=ComicPress Manager Comments">John Bintz</a>', 'comicpress-manager') ?> |
    <?php _e('Released under the GNU GPL', 'comicpress-manager') ?> |
    <?php echo $version_string ?>
    <?php _e('Uses the <a target="_new" href="http://www.dynarch.com/projects/calendar/">Dynarch DHTML Calendar Widget</a>', 'comicpress-manager') ?>
  </div>
<?php }

?>
