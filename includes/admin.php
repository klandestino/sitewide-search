<?php

/**
 * Sitewide Site administration class
 * Sets up settings page in the network admin interface
 * and provides settings and defaults.
 */
class Sitewide_Search_Admin {

	/**
	 * Static array with supported post types
	 * Added by registered_post_type action
	 */
	static public $post_types = array();

	/**
	 * Static array with supported taxonomies
	 * Add by registered_taxonomy action
	 */
	static public $taxonomies = array();

	/**
	 * Static function that setups the admin interface
	 * @uses add_action
	 * @return void
	 */
	static public function __setup() {
		// Add action for wordpress init
		add_action( 'init', array( Sitewide_Search_Admin, 'init' ) );
		// Fetch all post-types with registered_post_type to use in admin settings page
		add_action( 'registered_post_type', array( Sitewide_Search_Admin, 'add_post_type' ), 10, 2 );
		// Fetch all taxonomies with registered_taxonomy to use in admin settings page
		add_action( 'registered_taxonomy', array( Sitewide_Search_Admin, 'add_taxonomy' ), 10, 3 );
		// Add scripts and styles
		add_action( 'admin_enqueue_scripts', array( Sitewide_Search_Admin, 'enqueue_scripts' ) );
		// Hook the get_blogs ajax request
		add_action( 'wp_ajax_get_blogs', array( Sitewide_Search_Admin, 'get_blogs' ) );
		// Hook the reset_archive ajax request
		add_action( 'wp_ajax_reset_archive', array( Sitewide_Search_Admin, 'reset_archive' ) );
		// Hook the repopulate_archive ajax request
		add_action( 'wp_ajax_repopulate_archive', array( Sitewide_Search_Admin, 'repopulate_archive' ) );
	}

	/**
	 * Initializes the administration if thit is a sitewide-admin
	 * @uses is_site_admin
	 * @uses add_action
	 * @return void
	 */
	static public function init() {
		if( is_site_admin() ) {
			add_action( 'admin_init', array( Sitewide_Search_Admin, 'admin_page_save' ) );
			add_action( 'network_admin_menu', array( Sitewide_Search_Admin, 'admin_menu' ) );
		}
	}

	/**
	 * Gets settings stored as site options or defaults if there are none
	 * @uses get_site_option
	 * @return array
	 */
	static public function get_settings() {
		$settings = get_site_option( 'sitewide_search_settings', array() );
		$defaults = array(
			'enabled' => false,
			'archive_blog_id' => 0,
			'post_types' => array( 'post' ),
			'taxonomies' => array( 'post_tag', 'category' )
		);

		if( is_array( $settings ) ) {
			foreach( $defaults as $key => $val ) {
				if( array_key_exists( $key, $settings ) ) {
					$defaults[ $key ] = $settings[ $key ];
				}
			}
		}

		return $defaults;
	}

	/**
	 * Adds a post type into the static array Sitewide_Search_Admin::post_types
	 * @param string $name
	 * @param object $post_type
	 * @return void
	 */
	public static function add_post_type( $name, $post_type ) {
		if( $name != 'sitewide-search' ) {
			self::$post_types[ $name ] = $post_type->labels->name;
		}
	}

	/**
	 * Adds a taxnomy into the static array Sitewide_Search_Admin::taxonomies
	 * @param string $name
	 * @param string $post_type
	 * @param array $taxonomy
	 * @return void
	 */
	public static function add_taxonomy( $name, $post_type, $taxonomy ) {
		self::$taxonomies[ $name ] = $taxonomy[ 'labels' ]->name;
	}

	/**
	 * Makes wordpress load scripts and styles
	 * @uses wp_enqueue_script
	 * @uses wp_enqueue_style
	 * @return void
	 */
	static public function enqueue_scripts() {
		wp_enqueue_script( 'sitewide-search-admin', SITEWIDE_SEARCH_PLUGIN_URL . '/js/sitewide-search-admin.js', array( 'jquery' ) );
		wp_enqueue_style( 'sitewide-search-admin', SITEWIDE_SEARCH_PLUGIN_URL . '/css/sitewide-search-admin.css' );
	}

	/**
	 * Adds network admin menu items
	 * @uses add_submenu_page
	 * @return void
	 */
	public static function admin_menu() {
		add_submenu_page(
			'settings.php',
			__( 'Sitewide Search Settings', 'sitewide-search' ),
			__( 'Sitewide Search', 'sitewide-search' ),
			'manage_options',
			'sitewide-search',
			array( Sitewide_Search_Admin, 'admin_page' )
		);
	}

	/**
	 * Prints an admin page through a template
	 * @uses Sitewide_Search::get_template
	 * @return void
	 */
	public static function admin_page() {
		Sitewide_Search::get_template( 'sitewide-search-admin' );
	}

