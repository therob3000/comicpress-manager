<?php
/*
Plugin Name: ComicPress Manager
Plugin URI: http://claritycomic.com/comicpress-manager/
Description: Manage the comics within a <a href="http://www.mindfaucet.com/comicpress/">ComicPress</a> theme installation.
Version: 0.5.1
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

$comicpress_config = array(
  'comic_folder' => '',
  'comiccat'     => '',
  'blogcat'      => '',
  'rss_comic_folder' => '',
  'archive_comic_folder' => '',
  'archive_comic_width' => '',
  'blog_postcount' => '',
  'default_post_time' => "12:00 am",
  'default_post_content' => "{category} for {date} - {title}"
);

wp_enqueue_script('prototype');
add_action("admin_menu", "cpm_add_pages");
add_action("edit_form_advanced", "cpm_show_comic");

function cpm_add_pages() {
  add_menu_page("ComicPress Manager", "ComicPress", 10, __FILE__, "manager_index");
}

function cpm_show_comic() {
  global $post, $comicpress_config;

  read_current_theme_comicpress_config();

  if (($comic = find_comic_by_date(strtotime($post->post_date))) !== false) {
    $ok = false;
    $post_categories = wp_get_post_categories($post->ID);

    $comic_uri = substr(realpath($comic), strlen(realpath($_SERVER['DOCUMENT_ROOT'])));
    
    if (isset($comicpress_config['comiccat'])) {
      if (is_array($comicpress_config['comiccat'])) {
        $ok = count(array_intersect($comicpress_config['comiccat'], $post_categories)) > 0;
      } else {
        $ok = (in_array($comicpress_config['comiccat'], $post_categories));
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
  global $comicpress_config;
  
  return ABSPATH . $comicpress_config['comic_folder'];
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
function generate_comic_categories_options($comic_category_info) {
  global $comicpress_config;

  ob_start();
  foreach (get_all_category_ids() as $cat_id) {
    $ok = false;

    if (is_array($comicpress_config['comiccat'])) {
      $ok = in_array($cat_id, $comicpress_config['comiccat']);
    } else {
      $ok = ($cat_id == $comicpress_config['comiccat']);
    }

    if ($ok) {
      $category = get_category($cat_id);
      ?>
      <option
        value="<?php echo $category->cat_ID ?>"
        <?php if (!is_null($comic_category_info)) {
          echo ($comicpress_config['comiccat'] == $cat_id) ? " selected" : "";
        } ?>
        ><?php echo $category->cat_name; ?></option>
    <?php }
  }
  return ob_get_clean();
}

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
  global $comicpress_config;
  
  $file = file_get_contents($filepath);

  $variable_values = array();

  foreach (array_keys($comicpress_config) as $variable) {
    if (preg_match("#\\$${variable}\ *\=\ *([^\;]*)\;#", $file, $matches) > 0) {
      $variable_values[$variable] = preg_replace('#"#', '', $matches[1]);
    }
  }

  $comicpress_config = array_merge($comicpress_config, $variable_values);
}

function write_comicpress_config_functions_php($filepath) {
  global $comicpress_config;

  $file_lines = file($filepath, FILE_IGNORE_NEW_LINES);

  //foreach ($file_lines as &$line) {
  for ($i = 0; $i < count($file_lines); $i++) {
    foreach (array_keys($comicpress_config) as $variable) {
      if (preg_match("#\\$${variable}\ *\=\ *([^\;]*)\;#", $file_lines[$i], $matches) > 0) {
        $file_lines[$i] = '$' . $variable . ' = "' . $comicpress_config[$variable] . '";';
      }
    }
  }

  if (@rename($filepath, $filepath . '.' . time())) {
    return (@file_put_contents($filepath, implode("\n", $file_lines)) !== false);
  } else {
    return false;
  }
}

/**
 * Read the ComicPress config from config.json.
 */
function read_comicpress_config_json($filepath) {
  global $comicpress_config;

  $config = json_decode(file_get_contents($filepath), true);

  $comicpress_config = array_merge($comicpress_config, $config);
}

/**
 * Check the current ComicPress Config.
 */
