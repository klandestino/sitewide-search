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
	 * Holder for current blog id
	 */
	public $current_blog_id = 0;

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
		// If there's no archive blog set, then there's no blog to save and
		// get posts from.
		if( $this->settings[ 'archive_blog_id' ] ) {
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

			// Handle post queries
			// Adds post types and from what blog posts will be fetched
			add_action( 'pre_get_posts', array( $this, 'set_post_query' ) );
			// Return original permalink for posts from archive
			add_filter( 'post_link', array( $this, 'get_original_permalink' ), 10, 2 );
			//add_filter( 'get_permalink', array( $this, 'get_original_permalink' ), 10, 2 );
		}
	}

	/**
	 *
	 */

	/**
	 * Get sitewide-search post copies count
	 * @return int
	 */
	public function get_post_count() {
		if( $this->post_count < 0 && $this->settings[ 'archive_blog_id' ] ) {
			global $wpdb;
			$this->current_blog_id = $wpdb->blogid;
			$wpdb->set_blog_id( $this->settings[ 'archive_blog_id' ] );
			$this->post_count = $wpdb->get_var( sprintf(
				'SELECT COUNT( * ) FROM `%s` WHERE `post_status` = "publish"',
				$wpdb->prepare( $wpdb->posts )
			) );
			$wpdb->set_blog_id( $this->current_blog_id );
		}

		return $this->post_count;
	}

	/**
	 * Saves a post to archive blog
	 * @uses wp_update_post
	 * @uses wp_inster_post
	 * @param int $post_id
	 * @param object $post optional, uses $_POST, $_GET and get_post() as fallbacks and in that order
	 * @return void
	 */
	public function save_post( $post_id, $post = null ) {
		global $wpdb;

		// Only if archive blog has been set and this blog is public
		if( $this->settings[ 'archive_blog_id' ] != $wpdb->blogid && ! $this->current_blog_id && get_blog_option( $wpdb->blogid, 'public', true ) ) {
			// If not $post is defined, let's assume that the post has been posted here.
			// Otherwise load it with get_post()
			if( ! is_object( $post ) && array_key_exists( 'post_title', $_POST ) ) {
				$post = ( object ) $_POST;
			} elseif( ! is_object( $post ) && array_key_exists( 'post_title', $_GET ) ) {
				$post = ( object ) $_GET;
			} elseif( ! is_object( $post ) ) {
				$post = get_post( $post_id );
			}

			// Is this a post?
			if( property_exists( $post, 'post_type' ) && property_exists( $post, 'post_status' ) ) {
				if( property_exists( $post, 'guid' ) ) {
					$guid = $post->guid;
				} else {
					$guid = '';
				}

				// Save only posts with post type defined in settings
				// And only published posts
				if(
					in_array( $post->post_type, $this->settings[ 'post_types' ] )
					&& $post->post_status == 'publish'
					&& ! preg_match( '/^[^0-9]+[0-9]+,[0-9]+$/', $guid )
				) {
					$this->current_blog_id = $wpdb->blogid;
					// Saves a reference to the blog and the post with the following pattern:
					// [blog id],[post id]
					// Becouse it's saved in guid, wordpress will prepend this with a
					// http:// or https://
					$post->guid = sprintf( '%d,%d', $this->current_blog_id, $post->ID );
					// No pinging
					$post->ping_status = 'closed';
					// No comments
					$post->comment_status = 'closed';
					$copy_id = 0;

					// Switch to archive blog
					$wpdb->set_blog_id( $this->settings[ 'archive_blog_id' ] );

					// Look for a already save copy of this post
					$copy_id = $wpdb->get_var( sprintf(
						'SELECT `ID` FROM `%s` WHERE `guid` REGEXP "[^0-9]*%d,%d"',
						$wpdb->prepare( $wpdb->posts ),
						$wpdb->prepare( $this->current_blog_id ),
						$wpdb->prepare( $post_id )
					) );

					// Save post copy in archive blog
					if( $copy_id ) {
						$post->ID = $copy_id;
						wp_update_post( ( array ) $post );
					} else {
						unset( $post->ID );
						$copy_id = wp_insert_post( ( array ) $post );
					}

					// Switch back to original blog
					$wpdb->set_blog_id( $this->current_blog_id );
					$this->current_blog_id = 0;

					// Get all post terms and save them with $this->save_taxonomy()
					$terms = wp_get_object_terms( $post_id, $this->settings[ 'taxonomies' ] );
					$tax = array();

					foreach( $terms as $term ) {
						if( ! is_array( $tax[ $term->taxonomy ] ) ) {
							$tax[ $term->taxonomy ] = array( 'terms' => array(), 'term_ids' => array() );
						}

						$tax[ $term->taxonomy ][ 'terms' ][] = $term->name;
						$tax[ $term->taxonomy ][ 'term_ids' ][] = $term->term_id;
					}

					foreach( $tax as $tax_name => $term ) {
						$this->save_taxonomy( $post_id, $term[ 'terms' ], $terms[ 'term_ids' ], $tax_name );
					}
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

		if( $this->settings[ 'archive_blog_id' ] != $wpdb->blogid ) {
			$post = get_post( $post_id, OBJECT );

			if( property_exists( $post, 'guid' ) ) {
				$guid = $post->guid;
			} else {
				$guid = '';
			}

			if( $post ) {
				if(
					in_array( $post->post_type, $this->settings[ 'post_types' ] )
					&& $post->post_status == 'publish'
					&& in_array( $taxonomy, $this->settings[ 'taxonomies' ] )
					&& ! preg_match( '/^[^0-9]+[0-9]+,[0-9]+$/', $guid )
				) {
					foreach( $terms as $i => $term ) {
						if( is_numeric( $term ) ) {
							$term = get_term( $term_id, $taxonomy, OBJECT );
							$terms[ $i ] = $term->name;
							unset( $term );
						}
					}

					$this->current_blog_id = $wpdb->blogid;
					$wpdb->set_blog_id( $this->settings[ 'archive_blog_id' ] );

					$copy_id = $wpdb->get_var( sprintf(
						'SELECT `ID` FROM `%s` WHERE `guid` REGEXP "[^0-9]*%d,%d"',
						$wpdb->prepare( $wpdb->posts ),
						$wpdb->prepare( $this->current_blog_id ),
						$wpdb->prepare( $post_id )
					) );

					if( $copy_id ) {
						wp_set_object_terms( $copy_id, $terms, $taxonomy );
					}

					$wpdb->set_blog_id( $this->current_blog_id );
					$this->current_blog_id = 0;
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

		if( $this->settings[ 'archive_blog_id' ] != $wpdb->blogid ) {
			$this->current_blog_id = $wpdb->blogid;
			$wpdb->set_blog_id( $this->settings[ 'archive_blog_id' ] );

			$copies = $wpdb->get_results( sprintf(
				'SELECT `ID` FROM `%s` WHERE `guid` REGEXP "[^0-9]*%d,%d"',
				$wpdb->prepare( $wpdb->posts ),
				$wpdb->prepare( $this->current_blog_id ),
				$wpdb->prepare( $post_id )
			), OBJECT );

			if( $copies ) {
				foreach( $copies as $copy ) {
					wp_delete_post( $copy->ID );
				}
			}

			$wpdb->set_blog_id( $this->current_blog_id );
			$this->current_blog_id = 0;
		}
	}

	/**
	 * Check blog status and erase all posts if it's not public anymore
	 * Run by update_option-action
	 * @return void
	 */
	public function handle_blog_update() {
		global $blog_id;

		if( $this->settings[ 'archive_blog_id' ] != $wpdb->blogid ) {
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

		if( $this->settings[ 'archive_blog_id' ] != $wpdb->blogid ) {
			$this->current_blog_id = $wpdb->blogid;
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

			$wpdb->set_blog_id( $this->current_blog_id );
			$this->current_blog_id = 0;
		}
	}

	/**
	 * Delete all posts from archive blog by truncating post tables
	 * @return void
	 */
	public function delete_all_posts() {
		global $wpdb;

		if( $this->settings[ 'archive_blog_id' ] != $wpdb->blogid ) {
			$this->current_blog_id = $wpdb->blogid;
			$wpdb->set_blog_id( $this->settings[ 'archive_blog_id' ] );

			foreach( array(
				$wpdb->posts,
				$wpdb->postmeta,
				$wpdb->terms,
				$wpdb->term_taxonomy,
				$wpdb->term_relationships
			) as $table ) {
				$wpdb->query( sprintf( 'TRUNCATE TABLE `%s`', $wpdb->prepare( $table ) ) );
			}

			$wpdb->set_blog_id( $this->current_blog_id );
			$this->current_blog_id = 0;
		}
	}

	/**
	 * Sets the post query to fetch posts from archive blog
	 * @uses WP_Query
	 * @param object $query the wordpress query object to modify
	 * @return object WP_Query
	 */
	public function set_post_query( $query ) {
		// If bbpress is installed, then local forum listnings
		// defined as archive etc. will not be overrided.
		$is_forum = false;
		$forum_funcs = array( 'bbp_is_forum', 'bbp_is_topic', 'bbp_is_reply' );

		// Loop through bbpress-functions
		foreach( $forum_funcs as $func ) {
			if( function_exists( $func ) ) {
				$is_forum = call_user_func( $func );
			}
		}

		unset( $forum_funcs );

		// Only change query if archive blog is set
		// and if this query is a search query
		if(
			$this->settings[ 'archive_blog_id' ]
			&& (
				( $this->settings[ 'enable_search' ] && $query->is_search )
				|| ( $this->settings[ 'enable_archive' ] && $query->is_archive )
				|| ( $this->settings[ 'enable_categories' ] && $query->is_category )
				|| ( $this->settings[ 'enable_tags' ] && $query->is_tag )
				|| ( $this->settings[ 'enable_author' ] && $query->is_author )
			)
			&& ! $is_forum
		) {
			global $wpdb;

			if( $this->current_blog_id != $wpdb->blogid ) {
				$this->current_blog_id = $wpdb->blogid;
				$wpdb->set_blog_id( $this->settings[ 'archive_blog_id' ] );
			}

			// The filter posts_results is executed just after the query
			// was executed. We'll use it as a after_get_posts-action.
			// We want to restore the blog id to the current blog so we
			// don't mess up with the headers and so.
			add_filter( 'posts_results', array( $this, 'after_set_post_query' ) );

			return $query;
		}
	}

	/**
	 * When the posts has been fetched.
	 * Restore current blog id.
	 * And change post type to the original.
	 * @param array $posts The fetched posts
	 * @return array
	 */
	public function after_set_post_query( $posts ) {
		global $wpdb;
		remove_filter( 'posts_results', array( $this, 'after_set_post_query' ) );

		foreach( $posts as $i => $post ) {
			if( preg_match( '/[^0-9]*([0-9]+),([0-9]+)/', $post->guid, $guid ) ) {
				$post->ID = $guid[ 2 ];
				$post->blog_id = $guid[ 1 ];
				$posts[ $i ] = $post;
			}
		}

		$wpdb->set_blog_id( $this->current_blog_id );
		$this->current_blog_id = 0;

		return $posts;
	}

	/**
	 *
	 */
	public function get_original_permalink( $permalink, $post ) {
		if( property_exists( $post, 'blog_id' ) ) {
			return get_blog_permalink( $post->blog_id, $post->ID );
		} elseif( preg_match( '/[^0-9]*([0-9]+),([0-9]+)/', $post->guid, $guid ) ) {
			return get_blog_permalink( $guid[ 2 ], $post->ID );
		} else {
			return $permalink;
		}
	}

}
