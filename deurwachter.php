<?php

/**
 * The Deurwachter Plugin
 *
 * @package Deurwachter
 * @subpackage Main
 */

/**
 * Plugin Name:       Deurwachter (former Guard)
 * Description:       Restrict access to your (multi)site
 * Plugin URI:        https://github.com/lmoffereins/deurwachter
 * Author:            Laurens Offereins
 * Author URI:        https://github.com/lmoffereins
 * Version:           1.1.0
 * Text Domain:       deurwachter
 * Domain Path:       /languages/
 * GitHub Plugin URI: lmoffereins/deurwachter
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Deurwachter' ) ) :
/**
 * Main Deurwachter Class
 *
 * @since 1.0.0
 */
final class Deurwachter {

	/** Singleton *************************************************************/

	/**
	 * Main Deurwachter Instance
	 *
	 * @since 1.0.0
	 *
	 * @see deurwachter()
	 * @return The one true Deurwachter
	 */
	public static function instance() {

		// Store the instance locally to avoid private static replication
		static $instance = null;

		// Only run these methods if they haven't been ran previously
		if ( null === $instance ) {
			$instance = new Deurwachter;
			$instance->setup_globals();
			$instance->includes();
			$instance->setup_actions();
		}

		// Always return the instance
		return $instance;
	}

	/**
	 * A dummy constructor to prevent Deurwachter from being loaded more than once.
	 *
	 * @since 1.0.0
	 *
	 * @see Deurwachter::instance()
	 * @see deurwachter()
	 */
	private function __construct() { /* Do nothing here */ }

	/** Private Methods *******************************************************/

	/**
	 * Set default class globals
	 *
	 * @since 1.0.0
	 */
	private function setup_globals() {

		/** Versions **********************************************************/

		$this->version    = '1.1.0';
		$this->db_version = 110;

		/** Paths *************************************************************/

		// Setup some base path and URL information
		$this->file         = __FILE__;
		$this->basename     = plugin_basename( $this->file );
		$this->plugin_dir   = plugin_dir_path( $this->file );
		$this->plugin_url   = plugin_dir_url ( $this->file );

		// Includes
		$this->includes_dir = trailingslashit( $this->plugin_dir . 'includes'  );
		$this->includes_url = trailingslashit( $this->plugin_url . 'includes'  );

		// Languages
		$this->lang_dir     = trailingslashit( $this->plugin_dir . 'languages' );

		/** Misc **************************************************************/

		$this->extend       = new stdClass();
		$this->domain       = 'deurwachter';
	}

	/**
	 * Include required files
	 *
	 * @since 1.0.0
	 */
	private function includes() {
		require( $this->includes_dir . 'extend.php'    );
		require( $this->includes_dir . 'functions.php' );

		// Admin
		if ( is_admin() ) {
			require( $this->includes_dir . 'settings.php'  );
		}
	}

	/**
	 * Setup the default hooks and actions
	 *
	 * @since 1.0.0
	 */
	private function setup_actions() {

		// Plugin
		add_action( 'plugins_loaded', array( $this, 'load_textdomain'  ) );
		add_action( 'plugins_loaded', array( $this, 'load_for_network' ) );

		// Protection
		add_action( 'template_redirect', array( $this, 'site_protect'   ), 1 );
		add_filter( 'login_message',     array( $this, 'login_message'  ), 1 );
		add_action( 'admin_bar_menu',    array( $this, 'admin_bar_menu' )    );


		// Admin
		add_action( 'admin_init',             array( $this, 'register_settings'     ) );
		add_action( 'admin_menu',             array( $this, 'admin_menu'            ) );
		add_action( 'deurwachter_admin_head', array( $this, 'enqueue_admin_scripts' ) );

		// Plugin links
		add_filter( 'plugin_action_links', array( $this, 'settings_link' ), 10, 2 );

		// Setup extensions
		add_action( 'bp_loaded', 'deurwachter_setup_buddypress' );

		// Fire plugin loaded hook
		do_action( 'deurwachter_loaded' );
	}

	/** Plugin ****************************************************************/

	/**
	 * Loads the textdomain file for this plugin
	 *
	 * @since 1.0.0
	 *
	 * @uses apply_filters() Calls 'plugin_locale' with {@link get_locale()} value
	 */
	public function load_textdomain() {

		// Traditional WordPress plugin locale filter
		$locale        = apply_filters( 'plugin_locale', get_locale(), $this->domain );
		$mofile        = sprintf( '%1$s-%2$s.mo', $this->domain, $locale );

		// Setup paths to current locale file
		$mofile_local  = $this->lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/deurwachter/' . $mofile;

		// Look in global /wp-content/languages/deurwachter folder first
		load_textdomain( $this->domain, $mofile_global );

		// Look in global /wp-content/languages/plugins/ and local plugin languages folder
		load_plugin_textdomain( $this->domain, false, 'deurwachter/languages' );
	}

