<?php

function cpm_action_generate_thumbnails() {
  global $cpm_config;

  foreach ($_POST['comics'] as $comic) {
    $comic_file = stripslashes(pathinfo($comic, PATHINFO_BASENAME));

    $wrote_thumbnail = cpm_write_thumbnail($cpm_config->path . '/' . $comic_file, $comic_file, true);

    if (!is_null($wrote_thumbnail)) {
      if ($wrote_thumbnail) {
        $cpm_config->messages[] = sprintf(__("<strong>Wrote thumbnail for %s.</strong>", 'comicpress-manager'), $comic_file);
      } else {
        $cpm_config->warnings[] = sprintf(__("<strong>Could not write thumbnail for %s.</strong> Check the permissions on the thumbnail directories.", 'comicpress-manager'), $comic_file);
      }
    }
  }
}

?>