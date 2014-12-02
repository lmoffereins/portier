<?php

/**
 * The Guard Plugin
 *
 * @package Guard
 * @subpackage Main
 */

/**
 * Plugin Name:       Guard
 * Description:       Prevent people from visiting your (multi)site
 * Plugin URI:        https://github.com/lmoffereins/guard
 * Author:            Laurens Offereins
 * Author URI:        https://github.com/lmoffereins
 * Version:           0.2
 * Text Domain:       guard
 * Domain Path:       /languages/
 * GitHub Plugin URI: lmoffereins/guard
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Guard' ) ) :
/**
 * Main Guard Class
 *
 * @since 1.0.0
 */
final class Guard {

	/** Singleton *************************************************************/

	/**
	 * Main Guard Instance
	 *
	 * @since 1.0.0
	 *
	 * @uses Guard::setup_globals() Setup the globals needed
	 * @uses Guard::includes() Include the required files
	 * @uses Guard::setup_actions() Setup the hooks and actions
	 * @see guard()
	 * @return The one true Guard
	 */
	public static function instance() {

		// Store the instance locally to avoid private static replication
		static $instance = null;

		// Only run these methods if they haven't been ran previously
		if ( null === $instance ) {
			$instance = new Guard;
			$instance->setup_globals();
			$instance->includes();
			$instance->setup_actions();
		}

		// Always return the instance
		return $instance;
	}

	/**
	 * A dummy constructor to prevent Guard from being loaded more than once.
	 *
	 * @since 1.0.0
	 *
	 * @see Guard::instance()
	 * @see guard()
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

		$this->version = '0.2';

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

		$this->domain = 'guard';
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
		add_action( 'template_redirect', array( $this, 'site_protect'  ), 1 );
		add_filter( 'login_message',     array( $this, 'login_message' ), 1 );

		// Admin
		add_action( 'admin_init',       array( $this, 'register_settings' ) );
		add_action( 'admin_menu',       array( $this, 'admin_menu'        ) );
		add_action( 'guard_admin_head', array( $this, 'enqueue_scripts'   ) );

		// Plugin links
		add_filter( 'plugin_action_links', array( $this, 'settings_link' ), 10, 2 );

		// Setup extensions
		add_action( 'guard_loaded', 'guard_extend' );

		// Uninstall hook
		register_uninstall_hook( $this->file, array( $this, 'uninstall' ) );

		// Fire plugin loaded hook
		do_action( 'guard_loaded' );
	}

	/** Plugin ****************************************************************/

	/**
	 * Loads the textdomain file for this plugin
	 *
	 * @since 0.1
	 *
	 * @uses apply_filters() Calls 'plugin_locale' with {@link get_locale()} value
	 * @uses load_textdomain() To load the textdomain
	 * @uses load_plugin_textdomain() To load the plugin textdomain
	 */
	public function load_textdomain() {

		// Traditional WordPress plugin locale filter
		$locale        = apply_filters( 'plugin_locale', get_locale(), $this->domain );
		$mofile        = sprintf( '%1$s-%2$s.mo', $this->domain, $locale );

		// Setup paths to current locale file
		$mofile_local  = $this->lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/guard/' . $mofile;

		// Look in global /wp-content/languages/guard folder first
		load_textdomain( $this->domain, $mofile_global );

		// Look in global /wp-content/languages/plugins/ and local plugin languages folder
		load_plugin_textdomain( $this->domain, false, 'guard/languages' );
	}

	/**
	 * Initialize network functions when network activated
	 *
	 * @since 1.0.0
	 *
	 * @uses is_plugin_active_for_network()
	 * @uses guard_network()
	 */
	public function load_for_network() {

		// Load file to use its functions
		require_once( ABSPATH . 'wp-admin/includes/plugin.php' );

		// Bail when plugin is not network activated
		if ( ! is_plugin_active_for_network( $this->basename ) )
			return;

		// Load network file
		require( $this->includes_dir . 'network.php' );

		// Setup network functionality
		$this->network = new Guard_Network;
	}

	/** Protection ************************************************************/

	/**
	 * Redirect users on accessing a page of your site
	 *
	 * @since 0.1
	 *
	 * @uses is_user_logged_in() To check if the user is logged in
	 * @uses guard_is_user_allowed() To check if the user is allowed
	 * @uses do_action() Calls 'guard_site_protect'
	 * @uses auth_redirect() To log the user out and redirect to wp-login.php
	 */
	public function site_protect() {

		// Bail when protection is not active
		if ( ! guard_is_site_protected() )
			return;

		// When user is not logged in or is not allowed
		if ( ! is_user_logged_in() || ! guard_is_user_allowed() ) {

			// Provide hook
			do_action( 'guard_site_protect' );

			// Logout user and redirect to login page
			auth_redirect();
		}
	}

	/**
	 * Appends our custom message to the login messages
	 *
	 * @since 0.1
	 *
	 * @param string $message The current login messages
	 * @return string $message
	 */
	public function login_message( $message ) {

		// When protection is active
		if ( guard_is_site_protected() ) {
			$custom_message = get_option( '_guard_custom_message' );

			// Append message when it's provided
			if ( ! empty( $custom_message ) ) {
				$message .= '<p class="message">'. $custom_message .'<p>';
			}
		}

		return $message;
	}

	/** Admin *****************************************************************/

