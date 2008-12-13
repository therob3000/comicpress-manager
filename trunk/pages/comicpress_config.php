<?php

/**
 * The config editor dialog.
 */
function cpm_manager_config() {
  global $cpm_config;

  ob_start(); ?>

  <h2 style="padding-right:0;"><?php _e("Edit ComicPress Config", 'comicpress-manager') ?></h2>
  <?php if (!$cpm_config->can_write_config) { ?>
    <p>
      <?php
        _e("<strong>You won't be able to automatically update your configuration.</strong> After submitting, you will be shown the code to paste into comicpress-config.php. If you want to enable automatic updating, check the permissions of your theme folder and comicpress-config.php file.", 'comicpress-manager');
       ?>
    </p>
  <?php }
  echo cpm_manager_edit_config();
  ?>

  <?php

  $activity_content = ob_get_clean();

  cpm_wrap_content(null, $activity_content);
}

?>