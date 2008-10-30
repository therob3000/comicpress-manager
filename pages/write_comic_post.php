<?php

/**
 * Write Comic Post is a stripped-down version of the ComicPress Upload screen
 * that lives in the Write menu of WP Admin.
 */
function cpm_manager_write_comic() {
  global $cpm_config;

  $ok_to_generate_thumbs = false;
  $thumbnails_to_generate = array();

  if ($cpm_config->get_scale_method() != CPM_SCALE_NONE) {
    foreach ($cpm_config->thumbs_folder_writable as $type => $value) {
      if ($value) {
        if ($cpm_config->separate_thumbs_folder_defined[$type] !== false) {
          if ($cpm_config->properties[$type . "_generate_thumbnails"] == true) {
            $ok_to_generate_thumbs = true;
            $thumbnails_to_generate[] = $type;
          }
        }
      }
    }
  }

  cpm_write_global_styles_scripts();
  ?>
  <div class="wrap">
    <div id="cpm-container">
      <?php cpm_handle_warnings() ?>
      <?php if (count($cpm_config->errors) == 0) { ?>
        <h2><?php _e("Write Comic Post", 'comicpress-manager') ?></h2>

        <p>
          <?php printf(__("<strong>Upload a single comic file</strong> and immediately start editing the associated post. Your post will be going live at <strong>%s</strong> on the provided date.", 'comicpress-manager'), $cpm_config->properties['default_post_time']) ?>

          <?php if ($ok_to_generate_thumbs) {
            ?>
            <?php printf(__("You'll be generating <strong>%s</strong> thumbnails that are <strong>%s</strong> pixels wide.", 'comicpress-manager'), implode($thumbnails_to_generate), $cpm_config->properties['archive_comic_width']) ?>
          <?php } ?>
        </p>

        <form action="" method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="write-comic-post" />
          <input type="hidden" name="upload-destination" value="comic" />
          <input type="hidden" name="new_post" value="yes" />

          <table cellspacing="0">
            <tr>
              <td valign="top" class="form-title"><?php _e('File:', 'comicpress-manager') ?></td>
              <td><input type="file" name="upload" /></td>
            </tr>
            <tr>
              <td valign="top" class="form-title"><?php _e("Category:", 'comicpress-manager') ?></td>
              <td><?php echo generate_comic_categories_options('category') ?></td>
            </tr>
            <?php if (count($category_checkboxes = cpm_generate_additional_categories_checkboxes()) > 0) {
              ?>
              <tr>
                <td valign="top" class="form-title"><?php _e("Additional Categories:", 'comicpress-manager') ?></td>
                <td><?php echo implode("\n", $category_checkboxes) ?></td>
              </tr>
            <?php } ?>
          </table>
          <input type="submit" value="Upload Comic File and Edit Post" />
        </form>
      <?php } ?>
    </div>
  </div>

  <?php
}

?>