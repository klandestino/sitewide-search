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
				mysql_real_escape_string( $wpdb->blogs ),
				mysql_real_escape_string( implode( ',', $query ) )
			), ARRAY_A );
		} elseif( ! empty( $query ) ) {
			$query = $wpdb->get_results( sprintf(
				'SELECT `blog_id`, `domain` FROM `%s` WHERE `domain` LIKE "%%%s%%"',
				mysql_real_escape_string( $wpdb->blogs ),
				mysql_real_escape_string( $query )
			), ARRAY_A );
		}

		foreach( $query as $blog ) {
			$wpdb->set_blog_id( $blog[ 'blog_id' ] );

			$subquery = $wpdb->get_results( sprintf(
				'SELECT `option_name`, `option_value` FROM `%s` WHERE `option_name` IN ( "siteurl", "blogname", "blogdescription" )',
				mysql_real_escape_string( $wpdb->options )
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

}
