/**
 * hide/show the new post holder box depending on the status of the checkbox.
 */
function hide_show_checkbox_holder(which, reverse) {
  if (reverse !== true) { reverse = false; }
  if ($(which + '-checkbox').checked !== reverse) {
    $(which + '-holder').show();
  } else {
    $(which + '-holder').hide();
  }
}

function setup_hide_show_checkbox_holder(which) {
  Event.observe(which + '-checkbox', 'click', function() { hide_show_checkbox_holder(which) });
  hide_show_checkbox_holder(which);
}

function hide_show_div_on_checkbox(div, checkbox, flip_behavior) {
  if ($(checkbox) && $(div)) {
    ok = (flip_behavior) ? !$(checkbox).checked : $(checkbox).checked;
    if (ok) {
      $(div).show();
    } else {
      $(div).hide();
    }
  }
}

/**
 * Show the preview image for deleting an image.
 */
function change_image_preview() {
  var which = $F('delete-comic-dropdown');
  $('image-preview').innerHTML = '<img src="' + which + '" width="420" />';
}

var current_file_index = 0;
var current_file_upload_count = 0;

var on_change_file_upload_count = null;

/**
 * Add a file upload field.
 */
function add_file_upload() {
  var field  = "<div class=\"upload-holder\" id=\"upload-holder-" + current_file_index + "\">";
      field += messages['add_file_upload_file'] + "<input size=\"35\" type=\"file\" name=\"upload-" + current_file_index + "\" />";
      field += " [<a href=\"#\" onclick=\"remove_file_upload('" + current_file_index + "');\">" + messages['add_file_upload_remove'] + "</a>]";
      field += "</div>";
  Element.insert('multiple-file-upload', { bottom: field });
  current_file_index++;
  current_file_upload_count++;

  if (on_change_file_upload_count) { on_change_file_upload_count(current_file_upload_count); }
}

function remove_file_upload(which) {
  Element.remove('upload-holder-' + which);
  current_file_upload_count--;

  if (on_change_file_upload_count) { on_change_file_upload_count(current_file_upload_count); }
}

// page startup code
Event.observe(window, 'load', function() {
  if ($('multiple-new-post-checkbox')) {
    setup_hide_show_checkbox_holder("multiple-new-post");
    add_file_upload();

    hide_show_div_on_checkbox('override-title-holder', 'override-title');
    hide_show_div_on_checkbox('thumbnail-write-holder', 'no-thumbnails', true);
  }

  if ($('upload-destination')) {
    var toggle_upload_destination_holder = function() {
      if (!$('overwrite-existing-file-selector-checkbox').checked) {
        if ($('upload-destination').options[$('upload-destination').selectedIndex].value == "comic") {
          $('upload-destination-holder').show();
        } else {
          $('upload-destination-holder').hide();
        }
      } else {
        $('upload-destination-holder').hide();
      }
    };
    Event.observe('upload-destination', 'change', toggle_upload_destination_holder);
    toggle_upload_destination_holder();

    on_change_file_upload_count = function(count) {
      if (count == 1) {
        Element.show('specify-date-holder');
        Element.show('overwrite-existing-holder');
      } else {
        Element.hide('specify-date-holder');
        Element.hide('overwrite-existing-holder');
        $('overwrite-existing-file-selector-checkbox').checked = false;
        toggle_upload_destination_holder();
      }
      hide_show_checkbox_holder('overwrite-existing-file-selector');
    }

    Event.observe('overwrite-existing-file-selector-checkbox', 'click', function() {
      hide_show_checkbox_holder('overwrite-existing-file-selector');
      toggle_upload_destination_holder();
    });

    hide_show_checkbox_holder('overwrite-existing-file-selector');
  }

  if ($('count-missing-posts-clicker')) {
    hide_show_div_on_checkbox('override-title-holder', 'override-title');
    hide_show_div_on_checkbox('thumbnail-write-holder', 'no-thumbnails', true);

    Event.observe('count-missing-posts-clicker', 'click', function() {
      $('missing-posts-display').innerHTML = "..." + messages['count_missing_posts_counting'] + "...";

      new Ajax.Request(ajax_request_uri,
                       {
                         parameters: {
                           action: "count-missing-posts"
                         },
                         onSuccess: function(transport) {
                           if (transport.responseText.match(/missing-posts>(.*)<\/missing-posts/)) {
                             $('missing-posts-display').innerHTML = RegExp.$1;
                           }
                           if (RegExp.$1 == 0) {
                             $('create-missing-posts-holder').innerHTML = "<p><strong>" + messages['count_missing_posts_none_missing'] + "</strong></p>";
                           }
                         }
                       }
                      );
      return false;
    });
  }

  if ($('image-preview')) { change_image_preview(); }

  // just in case...

  $('cpm-right-column').style.minHeight = $('cpm-left-column').offsetHeight + "px";
});