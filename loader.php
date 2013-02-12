<?php

/*
Plugin Name: Sitewide Search
Plugin URI: https://github.com/klandestino/sitewide-search
Description: Creates a multisite searching and archive/category/tag/author browsing
Version: 0.9.5
Requires at least: 3.4.2
Tested up to: 3.5.1
License: GNU General Public License 2.0 (GPL) http://www.gnu.org/licenses/gpl.html
Author: spurge
Author URI: https://github.com/spurge
Network: true
*/

// Set symlink friendly dir constant
define( 'SITEWIDE_SEARCH_PLUGIN_DIR', dirname( $network_plugin ) );

// Where to find plugin templates
// Used by Sitewide_Search::get_template
define( 'SITEWIDE_SEARCH_TEMPLATE_DIR', dirname( __FILE__ ) . '/templates' );

// Set a symlink friendly plugin dir url
define( 'SITEWIDE_SEARCH_PLUGIN_URL', plugin_dir_url( '' ) . basename( SITEWIDE_SEARCH_PLUGIN_DIR ) );

// Require the main class
require_once( dirname( __FILE__ ) . '/includes/sitewide-search.php' );
// Require the admin class
require_once( dirname( __FILE__ ) . '/includes/admin.php' );

// Setup all the classes
Sitewide_Search::__setup();
Sitewide_Search_Admin::__setup();

/**
 * Loads language files during wordpress init action
 * @see add_action
 * @see load_plugin_textdomain
 * @return void
 */
function sitewide_search_load_textdomain() {
	load_plugin_textdomain( 'sitewide-search', false, plugin_basename( SITEWIDE_SEARCH_PLUGIN_DIR ) . "/languages/" );
}

// Hook languages-loading function to wordpress init action
add_action( 'init', 'sitewide_search_load_textdomain' );
