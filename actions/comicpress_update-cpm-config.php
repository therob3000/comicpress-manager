<?php

function cpm_action_update_cpm_config() {
  global $cpm_config;

  include(realpath(dirname(__FILE__)) . '/../cpm_configuration_options.php');

  foreach ($configuration_options as $option_info) {
    switch ($option_info['type']) {
      case "text":
      case "textarea":
        if (isset($_POST[$option_info['id']])) {
          update_option('comicpress-manager-' . $option_info['id'], $_POST[$option_info['id']]);
        }
        break;
      case "checkbox":
        update_option('comicpress-manager-' . $option_info['id'], (isset($_POST[$option_info['id']]) ? "1" : "0"));
        break;
      case "categories":
        if (isset($_POST[$option_info['id']])) {
          $all_categories = implode(",", $_POST[$option_info['id']]);
          update_option('comicpress-manager-' . $option_info['id'], $all_categories);
        }
        break;
    }
  }

  $cpm_config->messages[] = __("<strong>ComicPress Manager configuration updated.</strong>", 'comicpress-manager');
}

?>