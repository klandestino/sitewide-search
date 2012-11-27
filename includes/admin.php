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
	 * Static function that setups the admin interface
	 * @uses add_action
	 * @return void
	 */
	static public function __setup() {
		// Add action for wordpress init
		add_action( 'init', array( Sitewide_Search_Admin, 'init' ) );
		// Fetch all post-types with registered_post_type to use in admin settings page
		add_action( 'registered_post_type', array( Sitewide_Search_Admin, 'add_post_type' ), 10, 2 );
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
		$settings = get_site_options( 'sitewide_search_settings', array() );
		$defaults = array(
			'enabled' => false,
			'archive_blog_id' => 0,
			'post_types' => array()
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
		self::$post_types[ $name ] = $post_type->labels->name;
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

}
