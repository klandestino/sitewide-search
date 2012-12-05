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
		// Hook the populate_archive ajax request
		add_action( 'wp_ajax_populate_archive', array( Sitewide_Search_Admin, 'populate_archive' ) );
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
			'archive_blog_id' => 0,
			'post_types' => array( 'post' ),
			'taxonomies' => array( 'post_tag', 'category' ),
			'enable_search' => false,
			'enable_archive' => false,
			'enable_categories' => false,
			'enable_tags' => false,
			'enable_author' => false
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
	 * If no settings-form has been posted, this function adds an admin notification
	 * if there's a get-variable named sitewide-search-updated.
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

			foreach( array(
				'enable_search', 'enable_archive', 'enable_categories', 'enable_tags', 'enable_author'
			) as $override ) {
				if( ! array_key_exists( $override, $_POST ) ) {
					$settings[ $override ] = false;
				} else {
					$settings[ $override ] = ! empty( $settings[ $override ] );
				}
			}

			update_site_option( 'sitewide_search_settings', $settings );
			wp_redirect( add_query_arg( array( 'sitewide-search-updated' => '1' ) ) );
		} elseif( array_key_exists( 'sitewide-search-updated', $_GET ) ) {
			// Add a admin notification
			add_action( 'network_admin_notices', create_function( '', sprintf(
				'echo "<div class=\"updated\"><p>%s</p></div>";',
				__( 'Settings updated.', 'sitewide-search' )
			) ) );
		}
	}

	/**
	 * Searches for blogs by name.
	 * Mainly used by ajax-requests and therefor the print_json argument.
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
			// Query is an array, let's assume it's a set of ids.
			$query = $wpdb->get_results( sprintf(
				'SELECT `blog_id`, `domain` FROM `%s` WHERE `blog_id` IN ( %s )',
				$wpdb->prepare( $wpdb->blogs ),
				$wpdb->prepare( implode( ',', $query ) )
			), ARRAY_A );
		} elseif( ! empty( $query ) ) {
			// Query is a non-empty (and hopefully) string. Search blog by domain (name is not defined in wp_blogs-table
			$query = $wpdb->get_results( sprintf(
				'SELECT `blog_id`, `domain` FROM `%s` WHERE `domain` LIKE "%%%s%%"',
				$wpdb->prepare( $wpdb->blogs ),
				$wpdb->prepare( $query )
			), ARRAY_A );
		}

		foreach( $query as $blog ) {
			$wpdb->set_blog_id( $blog[ 'blog_id' ] );

			// Gather additional blog data in it's own option-table - wp_[blog id]_options
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
	 * Resets the archive blog, removes all posts.
	 * Prints a json encoded array when done.
	 * This is an ajax action, written only to run by ajax requests.
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
	 * Populates the archive blog.
	 * This is an ajax action, written only to run by ajax requests.
	 * Becouse copying posts from all blogs can take a long time, bepending on how large the site is,
	 * this action is split into several request with 100 posts each.
	 * First request will initiate this action and start with the first blog. Then print a json-
	 * encoded array for a javascript to handle and continue the process.
	 * @uses Sitewide_Search::save_post
	 * @return void
	 */
	static public function populate_archive() {
		check_ajax_referer( 'sitewide-search-populate', 'security' );

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
			// Gather current step data from the post request
			foreach( $step as $i => $prop ) {
				if( array_key_exists( $i, $_POST ) ) {
					$step[ $i ] = ( int ) $_POST[ $i ];
				}

				// All step data is defined by numbers,
				// so if no number, use default.
				if( ! is_numeric( $step[ $i ] ) ) {
					$step[ $i ] = $prop;
				}
			}

			if( ! $step[ 'blog' ] ) {
				// No blog defined, start with main blog (id 1)
				$step[ 'blog' ] = $wpdb->get_var( sprintf(
					'SELECT `blog_id` FROM `%s` ORDER BY `blog_id` ASC LIMIT 0,1',
					$wpdb->prepare( $wpdb->blogs )
				) );
			} else {
				// Check requested blog
				$step[ 'blog' ] = $wpdb->get_var( sprintf(
					'SELECT `blog_id` FROM `%s` WHERE `blog_id` = "%d" LIMIT 0,1',
					$wpdb->prepare( $wpdb->blogs ),
					$wpdb->prepare( $step[ 'blog' ] )
				) );
			}

			if( $step[ 'blog' ] ) {
				$step[ 'post_done' ] = 0;
				$step[ 'blog_name' ] = get_blog_option( $step[ 'blog' ], 'blogname' );

				// If there's no blog count defined, get amount of blogs to show the admin
				// of what's left to do.
				if( ! $step[ 'blog_count' ] ) {
					$step[ 'blog_count' ] = $wpdb->get_var( sprintf(
						'SELECT COUNT( * ) FROM `%s`',
						$wpdb->prepare( $wpdb->blogs )
					) );
				}

				// Only copy posts from public blogs
				if( get_blog_option( $step[ 'blog' ], 'public', true ) ) {
					$wpdb->set_blog_id( $step[ 'blog' ] );

					// If there's no post count defined, get amount of posts to show the
					// admin of what's left to do.
					if( ! $step[ 'post_count' ] ) {
						$step[ 'post_count' ] = $wpdb->get_var( sprintf(
							'SELECT COUNT( * ) FROM `%s` WHERE `post_status` = "publish" AND `post_type` IN ( %s )',
							$wpdb->prepare( $wpdb->posts ),
							$wpdb->prepare( sprintf( '"%s"', implode( '","', $settings[ 'post_types' ] ) ) )
						) );
					}

					// Do the post request
					$posts = $wpdb->get_results( sprintf(
						'SELECT `ID` FROM `%s` WHERE `ID` > "%d" AND `post_status` = "publish" AND `post_type` IN ( %s ) ORDER BY `ID` ASC LIMIT 0,%d',
						$wpdb->prepare( $wpdb->posts ),
						$wpdb->prepare( $step[ 'post' ] ),
						$wpdb->prepare( sprintf( '"%s"', implode( '","', $settings[ 'post_types' ] ) ) ),
						$wpdb->prepare( $chunk )
					), OBJECT );

					if( $posts ) {
						// Tell admin with how many posts been done
						$step[ 'post_done' ] = $wpdb->num_rows;

						foreach( $posts as $post ) {
							$sitewide_search->save_post( $post->ID );
							$step[ 'post' ] = $post->ID;
						}
					}

					if( $step[ 'post_done' ] ) {
						// Posts done - say so with message
						$step[ 'message' ] = sprintf( __( 'Copied %2$d of %3$d from %1$s.', 'sitewide-search' ), $step[ 'blog_name' ], $step[ 'post_done' ], $step[ 'post_count' ] );
						$step[ 'post_count' ] -= $step[ 'post_done' ];
					} else {
						// No posts been done - say so with message
						$step[ 'message' ] = sprintf( __( 'Blog %1$s done of %2$d left to do.', 'sitewide-search' ), $step[ 'blog_name' ], $step[ 'blog_count' ] - 1 );
						$step[ 'post_count' ] = 0;
					}
				} else {
					$step[ 'message' ] = sprintf( __( 'Blog %1$s is not public, skipping. %2$d left to do.', 'sitewide-search' ), $step[ 'blog_name' ], $step[ 'blog_count' ] - 1 );
				}

				// If no posts has been done, then we'll assume blog is done.
				// Select new blog and return the data to the ajax requester.
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
					// No blog found, guess it's done
					$step[ 'status' ] = 'done';
				}

				$step[ 'security' ] = wp_create_nonce( 'sitewide-search-populate' );
				$step[ 'action' ] = 'populate_archive';
			} else {
				// Current blog was not found. Assume it was the last and return a done status.
				$step[ 'status' ] = 'done';
				$step[ 'message' ] = __( 'No blogs found', 'sitewide-search' );
			}
		}

		echo json_encode( $step );
		// Exit when done and before wordpress or something else prints a zero.
		exit;
	}

}
