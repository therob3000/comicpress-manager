function reschedule_posts(start) {
  var start_processing = false;
  var interval = null;
  var current_date = null;
  var current_interval = 0;
  for (var i = 0, l = comic_files_keys.length; i < l; ++i) {
    if (start_processing) {
      top.console.log(interval[current_interval]);
      current_date += (interval[current_interval] * 86400 * 1000);
      current_interval = (current_interval + 1) % interval.length;

      var date_obj = new Date(current_date);

      var month_string = ("00" + date_obj.getMonth().toString());
          month_string = month_string.substr(month_string.length - 2, 2);

      var day_string = ("00" + date_obj.getDate().toString());
          day_string = day_string.substr(day_string.length - 2, 2);

      date_string = date_obj.getFullYear() + "-" + month_string + "-" + day_string;

      $('dates[' + comic_files_keys[i] + ']').value = date_string;
      $('holder-' + comic_files_keys[i]).style.backgroundColor = "#ddd";
    }
    if (comic_files_keys[i] == start) {
      start_processing = true;
      interval = prompt(days_between_posts_message, "7");

      if (interval !== null) {
        var all_valid = true;
        var parts = interval.split(",");
        for (var j = 0, jl = parts.length; j < jl; ++j) {
          if (!parts[j].toString().match(/^\d+$/)) { all_valid = false; break; }
        }

        if (all_valid) {
          interval = parts;
          date_parts = $F('dates[' + comic_files_keys[i] + ']').split("-");
          current_date = Date.UTC(date_parts[0], date_parts[1], date_parts[2]) + 86400 * 1000;
        } else {
          alert(interval + " " + valid_interval_message);
          break;
        }
      } else {
        break;
      }
    }
  }
}
