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
	 * Amount of sitewide-search post copies stored locally
	 */
	public $post_count = -1;

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
			// Register sitewide-search copy post-type
			add_action( 'init', array( $this, 'register_post_type' ) );
			// Handle post saving
			add_action( 'save_post', array( $this, 'save_post' ), 10, 2 );
			add_action( 'transition_post_status', array( $this, 'save_post', 10, 2 ) );
			// Handle taxonomy inserts
			add_action( 'set_object_terms', array( $this, 'save_taxonomy' ), 10, 4 );
			// Handle post trashing and deleting
			add_action( 'trash_post', array( $this, 'delete_post' ) );
			add_action( 'delete_post', array( $this, 'delete_post' ) );
			// Handle blog removal
			add_action( 'delete_blog', array( $this, 'delete_all_posts_by_blog' ) );
			add_action( 'archive_blog', array( $this, 'delete_all_posts_by_blog' ) );
			add_action( 'deactivate_blog', array( $this, 'delete_all_posts_by_blog' ) );
			add_action( 'make_spam_blog', array( $this, 'delete_all_posts_by_blog' ) );
			add_action( 'mature_blog', array( $this, 'delete_all_posts_by_blog' ) );
			add_action( 'update_option_blog_public', array( $this, 'handle_blog_update' ) );
		}
	}

	/**
	 * Get sitewide-search post copies count
	 * @return int
	 */
	public function get_post_count() {
		if( $this->post_count < 0 && $this->settings[ 'archive_blog_id' ] ) {
			global $wpdb;
			$current_blog_id = $wpdb->blogid;
			$wpdb->set_blog_id( $this->settings[ 'archive_blog_id' ] );
			$this->post_count = $wpdb->get_var( sprintf(
				'SELECT COUNT( * ) FROM `%s` WHERE `post_type` = "sitewide-search"',
				$wpdb->prepare( $wpdb->posts )
			) );
		}

		return $this->post_count;
	}

	/**
	 * Registers a post-type to use when saving sitewide-search post copies in the archive blog
	 * @uses register_post_type
	 * @return void
	 */
	public function register_post_type() {
		register_post_type( 'sitewide-search' );
	}

	/**
	 * Saves a post to archive blog
	 * @uses wp_update_post
	 * @uses wp_inster_post
	 * @param int $post_id
	 * @param object $post optional, uses $_POST and $_GET as fallbacks
	 * @return void
	 */
	public function save_post( $post_id, $post = null ) {
		global $wpdb;
		$current_blog_id = $wpdb->blogid;

		if( $this->settings[ 'archive_blog_id' ] != $current_blog_id && get_blog_option( $current_blog_id, 'public', true ) ) {
			if( ! is_object( $post ) && array_key_exists( 'post_title', $_POST ) ) {
				$post = ( object ) $_POST;
			} elseif( ! is_object( $post ) && array_key_exists( 'post_title', $_GET ) ) {
				$post = ( object ) $_GET;
			} elseif( ! is_object( $post ) ) {
				$post = get_post( $post_id );
			}

			if( property_exists( $post, 'post_type' ) && property_exists( $post, 'post_status' ) ) {
				if(
					in_array( $post->post_type, $this->settings[ 'post_types' ] )
					&& $post->post_status == 'publish'
				) {
					$permalink = get_permalink( $post->ID );
					$post->guid = sprintf( '%d,%d', $current_blog_id, $post->ID );
					$post_type = $post->post_type;
					$post->post_type = 'sitewide-search';
					$post->ping_status = 'closed';
					$post->comment_status = 'closed';
					$copy_id = 0;

					$wpdb->set_blog_id( $this->settings[ 'archive_blog_id' ] );

					$copy_id = $wpdb->get_var( sprintf(
						'SELECT `ID` FROM `%s` WHERE `guid` REGEXP "[^0-9]*%d,%d"',
						$wpdb->prepare( $wpdb->posts ),
						$wpdb->prepare( $current_blog_id ),
						$wpdb->prepare( $post_id )
					) );

					if( $copy_id ) {
						$post->ID = $copy_id;
						wp_update_post( ( array ) $post );
					} else {
						unset( $post->ID );
						$copy_id = wp_insert_post( ( array ) $post );
					}

					update_post_meta( $copy_id, 'permalink', $permalink );
					update_post_meta( $copy_id, 'post_type', $post_type );

					$wpdb->set_blog_id( $current_blog_id );
				}
			}
		}
	}

	/**
	 * Saves taxonomies related to post
	 * @uses wp_set_object_terms
	 * @param int $post_id
	 * @param array $terms
	 * @param array $term_ids
	 * @param string $taxonomy
	 * @return void
	 */
	public function save_taxonomy( $post_id, $terms, $term_ids, $taxonomy ) {
		global $wpdb;
		$current_blog_id = $wpdb->blogid;

		if( $this->settings[ 'archive_blog_id' ] != $current_blog_id ) {
			$post = get_post( $post_id, OBJECT );

			if( $post ) {
				if(
					in_array( $post->post_type, $this->settings[ 'post_types' ] )
					&& $post->post_status == 'publish'
					&& in_array( $taxonomy, $this->settings[ 'taxonomies' ] )
				) {
					foreach( $terms as $i => $term ) {
						if( is_numeric( $term ) ) {
							$term = get_term( $term_id, $taxonomy, OBJECT );
							$terms[ $i ] = $term->name;
							unset( $term );
						}
					}

					$wpdb->set_blog_id( $this->settings[ 'archive_blog_id' ] );

					$copy_id = $wpdb->get_var( sprintf(
						'SELECT `ID` FROM `%s` WHERE `guid` REGEXP "[^0-9]*%d,%d"',
						$wpdb->prepare( $wpdb->posts ),
						$wpdb->prepare( $current_blog_id ),
						$wpdb->prepare( $post_id )
					) );

					if( $copy_id ) {
						wp_set_object_terms( $copy_id, $terms, $taxonomy );
					}

					$wpdb->set_blog_id( $current_blog_id );
				}
			}
		}
	}

	/**
	 * Deletes a post from the archive blog
	 * @uses wp_delete_post
	 * @param int $post_id
	 * @return void
	 */
	public function delete_post( $post_id ) {
		global $wpdb;
		$current_blog_id = $wpdb->blogid;

		if( $this->settings[ 'archive_blog_id' ] != $current_blog_id ) {
			$wpdb->set_blog_id( $this->settings[ 'archive_blog_id' ] );

			$copies = $wpdb->get_results( sprintf(
				'SELECT `ID` FROM `%s` WHERE `guid` REGEXP "[^0-9]*%d,%d"',
				$wpdb->prepare( $wpdb->posts ),
				$wpdb->prepare( $current_blog_id ),
				$wpdb->prepare( $post_id )
			), OBJECT );

			if( $copies ) {
				foreach( $copies as $copy ) {
					wp_delete_post( $copy->ID );
				}
			}

			$wpdb->set_blog_id( $current_blog_id );
		}
	}

	/**
	 * Check blog status and erase all posts if it's not public anymore
	 * Run by update_option-action
	 * @return void
	 */
	public function handle_blog_update() {
		global $blog_id;

		if( $this->settings[ 'archive_blog_id' ] != $blog_id ) {
			if( ! get_blog_option( $blog_id, 'public', true ) ) {
				$this->delete_all_posts_by_blog( $blog_id );
			}
		}
	}

	/**
	 * Delete all posts by blog from archive blog
	 * @uses wp_delete_post
	 * @param int $blog_id
	 * $return void
	 */
	public function delete_all_posts_by_blog( $blog_id ) {
		global $wpdb;
		$current_blog_id = $wpdb->blogid;

		if( $this->settings[ 'archive_blog_id' ] ) {
			$wpdb->set_blog_id( $this->settings[ 'archive_blog_id' ] );

			$copies = $wpdb->get_results( sprintf(
				'SELECT `ID` FROM `%s` WHERE `guid` REGEXP "[^0-9]*%d,[0-9]+"',
				$wpdb->prepare( $wpdb->posts ),
				$wpdb->prepare( $blog_id )
			), OBJECT );

			if( $copies ) {
				foreach( $copies as $copy ) {
					wp_delete_post( $copy->ID );
				}
			}

			$wpdb->set_blog_id( $current_blog_id );
		}
	}

	/**
	 * Delete all posts from archive blog
	 * @uses wp_delete_post
	 * @return void
	 */
	public function delete_all_posts() {
		global $wpdb;

		if( $this->settings[ 'archive_blog_id' ] ) {
			$current_blog_id = $wpdb->blogid;
			$wpdb->set_blog_id( $this->settings[ 'archive_blog_id' ] );

			$copies = $wpdb->get_results( sprintf(
				'SELECT `ID` FROM `%s` WHERE `post_type` = "sitewide-search"',
				$wpdb->prepare( $wpdb->posts )
			), OBJECT );

			if( $copies ) {
				foreach( $copies as $copy ) {
					wp_delete_post( $copy->ID );
				}
			}

			$wpdb->set_blog_id( $current_blog_id );
		}
	}

}
