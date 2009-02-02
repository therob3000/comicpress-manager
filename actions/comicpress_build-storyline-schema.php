<?php

//harmonious @zip @hash

function cpm_action_build_storyline_schema() {
  global $cpm_config;

  update_option('comicpress-enable-storyline-support', isset($_POST['enable-storyline-support']) ? 1 : 0);
  update_option('comicpress-storyline-show-top-category', isset($_POST['show-top-category']) ? 1 : 0);

  if (isset($_POST['enable-storyline-support'])) {
    $cpm_config->is_cpm_modifying_categories = true;

    $categories_to_create = array();
    $categories_to_rename = array();

    extract(cpm_get_all_comic_categories());

    foreach ($_POST as $field => $value) {
      $parts = explode("/", $field);
      if (($parts[0] == "0") && (count($parts) > 1)) {
        $category_id = end($parts);
        $category = get_category($category_id, ARRAY_A);
        if (!empty($category)) {
          if ($category['cat_name'] != $value) {
            $cpm_config->messages[] = sprintf(__('Category <strong>%1$s</strong> renamed to <strong>%2$s</strong>.', 'comicpress-manager'), $category['cat_name'], $value);
            $category['cat_name'] = $value;
            wp_update_category($category);
          }
        } else {
          $categories_to_create[$field] = $value;
        }

        if (($index = array_search($field, $category_tree)) !== false) {
          array_splice($category_tree, $index, 1);
        }
      }
    }

    foreach ($category_tree as $node) {
      $category_id = end(explode("/", $node));
      $category = get_category($category_id);
      wp_delete_category($category_id);
      $cpm_config->messages[] = sprintf(__('Category <strong>%s</strong> deleted.', 'comicpress-manager'), $category->cat_name);
    }

    uksort($categories_to_create, 'cpm_sort_category_keys_by_length');

    $changed_field_ids = array();
    $removed_field_ids = array();

    $target_category_ids = array();

    foreach ($categories_to_create as $field => $value) {
      $original_field = $field;
      foreach ($changed_field_ids as $changed_field => $new_field) {
        if ((strpos($field, $changed_field) === 0) && (strlen($field) > strlen($changed_field))) {
          $field = str_replace($changed_field, $new_field, $field);
          break;
        }
      }

      $parts = explode("/", $field);
      $target_id = array_pop($parts);
      $parent_id = array_pop($parts);

      if (!category_exists($value)) {
        $category_id = wp_create_category($value, $parent_id);
        array_push($parts, $parent_id);
        array_push($parts, $category_id);
        $changed_field_ids[$original_field] = implode("/", $parts);

        $cpm_config->messages[] = sprintf(__('Category <strong>%s</strong> created.', 'comicpress-manager'), $value);
      } else {
        $cpm_config->warnings[] = sprintf(__("The category %s already exists. Please enter a new name.", 'comicpress-manager'), $value);
        $removed_field_ids[] = $field;
      }
    }

    $order = array_diff(explode(",", $_POST['order']), $removed_field_ids);
    for ($i = 0; $i < count($order); ++$i) {
      if (isset($changed_field_ids[$order[$i]])) {
        $order[$i] = $changed_field_ids[$order[$i]];
      }
    }

    // ensure we're writing sane data
    $new_order = array();
    foreach ($order as $node) {
      $parts = explode("/", $node);
      if (($parts[0] == "0") && (count($parts) > 1)) {
        $new_order[] = $node;
      }
    }

    $cpm_config->messages[] = __('Storyline structure saved.', 'comicpress-manager');
    update_option("comicpress-storyline-category-order", implode(",", $new_order));

    wp_cache_flush();
  }
}

function cpm_sort_category_keys_by_length($a, $b) {
  return strlen($a) - strlen($b);
}

?>
