=== ComicPress Manager ===
Contributors: johncoswell
Tags: comicpress, webcomics, management, admin, posts, plugin
Requires at least: 2.5.1
Tested up to: 2.5.1
Stable tag: 0.9
Donate link: http://claritycomic.com/comicpress-manager/#donate

ComicPress Manager ties in with the ComicPress theme to make managing your WordPress-hosted Webcomic easy and fast.

== Description ==

The ComicPress Manager plugin works in conjunction with an installation of [ComicPress](http://www.mindfaucet.com/comicpress/), the Webcomic theme for WordPress. ComicPress Manager is intended to reduce the amount of work required to administer a site running ComicPress.

As of version 0.9, it allows you to:

* Upload individual comic files or a Zip archive of comic files directly into your comics folder and generate posts for each comic as it's uploaded with the correct go-live date and time
  * To save a trip to the Edit Post page, you can use the Visual Editor right in ComicPress Manager to add styled text content to your post.
  * Using this method ensures that the post has the correct date and time as it's being created, reducing mistakes in posting.
  * You can also upload a single file that does not specify a date in the filename, and enter in the post date on the upload page.
  * You can also replace a single existing file with any other file, preserving the original file's name after upload
* Upload thumbnails directly to the archive and RSS folders
* Generate thumbnails for all uploaded comics for your archive and RSS folders
* Re-generate thumbnails after you've changed thumbnail parameters
* Get a quick status & sanity check on your installation
  * ComicPress Manager will check to see if:
    * Your comics, archive, and RSS folders exist
    * Your comics, archive, and RSS folders are writable by the Webserver (if you're generating thumbnails)
    * You have enough categories defined to use ComicPress
    * You have defined a valid blog category (and comic category for ComicPress 2.5)
    * You're using a ComicPress-derived theme
      * NOTE: This check is done by examining the name of the theme as defined in style.css. If you want this non-fatal check to succeed, leave the term "ComicPress" in the theme title.
    * You have comics in your comics folder (if there aren't any, it could be a sign of other problems)
  * You can also disable these checks once you know your configuration is correct, to improve performance
* Preview the comic that will go live with your comic post
  * Save a trip to your blog and see what comic will be going live with your post straight from the Write Post screen. You are also informed if thumbnails exist for this comic.
* Create any missing posts for comics that have been uploaded to your comics folder
  * If you're migrating from another Webcomic hosting solution, or if you prefer to directly transfer your comics into your comics folder, then you can generate posts for all comics that don't already have posts.
* Delete a comic file and the associated comic post
  * If you need to remove a comic, take care of both the file and the post at the same time to save yourself some time. ComicPress Manager plays it safe during this operation, and will not delete a comic if more than one post appears in your comic category for that day (which shouldn't happen anyway with the current version of ComicPress).
* Change the post dates and comic filenames for any comic you've uploaded
  * You can use this advanced feature to shift a large number of comics forward or backwards in time.
* Modify your comicpress-config.php file from ComicPress Manager.
  * If you're using a comicpress-config.php file, and the permissions are set correctly, you can modify the settings directly from ComicPress manager. If your permissions are not correct, the config file that ComicPress Manager would have written will be shown so that you can copy and paste it into your comicpress-config.php file.

ComicPress Manager is built for WordPress 2.5.1 and ComicPress 2.1 and 2.5. ComicPress Manager works on PHP 4, but using PHP 5 is strongly recommended.

Before you begin working with ComicPress Manager, and especially while the software is still in development, it is recommended that you make regular backups of your WordPress database and comics folder.

== Installation ==

(These instructions have changed since 0.8, so be careful!  If you're upgrading, remove your original standalone comicpress-config.php file before proceeding.)

Copy the comicpress_manager directory to your wp-content/plugins/ directory and activate the plugin.  ComicPress Manager works on PHP 4, but using PHP 5 is strongly recommended.

== Frequently Asked Questions ==

= I'm unable to edit my comicpress-config.php file from the plugin interface =

Check the permissions on the theme directory and on the comicpress-config.php file itself.  Both of these need to be writable by the user that the Webserver runs as.  For more information on this, contact your Webhost.

Alternatively, if you can't automatically write to the comicpress-config.php file, the config that would have been written will be shown on-screen.  Copy and paste this into your comicpress-config.php file.

= I edited my config, and now I'm getting errors =

Check your theme folder for the following

* A missing comicpress-config.php file
* A file (or files) named comicpress-config.php.{long string of numbers}

If this has happened, you won't be able to edit your config through ComicPress Manager.  Try experimenting with different permissions settings on your theme folder to improve the situation, or edit your configuration by hand.

= I can't upload a large image file or a large Zip file =

The upload\_max\_filesize setting on your server may be set too low.  You can do one of the following:

* Talk with your Webhost about increasing the upload\_max\_size for your entire site
* Split the upload up into several smaller piece
* Create or modify an .htaccess file at the root of your WordPress site, and place a php.ini directive to increase upload\_max\_filesize just for that part of the site:

<pre>php_value upload_max_filesize "5M"</pre>

= How can I change the minimum access level for the plugin? =

There are three lines at the top of the plugin that define the <code>$access_level</code> of the plugin.  Uncomment the line that defines
the level of access you want to give and comment out the others.

= Why can't I generate thumbnails? =

You will need either GD library support compiled into PHP or the ImageMagick "convert" binary in your path.  If neither of these are available, you will be unable to generate thumbnails.  Your thumbnail directories also need to be writable by the Webserver process.

= What if I don't want to automatically generate thumbnails? =

At the top of comicpress_manager.php file, within the ComicPressConfig class, is the following:

<pre>
'archive_generate_thumbnails' => true,
'rss_generate_thumbnails'     => true
</pre>

Set the appropriate setting to false to disable thumbnail writing.

= How do I change the output quality of the JPEG thumbnail file? =

Change the <code>'thumbnail_quality'</code> to a value between 0 (ugly & small filesize) to 100 (no compression).

= The plugin fails during import =

If you are importing a large number of files, especially if you're generating thumbnails, the amount of time it would take to process the comics can exceed the time allotted by your Webhost for a script to run.  In this case, you can do the following:

* Don't generate thumbnails during import, and instead generate them later.
* Import your comic in chunks by uploading Zip files of comics or by uploading only a few at a time to the comics directory.
* Add a [<code>set_time_limit</code>](http://us3.php.net/set_time_limit) command to the top of the plugin.
* Ask your Webhost to increase the <code>max_execution_time</code> for your site.

= I know what I'm doing.  How do I disable the sanity checks to improve performance? =

Find this line:

<pre>define("CPM_SKIP_CHECKS", false);</pre>

and set it to true;

<pre>define("CPM_SKIP_CHECKS", true);</pre>

= I want to translate your plugin into my language. =

Feel free to contact me, or better yet, send a translation in.  The POT file is in the plugin directory.  I'm still new to this, so if I'm doing something wrong in the code, please tell me.  :)

= I'm having another problem =

Post a detailed description of the problem on the [Lunchbox Funnies ComicPress Support Forum](http://www.lunchboxfunnies.com/forum/viewforum.php?f=7).  If asked, provide the info given when you click the Show Debug Info link on the left-hand side.

== License ==

ComicPress Manager is released under the GNU GPL version 2.0 or later.

The Dynarch DHTML Calendar Widget is released under the GNU LGPL.

== Credits ==

Big thanks to Tyler Martin for his assistance, bug finding, and with coming up with ComicPress in the first place.  Also thanks to Danny Burleson, tk0169, Tim Hengeveld, and Keith C. Smith for beta testing, and the folks at the Lunchbox Funnies forum for finding bugs in the initial releases.

ComicPress Manager uses the [Dynarch DHTML Calendar Widget](http://www.dynarch.com/projects/calendar/) for date fields.