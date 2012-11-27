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
	 * Settings stored locally
	 */
	public $settings = array();

	/**
	 * Constructor
	 */
	function __construct() {
		$this->settings = Sitewide_Search_Admin::get_settings();
	}

	/**
	 * Adds all wordpress actions and filters
	 * @uses add_action
	 * @uses add_filter
	 * @return void
	 */
	public function add_actions_and_filters() {
		// If there's no archive blog set, then there's no blog to save posts to
		if( $this->settings[ 'archive_blog_id' ] ) {
			// Handle post saving
			add_action( 'save_post', array( $this, 'save_post', 10, 2 ) );
			// Handle post trashing and deleting
			add_action( 'trash_post', array( $this, 'delete_post' ) );
			add_action( 'delete_post', array( $this, 'delete_post' ) );
			// Handle blog removal
			add_action( 'delete_blog', array( $this, 'delete_all_posts_by_blog' ) );
			add_action( 'archive_blog', array( $this, 'delete_all_posts_by_blog' ) );
			add_action( 'deactivate_blog', array( $this, 'delete_all_posts_by_blog' ) );
			add_action( 'make_spam_blog', array( $this, 'delete_all_posts_by_blog' ) );
			add_action( 'mature_blog', array( $this, 'delete_all_posts_by_blog' ) );
			add_action( 'transition_post_status', array( $this, 'delete_all_posts_by_blog' ) );
		}
	}

	/**
	 * Saves a post to archive blog
	 */
	public function save_post( $post_id, $post ) {
	}

	/**
	 * Deletes a post from the archive blog
	 */
	public function delete_post( $post_id ) {
	}

	/**
	 * Delete all posts by blog from archive blog
	 */
	public function delete_all_posts_by_blog( $blog_id ) {
	}

}