function check_comicpress_config($variables) {
  global $comicpress_config;

  extract($variables);

  $errors = array();
  $warnings = array();
  $messages = array();

  // quick check to see if the theme is ComicPress.
  // this needs to be made more robust.
  if (preg_match('/ComicPress/', get_current_theme()) == 0) {
    $warnings[] = "The current theme isn't the ComicPress theme.  If you've renamed the theme, ignore this warning.";
  }

  // sanity checks for the comic upload folder
  if (!file_exists($path)) {
    $errors[] = "The comic folder <strong>" . $comicpress_config['comic_folder'] . "</strong> does not exist.";
  } else {
    do {
      $tmp_filename = "test-" . md5(rand(1, 1000000));
    } while (file_exists($path . '/' . $tmp_filename));
    
    if (!@touch($path . '/' . $tmp_filename)) {
      $errors[] = "The comic folder <strong>" . $comicpress_config['comic_folder'] . "</strong> is not writable by the Webserver process.";
    } else {
      @unlink($path . '/' . $tmp_filename);
    }
  }

  // ensure the defined comic category exists
  if (is_null($comicpress_config['comiccat'])) {
    // all non-blog categories are comic categories
    $comic_category_info = array(
      'name' => "All other categories",
    );
    $comicpress_config['comiccat'] = array_diff(get_all_category_ids(), array($comicpress_config['blogcat']));

    if (count($comicpress_config['comiccat']) == 1) {
      $comicpress_config['comiccat'] = $comicpress_config['comiccat'][0];
      $comic_category_info = get_object_vars(get_category($comicpress_config['comiccat']));
    }
  } else {
    // one comic category is specified
    if (is_null($comic_category_info = get_category($comicpress_config['comiccat']))) {
      $warnings[] = "The requested category ID for your comic, <strong>" . $comicpress_config['comiccat'] . "</strong>, doesn't exist!";
    } else {
      $comic_category_info = get_object_vars($comic_category_info);
    }
  }

  // ensure the defined blog category exists
  // TODO: multiple blog categories
  if (is_null($blog_category_info = get_category($comicpress_config['blogcat']))) {
    $errors[] = "The requested category ID for your blog, <strong>" . $comicpress_config['blogcat'] . "</strong>, doesn't exist!";
  } else {
    $blog_category_info = get_object_vars($blog_category_info);
  }

  // a quick note if you have no comics uploaded.
  // could be a sign of something more serious.
  if (count($comic_files = glob($path . "/*")) == 0) {
    $warnings[] = "Your comics directory is empty!";
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

    $post_title    = $filename_converted_title;
    $post_date     = date('Y-m-d H:i:s', $timestamp);
    $post_category = array($_POST['category']);
    $post_status   = isset($_POST['publish']) ? "publish" : "draft";

    return compact('post_content', 'post_title', 'post_date', 'post_category', 'post_status');
  }

  return false;
}

/**
 * Handle uploading a file.
 */
function handle_file_upload($key, $path, $messages, $errors) {
  if (is_uploaded_file($_FILES[$key]['tmp_name'])) {
    if (($result = breakdown_comic_filename($_FILES[$key]['name'])) !== false) {
      extract($result, EXTR_PREFIX_ALL, "filename");
      move_uploaded_file($_FILES[$key]['tmp_name'], $path . '/' . $_FILES[$key]['name']);
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
              $messages[] = "Post created for " . $_FILES[$key]['name'] . ". " . generate_view_edit_post_links($post_info);
            }
          } else {
            $post_info = get_post($post_id, ARRAY_A);

            $messages[] = "File " . $_FILES[$key]['name'] . " uploaded, but a post for this comic already exists. " . generate_view_edit_post_links($post_info);
          }
        } else {
          $errors[] = "There was an error in the post time for filename <strong>" . $_FILES[$key]['name'] . " - " .  $_POST['time'] . "</strong>";
        }
      } else {
        $messages[] = "Comic " . $_FILES[$key]['name'] . " uploaded.";
      }
    } else {
      $errors[] = "There was an error in the filename <strong>" . $_FILES[$key]['name'] . "</strong>";
    }
  }

  return array($messages, $errors);
}

/**
 * Show the Post Body Template.
 */
