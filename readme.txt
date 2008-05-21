=== ComicPress Manager ===
Contributors: johncoswell
Tags: comicpress, webcomics, management
Requires at least: 2.5.1
Tested up to: 2.5.1
Stable tag: 0.5.2
Donate link: http://claritycomic.com/comicpress-manager/#donate

ComicPress Manager ties in with the ComicPress theme to make managing your WordPress-hosted Webcomic easy and fast.

== Description ==

The ComicPress Manager plugin works in conjunction with an installation of [ComicPress](http://www.mindfaucet.com/comicpress/), the Webcomic theme for WordPress. ComicPress Manager is intended to reduce the amount of work required to administer a site running ComicPress. It exists because I'm both lazy and busy. :)

As of version 0.5.2, it allows you to:

* Upload individual comic files directly into your comics folder and generate a post for each comic as it's uploaded with the correct go-live date and time
  * Using this method ensures that the post has the correct date and time as it's being created, reducing mistakes in posting.
* Upload multiple comic files and generate posts for each comic
* Get a quick status & sanity check on your installation
  * ComicPress Manager will check to see if:
    * Your comics folder exists
    * Your comics folder is writable by the Webserver (essential for the use of ComicPress Manager!)
    * You have defined a blog category (and a comic category for ComicPress 2.5)
    * You're using a ComicPress-derived theme
      * NOTE: This check is done by examining the name of the theme as defined in style.css. If you want this non-fatal check to succeed, leave the term "ComicPress" in the theme title.
    * You have comics in your comics folder (if there aren't any, it could be a sign of other problems)
* Preview the comic that will go live with your comic post
  * Save a trip to your blog and see what comic will be going live with your post straight from the Write Post screen.
* Upload a Zip archive of comic files and generate posts based on all of those files
  * If you upload a lot of comics at a time (like I do), uploading a Zip file and creating placeholder entries with the correct go-live date, time, category, and a placeholder post body can save a lot of time.
* Create any missing posts for comics that have been uploaded to your comics folder
  * If you're migrating from another Webcomic hosting solution, or if you prefer to directly transfer your comics into your comics folder, then you can generate posts for all comics that don't already have posts.
* Delete a comic file and the associated comic post
  * If you need to remove a comic, take care of both the file and the post at the same time to save yourself some time. ComicPress Manager plays it safe during this operation, and will not delete a comic if more than one post appears in your comic category for that day (which shouldn't happen anyway with the current version of ComicPress).
* Modify your comicpress-config.php
  * If you're using a comicpress-config.php file, and the permissions are set correctly, you can modify the settings directly from ComicPress manager.

ComicPress Manager is built for WordPress 2.5.1 and ComicPress 2.1 and 2.5. ComicPress Manager works on PHP 4, but using PHP 5 is strongly recommended.

== Installation ==

Copy the comicpress_manager.php file to your wp-content/plugins/ directory and activate it.  ComicPress Manager works on PHP 4, but using PHP 5 is strongly recommended.

== Frequently Asked Questions ==

= I'm unable to edit my comicpress-config.php file from the plugin interface =

Check the permissions on the theme directory and on the comicpress-config.php file itself.  Both of these need to be writable by the user that the Webserver runs as.  For more information on this, contact your Webhost.

== License ==

ComicPress Manager is released under the GNU GPL version 2.0 or later.

== Credits ==

Big thanks to Tyler Martin for his assistance, bug finding, and with coming up with ComicPress in the first place.  Also thanks to the folks at the Lunchbox Funnies forum for finding bugs in the initial release.