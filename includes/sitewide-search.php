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
	 * Holder for which blog thumbnail will be fetched from
	 */
	public $thumbnail_blog_id = 0;

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
			add_action( 'save_post', array( &$this, 'save_post' ), 1000 );
			add_action( 'transition_post_status', array( &$this, 'save_post' ), 1000 );
			// Handle taxonomy inserts
			add_action( 'set_object_terms', array( &$this, 'save_taxonomy' ), 1000 );
			// Handle meta data
			add_action( 'added_post_meta', array( &$this, 'save_meta' ), 1000, 2 );
			add_action( 'updated_post_meta', array( &$this, 'save_meta' ), 1000, 2 );
			add_action( 'deleted_post_meta', array( &$this, 'save_meta' ), 1000, 2 );
			// Handle post trashing and deleting
			add_action( 'trash_post', array( &$this, 'delete_post' ), 1000 );
			add_action( 'delete_post', array( &$this, 'delete_post' ), 1000 );
			// Handle blog removal
			add_action( 'delete_blog', array( &$this, 'delete_all_posts_by_blog' ), 1000 );
			add_action( 'archive_blog', array( &$this, 'delete_all_posts_by_blog' ), 1000 );
			add_action( 'deactivate_blog', array( &$this, 'delete_all_posts_by_blog' ), 1000 );
			add_action( 'make_spam_blog', array( &$this, 'delete_all_posts_by_blog' ), 1000 );
			add_action( 'mature_blog', array( &$this, 'delete_all_posts_by_blog' ), 1000 );
			add_action( 'update_option_blog_public', array( &$this, 'handle_blog_update' ), 1000 );

			// Handle post queries
			// Adds post types and from what blog posts will be fetched
			add_action( 'pre_get_posts', array( &$this, 'set_post_query' ) );
			// Return original permalink for posts from archive
			add_filter( 'post_link', array( &$this, 'get_original_permalink' ), 10, 2 );
			//add_filter( 'get_permalink', array( &$this, 'get_original_permalink' ), 10, 2 );
			// Set blog for original thumbnail for archive posts from correct blog
			add_action( 'begin_fetch_post_thumbnail_html', array( &$this, 'switch_blog_for_thumbnail_begin' ), 10, 3 );
			add_action( 'end_fetch_post_thumbnail_html', array( &$this, 'switch_blog_for_thumbnail_end' ), 10, 3 );
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

			$this->current_blog_id = get_current_blog_id();
			switch_to_blog( $this->settings[ 'archive_blog_id' ] );

			$this->post_count = $wpdb->get_var( sprintf(
				'SELECT COUNT( * ) FROM `%s` WHERE `post_status` = "publish"',
				$wpdb->posts
			) );
			restore_current_blog();
		}

		return $this->post_count;
	}

	/**
	 * Saves a post to archive blog
	 * @uses wp_update_post
	 * @uses wp_inster_post
	 * @param int $post_id
	 * @return void
	 */
	public function save_post( $post_id ) {
		global $wpdb;

		// Only if archive blog has been set, current blog hasn't changed to archive blog and this blog is public
		if(
			$this->settings[ 'archive_blog_id' ] != get_current_blog_id()
			&& ! $this->current_blog_id
			&& get_blog_option( get_current_blog_id(), 'public', true )
		) {
			$post = get_post( $post_id );

			if( is_object( $post ) ) {
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
						$this->current_blog_id = get_current_blog_id();
						$copy = new stdClass();

						// Sanitize post input and copy fields
						$fields = array(
							'ID',
							'post_author',
							'post_date',
							'post_date_gmt',
							'post_content',
							'post_content_filtered',
							'post_title',
							'post_excerpt',
							'post_status',
							'post_type',
							'comment_status',
							'ping_status',
							'post_password',
							'post_name',
							'to_ping',
							'pinged',
							'post_modified',
							'post_modified_gmt',
							'post_parent',
							'menu_order',
							'guid'
						);

						foreach( $fields as $field ) {
							if( property_exists( $post, $field ) ) {
								$copy->$field = $post->$field;
							}
						}

						// Saves a reference to the blog and the post with the following pattern:
						// [blog id],[post id]
						// Becouse it's saved in guid, wordpress will prepend this with a
						// http:// or https://
						$copy->guid = sprintf( '%d,%d', $this->current_blog_id, $copy->ID );
						// No pinging
						$copy->ping_status = 'closed';
						// No comments
						$copy->comment_status = 'closed';

						$copy_id = 0;

						// Make post copy available for filter
						$copy = apply_filters( 'sitewide_search_save_post', $copy, $post, $this->current_blog_id );

						// If post copy filter returned false ...
						if( is_object( $copy ) ) {
							// Switch to archive blog
							switch_to_blog( $this->settings[ 'archive_blog_id' ] );

							// Look for a already save copy of this post
							$copy_id = $wpdb->get_var( $wpdb->prepare(
								'SELECT `ID` FROM `' . $wpdb->posts . '` WHERE `guid` REGEXP "[^0-9]*%d,%d"',
								$this->current_blog_id,
								$post_id
							) );
							unset( $copy->ID );

							/*
							 * Inserting data with wpdb instead of wp_insert/update_post.
							 * In this way we won't get any trouble with data in
							 * the wrong place when switching blog and running a
							 * bunch of actions and filters.
							 */

							// Save post copy in archive blog
							if( $copy_id ) {
								// The old way
								//wp_update_post( ( array ) $copy );
								// The new way
								$wpdb->update( $wpdb->posts, ( array ) $copy, array( 'ID' => $copy_id ) );
							} else {
								// The old way
								//$copy_id = wp_insert_post( ( array ) $copy );
								// The new way
								$wpdb->insert( $wpdb->posts, ( array ) $copy );
							}

							// Switch back to original blog
							restore_current_blog();
							$this->current_blog_id = 0;

							// Save taxonomies and metadata
							$this->save_taxonomy( $post_id );
							$this->save_meta( 0, $post_id );
						} else {
							$this->current_blog_id = 0;
						}
					}
				}
			}
		}
	}

	/**
	 * Saves taxonomies related to post
	 * @param int $post_id
	 * @return void
	 */
	public function save_taxonomy( $post_id ) {
		global $wpdb;

		if( $this->settings[ 'archive_blog_id' ] != get_current_blog_id() ) {
			$post = get_post( $post_id, OBJECT );

			if( $post ) {
				if( property_exists( $post, 'guid' ) ) {
					$guid = $post->guid;
				} else {
					$guid = '';
				}

				if(
					in_array( $post->post_type, $this->settings[ 'post_types' ] )
					&& $post->post_status == 'publish'
					&& ! preg_match( '/^[^0-9]+[0-9]+,[0-9]+$/', $guid )
				) {

					$terms = wp_get_object_terms( $post_id, $this->settings[ 'taxonomies' ] );

					// Make terms available with filters
					$terms = apply_filters( 'sitewide_search_save_taxonomy', $terms, $post, $this->current_blog_id );

					$this->current_blog_id = get_current_blog_id();
					switch_to_blog( $this->settings[ 'archive_blog_id' ] );

					$copy_id = $wpdb->get_var( $wpdb->prepare(
						'SELECT `ID` FROM `' . $wpdb->posts . '` WHERE `guid` REGEXP "[^0-9]*%d,%d"',
						$this->current_blog_id,
						$post_id
					) );

					if( $copy_id ) {
						// Delete old term relationships
						$wpdb->query( $wpdb->prepare(
							'DELETE FROM `' . $wpdb->term_relationships . '` WHERE `object_id` = %d',
							$copy_id
						) );

						if( is_array( $terms ) ) {
							// The old way
							//wp_set_object_terms( $copy_id, $terms, $taxonomy );
							// The new way ...

							// Used to recount term count
							$recount = array();

							foreach( $terms as $term ) {
								$term_info = term_exists( $term->name, $term->taxonomy );

								if( ! $term_info ) {
									$term_info = wp_insert_term( $term->name, $term->taxonomy );
								}

								$wpdb->insert( $wpdb->term_relationships, array(
									'object_id' => $copy_id,
									'term_taxonomy_id' => $term_info[ 'term_taxonomy_id' ]
								) );

								if( ! array_key_exists( $term->taxonomy, $recount ) ) {
									$recount[ $term->taxonomy ] = array();
								}

								// Add taxonomy terms for counting
								$recount[ $term->taxonomy ][] = $term_info[ 'term_taxonomy_id' ];
							}

							// Update term count
							foreach( $recount as $tax => $ids ) {
								wp_update_term_count( $ids, $tax );
							}
						}
					}

					restore_current_blog();
					$this->current_blog_id = 0;
				}
			}
		}
	}

	/**
	 * Saves post meta
	 * @param int|array $meta_id *Not used*
	 * @param int $post_id
	 * @return void
	 */
	public function save_meta( $meta_id, $post_id ) {
		global $wpdb;

		if(
			$this->settings[ 'archive_blog_id' ] != get_current_blog_id()
			&& $this->settings[ 'meta' ]
		) {
			$post = get_post( $post_id, OBJECT );

			if( $post ) {
				if( property_exists( $post, 'guid' ) ) {
					$guid = $post->guid;
				} else {
					$guid = '';
				}

				if(
					in_array( $post->post_type, $this->settings[ 'post_types' ] )
					&& $post->post_status == 'publish'
					&& ! preg_match( '/^[^0-9]+[0-9]+,[0-9]+$/', $guid )
				) {
					$this->current_blog_id = get_current_blog_id();

					$meta = get_metadata( 'post', $post_id );

					// Make meta available with filters
					$meta = apply_filters( 'sitewide_search_save_meta', $meta, $post, $this->current_blog_id );

					// Run is there's any meta
					if( is_array( $meta ) ) {
						switch_to_blog( $this->settings[ 'archive_blog_id' ] );

						$copy_id = $wpdb->get_var( $wpdb->prepare(
							'SELECT `ID` FROM `' . $wpdb->posts . '` WHERE `guid` REGEXP "[^0-9]*%d,%d"',
							$this->current_blog_id,
							$post_id
						) );

						if( $copy_id ) {
							/*
							 * Inserting meta with wpdb instead of add_metadata.
							 * In this way we won't get any trouble with data in
							 * the wrong place when switching blog and running a
							 * bunch of actions and filters.
							 */

							$table = _get_meta_table( 'post' );
							$column = esc_sql( 'post_id' );

							// First delete all metadata
							$wpdb->query( $wpdb->prepare(
								'DELETE FROM `' . $table . '` WHERE `' . $column . '` = %d',
								$copy_id
							) );

							// Then insert the originals
							foreach( $meta as $key => $values ) {
								foreach( $values as $val ) {
									$wpdb->insert( $table, array(
										$column => $copy_id,
										'meta_key' => $key,
										'meta_value' => $val
									) );
								}
							}
						}

						restore_current_blog();
					}

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

		if( $this->settings[ 'archive_blog_id' ] != get_current_blog_id() ) {
			$this->current_blog_id = get_current_blog_id();

			$post_id = apply_filters( 'sitewide_search_delete_post', $post_id, $this->current_blog_id );

			if( $post_id ) {
				switch_to_blog( $this->settings[ 'archive_blog_id' ] );

				$copies = $wpdb->get_results( $wpdb->prepare(
					'SELECT `ID` FROM `' . $wpdb->posts . '` WHERE `guid` REGEXP "[^0-9]*%d,%d"',
					$this->current_blog_id,
					$post_id
				), OBJECT );

				if( $copies ) {
					foreach( $copies as $copy ) {
						wp_delete_post( $copy->ID );
					}
				}

				restore_current_blog();
			}

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

		if( $this->settings[ 'archive_blog_id' ] != get_current_blog_id() ) {
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

		if( $this->settings[ 'archive_blog_id' ] != get_current_blog_id() ) {
			$this->current_blog_id = get_current_blog_id();
			switch_to_blog( $this->settings[ 'archive_blog_id' ] );

			$copies = $wpdb->get_results( $wpdb->prepare(
				'SELECT `ID` FROM `' . $wpdb->posts . '` WHERE `guid` REGEXP "[^0-9]*%d,[0-9]+"',
				$blog_id
			), OBJECT );

			if( $copies ) {
				foreach( $copies as $copy ) {
					wp_delete_post( $copy->ID );
				}
			}

			restore_current_blog();
			$this->current_blog_id = 0;
		}
	}

	/**
	 * Delete all posts from archive blog by truncating post tables
	 * @return void
	 */
	public function delete_all_posts() {
		global $wpdb;

		if( $this->settings[ 'archive_blog_id' ] != get_current_blog_id() ) {
			$this->current_blog_id = get_current_blog_id();
			switch_to_blog( $this->settings[ 'archive_blog_id' ] );

			foreach( array(
				$wpdb->posts,
				$wpdb->postmeta,
				$wpdb->terms,
				$wpdb->term_taxonomy,
				$wpdb->term_relationships
			) as $table ) {
				$wpdb->query( sprintf( 'TRUNCATE TABLE `%s`', $table ) );
			}

			restore_current_blog();
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
			&& ! is_admin()
		) {
			if( $this->current_blog_id != get_current_blog_id() ) {
				$this->current_blog_id = get_current_blog_id();
				switch_to_blog( $this->settings[ 'archive_blog_id' ] );
			}

			// The filter posts_results is executed just after the query
			// was executed. We'll use it as a after_get_posts-action.
			// We want to restore the blog id to the current blog so we
			// don't mess up with the headers and so.
			add_filter( 'posts_results', array( &$this, 'after_set_post_query' ) );

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
		remove_filter( 'posts_results', array( &$this, 'after_set_post_query' ) );

		foreach( $posts as $i => $post ) {
			if( preg_match( '/[^0-9]*([0-9]+),([0-9]+)/', $post->guid, $guid ) ) {
				$post->ID = intval( $guid[ 2 ] );
				$post->blog_id = intval( $guid[ 1 ] );
				$posts[ $i ] = $post;
			}
		}

		restore_current_blog();
		$this->current_blog_id = 0;

		return $posts;
	}

	/**
	 * Get original permalink from correct blog
	 * @param string $permalink
	 * @param object $post
	 * return string
	 */
	public function get_original_permalink( $permalink, $post ) {
		if( property_exists( $post, 'blog_id' ) ) {
			if( intval( $post->blog_id ) != get_current_blog_id() ) {
				return get_blog_permalink( $post->blog_id, $post->ID );
			}
		} elseif( preg_match( '/[^0-9]*([0-9]+),([0-9]+)/', $post->guid, $guid ) ) {
			if( intval( $guid[ 1 ] ) != get_current_blog_id() ) {
				return get_blog_permalink( $guid[ 1 ], $guid[ 2 ] );
			}
		}

		return $permalink;
	}

	/**
	 * Switches blog if the post's blog is somewhere else
	 * @param int $post_id
	 * @param int $thumb_id
	 * @param string $size
	 * return void
	 */
	public function switch_blog_for_thumbnail_begin( $post_id, $thumb_id, $size ) {
		if( $this->settings[ 'archive_blog_id' ] ) {
			switch_to_blog( $this->settings[ 'archive_blog_id' ] );
			$this->thumbnai_blog_id = 0;
			$post = get_post( $post_id );

			if( preg_match( '/[^0-9]*([0-9]+),([0-9]+)/', $post->guid, $guid ) ) {
				$this->thumbnail_blog_id = $guid[ 1 ];
			}

			restore_current_blog();

			if( $this->thumbnail_blog_id ) {
				switch_to_blog( $this->thumbnail_blog_id );
			}
		}
	}

	/**
	 * Switches blog back to current blog if the post's blog is somewhere else
	 * @param int $post_id
	 * @param int $thumb_id
	 * @param string $size
	 * return void
	 */
	public function switch_blog_for_thumbnail_end( $post_id, $thumb_id, $size ) {
		if( $this->thumbnail_blog_id ) {
			restore_current_blog();
			$this->thumbnail_blog_id = 0;
		}
	}

}