	/**
	 * Initialize network functions when network activated
	 *
	 * @since 1.0.0
	 */
	public function load_for_network() {

		// Load file to use plugin functions
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		// Bail when plugin is not network activated
		if ( ! is_plugin_active_for_network( $this->basename ) )
			return;

		// Load network file
		require( $this->includes_dir . 'network.php' );

		// Setup network functionality
		$this->network = new Deurwachter_Network;
	}

	/** Protection ************************************************************/

	/**
	 * Redirect users on accessing a page of your site
	 *
	 * @since 1.0.0
	 * @since 2.0.0 Handle feed requests
	 *
	 * @uses do_action() Calls 'deurwachter_site_protect'
	 */
	public function site_protect() {

		// Bail when protection is not active
		if ( ! deurwachter_is_site_protected() || is_404() )
			return;

		// Handle feed requests
		if ( is_feed() ) {
			global $wp_query;

			// Block with 404 status
			$wp_query->is_feed = false;
			$wp_query->set_404();
			status_header( 404 );
		}

		// When user is not logged in or is not allowed
		if ( ! is_user_logged_in() || ! deurwachter_is_user_allowed() ) {

			// Provide hook
			do_action( 'deurwachter_site_protect' );

			// Logout user and redirect to login page
			auth_redirect();
		}
	}

	/**
	 * Append our custom login message to the login messages
	 *
	 * @since 1.0.0
	 * 
	 * @param string $message The current login messages
	 * @return string $message
	 */
	public function login_message( $message ) {

		// When protection is active
		if ( deurwachter_is_site_protected() ) {
			$login_message = get_option( '_deurwachter_login_message' );

			// Append message when it's provided
			if ( ! empty( $login_message ) ) {
				$message .= '<p class="message">' . $login_message . '<p>';
			}
		}

		return $message;
	}

	/**
	 * Add the plugin's admin bar menu item
	 * 
	 * @since 1.0.0
	 * 
	 * @param WP_Admin_Bar $wp_admin_bar
	 */
	public function admin_bar_menu( $wp_admin_bar ) {

		// Not in the network admin and when the user is capable
		if ( ! is_network_admin() && current_user_can( 'manage_options' ) ) {

			// When protection is active
			$active = deurwachter_is_site_protected();
			$title1 = $active ? esc_html__( 'Site protection is active', 'deurwachter' ) : esc_html__( 'Site protection is not active', 'deurwachter' );
			$title2 = $active ? deurwachter_get_protection_details() : $title1;
			$class  = $active ? 'hover site-protected' : '';

			// Add site-is-protected menu notification
			$wp_admin_bar->add_menu( array(
				'id'        => 'deurwachter',
				'parent'    => 'top-secondary',
				'title'     => '<span class="ab-icon"></span><span class="screen-reader-text">' . $title1 . '</span>',
				'href'      => add_query_arg( 'page', 'deurwachter', admin_url( 'options-general.php' ) ),
				'meta'      => array(
					'class' => $class,
					'title' => $title2,
				),
			) );

			// Hook admin bar styles. After core's footer scripts
			add_action( 'wp_footer',    array( $this, 'admin_bar_scripts' ), 21 );
			add_action( 'admin_footer', array( $this, 'admin_bar_scripts' ), 21 );
		}
	}

	/**
	 * Output custom scripts
	 *
	 * @since 1.0.0
	 *
	 * @uses is_admin_bar_showing()
	 */
	public function admin_bar_scripts() {

		// For the admin bar
		if ( ! is_admin_bar_showing() )
			return; ?>

		<style type="text/css">
			#wpadminbar #wp-admin-bar-deurwachter > .ab-item {
				padding: 0 9px 0 7px;
			}