function cpm_show_post_body_template($width = 435) {
  global $comicpress_config; ?>

  Post body template:<br />
  <textarea name="post-text" rows="4" style="width: <?= $width ?>px"><?php echo $comicpress_config['default_post_content'] ?></textarea>
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
 * The main manager screen.
 */
function manager_index() {
  global $comicpress_config;

  $config_method = read_current_theme_comicpress_config();

  $path = get_comic_folder_path();
  $plugin_path = substr(__FILE__, strlen($_SERVER['DOCUMENT_ROOT']));

  extract(check_comicpress_config(compact('path')));

  $get_posts_string = "numberposts=9999&post_status=&category=";
  if (is_array($comicpress_config['comiccat'])) {
    $get_posts_string .= implode(",", $comicpress_config['comiccat']);
  } else {
    $get_posts_string .= $comicpress_config['comiccat'];
  }
  
  //
  // take actions based upon $_POST['action']
  //
  if (isset($_POST['action'])) {
    switch (strtolower($_POST['action'])) {
      // upload a single comic file
      case "upload-file":
        if (isset($_FILES['uploaded_file'])) {
          list($messages, $errors) = handle_file_upload('uploaded_file', $path, $messages, $errors);
        }
        break;
      case "multiple-upload-file":
        foreach ($_FILES as $name => $info) {
          if (strpos($name, "upload-") !== false) {
            list($messages, $errors) = handle_file_upload($name, $path, $messages, $errors);
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
        foreach ($comic_files as $comic_file) {
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

        foreach ($comic_files as $comic_file) {
          $comic_file = pathinfo($comic_file, PATHINFO_BASENAME);
          if (($result = breakdown_comic_filename($comic_file)) !== false) {
            extract($result, EXTR_PREFIX_ALL, 'filename');
            if (!in_array($result['date'], $all_post_dates)) {
              if (($post_hash = generate_post_hash($filename_date, $filename_converted_title)) !== false) {
                if (!is_null($post_id = wp_insert_post($post_hash))) {
                  $posts_created[] = get_post($post_id, ARRAY_A);
                }
              } else {
                $warnings[] = "There was an error in the post time for ${comic_file}.";
              }
            }
          }
        }

        if (count($posts_created) > 0) {
          $post_links = array();
          foreach ($posts_created as $comic_post) {
            $post_links[] = "<li>(" . $comic_post['post_date'] . ") " . generate_view_edit_post_links($comic_post);
          }

          $messages[] = "New posts created.  View them from the links below: <ul>" . implode("", $post_links) . "</ul>";
        } else {
          $messages[] = "No new posts created.";
        }
        break;
      // upload a zip file of posts
      case "upload-zip-file":
        $invalid_files = array();
        $posts_created = array();
        $duplicate_posts = array();

        if (isset($_FILES['uploaded_file'])) {
          if (is_uploaded_file($_FILES['uploaded_file']['tmp_name'])) {
            if (is_resource($zip = zip_open($_FILES['uploaded_file']['tmp_name']))) {

              while ($zip_entry = zip_read($zip)) {
                $comic_file = zip_entry_name($zip_entry);
                if (($result = breakdown_comic_filename($comic_file)) !== false) {
                  extract($result, EXTR_PREFIX_ALL, 'filename');
                  $target_path = $path . '/' . zip_entry_name($zip_entry);
                  if (zip_entry_open($zip, $zip_entry, "r")) {
                    file_put_contents($target_path,
                                      zip_entry_read($zip_entry,
                                                     zip_entry_filesize($zip_entry)));
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
                      $warnings[] = "There was an error in the post time for ${comic_file}.";
                    }
                  }
                } else {
                  $invalid_files[] = $comic_file;
                }
              }
              zip_close($zip);
            }
          }
        }

        if (count($posts_created) > 0) {
          $post_links = array();
          foreach ($posts_created as $comic_post) {
            $post_links[] = "<li>(" . $comic_post['post_date'] . ") " . generate_view_edit_post_links($comic_post);
          }

          $messages[] = "New posts created.  View them from the links below: <ul>" . implode("", $post_links) . "</ul>";
        } else {
          $messages[] = "No new posts created.";
        }

        if (count($invalid_files) > 0) {
          $messages[] = "The following filenames were invalid: " . implode(", ", $invalid_files);
        }

        if (count($duplicate_posts) > 0) {
          $messages[] = "The following files would have created duplicate posts: " . implode(", ", $duplicate_posts);
        }
        break;
      case "delete-comic-and-post":
        $comic_file = pathinfo($_POST['comic'], PATHINFO_BASENAME);

        if (file_exists($path . '/' . $comic_file)) {
          if (($result = breakdown_comic_filename($comic_file)) !== false) {
            extract($result, EXTR_PREFIX_ALL, 'filename');

            $all_possible_posts = array();
            foreach (get_posts($get_posts_string) as $comic_post) {
              if (date("Y-m-d", strtotime($comic_post->post_date)) == $filename_date) {
                $all_possible_posts[] = $comic_post->ID;
              }
            }

            if (count($all_possible_posts) > 1) {
              $messages[] = "There are multiple posts (" . implode(", ", $all_possible_posts) . ") with the date ${filename_date} in the comic categories.  Please manually delete the posts.";
            } else {
              @unlink($path . '/' . $comic_file);
              if (count($all_possible_posts) == 0) {
                $messages[] = "<strong>${comic_file} deleted</strong>.  No matching posts found.";
              } else {
                wp_delete_post($all_possible_posts[0]);
                $messages[] = "<strong>${comic_file} and post " . $all_possible_posts[0] . " deleted.</strong>";
              }
              $comic_files = glob($path . "/*");
            }
          }
        }
        break;
      case "update-config":
        if ($config_method == "comicpress-config.php") {
          foreach (array_keys($comicpress_config) as $property) {
            if (isset($_POST[$property])) {
              $comicpress_config[$property] = $_POST[$property];
            }
          }

          if (!is_null($filepath = get_functions_php_filepath())) {
            if (write_comicpress_config_functions_php($filepath)) {
              $config_method = read_current_theme_comicpress_config();
              $path = get_comic_folder_path();
              $plugin_path = substr(__FILE__, strlen($_SERVER['DOCUMENT_ROOT']));

              extract(check_comicpress_config(compact('path')));

              $messages[] = "Configuration updated and original config backed up.";
            } else {
              $relative_path = substr(realpath($filepath), strlen(realpath($_SERVER['DOCUMENT_ROOT'])));
              $warnings[] = "<strong>Configuration not updated</strong>, check the permissions of ${relative_path} and the theme folder.";
            }
          }
        }
        break;
    }
  }

  ?>

  <script type="text/javascript">
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
  Event.observe('new-post-checkbox', 'click', function() { hide_show_new_post_holder("new-post") });
  Event.observe('multiple-new-post-checkbox', 'click', function() { hide_show_new_post_holder("multiple-new-post") });
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

  hide_show_new_post_holder("new-post");
  hide_show_new_post_holder("multiple-new-post");
  change_image_preview();

  // just in case...
  $('cpm-right-column').style.minHeight = $('cpm-left-column').offsetHeight + "px";

  add_file_upload();
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
  float: left;
  display: inline;
  clear: left;
}

form#config-editor span.config-field {
  width: 145px;
  float: left;
  display: inline;
}

form#config-editor input.update-config {
  clear: left;
  width: 295px;
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
  
<div class="wrap">  
  
  <div id="cpm-container">
    <?php

    // display informative messages to the use
    // TODO: remove separate arrays and tag messages based on an enum value
    foreach (array(
      array($messages, "The operation you just performed returned the following:"),
      array($warnings, "Your configuration has some potential problems:"),
      array($errors,   "The following problems were found in your configuration:")
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
    if (count($errors) > 0) {
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

      if ($config_method == "comicpress-config.php") {
        echo manager_edit_config();
      }

      return;
    } ?>

    <!-- Header -->
    
    <?php if (!is_null($comic_category_info)) { ?>
      <h2>Managing &#8216;<?php echo $comic_category_info['name'] ?>&#8217;</h2>
    <?php } else { ?>
      <h2>Managing ComicPress</h2>
    <?php } ?>

	
    <div id="cpm-left-column">
      <!-- ComicPress details -->
      <div id="comicpress-details">
        <h2 style="padding-right: 0">ComicPress Details</h2>
        <ul style="padding-left: 30px">
          <li><strong>Configuration method:</strong>
            <?php if ($config_method == "comicpress-config.php") { ?>
              <a href="#" onclick="$('config-editor').show(); return false"><?php echo $config_method ?></a> (click to edit)
            <?php } else { ?>
              <?php echo $config_method ?>
            <?php } ?>
          </li>
          <li><strong>Comics folder:</strong> ABSPATH/<?php echo $comicpress_config['comic_folder'] ?><br />
              (<?php echo count($comic_files) ?> comic<?php echo (count($comic_files) != 1) ? "s" : "" ?> in folder)</li>
          <li><strong>Comic categor<?php echo (is_array($comicpress_config['comiccat']) && count($comicpress_config['comiccat']) != 1) ? "ies" : "y" ?>:</strong>
            <?php if (is_array($comicpress_config['comiccat'])) { ?>
              <ul>
                <?php foreach ($comicpress_config['comiccat'] as $cat_id) { ?>
                  <li><a href="<?php echo get_category_link($cat_id) ?>"><?php echo get_cat_name($cat_id) ?></a> (ID <?php echo $cat_id ?>)</li>
                <?php } ?>
              </ul>
            <?php } else { ?>
              <a href="<?php echo get_category_link($comicpress_config['comiccat']) ?>"><?php echo $comic_category_info['name'] ?></a> (ID <?php echo $comicpress_config['comiccat'] ?>)
            <?php } ?>
          </li>
          <li><strong>Blog category:</strong> <a href="<?php echo get_category_link($comicpress_config['blogcat']) ?>" ?>
              <?php echo $blog_category_info['name'] ?></a> (ID <?php echo $comicpress_config['blogcat'] ?>)</li>
          <li><strong>PHP Version:</strong> <?= phpversion() ?>
              <?php if (substr(phpversion(), 0, 3) < 5.2) { ?>
                (<a href="http://gophp5.org/hosts">upgrade strongly recommended</a>)
              <?php } ?>
          </li>
        </ul>
      </div>

      <div id="config-editor" style="display: none">
        <h2 style="padding-right:0;">Configuration Editor</h2>
        <?php echo manager_edit_config() ?>
      </div>

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
          <strong>Upload a single comic</strong> is for when you draw and upload one comic at a time.
          <strong>Upload multiple comics</strong> lets you upload multiple comics at a time, and add a default
          post body for each comic.
        </p>

        <p>
          <?php if (extension_loaded('zip')) { ?>
            <strong>Upload a Zip file and create new posts</strong> combines the file transfer and post creation steps into one action, allowing you to quickly add new comics to your site.
          <?php } else { ?>
            The <strong>Upload a Zip file and create new posts</strong> options is not available to you because you do not have the PHP <strong>zip</strong> extension installed.
          <?php } ?>
        </p>
 
        <p>
          <strong>Create missing posts for uploaded comics</strong> is for when you upload a lot of comics to your comic folder and want to generate generic posts for all of the new comics, or for when you're migrating from another system to ComicPress.
        </p>

        <p>
          <strong>Delete a comic file and the associated post, if found</strong> lets you delete a comic file and the post that goes with it.
        </p>
      </div>
    </div>

    <!-- Upload a single comic -->
    <div id="cpm-right-column">
      <div class="activity-box top-activity-box">
        <h2 style="padding-right:0;">Upload A Single Comic</h2>
        <h3>&mdash; any existing file with the same name will be overwritten</h3>

        <form action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="upload-file" />
          File: <input type="file" name="uploaded_file" /><br />
          Generate new post for uploaded file: <input id="new-post-checkbox" type="checkbox" name="new_post" value="yes" checked />
          <div id="new-post-holder">
            Category: <select name="category"><?php echo generate_comic_categories_options($comic_category_info) ?></select><br />
            Time to post: <input type="text" name="time" value="<?php echo $comicpress_config['default_post_time'] ?>" size="10" /><br />
            Publish post: <input type="checkbox" name="publish" value="yes" checked />
            Don't check for duplicate posts: <input type="checkbox" name="no_duplicate_check" value="yes" />
          </div>
          <br /><input type="submit" value="Upload Single Comic" style="width: 445px" />
        </form>
      </div>

      <div class="activity-box">
        <h2 style="padding-right:0;">Upload Multiple Comics</h2>
        <h3>&mdash; any existing files with the same name will be overwritten</h3>

        <form action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="multiple-upload-file" />
          <div id="multiple-file-upload">
          </div>
          <div style="text-align: center">
            [<a href="#" onclick="add_file_upload(); return false">Add file to upload</a>]
          </div>

          Generate new posts for uploaded file: <input id="multiple-new-post-checkbox" type="checkbox" name="new_post" value="yes" checked />
          <div id="multiple-new-post-holder">
            Category: <select name="category"><?php echo generate_comic_categories_options($comic_category_info) ?></select><br />
            Time to post: <input type="text" name="time" value="<?php echo $comicpress_config['default_post_time'] ?>" size="10" /><br />
            Publish post: <input type="checkbox" name="publish" value="yes" checked />
            Don't check for duplicate posts: <input type="checkbox" name="no_duplicate_check" value="yes" /><br />
            
            <?php cpm_show_post_body_template(420) ?>
            
          </div>
          <br /><input type="submit" value="Upload Multiple Comics" style="width: 445px" />
        </form>
      </div>

      <!-- Upload a Zip file and create new posts -->
      <?php if (extension_loaded('zip')) { ?>
        <div class="activity-box">
          <h2 style="padding-right:0;">Upload A Zip File &amp; Create New Posts</h2>
          <h3>&mdash; any existing files with the same name will be overwritten)</h3>

          <form action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post" style="margin-top: 10px" enctype="multipart/form-data">
            <input type="hidden" name="action" value="upload-zip-file" />
            File: <input type="file" name="uploaded_file" /><br />
            Category: <select name="category"><?php echo generate_comic_categories_options($comic_category_info) ?></select><br />
            Time for each post: <input type="text" name="time" value="<?php echo $comicpress_config['default_post_time'] ?>" size="10" /><br />
            Publish posts: <input type="checkbox" name="publish" value="yes" checked />
            Don't check for duplicate posts: <input type="checkbox" name="no_duplicate_check" value="yes" /><br />

            <?php cpm_show_post_body_template() ?>

            <input type="submit" value="Upload Zip and Create posts" style="width: 445px" />
          </form>
        </div>
      <?php } ?>

      <!-- Create missing posts for uploaded comics -->
      <div class="activity-box">
        <h2 style="padding-right:0;">Create Missing Posts For Uploaded Comics</h2>
        <h3>&mdash; acts as a batch import process</h3>

        <a href="#" onclick="return false" id="count-missing-posts-clicker">Count the number of missing posts</a> (may take a while): <span id="missing-posts-display"></span>

        <div id="create-missing-posts-holder">
          <form action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post" style="margin-top: 10px">
            <input type="hidden" name="action" value="create-missing-posts" />
            Category: <select id="missing-posts-category" name="category"><?php echo generate_comic_categories_options($comic_category_info) ?></select><br />
            Time for each post: <input type="text" name="time" value="<?php echo $comicpress_config['default_post_time'] ?>" size="10" /><br />
            Publish posts: <input type="checkbox" name="publish" value="yes" checked /><br />
            
            <?php cpm_show_post_body_template() ?>
            
            <input type="submit" value="Create posts" style="width: 445px" />
          </form>
        </div>
      </div>

      <!-- Delete a comic and a post -->
      <div class="activity-box">
        <h2 style="padding-right:0;">Delete A Comic File &amp; Post (if found)</h2>

        <?php if (count($comic_files) > 0) { ?>
          <form action="<?php echo $_SERVER['REQUEST_URI'] ?>" method="post" onsubmit="return confirm('Are you sure?')">
            <input type="hidden" name="action" value="delete-comic-and-post" />
            
            Comic to delete:<br />
              <select style="width: 445px" id="delete-comic-dropdown" name="comic" align="absmiddle" onchange="change_image_preview()">
                <?php foreach ($comic_files as $file) { ?>
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

    <div id="cpm-footer">
      <a href="http://claritycomic.com/comicpress-manager/" target="_new">ComicPress Manager</a> is built for the <a href="http://www.mindfaucet.com/comicpress/" target="_new">ComicPress</a> theme | Copyright 2008 <a href="mailto:jcoswell@coswellproductions.org?Subject=ComicPress Manager Comments">John Bintz</a> | Released under the GNU GPL | Version 0.5
    </div>
  </div>

</div>

  <?php
}

function manager_edit_config() {
  global $comicpress_config;
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
                                     <?php echo ($comicpress_config[$field] == $cat_id) ? " selected" : "" ?>><?php echo $category->cat_name; ?></option>
                           <?php } ?>
                         </select></span>
          <?php break;
        case "directory":
        case "integer": ?>
          <span class="config-title"><?= $title ?>:</span>
          <span class="config-field"><input type="text" name="<?= $field ?>" size="10" value="<?php echo $comicpress_config[$field] ?>" /></span>
          <?php break;
      }
    } ?>
    <input class="update-config" type="submit" value="Update Config" />
  </form>

  <?php return ob_get_clean();
}

?>