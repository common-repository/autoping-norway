=== Plugin Name ===
Contributors: knutsp
Tags: trackback, ping, promote, share, post, services, writing, blopp, bloggarkivet, vote
Requires at least: 2.7
Tested up to: 2.9.2
Stable tag: 2.6.3

Pings some Norwegian and international ping service.

== Description ==

This plugin is dying. Update to disable functions and services no longer avaiable.

BUT: This plugin may add a few ordninary XML-RPC pingback services. Currently included: 

* Twingly
* Bloggoversikten
* Bloggurat
* Technorati
* Google BlogSearch

**NOTES:** 

* The Twingly services is a special services which *re-pings* your posts to several affiliated newspaper web editions.
Hence, if you link to a newspaper article published online, your blog post will be listed and linked from that article.
* Bloggoversikten and Bloggurat are other Norwegian blog search, list and aggregating sites.
* Technorati and Google BlogSearch are blog search and overview sites.

This plugin lets you easily add or remove these services without copying and pasting their ping-URL into the 'Ping services'
field onthe 'Options - Writing' page.

== Installation ==

1. Upload the contents (zipped subfolder 'autoping-norway') to 'wp-content/plugins/' of your WordPress site.
1. Activate the plugin through the 'Plugins - Installed' page under WordPress Dashboard.
1. Optionally, configure wanted pingback services under 'Settings - Autoping Other'.
1. If you want to use "Blopp", visit either the 'Users - Autoping Blopp' or the 'Settings - Autoping Blopp' page and follow the instructions for registration.
1. Optionally, configure automatic category selection using the 'Posts - Autoping Categories' page under WordPress Dashboard.

== Frequently Asked Questions ==

= When will pinging the service "Blogglisten", as indicated in an earlier version, be available? =

This is not decided. I don't even know if they will allow automatic pings.
At last it's a bit complicated because they use form based pings only, and have no separate interface for automated pings.

== Changelog ==

= Version 2.6.3, June 10, 2010 =

* Both Bloggarkivet and Blopp has now terminated their services or using other mechanisms.  This plugin will die. 

= Version 2.6.2, August 4, 2009 =

* Needed update for blogs where automatic categories mapping has never been set for either "Bloggarkivet" or "Blopp"
* Fixes a bug in 2.6 making the *selection list of categories* for "Bloggarkivet" and "Blopp" on the 'Autoping Categories' page to be **empty**, except for "Don't ping".
* Fixes a mistake making the **vote button** for "Blopp" not to appear in Internet Explorer (unsupported MIME type)

= Version 2.6.1, July 20, 2009 =

* Fixes a bug that made the blog-wide "Blopp" registration undetected. If a per user based registration wasn't
  saved then a "Missing" warning was seen on the Categories page, and "Blopp" would not be pinged.

= Version 2.6, July 16, 2009 =

* Enables a per user setting of each Blopp registration
* Optionally adds a **vote button** for posts pinged to Blopp and that is entirely displayed (no *more* tag active) on either a single post page, the front page, all kinds archive pages and so on.
* Removed all traces of "Bloggrevyen".

= Version 2.5.1, June 20, 2009 =

* **Bugfix** for only the first few categories displayed.

= Version 2.5, June 19, 2009 =

* Added support for some ordinary pingback services, currently "Twingly", "Bloggoversikten", "Bloggurat", "Google Blogsearch" and "Technorati".
* Fixed a **bug** (discovered back in version 2.0) causing new *pages* to be pinged when the next a post is published.
* Inhibited the browser *autocomplete* to work on the username and password fields for "Blopp" registration settings.

= Version 2.2, June 11, 2009 =

* Removed "Bloggrevyen", as the service is terminated.
* Added the new service "Blopp", which requires authentication to allow trackback pings.
* New options/settings page for services requiring registration (currently "Blopp" only).

= Version 2.1.1, June 2, 2009 =

* Added a new category for Bloggarkivet.

= Version 2.1, May 27, 2009 =

* Fix for a language/localization bug wich caused the translation not to work, except when the plugin folder happened to be the non-standard `autoping` instead of `autoping-norway`.
* Fix for listing of grandchild categories and below. They are now listed under its parent category, with a dash indicating the sublevel.
* Plugin now completely independent of the plugin install folder. May this make it easier to work with WP-MU?
* Added an extra information line, or a warning message, on the same page, after checking the current language/localization settings and file. This should help debugging any misbehaviour here.

= Version 2.0, March 3, 2009 =

* Original release of this plugin.

= Version 1.0, November 6, 2006 =

* This plugin's predecessor "Trackback Bloggarkivet/-revyen" released. Then maintained for 2.5 years.
