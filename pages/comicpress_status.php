<?php

/**
 * The generate status dialog.
 */
function cpm_manager_status() {
  global $cpm_config;

  $help_content = __("<p><strong>Status</strong> shows all comic files, posts in the comics category, and thumbnail existence in one table, to make it easy to analyze the current status of your site.</p>", 'comicpress-manager');

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
        $data_by_date[$comic_date] = array('timestamp' => $timestamp); 
      }
      $data_by_date[$comic_date]['comic_file'] = $comic_file;
      $data_by_date[$comic_date]['comic_uri'] = cpm_build_comic_uri($comic_filepath, CPM_DOCUMENT_ROOT);

      if (count($thumbnails_found = cpm_find_thumbnails($result['date'])) > 0) {
        foreach ($thumbnails_found as $thumb_type => $thumb_filename) {
          $data_by_date[$comic_date]["thumbnails_found_${thumb_type}"] = $thumb_filename;
        }
      }
    }
  }

  foreach (cpm_query_posts() as $comic_post) {
    $timestamp = strtotime($comic_post->post_date);
    $post_date = date("Y-m-d", $timestamp);
    if (!isset($data_by_date[$post_date])) {
      $data_by_date[$post_date] = array('timestamp' => $timestamp); 
    }
    $data_by_date[$post_date]['post_id'] = $comic_post->ID;
    $data_by_date[$post_date]['post_title'] = $comic_post->post_title;
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
  </style>
  <div id="table-holder">
    <table>
      <tr>
        <th width="20%">Date</th>
        <th width="30%" class="toggler" id="comic_file">File?</th>
        <th width="10%" class="toggler" id="thumbnails_found_archive">Archive?</th>
        <th width="10%" class="toggler" id="thumbnails_found_rss">RSS?</th>
        <th width="30%" class="toggler" id="post_id">Post?</th>
      </tr>
      <?php foreach ($data_by_date as $date => $data) { ?>
        <tr class="data-row <?php echo implode(" ", array_keys($data)) ?>">
          <td><?php echo $date ?></td>
          <td>
            <?php if (isset($data['comic_file'])) { ?>
              <a href="<?php echo $data['comic_uri'] ?>"><?php echo $data['comic_file'] ?></a>
            <?php } ?>
          </td>
          <td>
            <?php if (isset($data['thumbnails_found_archive'])) { ?>
              <a href="<?php echo $data['thumbnails_found_archive'] ?>">Yes</a>
            <?php } ?>
          </td>
          <td>
            <?php if (isset($data['thumbnails_found_rss'])) { ?>
              <a href="<?php echo $data['thumbnails_found_rss'] ?>">Yes</a>
            <?php } ?>
          </td>
          <td>
            <?php if (isset($data['post_id'])) { ?>
              <a title="Edit post" href="post.php?action=edit&amp;post=<?php echo $data['post_id'] ?>"><?php echo $data['post_title'] ?></a>
            <?php } ?>
          </td>
        </tr>
      <?php } ?>
    </table>
    <script type="text/javascript">setup_status_togglers()</script>
  </div>

  <?php

  $activity_content = ob_get_clean();

  cpm_wrap_content($help_content, $activity_content);
}

?>