	/**
	 * Receives the posted admin form and saves settings
	 * @uses update_site_option
	 * @return void
	 */
	public static function admin_page_save() {
		if( array_key_exists( 'sitewide-search-save', $_POST ) ) {
			check_admin_referer( 'sitewide_search_admin' );
			$settings = self::get_settings();
			
			foreach( $settings as $key => $val ) {
				if( array_key_exists( $key, $_POST ) ) {
					$settings[ $key ] = $_POST[ $key ];
				}
			}

			update_site_option( 'sitewide_search_settings', $settings );
			wp_redirect( add_query_arg( array( 'sitewide-search-updated' => '1' ) ) );
		} elseif( array_key_exists( 'sitewide-search-updated', $_GET ) ) {
			add_action( 'network_admin_notices', create_function( '', sprintf(
				'echo "<div class=\"updated\"><p>%s</p></div>";',
				__( 'Settings updated.', 'sitewide-search' )
			) ) );
		}
	}

	/**
	 * Searches for blogs by name
	 * @uses $wpdb->get_results
	 * @uses $wpdb->set_blog_id
	 * @param string|int|array $query optional, search string or int or array with blog id's. Using $_POST[ query ] if empty
	 * @param boolean $print_json optional, if set to true, result will be printed as json and exit
	 * @return array|void
	 */
	static public function get_blogs( $query = '', $print_json = true ) {
		global $wpdb;
		$blogs = array();
		$current_blog_id = $wpdb->blogid;

		if( empty( $query ) && array_key_exists( 'query', $_POST ) ) {
			$query = $_POST[ 'query' ];
		}

		if( is_numeric( $query ) ) {
			$query = array( $query );
		}

		if( is_array( $query ) ) {
			$query = $wpdb->get_results( sprintf(
				'SELECT `blog_id`, `domain` FROM `%s` WHERE `blog_id` IN ( %s )',
				$wpdb->prepare( $wpdb->blogs ),
				$wpdb->prepare( implode( ',', $query ) )
			), ARRAY_A );
		} elseif( ! empty( $query ) ) {
			$query = $wpdb->get_results( sprintf(
				'SELECT `blog_id`, `domain` FROM `%s` WHERE `domain` LIKE "%%%s%%"',
				$wpdb->prepare( $wpdb->blogs ),
				$wpdb->prepare( $query )
			), ARRAY_A );
		}

		foreach( $query as $blog ) {
			$wpdb->set_blog_id( $blog[ 'blog_id' ] );

			$subquery = $wpdb->get_results( sprintf(
				'SELECT `option_name`, `option_value` FROM `%s` WHERE `option_name` IN ( "siteurl", "blogname", "blogdescription" )',
				$wpdb->prepare( $wpdb->options )
			), ARRAY_A );

			foreach( $subquery as $opt ) {
				$blog[ $opt[ 'option_name' ] ] = esc_attr( $opt[ 'option_value' ] );
			}

			$blogs[] = $blog;
		}

		$wpdb->set_blog_id( $current_blog_id );

		if( $print_json ) {
			echo json_encode( $blogs );
			// Exit when done and before wordpress or something else prints a zero.
			exit;
		} else {
			return $blogs;
		}
	}

	/**
	 * This is an ajax action.
	 * Resets the archive blog, removes all posts.
	 * Prints a json encoded array when done.
	 * @uses Sitewide_Search::delete_all_posts
	 * @uses json_encode
	 * @return void
	 */
	static public function reset_archive() {
		check_ajax_referer( 'sitewide-search-reset', 'security' );

		global $sitewide_search;
		$sitewide_search->delete_all_posts();

		echo json_encode( array( 'deleted' => 'all' ) );
		// Exit when done and before wordpress or something else prints a zero.
		exit;
	}

