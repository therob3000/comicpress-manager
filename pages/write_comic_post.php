<?php

/**
 * Write Comic Post is a stripped-down version of the ComicPress Upload screen
 * that lives in the Write menu of WP Admin.
 */
function cpm_manager_write_comic($form_target, $show_header = true) {
  global $cpm_config;

  $ok_to_generate_thumbs = false;
  $thumbnails_to_generate = array();

  $cpm_config->need_calendars = true;

  if ($cpm_config->get_scale_method() != CPM_SCALE_NONE) {
    foreach ($cpm_config->thumbs_folder_writable as $type => $value) {
      if ($value) {
        if ($cpm_config->separate_thumbs_folder_defined[$type] !== false) {
          if (cpm_option("${type}-generate-thumbnails") == 1) {
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
        <?php if ($show_header) { ?>
          <h2><?php _e("Write Comic Post", 'comicpress-manager') ?></h2>
        <?php } ?>

        <p>
          <?php printf(__("<strong>Upload a single comic file</strong> and immediately start editing the associated published post. Your post will be going live at <strong>%s</strong> on the provided date and will be posted in the <strong>%s</strong> category.", 'comicpress-manager'), cpm_option('cpm-default-post-time'), generate_comic_categories_options('category')) ?>

          <?php if ($ok_to_generate_thumbs) {
            ?>
            <?php printf(__("You'll be generating <strong>%s</strong> thumbnails that are <strong>%s</strong> pixels wide.", 'comicpress-manager'), implode($thumbnails_to_generate), $cpm_config->properties['archive_comic_width']) ?>
          <?php } ?>
        </p>

        <form action="?page=<?php echo $form_target ?>" method="post" enctype="multipart/form-data">
          <input type="hidden" name="action" value="write-comic-post" />
          <input type="hidden" name="upload-destination" value="comic" />
          <input type="hidden" name="new_post" value="yes" />

          <table class="form-table">
            <tr>
              <th scope="row"><?php _e('File:', 'comicpress-manager') ?></th>
              <td><input type="file" name="upload" /></td>
            </tr>
            <?php if (count($category_checkboxes = cpm_generate_additional_categories_checkboxes()) > 0) {
              ?>
              <tr>
                <th scope="row"><?php _e("Additional Categories:", 'comicpress-manager') ?></th>
                <td><?php echo implode("\n", $category_checkboxes) ?></td>
              </tr>
            <?php } ?>
            <tr>
              <th scope="row"><?php _e("Post date (leave blank if already in filename):", 'comicpress-manager') ?></th>
              <td><input type="text" id="override-date" name="override-date" /></td>
            </tr>
            <tr>
              <td colspan="2"><input type="submit" class="button" value="Upload Comic File and Edit Post" /></td>
            </tr>
          </table>
        </form>
        <script type="text/javascript">
          Calendar.setup({
            inputField: "override-date",
            ifFormat: "%Y-%m-%d",
            button: "override-date"
          });
        </script>
      <?php } ?>
    </div>
  </div>

  <?php
}

?>