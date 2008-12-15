<?php

function cpm_action_create_missing_posts() {
  global $cpm_config;

  $all_post_dates = array();
  foreach (cpm_query_posts() as $comic_post) {
    $all_post_dates[] = date(CPM_DATE_FORMAT, strtotime($comic_post->post_date));
  }
  $all_post_dates = array_unique($all_post_dates);
  $duplicate_posts_within_creation = array();

  $posts_created = array();
  $thumbnails_written = array();
  $thumbnails_not_written = array();
  $invalid_filenames = array();
  $duplicate_posts = array();
  $new_thumbnails_not_needed = array();

  if (strtotime($_POST['time']) === false) {
    $cpm_config->warnings[] = sprintf(__('<strong>There was an error in the post time (%1$s)</strong>.  The time is not parseable by strtotime().', 'comicpress-manager'), $_POST['time']);
  } else {
    foreach ($cpm_config->comic_files as $comic_file) {
      $comic_file = pathinfo($comic_file, PATHINFO_BASENAME);
      if (($result = cpm_breakdown_comic_filename($comic_file)) !== false) {
        extract($result, EXTR_PREFIX_ALL, 'filename');

        $ok_to_create_post = !in_array($result['date'], $all_post_dates);

        if (isset($duplicate_posts_within_creation[$result['date']])) {
          $ok_to_create_post = false;
          $post_id = $duplicate_posts_within_creation[$result['date']];
        }

        if ($ok_to_create_post) {
          if (isset($_POST['duplicate_check'])) {
            $ok_to_create_post = (($post_id = post_exists($post_title, $post_content, $post_date)) == 0);
          }
        } else {
          if (!isset($_POST['duplicate_check'])) {
            $ok_to_create_post = true;
          }
        }

        if ($ok_to_create_post) {
          if (($post_hash = generate_post_hash($filename_date, $filename_converted_title)) !== false) {
            if (!is_null($post_id = wp_insert_post($post_hash))) {
              $posts_created[] = get_post($post_id, ARRAY_A);
              $date = date(CPM_DATE_FORMAT, strtotime($filename_date));
              $all_post_dates[] = $date;
              $duplicate_posts_within_creation[$date] = $post_id;

              if (isset($_POST['thumbnails'])) {
                $wrote_thumbnail = cpm_write_thumbnail($cpm_config->path . '/' . $comic_file, $comic_file);
                if (!is_null($wrote_thumbnail)) {
                  if ($wrote_thumbnail) {
                    $thumbnails_written[] = $comic_file;
                  } else {
                    $thumbnails_not_written[] = $comic_file;
                  }
                } else {
                  $new_thumbnails_not_needed[] = $comic_file;
                }
              }
            }
          } else {
            $invalid_filenames[] = $comic_file;
          }
        } else {
          $duplicate_posts[] = array(get_post($post_id, ARRAY_A), $comic_file);
        }
      }
    }
  }

  if (count($posts_created) > 0) {
    cpm_display_operation_messages(compact('invalid_filenames', 'thumbnails_written',
                                           'thumbnails_not_written', 'posts_created',
                                           'duplicate_posts', 'new_thumbnails_not_needed'));
  } else {
    $cpm_config->messages[] = __("<strong>No new posts needed to be created.</strong>", 'comicpress-manager');
  }
}

?>