			#wpadminbar #wp-admin-bar-deurwachter > .ab-item .ab-icon {
				width: 18px;
				height: 20px;
				margin-right: 0;
			}

			#wpadminbar #wp-admin-bar-deurwachter > .ab-item .ab-icon:before {
				content: '\f334'; /* dashicons-shield-alt */
				top: 2px;
				opacity: 0.4;
			}

				#wpadminbar #wp-admin-bar-deurwachter.site-protected > .ab-item .ab-icon:before {
					opacity: 1;
				}

			/* Non-unique specific selector (!) */
			#wp-pointer-0.wp-pointer-top .wp-pointer-content h3:before {
				content: '\f334'; /* dashicons-shield-alt */
			}

			/* Non-unique specific selector (!) */
			#wp-pointer-0.wp-pointer-top .wp-pointer-arrow {
				left: auto;
				right: 27px;
			}
		</style>

		<?php
	}

	/** Admin *****************************************************************/

	/**
	 * Create the plugin admin page menu item
	 *
	 * @since 1.0.0
	 */
	public function admin_menu() {

		// Setup settings page
		$hook = add_options_page(
			esc_html__( 'Deurwachter Settings', 'deurwachter' ),
			esc_html__( 'Deurwachter', 'deurwachter' ),
			'manage_options',
			'deurwachter',
			array( $this, 'admin_page' )
		);

		add_action( "admin_head-$hook",   array( $this, 'admin_head'   ) );
		add_action( "admin_footer-$hook", array( $this, 'admin_footer' ) );
	}

	/**
	 * Enqueue script and style in plugin admin page head
	 *
	 * @since 1.0.0
	 */
	public function admin_head() {
		do_action( 'deurwachter_admin_head' );
	}

	/**
	 * Output plugin admin page footer contents
	 *
	 * @since 1.0.0
	 */
	public function admin_footer() { 
		do_action( 'deurwachter_admin_footer' );
	}

	/**
	 * Output plugin admin page contents
	 *
	 * @since 1.0.0
	 */
	public function admin_page() { ?>

		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Deurwachter', 'deurwachter' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'deurwachter' ); ?>
				<?php do_settings_sections( 'deurwachter' ); ?>
				<?php submit_button(); ?>
			</form>
		</div>

		<?php
	}

	/**
	 * Output admin page scripts and styles
	 * 
	 * @since 1.0.0
	 */
	public function enqueue_admin_scripts() {

		// Register Chosen when not done already
		if ( ! wp_script_is( 'chosen', 'registered' ) ) {
			wp_register_script( 'chosen', $this->includes_url . 'assets/js/chosen/chosen.jquery.min.js', array( 'jquery' ), '1.2.0' );
		}
		wp_enqueue_script( 'chosen' );

		if ( ! wp_style_is( 'chosen', 'registered' ) ) {
			wp_register_style( 'chosen', $this->includes_url . 'assets/js/chosen/chosen.min.css', false, '1.2.0' );
		}
		wp_enqueue_style( 'chosen' );

		// WP pointer
		wp_enqueue_script( 'wp-pointer' );
		wp_enqueue_style ( 'wp-pointer' ); 

		// Plugin admin
		wp_register_script( 'deurwachter-admin', $this->includes_url . 'assets/js/deurwachter-admin.js', array( 'jquery', 'chosen', 'wp-pointer' ), $this->version );
		wp_enqueue_script ( 'deurwachter-admin' );
		wp_localize_script( 'deurwachter-admin', 'deurwachterAdminL10n', array(
			'pointerContent' => sprintf( '<h3>%s</h3><p>%s</p>',
				esc_html__( 'Site Protection', 'deurwachter' ),
				esc_html__( 'The shield icon will show the current state of the protection of this site. When site protection is active, it is colored accordingly.', 'deurwachter' )
			),
			'settings' => array(
				'showPointer' => is_admin_bar_showing() && current_user_can( 'manage_options' ) && ! in_array( 'deurwachter_protection', explode( ',', get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) ) ),
			)
		) ); ?>

		<style type="text/css">
			.chzn-container-multi .chzn-choices .search-field input {
				height: 25px !important;
			}

			.form-table div + label,
			.form-table textarea + label {
				display: block;
			}
		</style>

		<?php
	}

	/**
	 * Setup the plugin settings
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {

		// Create settings sections
		add_settings_section( 'deurwachter-options-access', esc_html__( 'Access Settings', 'deurwachter' ), 'deurwachter_access_settings_info', 'deurwachter' );

		// Loop all settings to register
		foreach ( deurwachter_settings() as $setting => $args ) {

			// Only render field when label and callback are present
			if ( isset( $args['label'] ) && isset( $args['callback'] ) ) {
				add_settings_field( $setting, $args['label'], $args['callback'], $args['page'], $args['section'] );
			}

			register_setting( $args['page'], $setting, $args['sanitize_callback'] );
		}
	}

	/**
	 * Add a settings link to the plugin actions on plugin.php
	 *
	 * @since 1.0.0
	 *
	 * @param array $links The current plugin action links
	 * @param string $file The current plugin file
	 * @return array $links All current plugin action links
	 */
	public function settings_link( $links, $file ) {

		// Add settings link for our plugin
		if ( $file == $this->basename ) {
			$links['settings'] = '<a href="' . add_query_arg( 'page', 'deurwachter', 'options-general.php' ) . '">' . esc_html__( 'Settings', 'deurwachter' ) . '</a>';
		}

		return $links;
	}
}

/**
 * The main public function responsible for returning the one true Deurwachter instance
 * to functions everywhere.
 *
 * @since 1.0.0
 *
 * @return The one true Deurwachter instance
 */
function deurwachter() {
	return Deurwachter::instance();
}

// Do the magic
deurwachter();

endif; // class_exists
