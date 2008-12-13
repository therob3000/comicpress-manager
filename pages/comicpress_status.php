<?php

/**
 * The generate status dialog.
 */
function cpm_manager_status() {
  global $cpm_config;

  $help_content = __("<p><strong>Status</strong> shows all comic files, posts in the comics category, and thumbnail existence in one table, to make it easy to analyze the current status of your site. Problem dates will be indicated with <strong>red</strong> row colors.</p>", 'comicpress-manager');

  ob_start(); ?>
  
  <h2 style="padding-right:0;"><?php _e("Status", 'comicpress-manager') ?></h2>
  <h3>&mdash; <?php _e("click the headers to filter the view", 'comicpress-manager') ?></h3>

  <?php

  $data_by_date = array();

  foreach ($cpm_config->comic_files as $comic_filepath) {
    $comic_file = pathinfo($comic_filepath, PATHINFO_BASENAME);
    if (($result = cpm_breakdown_comic_filename($comic_file)) !== false) {
      $timestamp = strtotime($result['date']);
      $comic_date = date("Y-m-d", $timestamp);
      if (!isset($data_by_date[$comic_date])) {
        $data_by_date[$comic_date] = array();
      }

      $comic_info = array(
        'type' => 'comic',
        'timestamp' => $timestamp,
        'comic_file' => $comic_file,
        'comic_uri' => cpm_build_comic_uri($comic_filepath, CPM_DOCUMENT_ROOT)
      );

      if (count($thumbnails_found = cpm_find_thumbnails($result['date'])) > 0) {
        foreach ($thumbnails_found as $thumb_type => $thumb_filename) {
          $comic_info["thumbnails_found_${thumb_type}"] = $thumb_filename;
        }
      }

      $data_by_date[$comic_date][] = $comic_info;
    }
  }

  foreach (cpm_query_posts() as $comic_post) {
    $timestamp = strtotime($comic_post->post_date);
    $post_date = date("Y-m-d", $timestamp);
    if (!isset($data_by_date[$post_date])) {
      $data_by_date[$post_date] = array();
    }

    $post_info = array(
      'type' => 'post',
      'timestamp' => $timestamp,
      'post_id' => $comic_post->ID,
      'post_title' => $comic_post->post_title
    );

    $data_by_date[$post_date][] = $post_info;
  }

  krsort($data_by_date);

  ?>
  <?php cpm_include_javascript("comicpress_status.js") ?>
  <style type="text/css">
    div#table-holder table th, div#table-holder table td {
      border-right: solid #ccc 1px
    }

    div#table-holder th.toggler {
      cursor: pointer; 
      cursor: hand; 
      text-decoration: underline
    }

    th.enabled { color: blue; }

    tr.grey { background-color: #ddd }
    tr.problem { background-color: #daa }
    tr.problem.grey { background-color: #c99 }
  </style>
  <div id="table-holder">
    <table>
      <tr>
        <th width="15%">Date</th>
        <th width="25%" class="toggler" id="comic_file">File?</th>
        <th width="15%" class="toggler" id="thumbnails_found_archive">Archive?</th>
        <th width="15%" class="toggler" id="thumbnails_found_rss">RSS?</th>
        <th width="30%" class="toggler" id="post_id">Post?</th>
      </tr>
      <?php
      $is_grey = false;

      foreach ($data_by_date as $date => $data) {
        $all_objects_by_type = array();
        $is_problem = (count($data) > 2);;
        foreach ($data as $object) {
          if (!isset($all_objects_by_type[$object['type']])) {
            $all_objects_by_type[$object['type']] = array();
          }
          $all_objects_by_type[$object['type']][] = $object;
        }
        $row_title = __("No problems", 'comicpress-manager');
        $classes = array("data-row");
        if ($is_grey) { $classes[] = "grey"; }

        if ($is_problem) {
          $classes[] = "problem";

          $too_many_comics = (count($all_objects_by_type['comic']) > 1);
          $too_many_posts = (count($all_objects_by_type['post']) > 1);

          if ($too_many_comics) { $row_title = __("Too many comics on this date", 'comicpress-manager'); }
          if ($too_many_posts)  { $row_title = __("Too many posts on this date", 'comicpress-manager'); }
          if ($too_many_comics && $too_many_posts) { $row_title = __("Too many comics and posts on this date", 'comicpress-manager'); }
        }
        ?>
        <tr class="<?php echo implode(" ", $classes) ?>" title="<?php echo $row_title ?>">
          <td><?php echo $date ?></td>
          <td>
            <?php foreach ($all_objects_by_type['comic'] as $comic_info) { ?>
              <a href="<?php echo $comic_info['comic_uri'] ?>"><?php echo $comic_info['comic_file'] ?></a><br />
            <?php } ?>
          </td>
          <td>
            <?php foreach ($all_objects_by_type['comic'] as $comic_info) { ?>
              <?php if (isset($comic_info['thumbnails_found_archive'])) { ?>
                <a href="<?php echo $comic_info['thumbnails_found_archive'] ?>"><?php echo $comic_info['comic_file'] ?></a>
              <?php } ?>
            <?php } ?>
          </td>
          <td>
            <?php foreach ($all_objects_by_type['comic'] as $comic_info) { ?>
              <?php if (isset($comic_info['thumbnails_found_rss'])) { ?>
                <a href="<?php echo $comic_info['thumbnails_found_rss'] ?>"><?php echo $comic_info['comic_file'] ?></a>
              <?php } ?>
            <?php } ?>
          </td>
          <td>
            <?php foreach ($all_objects_by_type['post'] as $post_info) { ?>
              <?php if (isset($post_info['post_id'])) { ?>
                <a title="Edit post" href="post.php?action=edit&amp;post=<?php echo $post_info['post_id'] ?>"><?php echo $post_info['post_title'] ?></a>
              <?php } ?>
            <?php } ?>
          </td>
        </tr>
        <?php $is_grey = !$is_grey;
      } ?>
    </table>
    <script type="text/javascript">setup_status_togglers()</script>
  </div>

  <?php

  $activity_content = ob_get_clean();

  cpm_wrap_content($help_content, $activity_content);
}

?>