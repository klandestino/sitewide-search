Sitewide Search
===============

* Contributors: spurge, lakrisgubben, alfreddatakillen
* Tags: wordpressmu, sitewide, multisite, search, archive
* Requires at least: WordPress 3.4.2
* Tested up to: WordPress 3.6
* Stable tag: 0.9.6

Creates a multisite searching and archive/category/tag/author browsing.

Description
-----------

Sitewide Search copies all posts from your site to a specified blog. You'll then be able to search and browse posts from the whole site at one place.

This plugin can also be set to override search and browse queries so the results is sitewide wherever you are.

What post types and which taxnonomies to include is also editable with an easy admin interface.

### More info, wiki and issue tracker

https://github.com/klandestino/sitewide-search

### Available languages

* English (built-in)
* Swedish

Installation
------------

Download and upload the plugin to your plugins folder. Then activate it in your network administration.

Then create a blog you wish to save all the posts to. Select the blog in the administration settings page, tune your settings and populate the the blog with the sites all posts.

All the settings is available in the network administration settings pages.

Filters
-------

You can filter posts and taxonomies during saving and deleting.

### sitewide_search_save_post

Is triggered when a post is about to be copied into the archive blog.
Filters the post copy. Gives you 3 arguments: object `$copy`, object
`$post` and int `$blog_id`.

Return the copy as an object if you want it modified.

Return false if you don't want the post to get copied.

### sitewide_search_save_taxonomy

Is triggered when a posts taxonomy terms are about to be copied into the
archive blog. Filters the terms. Gives you 3 arguments: array `$terms`,
object `$post` and int `$blog_id`.

Return the terms as an array if you want it modified.

Return false if you don't want any terms to get copied.

### sitewide_search_delete_post

Is triggered then a post is about to be deleted from the archive blog.
Filters the post id. Gives you 2 arguments: int `$post_id` and int
`$blog_id`.

Return false if you don't want the post to get deleted.

Changelog
---------

### v0.9.6

* Fixed issue with not restoring blog when requesting an empty loop.

### v0.9.5

* Fixed issue with wrong blog id being saved during populate post action.
* Changed the way posts and terms are copied so no other actions and filters whould be invoked.
* Changed the way how posts are being fetched during searching and browsing.
* Removed browsing overridings in admin.
* Added support for copying post metadata.
* Added support for fetching thumbnail from post original blog.

### v0.9.3

* Fixed issue with archive blog being present in populate posts action.
* Fixed issue with loading indication in administration.

### v0.9.2

* Changed blog switching method wich solved a lot of problems with data saved in wrong places.
* Added filters for saving posts and taxonomies and deleting posts.

### v0.9.1

* Amount of posts per request can be set when running populate.
* Fixed issue with garbage in output buffer before returning json to populate action.

### v0.9

* Saving posts and taxonomies to archive blog.
* Post types, taxonomies and archive blog can be administrated.
* Override blog source for searching and browsing posts by date, tags, categories and author.
* Reset function for archive blog.
* Populate function for archive blog.
