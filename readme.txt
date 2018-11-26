=== Portier ===
Contributors: offereins
Tags: protect, access, restrict, site, blog, user
Requires at least: 4.6
Tested up to: 4.9.8
Stable tag: 1.2.2
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Restrict access to your (multi)site

== Description ==

Restrict access to your site(s) or network for selected users. Additionally show a message at the login screen to notify the user.

Supports BuddyPress:
* provide access for selected user groups
* provide access for selected member types

== Installation ==

If you download Portier manually, make sure it is uploaded to "/wp-content/plugins/portier/".

Activate Portier in the "Plugins" admin panel using the "Activate" link. If you're using WordPress Multisite, you can choose to activate Portier network wide for full integration with all of your sites.

This plugin is not hosted in the official WordPress repository. Instead, updating is supported through use of the [GitHub Updater](https://github.com/afragen/github-updater/) plugin by @afragen and friends.

== Changelog ==

= 1.2.2 =
* Fixed feed access restrictions that were applied before the current user was checked
* Fixed admin-bar styles in the front-end

= 1.2.1 =
* Updated translations

= 1.2.0 =
* Renamed plugin to Portier because of a naming conflict with another Guard plugin in the .org repository
* Added support for BuddyPress member types

= 1.1.0 =
* Changed Network Sites admin to use a list table
* Added admin pointer for the admin bar Guard icon

= 1.0.0 =
* Initial release
