<?php

/**
 * Portier Network Functions
 *
 * @package Portier
 * @subpackage Multisite
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Portier_Network' ) ) :
/**
 * Portier Network Class
 *
 * @since 1.0.0
 */
final class Portier_Network {

	/**
	 * Setup class
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->setup_globals();
		$this->includes();
		$this->setup_actions();
	}

	/** Private Methods **********************************************/

	/**
	 * Set default class globals
	 *
	 * @since 1.2.0
	 */
	private function setup_globals() {

		/** Paths *******************************************************/

		// Includes
		$this->includes_dir = trailingslashit( portier()->includes_dir . 'network' );
		$this->includes_url = trailingslashit( portier()->includes_url . 'network' );
	}

	/**
	 * Include required files
	 *
	 * @since 1.0.0
	 */
	private function includes() {
		require( $this->includes_dir . 'functions.php' );

		// Admin
		if ( is_admin() ) {
			require( $this->includes_dir . 'admin.php'    );
			require( $this->includes_dir . 'settings.php' );
		}
	}

	/**
	 * Setup network actions and filters
	 *
	 * @since 1.0.0
	 */
	private function setup_actions() {

		// Plugin
		add_action( 'plugins_loaded', array( $this, 'network_only' ), 20 );

		// Protection
		add_action( 'template_redirect',    array( $this, 'network_protect'   ), 0     );
		add_filter( 'login_message',        array( $this, 'login_message'     ), 0     );
		add_action( 'portier_site_protect', array( $this, 'network_redirect'  )        );
		add_action( 'admin_bar_menu',       array( $this, 'filter_admin_bar'  ), 99    );
		add_action( 'admin_menu',           array( $this, 'filter_admin_menu' ), 99    );
		add_action( 'get_blogs_of_user',    array( $this, 'filter_user_sites' ), 10, 3 );
		add_filter( 'user_has_cap',         array( $this, 'user_has_cap'      ), 10, 3 );

		// Admin
		if ( is_admin() ) {
			add_action( 'init', 'portier_network_admin' );
		}

		// Fire plugin network loaded hook
		do_action( 'portier_network_loaded' );
	}

	/** Plugin *******************************************************/

	/**
	 * Ensure Portier is only used for the network
	 *
	 * Remove plugin hooks for the single site context.
	 * 
	 * @since 1.0.0
	 */
	public function network_only() {

		// Bail when not marked as network only
		if ( ! portier_is_network_only() )
			return;

		$prtr = portier();

		// Protection
		remove_action( 'template_redirect', array( $prtr, 'site_protect'   ), 1 );
		remove_filter( 'login_message',     array( $prtr, 'login_message'  ), 1 );
		remove_action( 'admin_bar_menu',    array( $prtr, 'admin_bar_menu' )    );

		// Admin
		remove_action( 'admin_init', array( $prtr, 'register_settings' ) );
		remove_action( 'admin_menu', array( $prtr, 'admin_menu'        ) );
	}

	/** Protection ***************************************************/

	/**
	 * Redirect user when network is protected
	 *
	 * The network-defined access restrictions are enforced before any site access
	 * restrictions are evaluated. This means that more strict network restrictions
	 * are favored over less strict site restrictions.
	 *
	 * @since 1.0.0
	 */
	public function network_protect() {

		// Bail when network protection is not active
		if ( ! portier_is_network_protected() )
			return;

		// Bail when the main site is allowed
		if ( portier_network_allow_main_site() && is_main_site() )
			return;

		// Redirect when the user is not logged in or is not allowed
		if ( ! is_user_logged_in() || ! portier_network_is_user_allowed() ) {

			// Provide hook
			do_action( 'portier_network_protect' );

			// Handle feed requests
			if ( is_feed() ) {
				global $wp_query;

				// Block with 404 status
				$wp_query->is_feed = false;
				$wp_query->set_404();
				status_header( 404 );
			}

			auth_redirect();
		}
	}

	/**
	 * Append our custom network login message to the login messages
	 *
	 * @since 1.0.0
	 *
	 * @param string $message The current login messages
	 * @return string $message
	 */
	public function login_message( $message ) {

		// When network protection is active
		if ( portier_is_network_protected() ) {
			$login_message = get_site_option( '_portier_network_login_message' );

			// Append message when it's provided
			if ( ! empty( $login_message ) ) {
				$message .= '<p class="message">' . $login_message . '<p>';
			}
		}

		return $message;
	}

