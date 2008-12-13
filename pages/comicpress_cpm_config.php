<?php

/**
 * The config editor dialog.
 */
function cpm_manager_cpm_config() {
  global $cpm_config;

  include(realpath(dirname(__FILE__)) . '/../cpm_configuration_options.php');

  $is_table = false;

  ob_start(); ?>

  <h2 style="padding-right:0;"><?php _e("Edit ComicPress Manager Config", 'comicpress-manager') ?></h2>

  <form action="" method="post" id="config-editor">
    <input type="hidden" name="action" value="update-cpm-config" />
      <?php foreach ($configuration_options as $option) { ?>
        <?php if (is_string($option)) { ?>
          <?php if ($is_table) { ?>
            </table></div>
            <?php $is_table = false;
          } ?>
          <h3><?php echo $option ?></h3>
        <?php } else {
          if (!$is_table) { ?>
            <div style="overflow: hidden"><table class="form-table">
            <?php $is_table = true;
          } ?>
          <tr>
            <th scope="row"><?php echo $option['name'] ?></th>
            <td>
              <?php
                $result = cpm_option($option['id']);
                switch($option['type']) {
                case "checkbox": ?>
                  <input type="checkbox" id="<?php echo $option['id'] ?>" name="<?php echo $option['id'] ?>" value="yes" <?php echo ($result == 1) ? " checked" : "" ?> />
                  <?php break;
                case "text": ?>
                  <input type="text" size="<?php echo (isset($option['size']) ? $option['size'] : 10) ?>" name="<?php echo $option['id'] ?>" value="<?php echo $result ?>" />
                  <?php break;
                case "textarea": ?>
                  <textarea name="<?php echo $option['id'] ?>" rows="4" cols="30"><?php echo $result ?></textarea>
                  <?php break;
                case "categories":
                  if (count($category_checkboxes = cpm_generate_additional_categories_checkboxes($option['id'], explode(",", $result))) > 0) {
                    echo implode("\n", $category_checkboxes);
                  }
                  break;
                }
              ?>
              <em><label for="<?php echo $option['id'] ?>">(<?php echo $option['message'] ?>)<label></em>
            </td>
          </tr>
        <?php } ?>
      <?php } ?>
      <tr>
        <td>&nbsp;</td>
        <td>
          <input class="button" type="submit" value="Change Configuration" />
        </td>
      </tr>
    </table></div>
  </form>

  <div id="first-run-holder">
    <p><strong>Re-run the &quot;First Run&quot; action? This will attempt to create the default comic folders on your site.</strong></p>

    <form action="<?php echo $target_page ?>" method="post">
      <input type="hidden" name="action" value="do-first-run" />
      <input class="button" type="submit" value="Yes, try and make my comic directories" />
    </form>
  </div>

  <?php

  $activity_content = ob_get_clean();

  cpm_wrap_content(null, $activity_content);
}

?>