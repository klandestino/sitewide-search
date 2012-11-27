<?php

/**
 * Sitewide Search main class
 */
class Sitewide_Search {

	/**
	 * Static function thats sets up Sitewide_Search as a global object
	 * and adds all wordpress actions and filters
	 * @uses Sitewide_Search::add_actions_and_filters
	 * @return void
	 */
	static function __setup() {
		global $sitewide_search;
		$sitewide_search = new Sitewide_Search();
		$sitewide_search->add_actions_and_filters();
	}

	/**
	 * Locates and loads a template by using Wordpress locate_template.
	 * If no template is found, it loads a template from this plugins template
	 * directory.
	 * @uses locate_template
	 * @param string $slug
	 * @param string $name
	 * @return void
	 */
	public static function get_template( $slug, $name = '' ) {
		$template_names = array(
			$slug . '-' . $name . '.php',
			$slug . '.php'
		);

		$located = locate_template( $template_names );

		if ( empty( $located ) ) {
			foreach( $template_names as $name ) {
				if ( file_exists( SITEWIDE_SEARCH_TEMPLATE_DIR . '/' . $name ) ) {
					load_template( SITEWIDE_SEARCH_TEMPLATE_DIR . '/' . $name, false );
					return;
				}
			}
		} else {
			load_template( $located, false );
		}
	}

	/**
	 * Settings

	/**
	 * Constructor
	 */
	function __construct() {
		//
	}

	/**
	 * Adds all wordpress actions and filters
	 * @uses add_action
	 * @uses add_filter
	 * @return void
	 */
	public function add_actions_and_filters() {
		//
	}

}