	/**
	 * This is an ajax action.
	 * Repopulates the archive blog.
	 * Prints a json encoded array when done.
	 * @uses Sitewide_Search::save_post
	 * @return void
	 */
	static public function repopulate_archive() {
		check_ajax_referer( 'sitewide-search-repopulate', 'security' );

		global $wpdb, $sitewide_search;
		$chunk = 100;
		$step = array(
			'blog' => 0,
			'blog_count' => 0,
			'post' => 0,
			'post_count' => 0,
		);
		$settings = self::get_settings();

		if( $settings[ 'archive_blog_id' ] ) {
			foreach( $step as $i => $prop ) {
				if( array_key_exists( $i, $_POST ) ) {
					$step[ $i ] = ( int ) $_POST[ $i ];
				}

				if( ! is_numeric( $step[ $i ] ) ) {
					$step[ $i ] = $prop;
				}
			}

			if( ! $step[ 'blog' ] ) {
				$step[ 'blog' ] = $wpdb->get_var( sprintf(
					'SELECT `blog_id` FROM `%s` ORDER BY `blog_id` ASC LIMIT 0,1',
					$wpdb->prepare( $wpdb->blogs )
				) );
			} else {
				$step[ 'blog' ] = $wpdb->get_var( sprintf(
					'SELECT `blog_id` FROM `%s` WHERE `blog_id` = "%d" LIMIT 0,1',
					$wpdb->prepare( $wpdb->blogs ),
					$wpdb->prepare( $step[ 'blog' ] )
				) );
			}

			if( $step[ 'blog' ] ) {
				$step[ 'post_done' ] = 0;
				$step[ 'blog_name' ] = get_blog_option( $step[ 'blog' ], 'blogname' );

				if( ! $step[ 'blog_count' ] ) {
					$step[ 'blog_count' ] = $wpdb->get_var( sprintf(
						'SELECT COUNT( * ) FROM `%s`',
						$wpdb->prepare( $wpdb->blogs )
					) );
				}

				if( get_blog_option( $step[ 'blog' ], 'public', true ) ) {
					$wpdb->set_blog_id( $step[ 'blog' ] );

					if( ! $step[ 'post_count' ] ) {
						$step[ 'post_count' ] = $wpdb->get_var( sprintf(
							'SELECT COUNT( * ) FROM `%s` WHERE `post_status` = "publish" AND `post_type` IN ( %s )',
							$wpdb->prepare( $wpdb->posts ),
							$wpdb->prepare( sprintf( '"%s"', implode( '","', $settings[ 'post_types' ] ) ) )
						) );
					}

					$posts = $wpdb->get_results( sprintf(
						'SELECT `ID` FROM `%s` WHERE `ID` > "%d" AND `post_status` = "publish" AND `post_type` IN ( %s ) ORDER BY `ID` ASC LIMIT 0,%d',
						$wpdb->prepare( $wpdb->posts ),
						$wpdb->prepare( $step[ 'post' ] ),
						$wpdb->prepare( sprintf( '"%s"', implode( '","', $settings[ 'post_types' ] ) ) ),
						$wpdb->prepare( $chunk )
					), OBJECT );

					if( $posts ) {
						$step[ 'post_done' ] = $wpdb->num_rows;

						foreach( $posts as $post ) {
							$sitewide_search->save_post( $post->ID );
							$step[ 'post' ] = $post->ID;
							$terms = wp_get_object_terms( $post->ID, $settings[ 'taxonomies' ] );
							$tax = array();

							foreach( $terms as $term ) {
								if( ! is_array( $tax[ $term->taxonomy ] ) ) {
									$tax[ $term->taxonomy ] = array( 'terms' => array(), 'term_ids' => array() );
								}

								$tax[ $term->taxonomy ][ 'terms' ][] = $term->name;
								$tax[ $term->taxonomy ][ 'term_ids' ][] = $term->term_id;
							}

							foreach( $tax as $tax_name => $term ) {
								$sitewide_search->save_taxonomy( $post->ID, $term[ 'terms' ], $terms[ 'term_ids' ], $tax_name );
							}
						}
					}

					if( $step[ 'post_done' ] ) {
						$step[ 'message' ] = sprintf( __( 'Copied %2$d of %3$d from %1$s.', 'sitewide-search' ), $step[ 'blog_name' ], $step[ 'post_done' ], $step[ 'post_count' ] );
						$step[ 'post_count' ] -= $step[ 'post_done' ];
					} else {
						$step[ 'message' ] = sprintf( __( 'Blog %1$s done of %2$d left to do.', 'sitewide-search' ), $step[ 'blog_name' ], $step[ 'blog_count' ] - 1 );
						$step[ 'post_count' ] = 0;
					}
				} else {
					$step[ 'message' ] = sprintf( __( 'Blog %1$s is not public, skipping. %2$d left to do.', 'sitewide-search' ), $step[ 'blog_name' ], $step[ 'blog_count' ] - 1 );
				}

				if( ! $step[ 'post_done' ] ) {
					$step[ 'post' ] = 0;
					$step[ 'blog_count' ]--;
					$step[ 'blog' ] = $wpdb->get_var( sprintf(
						'SELECT `blog_id` FROM `%s` WHERE `blog_id` > "%d" ORDER BY `blog_id` ASC LIMIT 0,1',
						$wpdb->prepare( $wpdb->blogs ),
						$wpdb->prepare( $step[ 'blog' ] )
					) );
				}

				if( $step[ 'blog' ] ) {
					$step[ 'status' ] = 'ok';
				} else {
					$step[ 'status' ] = 'done';
				}

				$step[ 'security' ] = wp_create_nonce( 'sitewide-search-repopulate' );
				$step[ 'action' ] = 'repopulate_archive';
			} else {
				$step[ 'status' ] = 'done';
				$step[ 'message' ] = __( 'No blogs found', 'sitewide-search' );
			}
		}

		echo json_encode( $step );
		// Exit when done and before wordpress or something else prints a zero.
		exit;
	}

}
