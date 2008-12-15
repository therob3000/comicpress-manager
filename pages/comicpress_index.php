<?php

/**
 * The main manager screen.
 */
function cpm_manager_index() {
  global $cpm_config;

  $cpm_config->need_calendars = true;

  $example_date = cpm_generate_example_date(CPM_DATE_FORMAT);

  $example_real_date = date(CPM_DATE_FORMAT);

  $zip_extension_loaded = extension_loaded('zip');

  ob_start(); ?>
    <p>
      <strong>
        <?php _e("ComicPress Manager manages your comics and your time.", 'comicpress-manager') ?>
      </strong>
      <?php _e("It makes uploading new comics, importing comics from a non-ComicPress setup, and batch uploading a lot of comics at once, very fast and configurable.", 'comicpress-manager') ?>
    </p>

    <p>
      <strong>
        <?php _e("ComicPress Manager also manages yours and your Website's sanity.", 'comicpress-manager') ?>
      </strong>

      <?php printf(__("It can check for misconfigured ComicPress setups, for incorrectly-named files (remember, it's <em>%s-single-comic-title.ext</em>) and for when you might be duplicating a post. You will also be shown which comic will appear with which blog post in the Post editor.", 'comicpress-manager'), $example_date) ?>
    </p>

    <p>
      <?php printf(__("<strong>Single comic titles</strong> are generated from the incoming filename.  If you've named your file <strong>%s-my-new-years-day.jpg</strong> and create a new post for the file, the post title will be <strong>My New Years Day</strong>.  This default should handle the majority of cases.  If a comic file does not have a title, the date in <strong>MM/DD/YYYY</strong> format will be used.", 'comicpress-manager'), $example_real_date) ?>
    </p>

    <p>
      <?php _e("<strong>Upload image files</strong> lets you upload multiple comics at a time, and add a default post body for each comic.", 'comicpress-manager') ?>
      <?php if ($zip_extension_loaded) { ?>
        <?php _e("You can <strong>upload a Zip file and create new posts</strong> from the files contained within the Zip file.", 'comicpress-manager') ?>
      <?php } else { ?>
        <?php _e("<strong>You can't upload a Zip file</strong> because you do not have the PHP <strong>zip</strong> extension installed.", 'comicpress-manager') ?>
      <?php } ?>
    </p>

    <p>
      <?php _e("Has ComicPress Manager saved you time and sanity?  <strong>Donate a few bucks to show your appreciation!</strong>", 'comicpress-manager') ?>
      <span style="display: block; text-align: center">
        <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
        <input type="hidden" name="cmd" value="_s-xclick">
        <input type="image" src="https://www.paypal.com/en_US/i/btn/x-click-but11.gif" border="0" name="submit" alt="PayPal - The safer, easier way to pay online!">
        <img alt="" border="0" src="https://www.paypal.com/en_US/i/scr/pixel.gif" width="1" height="1">
        <input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----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-----END PKCS7-----
        ">
        </form>
      </span>
    </p>
  <?php $help_content = ob_get_clean();

  ob_start(); ?>

  <h2 style="padding-right:0;">
    <?php if ($zip_extension_loaded) {
      _e("Upload Image &amp; Zip Files", 'comicpress-manager');
    } else {
      _e("Upload Image Files", 'comicpress-manager');
    } ?>
  </h2>
  <h3>&mdash;
    <?php if (cpm_option('cpm-obfuscate-filenames-on-upload') === "none") { ?>
      <?php _e("any existing files with the same name will be overwritten", 'comicpress-manager') ?>
    <?php } else { ?>
      <?php _e("uploaded filenames will be obfuscated, therefore no old files will be overwritten after uploading", 'comicpress-manager') ?>
    <?php } ?>
  </h3>

  <?php if (!function_exists('get_site_option')) { ?>
    <?php if (!$zip_extension_loaded) { ?>
      <div id="zip-upload-warning">
        <?php printf(__('<strong>You do not have the Zip extension installed.</strong> Uploading a Zip file <strong>will not work</strong>. Either upload files individually or <a href="%s">FTP/SFTP the files to your site and import them</a>.'), "?page=" .  plugin_basename(realpath(dirname(__FILE__) . '/../comicpress_manager_admin.php') . '-import')) ?>
      </div>
    <?php } ?>
  <?php } ?>

  <form onsubmit="$('submit').disabled=true" action="" method="post" enctype="multipart/form-data">
    <input type="hidden" name="action" value="multiple-upload-file" />
    <div id="multiple-file-upload">
    </div>
    <div style="text-align: center">
      [<a href="#" onclick="add_file_upload(); return false"><?php _e("Add file to upload", 'comicpress-manager') ?></a>]
    </div>

    <table class="form-table">
      <tr>
        <th scope="row"><?php _e("Destination for uploaded files:", 'comicpress-manager') ?></th>
        <td>
          <select name="upload-destination" id="upload-destination">
            <option value="comic"><?php _e("Comics folder", 'comicpress-manager') ?></option>
            <option value="archive_comic"><?php _e("Archive folder", 'comicpress-manager') ?></option>
            <option value="rss_comic"><?php _e("RSS feed folder", 'comicpress-manager') ?></option>
          </select>
        </td>
      </tr>
      <?php if (count($cpm_config->comic_files) > 0) { ?>
        <tr id="overwrite-existing-holder">
          <th scope="row"><?php _e("Overwrite an existing file:", 'comicpress-manager') ?></th>
          <td>
            <select name="overwrite-existing-file-choice" id="overwrite-existing-file-choice">
              <option value=""><?php _e("-- no --", 'comicpress-manager') ?></option>
              <?php foreach ($cpm_config->comic_files as $file) {
                $basename = pathinfo($file, PATHINFO_BASENAME);
                ?>
                <option value="<?php echo $basename ?>"
                <?php echo ($_GET['replace'] == $basename) ? "selected" : "" ?>><?php echo $basename ?></option>
              <?php } ?>
            </select>
          </td>
        </tr>
      <?php } ?>
      <tr>
        <td align="center" colspan="2">
          <input class="button" id="submit" type="submit" value="<?php
            if (extension_loaded("zip")) {
              _e("Upload Image &amp; Zip Files", 'comicpress-manager');
            } else {
              _e("Upload Image Files", 'comicpress-manager');
            }
          ?>" />
        </td>
      </tr>
    </table>

    <div id="upload-destination-holder">
      <table class="form-table">
        <tr>
          <th scope="row"><?php _e("Generate new posts for each uploaded file:", 'comicpress-manager') ?></th>
          <td>
            <input id="multiple-new-post-checkbox" type="checkbox" name="new_post" value="yes" checked />
            <label for="multiple-new-post-checkbox"><em>(if you only want to upload a series of files to replace others, leave this unchecked)</em></label>
          </td>
        </tr>
      </table>

      <div id="multiple-new-post-holder">
        <table class="form-table" id="specify-date-holder">
          <tr>
            <th scope="row"><?php _e("Date for uploaded file:", 'comicpress-manager') ?></th>
            <td>
              <input type="text" id="override-date" name="override-date" /> <?php _e("<em>(click to open calendar. for single file uploads only. can accept any date format parseable by <a href=\"http://us.php.net/strtotime\" target=\"php\">strtotime()</a>)</em>", 'comicpress-manager') ?>
            </td>
          </tr>
        </table>

        <?php cpm_post_editor(420) ?>

        <table class="form-table">
          <tr>
            <td align="center">
              <input class="button" id="submit" type="submit" value="<?php
                if (extension_loaded("zip")) {
                  _e("Upload Image &amp; Zip Files", 'comicpress-manager');
                } else {
                  _e("Upload Image Files", 'comicpress-manager');
                }
              ?>" />
            </td>
          </tr>
        </table>
      </div>
    </div>
  </form>
  <script type="text/javascript">
    Calendar.setup({
      inputField: "override-date",
      ifFormat: "%Y-%m-%d",
      button: "override-date"
    });
  </script>

  <?php

  $activity_content = ob_get_clean();

  cpm_wrap_content($help_content, $activity_content);
}
