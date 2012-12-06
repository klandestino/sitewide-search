=== Sitewide Search ===

Contributors: spurge, lakrisgubben, alfreddatakillen
Tags: wordpressmu, sitewide, multisite, search, archive
Requires at least: WordPress 3.4.2
Tested up to: WordPress 3.4.2
Stable tag: 0.9.1

Creates sitewide searching and archive/category/tag/author browsing.

== Description ==

Sitewide Search copies all posts from your site to a specified blog. You'll then be able to search and browse posts from the whole site at one place.

This plugin can also be set to override search and browse queries so the results is sitewide wherever you are.

What post types and which taxnonomies to include is also editable with an easy admin interface.

= More info, wiki and issue tracker =

https://github.com/klandestino/sitewide-search

= Available languages =

* English (built-in)
* Swedish

== Installation ==

Download and upload the plugin to your plugins folder. Then activate it in your network administration.

Then create a blog you wish to save all the posts to. Select the blog in the administration settings page, tune your settings and populate the the blog with the sites all posts.

All the settings is available in the network administration settings pages.

== Changelog ==

= v0.9.1 =

* Amount of posts per request can be set when running populate.
* Fixed issue with garbage in output buffer before returning json to populate action.

= v0.9 =

* Saving posts and taxonomies to archive blog.
* Post types, taxonomies and archive blog can be administrated.
* Override blog source for searching and browsing posts by date, tags, categories and author.
* Reset function for archive blog.
* Populate function for archive blog.
