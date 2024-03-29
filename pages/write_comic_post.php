<?php

/**
 * Write Comic Post is the QuomicPress panel on the Dashboard..
 */
function cpm_manager_write_comic($form_target, $show_header = true) {
  global $cpm_config;

  $ok_to_generate_thumbs = false;
  $thumbnails_to_generate = array();

  $cpm_config->need_calendars = true;

  $thumbnails_to_generate = cpm_get_thumbnails_to_generate();

  $go_live_time_string = (cpm_option('cpm-default-post-time') == "now") ?
                         __("<strong>now</strong>", 'comicpress-manager') :
                         sprintf(__("at <strong>%s</strong>", 'comicpress-manager'), cpm_option('cpm-default-post-time'));

  cpm_write_global_styles_scripts();
  ?>
  <div class="wrap">
    <?php cpm_handle_warnings() ?>
    <?php if (count($cpm_config->errors) == 0) { ?>
      <?php if ($show_header) { ?>
        <h2><?php _e("Write Comic Post", 'comicpress-manager') ?></h2>
      <?php } ?>

      <?php if (count($cpm_config->comic_files) == 0) { ?>
        <div style="border: solid #daa 1px; background-color: #ffe7e7; padding: 5px">
          <strong>It looks like this is a new ComicPress install.</strong> You should test to make
          sure uploading works correctly by visiting <a href="admin.php?page=<?php echo plugin_basename(realpath(dirname(__FILE__) . '/../comicpress_manager_admin.php')) ?>">ComicPress -> Upload</a>.
        </div>
      <?php } ?>

      <p>
        <?php printf(__("<strong>Upload a single comic file</strong> and immediately start editing the associated published post. Your post will be going live %s on the provided date and will be posted in the <strong>%s</strong> category.", 'comicpress-manager'), $go_live_time_string, generate_comic_categories_options('category')) ?>

        <?php if (!empty($thumbnails_to_generate)) {
          $thumbnail_strings = array();

          foreach ($thumbnails_to_generate as $type) {
            $thumbnail_strings[] = sprintf(__("<strong>%s</strong> thumbnails that are <strong>%spx</strong> wide", 'comicpress-manager'), $type, $cpm_config->properties["${type}_comic_width"]);
          }

          ?>
          <?php printf(__("You'll be generating: %s.", 'comicpress-manager'), implode(", ", $thumbnail_strings)) ?>
        <?php } ?>
      </p>

      <form action="?page=<?php echo $form_target ?>" method="post" enctype="multipart/form-data">
        <input type="hidden" name="MAX_FILE_SIZE" value="<?php echo cpm_short_size_string_to_bytes(ini_get('upload_max_filesize')) ?>" />
        <input type="hidden" name="action" value="write-comic-post" />
        <input type="hidden" name="upload-destination" value="comic" />
        <input type="hidden" name="thumbnails" value="yes" />
        <input type="hidden" name="new_post" value="yes" />
        <?php echo generate_comic_categories_options('in-comic-category[]', false) ?>
        <input type="hidden" name="time" value="<?php echo cpm_option('cpm-default-post-time') ?>" />

        <table class="form-table">
          <tr>
            <th scope="row"><?php _e('File:', 'comicpress-manager') ?></th>
            <td><input type="file" name="upload" /></td>
          </tr>
          <?php if (count($category_checkboxes = cpm_generate_additional_categories_checkboxes()) > 0) {
            ?>
<?php /*
            <tr>
              <th scope="row"><?php _e("Additional Categories:", 'comicpress-manager') ?></th>
              <td><?php echo implode("\n", $category_checkboxes) ?></td>
            </tr>
*/ ?>
          <?php } ?>
          <tr>
            <th scope="row"><?php _e("Post date (leave blank if already in filename):", 'comicpress-manager') ?></th>
            <td>
              <div class="curtime"><input type="text" id="override-date" name="override-date" /></div>
            </td>
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

  <?php
}

?>