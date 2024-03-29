<?php

/**
 * The import dialog.
 */
function cpm_manager_import() {
	global $cpm_config;

	$all_post_dates = array();
	
	$format = CPM_DATE_FORMAT;
	if (isset($_POST['format'])) { $format = $_POST['format']; }

	if (cpm_option('cpm-skip-checks') != 1) {
		if (!function_exists('get_comic_path')) {
			$cpm_config->warnings[] =  __('<strong>It looks like you\'re running an older version of ComicPress.</strong> Storyline, hovertext, and transcript are fully supported in <a href="http://comicpress.org/">ComicPress 2.7</a>. You can use hovertext and transcripts in earlier themes by using <tt>get_post_meta($post->ID, "hovertext", true)</tt> and <tt>get_post_meta($post->ID, "transcript", true)</tt>.', 'comicpress-manager');
		}
	}
	foreach (cpm_read_comics_folder() as $comic_file) {
		$comic_file = basename($comic_file);
		if (($result = cpm_breakdown_comic_filename($comic_file, $format)) !== false) {
			if (!in_array($result['date'], $all_post_dates)) {
				if (($post_hash = generate_post_hash($result['date'], $result['converted_title'])) !== false) {
					$missing_comic_count++;
				}
			}
		}
	}
	
	foreach (cpm_query_posts() as $comic_post) {
		$all_post_dates[] = date($format, strtotime($comic_post->post_date));
	}
	$total_comics = count(cpm_read_comics_folder());
	$all_post_dates = array_unique($all_post_dates);
	$total_unique = count($all_post_dates);
	
	//    ob_start();
	$missing_comic_count = 0;
	foreach (cpm_read_comics_folder() as $comic_file) {
		$comic_file = pathinfo($comic_file, PATHINFO_BASENAME);
		if (($result = cpm_breakdown_comic_filename($comic_file, $format)) !== false) {
			if (!in_array($result['date'], $all_post_dates)) {
				if (($post_hash = generate_post_hash($result['date'], $result['converted_title'])) !== false) {
					$missing_comic_count++;
				}
	
			}
		}
	}
	ob_start(); 
?>
    <p>
      <?php _e("<strong>Create missing posts for uploaded comics</strong> is for when you upload a lot of comics to your comic folder and want to generate generic posts for all of the new comics, or for when you're migrating from another system to ComicPress.", 'comicpress-manager') ?>
    </p>

    <p>
      <?php
        $link_text = __("Bulk Edit page", 'comicpress-manager');
        $link = "<a href=\"?page=" . plugin_basename(realpath(dirname(__FILE__) . '/../comicpress_manager_admin.php')) . "-status\">${link_text}</a>";

        printf(__("<strong>Generating thumbnails on an import is a slow process.</strong>  Some Webhosts will limit the amount of time a script can run.  If your import process is failing with thumbnail generation enabled, disable thumbnail generation, perform your import, and then visit the %s to complete the thumbnail generation process.", 'comicpress-manager'), $link);
      ?>
    </p>
  <?php $help_content = ob_get_clean();

  ob_start(); ?>
  
  <h2 style="padding-right:0;"><?php _e("Create Missing Posts For Uploaded Comics", 'comicpress-manager') ?></h2>
  <h3>&mdash; <?php _e("acts as a batch import process", 'comicpress-manager') ?></h3>
  <div id="import-count-information">
    <?php
      if ($cpm_config->import_safe_exit === true) {
        _e("<strong>You are in the middle of an import operation.</strong> To continue, click the button below:", 'comicpress-manager');

        ?>
		
          <form action="" method="post">
            <?php foreach ($_POST as $key => $value) {
              if (is_array($value)) {
                foreach ($value as $subvalue) { ?>
                  <input type="hidden" name="<?php echo $key ?>[]" value="<?php echo $subvalue ?>" />
                <?php }
              } else { ?>
                <input type="hidden" name="<?php echo $key ?>" value="<?php echo $value ?>" />
              <?php }
            } ?>
            <input type="submit" class="button" value="Continue Creating Posts" />
          </form>
        <?php

	} else {
		?>
		<div style="font-size: 10px;">
		<?php
		$execution_time = ini_get("max_execution_time");
		$max_posts_imported = (int)($execution_time / 3);
		
		if ($execution_time == 0) {
			_e("<strong>Congratulations, your <tt>max_execution_time</tt> is 0</strong>. You'll be able to import all of your comics in one import operation.", 'comicpress-manager');
		} else {
			if ($max_posts_imported == 0) {
				_e("<strong>Something is very wrong with your configuration!.</strong>", 'comicpress-manager');
			} else {
				printf(__("<strong>Your <tt>max_execution_time</tt> is %s</strong>. You'll be able to safely import %s comics in one import operation.   <br />WARNING: Do not add more then %s comics to your comics directory for importing at a time.", 'comicpress-manager'), $execution_time, $max_posts_imported, $max_posts_imported);
			}
		} ?>
		</div>
<?php }
    ?>
  </div>

  <table class="form-table">
    <tr>
      <th scope="row">
        <?php _e("Missing Post Count", 'comicpress-manager') ?>
      </th>
      <td>
	<?php _e("There are", 'comicpress-manager') ?> <?php echo $missing_comic_count; ?> <?php _e(" comics missing posts.", 'comicpress-manager') ?><br />
	<em><?php _e("With a large archive this import page will take a long time to generate.", 'comicpress-manager'); ?></em>
      </td>
    </tr>
  </table>

  <div id="create-missing-posts-holder">
    <form onsubmit="$('submit').disabled=true" action="" method="post" style="margin-top: 10px">
      <input type="hidden" name="action" value="create-missing-posts" />

      <?php cpm_post_editor(435, true) ?>

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