	/**
	 * Try to rediect the unauthorized user to an allowed site instead of the login page
	 *
	 * @since 1.0.0
	 *
	 * @uses apply_filters() Calls 'portier_network_redirect_location'
	 */
	public function network_redirect() {

		// When network redirection is active
		if ( portier_network_redirect() ) {

			// Define local variable(s)
			$user_id  = get_current_user_id();
			$location = '';

			// Find an allowed location when the user is loggedin
			if ( ! empty( $user_id ) ) {

				// Get the user's primary site
				$site = get_active_blog_for_user( $user_id );

				// Redirect user to their primary site, only when they are allowed there
				if ( ! empty( $site ) && ( ! portier_is_site_protected( $site->blog_id ) || portier_is_user_allowed( $user_id, $site->blog_id ) ) ) {
					$location = $site->siteurl . $site->path;
				}

			// Try to return the anonymous user to the network home
			} elseif ( ! portier_is_site_protected( BLOG_ID_CURRENT_SITE ) ) {
				$location = network_home_url();
			}

			// Provide hook to filter the redirect location
			$location = apply_filters( 'portier_network_redirect_location', $location, $user_id );

			// Redirect when a location is provided
			if ( ! empty( $location ) ) {
				wp_redirect( $location );
				exit;
			}
		}
	}

	/**
	 * Remove user sites that are not allowed for given user
	 *
	 * The used functions do their own blog switching.
	 * 
	 * @since 1.0.0
	 *
	 * @param array $sites Sites where user is registered
	 * @param int $user_id User ID
	 * @param boolean $all Whether to return also all hidden sites
	 * @return array Sites
	 */
	public function filter_user_sites( $sites, $user_id, $all ) {

		// Do not change site list when requesting all
		if ( $all ) {
			return $sites;
		}

		// Get network params
		$network_only    = portier_is_network_only();
		$network_protect = portier_is_network_protected();

		// Walk all sites
		foreach ( $sites as $site_id => $details ) {

			// Network protection is actively blocking this site
			// or site protection is active and user is not allowed
			if ( ( $network_protect && ! portier_network_is_user_allowed( $user_id, $site_id ) )
				|| ( ! $network_only && portier_is_site_protected( $site_id ) && ! portier_is_user_allowed( $user_id, $site_id ) )
			) {

				// Remove site from collection
				unset( $sites[ $site_id ] );
			}
		}

		return $sites;
	}

	/**
	 * Modify the admin bar for protected sites
	 *
	 * @since 1.0.0
	 *
	 * @param WP_Admin_Bar $wp_admin_bar
	 */
	public function filter_admin_bar( $wp_admin_bar ) {

		// Hiding 'My Sites'
		if ( portier_network_hide_my_sites() ) {

			// Remove admin bar menu top item
			$wp_admin_bar->remove_menu( 'my-sites' );
		}
	}

	/**
	 * Modify the admin menu for protected sites
	 *
	 * @since 1.0.0
	 */
	public function filter_admin_menu() {

		// Hiding 'My Sites'
		if ( portier_network_hide_my_sites() ) {

			// This only removes the admin menu item, not the page
			remove_submenu_page( 'index.php', 'my-sites.php' );
		}
	}

	/**
	 * Modify the user capabilities by filtering 'user_has_cap'
	 *
	 * Doing so is the only easy way to prevent a user from entering
	 * the My Sites admin page.
	 *
	 * @since 1.0.0
	 *
	 * @param array $allcaps All user caps
	 * @param array $caps Required caps
	 * @param array $args User ID and public function arguments
	 * @return array $allcaps
	 */
	public function user_has_cap( $allcaps, $caps, $args ) {

		// Prevent access to 'My Sites' admin page by blocking user cap
		if ( is_admin()

			// We are on the 'My Sites' page
			&& function_exists( 'get_current_screen' ) && isset( get_current_screen()->id ) && 'my-sites' == get_current_screen()->id

			// Hiding 'My Sites'
			&& portier_network_hide_my_sites()

			// Requesting the 'read' cap
			&& in_array( 'read', $caps )
		) {

			// Disable the read user cap
			$allcaps['read'] = false;
		}

		return $allcaps;
	}
}

endif; // class_exists
