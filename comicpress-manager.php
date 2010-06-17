<?php
/*
Plugin Name: ComicPress Manager
Plugin URI: http://www.coswellproductions.com/wordpress/wordpress-plugins/
Description: Manage the comics within a <a href="http://www.comicpress.org/">ComicPress</a> theme installation.
Version: 1.4.9.6
Author: John Bintz, Philip M. Hofer (Frumph)
Author URI: http://www.coswellproductions.com/wordpress/

Copyright 2009 John Bintz  (email : john@coswellproductions.com, philip@frumph.net)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

/**
 * The loader for the rest of the plugin components.
 */

// load the config, since you can do useful things with this in your theme
require_once('comicpress_manager_config.php');

// only load the plugin code of we're in the administration part of WordPress.
if (is_admin()) {
  require_once('comicpress_manager_admin.php');
}
