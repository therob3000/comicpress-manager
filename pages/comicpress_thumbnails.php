<?php

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
            if (cpm_option("cpm-${type}-generate-thumbnails") == 1) {
              $ok_to_generate_thumbs = true; break;
            }
          }
        }
      }
    }

    if ($ok_to_generate_thumbs) {
      if (count($cpm_config->comic_files) > 0) { ?>
        <form onsubmit="$('submit').disabled=true" action="" method="post">
          <input type="hidden" name="action" value="generate-thumbnails" />

          <p><?php printf(__("You'll be generating <strong>archive thumbnails</strong> that are <strong>%s</strong> pixels wide and <strong>RSS thumbnails</strong> that are <strong>%s</strong> pixels wide.", 'comicpress-manager'), $cpm_config->properties['archive_comic_width'], $cpm_config->properties['rss_comic_width']) ?></p>

          <?php _e("Thumbnails to regenerate (<em>to select multiple comics, [Ctrl]-click on Windows &amp; Linux, [Command]-click on Mac OS X</em>):", 'comicpress-manager') ?>
          <br />
            <select style="height: auto; width: 445px" id="select-comics-dropdown" name="comics[]" size="<?php echo min(count($cpm_config->comic_files), 30) ?>" multiple>
              <?php foreach ($cpm_config->comic_files as $file) {
                $any_thumbs = false;
                foreach (array('rss', 'archive') as $type) {
                  $thumb_file = str_replace($cpm_config->properties['comic_folder'],
                                            $cpm_config->properties["${type}_comic_folder"],
                                            $file);
                  if (file_exists($thumb_file)) { $any_thumbs = true; break; }
                }
                ?><option value="<?php echo substr($file, CPM_STRLEN_REALPATH_DOCUMENT_ROOT) ?>"><?php echo pathinfo($file, PATHINFO_BASENAME) ?><?php echo ($any_thumbs) ? " (*)" : "" ?></option>
              <?php } ?>
            </select>
          <input class="button" type="submit" id="submit" value="<?php _e("Generate Thumbnails for Selected Comics", 'comicpress-manager') ?>" style="width: 520px" />
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

  <?php

  $activity_content = ob_get_clean();

  cpm_wrap_content($help_content, $activity_content);
}

?>