	/**
	 * Create the plugin admin page menu item
	 *
	 * @since 0.1
	 *
	 * @uses add_options_page() To add the menu to the options menu
	 * @uses add_action() To enable functions hooking into admin page
	 *                     head en footer
	 */
	public function admin_menu() {

		// Setup settings page
		$hook = add_options_page(
			__( 'Guard Settings', 'guard' ),
			__( 'Guard', 'guard' ),
			'manage_options',
			'guard',
			array( $this, 'admin_page' )
		);

		add_action( "admin_head-$hook",   array( $this, 'admin_head'   ) );
		add_action( "admin_footer-$hook", array( $this, 'admin_footer' ) );
	}

	/**
	 * Enqueue script and style in plugin admin page head
	 *
	 * @since 0.1
	 */
	public function admin_head() {
		do_action( 'guard_admin_head' );
	}

	/**
	 * Output plugin admin page footer contents
	 *
	 * @since 0.1
	 */
	public function admin_footer() { 
		do_action( 'guard_admin_footer' );
	}

	/**
	 * Output plugin admin page contents
	 *
	 * @since 0.1
	 *
	 * @uses settings_fields() To output the form validation inputs
	 * @uses do_settings_section() To output all form fields
	 * @uses submit_button() To output the form submit button
	 */
	public function admin_page() { ?>

		<div class="wrap">
			<h2><?php _e( 'Guard', 'guard' ); ?></h2>

			<form method="post" action="options.php">
				<?php settings_fields( 'guard' ); ?>
				<?php do_settings_sections( 'guard' ); ?>
				<?php submit_button(); ?>
			</form>
		</div>

		<?php
	}

	/**
	 * Output admin page scripts and styles
	 * 
	 * @since 1.0.0
	 *
	 * @uses wp_script_is() To check if the script is already registered
	 * @uses wp_style_is() To check if the style is already registered
	 * @uses wp_register_script()
	 * @uses wp_register_style()
	 */
	public function enqueue_scripts() {

		// Register Chosen when not done already
		if ( ! wp_script_is( 'chosen', 'registered' ) ) {
			wp_register_script( 'chosen', plugins_url( 'js/chosen/jquery.chosen.min.js', __FILE__), array( 'jquery' ), '0.9.8' );
		}

		if ( ! wp_style_is( 'chosen', 'registered' ) ) {
			wp_register_style( 'chosen', plugins_url( 'js/chosen/chosen.css', __FILE__ ) );
		}

		// Enqueue Chosen
		wp_enqueue_script( 'chosen' );
		wp_enqueue_style(  'chosen' ); 

		?>

		<script type="text/javascript">
			jQuery(document).ready( function($) {
				$( '.chzn-select' ).chosen();
			});
		</script>

		<style type="text/css">
			.chzn-select,
			.chzn-container,
			textarea {
				float: left;
			}

			.chzn-container-multi .chzn-choices .search-field input {
				height: 25px;
				padding: 3px;
			}

			span.float {
				padding: 4px 6px;
				float: left;
			}
		</style>

		<?php
	}

	/**
	 * Setup the plugin settings
	 *
	 * @since 0.1
	 *
	 * @uses add_settings_section() To create the settings sections
	 * @uses guard_settings()
	 * @uses add_settings_field() To create a setting with it's field
	 * @uses register_setting() To enable the setting being saved to the DB
	 */
	public function register_settings() {

		// Create settings sections
		add_settings_section( 'guard-options-access',     __( 'Access Settings',     'guard' ), 'guard_access_settings_info',     'guard' );
		add_settings_section( 'guard-options-additional', __( 'Additional Settings', 'guard' ), 'guard_additional_settings_info', 'guard' );

		// Loop all settings to register
		foreach ( guard_settings() as $setting => $args ) {

			// Only render field when label and callback are present
			if ( isset( $args['label'] ) && isset( $args['field_cb'] ) ) {
				add_settings_field( $setting, $args['label'], $args['field_cb'], $args['page'], $args['section'] );
			}

			register_setting( $args['page'], $setting, $args['sanitize_cb'] );
		}
	}

	/**
	 * Add a settings link to the plugin actions on plugin.php
	 *
	 * @since 0.1
	 *
	 * @uses add_query_arg() To create the url to the settings page
	 *
	 * @param array $links The current plugin action links
	 * @param string $file The current plugin file
	 * @return array $links All current plugin action links
	 */
	public function settings_link( $links, $file ) {

		// Add settings link for our plugin
		if ( $file == $this->basename ) {
			$links['settings'] = '<a href="' . add_query_arg( 'page', 'guard', 'options-general.php' ) . '">' . __( 'Settings', 'guard' ) . '</a>';
		}

		return $links;
	}

	/**
	 * Clean up when this plugin is deleted
	 *
	 * @since 0.2
	 *
	 * @uses guard_settings()
	 * @uses delete_option()
	 */
	public function uninstall() {

		// Delete all settings
		foreach ( guard_settings() as $option => $args ) {
			delete_option( $option );
		}
	}
}

/**
 * The main public function responsible for returning the one true Guard Instance
 * to functions everywhere.
 *
 * @since 1.0.0
 *
 * @return The one true Guard Instance
 */
function guard() {
	return Guard::instance();
}

// Do the magic
guard();

endif; // class_exists
