=== Z Like Button ===
Contributors: whodunitagency, larrach, audrasjb, leprincenoir
Tags: like, button, like, like button, custom like
Requires at least: 5.5
Tested up to: 6.8
Requires PHP: 5.6
Stable tag: 0.0.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Display a very simple and customisable like button for your posts or any custom post type.

== Description ==

Display a very simple and customisable like button for your posts or any custom post type.

== Installation ==

= Install the Mini Menu from within WordPress =

1. Visit the plugins page within your dashboard and select ‘Add New’;
1. Search for ‘Z Like Button’;
1. Activate the plugin from your Plugins page;
1. Go to ‘after activation’ below.

= Install manually =

1. Unzip the Z Like Button zip file
2. Upload the unzipped folder to the /wp-content/plugins/ directory;
3. Activate the plugin through the ‘Plugins’ menu in WordPress;
4. Go to ‘after activation’ below.

= After activation =

1. On the Plugins page in WordPress you will see a 'settings' link below the plugin name;
2. On the settings page, tick the boxes for location (**where** on the page you want the button to appear) and post types (on **what type** of posts/pages);
3. I you want you can
    - hide the counter box
    - change the icon
    - change the color of the icon
4. If you want, you can give users a list of all the posts and pages they liked by
    a. Selecting a page to automatically have the list add to that page or
    b. Select 'None' and add the `[z_my_likes_list]` shortcode on the page of your liking. If a user is logged in, the list of liked posts and pages will appear.
5. Save your settings and you’re done!

== Frequently Asked Questions ==

= Can I add additional styling? =

Yes you can. By adding custom styles in the WordPress customizer under /Appearance/Customize. The parent element of the button has the `.zLikeButton` class, the label containing the icon is styled using the `zLikeLabel` class.

= Do you have plans to improve the plugin =

Yes. We currently have on our roadmap:
* Adding translations
* Adding more features for both the button and the 'My liks list' (ordering in time, grouping by post type)
* Adding option for minifying the assets

== Screenshots ==

1. Plugin settings
2. Plugin default rendering
3. Plugin metabox: you can remove the like button for any post or content individually and even cheat with you counters with manual editing :P


== Changelog ==

= 0.0.4 =
* Added color selection for the icons

= 0.0.3 =
* Added shortcode for a "My liked posts" overview

= 0.0.2 =
* Optimized validation, added icon selection

= 0.0.1 =
* Pre-release