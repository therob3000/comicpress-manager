<?php

function cpm_action_create_missing_posts() {
  global $cpm_config;

  $all_post_dates = array();
  foreach (cpm_query_posts() as $comic_post) {
    $all_post_dates[] = date(CPM_DATE_FORMAT, strtotime($comic_post->post_date));
  }
  $all_post_dates = array_unique($all_post_dates);

  $posts_created = array();
  $thumbnails_written = array();
  $thumbnails_not_written = array();
  $invalid_filenames = array();

  if (strtotime($_POST['time']) === false) {
    $cpm_config->warnings[] = sprintf(__('<strong>There was an error in the post time (%1$s)</strong>.  The time is not parseable by strtotime().', 'comicpress-manager'), $_POST['time']);
  } else {
    foreach ($cpm_config->comic_files as $comic_file) {
      $comic_file = pathinfo($comic_file, PATHINFO_BASENAME);
      if (($result = cpm_breakdown_comic_filename($comic_file)) !== false) {
        extract($result, EXTR_PREFIX_ALL, 'filename');

        if (!in_array($result['date'], $all_post_dates)) {
          if (($post_hash = generate_post_hash($filename_date, $filename_converted_title)) !== false) {
            if (!is_null($post_id = wp_insert_post($post_hash))) {
              $posts_created[] = get_post($post_id, ARRAY_A);

              if (!isset($_POST['no-thumbnails'])) {
                $wrote_thumbnail = cpm_write_thumbnail($cpm_config->path . '/' . $comic_file, $comic_file);
                if (!is_null($wrote_thumbnail)) {
                  if ($wrote_thumbnail) {
                    $thumbnails_written[] = $comic_file;
                  } else {
                    $thumbnails_not_written[] = $comic_file;
                  }
                }
              }
            }
          } else {
            $invalid_filenames[] = $comic_file;
          }
        }
      }
    }
  }

  if (count($posts_created) > 0) {
    cpm_display_operation_messages(compact('invalid_filenames', 'thumbnails_written',
                                           'thumbnails_not_written', 'posts_created'));
  } else {
    $cpm_config->messages[] = __("<strong>No new posts needed to be created.</strong>", 'comicpress-manager');
  }
}

?>
