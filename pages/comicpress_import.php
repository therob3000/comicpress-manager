<?php

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
        $link = "<a href=\"?page=" . plugin_basename(realpath(dirname(__FILE__) . '/../comicpress_manager_admin.php')) . "-thumbnails\">${link_text}</a>";

        printf(__("<strong>Generating thumbnails on an import is a slow process.</strong>  Some Webhosts will limit the amount of time a script can run.  If your import process is failing with thumbnail generation enabled, disable thumbnail generation, perform your import, and then visit the %s to complete the thumbnail generation process.", 'comicpress-manager'), $link);
      ?>
    </p>
  <?php $help_content = ob_get_clean();

  ob_start(); ?>
  
  <h2 style="padding-right:0;"><?php _e("Create Missing Posts For Uploaded Comics", 'comicpress-manager') ?></h2>
  <h3>&mdash; <?php _e("acts as a batch import process", 'comicpress-manager') ?></h3>

  <table class="form-table">
    <tr>
      <th scope="row">
        <?php _e("Count the number of missing posts", 'comicpress-manager') ?>
      </th>
      <td>
        <a href="#" onclick="return false" id="count-missing-posts-clicker"><?php _e("Click here to count", 'comicpress-manager') ?></a> (<?php _e("may take a while", 'comicpress-manager') ?>): <span id="missing-posts-display"></span>
      </td>
    </tr>
  </table>

  <div id="create-missing-posts-holder">
    <form onsubmit="$('submit').disabled=true" action="" method="post" style="margin-top: 10px">
      <input type="hidden" name="action" value="create-missing-posts" />

      <?php cpm_post_editor() ?>

      <table class="form-table">
        <tr>
          <td align="center">
            <input class="button" type="submit" id="submit" value="<?php _e("Create posts", 'comicpress-manager') ?>" />
          </td>
        </tr>
      </table>
    </form>
  </div>

  <?php

  $activity_content = ob_get_clean();

  cpm_wrap_content($help_content, $activity_content);
}

?>