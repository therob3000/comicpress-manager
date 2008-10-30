<?php

/**
 * Show the comic in the Post editor.
 */
function cpm_show_comic() {
  global $post, $cpm_config;

  read_current_theme_comicpress_config();

  if (($comic = find_comic_by_date(strtotime($post->post_date))) !== false) {
    $ok = false;
    $post_categories = wp_get_post_categories($post->ID);

    $comic_uri = cpm_build_comic_uri($comic, CPM_DOCUMENT_ROOT);

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
      $date_root = substr($comic_filename, 0, strlen(date(CPM_DATE_FORMAT)));
      $thumbnails_found = cpm_find_thumbnails($date_root); ?>
        <script type="text/javascript">
          function show_comic() {
            if ($('comic-icon').offsetWidth > $('comic-icon').offsetHeight) {
              $('preview-comic').width = 400;
            } else {
              $('preview-comic').height = 400;
            }
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
              <img id="preview-comic" src="<?php echo $comic_uri ?>" />
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

?>