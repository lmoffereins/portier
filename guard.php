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
 * Main Plugin Class
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
	 * A dummy constructor to prevent Plugin from being loaded more than once.
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
		require( $this->includes . 'guard-multisite.php' );
	}

	/**
	 * Setup the default hooks and actions
	 *
	 * @since 1.0.0
	 */
	private function setup_actions() {
		add_action( 'plugins_loaded',      'load_textdomain'      );
		add_action( 'admin_init',          'register_settings'    );
		add_action( 'admin_menu',          'admin_menu'           );
		add_action( 'template_redirect',   'site_protect',  1     );
		add_filter( 'plugin_action_links', 'settings_link', 10, 2 );
		add_action( 'login_message',       'login_message', 1     );
		register_uninstall_hook( __FILE__, 'uninstall'            );

		// For WP MS
		if ( is_multisite() ) {
			add_action( 'guard_loaded', 'guard_ms' );
		}

		// Fire plugin loaded hook
		do_action( 'guard_loaded' );
	}

	/** Public Methods ********************************************************/

	/**
	 * Redirect users on accessing a page of your site
	 *
	 * @since 0.1
	 *
	 * @uses is_user_logged_in() To check if the user is logged in
	 * @uses guard_users_is_allowed() To check if the user is allowed
	 * @uses auth_redirect() To log the user out and redirect to wp-login.php
	 */
	public function site_protect() {

		// Only redirect if site protection is activated
		if ( ! get_option( '_guard_site_protect' ) )
			return;

		// Redirect user if not logged in or if not allowed
		if ( ! is_user_logged_in() || ! $this->user_is_allowed() ) {
			do_action( 'guard_site_protect' );
			auth_redirect();
		}
	}

	/**
	 * Returns whether the current user is allowed to enter
	 *
	 * @since 0.1
	 *
	 * @uses apply_filters() To call 'guard_user_is_allowed' for
	 *                        plugins to override the access granted
	 * @uses current_user_can() To check if the current user is admin
	 *
	 * @return boolean The user is allowed
	 */
	public function user_is_allowed() {
		global $current_user;

		// Get allowed users array
		$allowed = (array) get_option( '_guard_allowed_users', array() );

		// Filter if user is in it
		$allow = apply_filters( 'guard_user_is_allowed', in_array( $current_user->ID, $allowed ) );

		// Admins are ALLWAYS allowed
		return current_user_can( 'administrator' ) || $allow;
	}

	/**
	 * Create the plugin admin page menu item
	 *
	 * @since 0.1
	 *
	 * @uses add_options_page() To add the menu to the options pane
	 * @uses add_action() To enable functions hooking into admin page
	 *                     head en footer
	 */
	public function admin_menu() {
		$hook = add_options_page(
			__( 'Guard Settings', 'guard' ),
			'Guard',
			'manage_options',
			'guard',
			array( $this, 'admin_page' )
		);

		add_action( "admin_head-$hook",   array( $this, 'admin_head'   ) );
		add_action( "admin_footer-$hook", array( $this, 'admin_footer' ) );
	}

	/**
	 * Output plugin admin page contents
	 *
	 * @since 0.1
	 *
	 * @uses screen_icon() To output the screen icon
	 * @uses settings_fields() To output the form validation inputs
	 * @uses do_settings_section() To output all form fields
	 * @uses submit_button() To output the form submit button
	 */
	public function admin_page() {
		?>
			<div class="wrap">
				<?php screen_icon(); ?>
				<h2>Guard</h2>

				<form method="post" action="options.php">
					<?php settings_fields( 'guard' ); ?>
					<?php do_settings_sections( 'guard' ); ?>
					<?php submit_button(); ?>
				</form>

			</div>
		<?php
	}

	/**
	 * Enqueue script and style in plugin admin page head
	 *
	 * @since 0.1
	 *
	 * @uses wp_script_is() To check if the script is already registered
	 * @uses wp_style_is() To check if the style is already registered
	 */
	public function admin_head() {
		if ( ! wp_script_is( 'chosen', 'registered' ) )
			wp_register_script( 'chosen', plugins_url( 'js/chosen/jquery.chosen.min.js', __FILE__), array( 'jquery' ), '0.9.8' );

		if ( ! wp_style_is( 'chosen', 'registered' ) )
			wp_register_style( 'chosen', plugins_url( 'js/chosen/chosen.css', __FILE__ ) );

		wp_enqueue_script( 'chosen' );
		wp_enqueue_style(  'chosen' );

		do_action( 'guard_admin_head' );
	}

	/**
	 * Output plugin admin page footer contents
	 *
	 * Some restyling of chosen elements in the admin page and a
	 * float class for description spans to display well with inputs
	 * and a lines to initialize the chosen script.
	 *
	 * @since 0.1
	 */
	public function admin_footer() {
		?>
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

	<script type="text/javascript">
		jQuery( '.chzn-select' ).chosen();
	</script>
		<?php
			do_action( 'guard_admin_footer' );
	}

	/**
	 * Return the plugin settings
	 *
	 * @since 0.x
	 *
	 * @uses apply_filters() Calls 'guard_settings' hook on the settings
	 *
	 * @return array $settings {
	 *  @type array Setting ID {
	 *   @type string $label Setting label
	 *   @type string $field_cb Setting input field callback
	 *   @type string $section Setting section name
	 *   @type string $page Setting page name
	 *   @type string $sanitize_cb Setting sanitization callback
	 *  }
	 * }
	 */
	public function settings() {
		$settings = array(

			/** Access Settings **********************************************/

			// Site protect switch
			'_guard_site_protect', array(
				'label'       => __( 'Protect my site', 'guard' ),
				'field_cb'    => array( $this, 'setting_protect_site' ),
				'section'     => 'guard-options-access',
				'page'        => 'guard',
				'sanitize_cb' => 'intval'
			),

			// Allowed users
			'_guard_allowed_users', array(
				'label'       => __( 'Allowed users', 'guard' ),
				'field_cb'    => array( $this, 'setting_allow_users' ),
				'section'     => 'guard-options-access',
				'page'        => 'guard',
				'sanitize_cb' => array( $this, 'setting_allow_users_sanitize' )
			),

			/** Additional Settings ******************************************/

			// Custom login message
			'_guard_custom_message', array(
				'label'       => __( 'Custom login message', 'guard' ),
				'field_cb'    => array( $this, 'setting_custom_message' ),
				'section'     => 'guard-options-additional',
				'page'        => 'guard',
				'sanitize_cb' => array( $this, 'setting_custom_message_sanitize' )
			)
		);

		return apply_filters( 'guard_settings', $settings );
	}

	/**
	 * Setup the plugin settings
	 *
	 * @since 0.1
	 *
	 * @uses add_settings_section() To create the settings sections
	 * @uses Guard::settings()
	 * @uses add_settings_field() To create a setting with it's field
	 * @uses register_setting() To enable the setting being saved to the DB
	 */
	public function register_settings() {
		add_settings_section( 'guard-options-access',     __( 'Access Settings',     'guard' ), array( $this, 'access_settings_info'     ), 'guard' );
		add_settings_section( 'guard-options-additional', __( 'Additional Settings', 'guard' ), array( $this, 'additional_settings_info' ), 'guard' );

		// Loop all settings to register
		foreach ( $this->settings() as $setting => $args ) {
			add_settings_field( $setting, $args['label'], $args['field_cb'], $args['page'], $args['section'] );
			register_setting( $args['page'], $setting, $args['sanitize_cb'] );
		}
	}

	/**
	 * Output access settings section information header
	 *
	 * @since 0.1
	 */
	public function access_settings_info() {
		?>
			<p>
				<?php _e( 'Here you enable the Guard plugin. By checking the <em>Protect my site</em> input, this site will only be accessible for admins and allowed users, specified by you in the select option below. No one else shall pass!', 'guard' ); ?>
			</p>
		<?php
	}

	/**
	 * Output additional settings section information header
	 *
	 * @since 0.1
	 */
	public function additional_settings_info() {
		?>
			<p>
				<?php _e( 'Below you can set additional Guard options.', 'guard' ); ?>
			</p>
		<?php
	}

	/**
	 * Output the enable site protection input field
	 *
	 * @since 0.1
	 */
	public function setting_protect_site() {
		?>
			<p>
				<label>
					<input type="checkbox" name="_guard_site_protect" <?php checked( get_option( '_guard_site_protect' ), 1 ) ?> value="1" />
					<span class="description"><?php _e( 'Enable site protection.', 'guard' ); ?></span>
				</label>
			</p>
		<?php
	}

	/**
	 * Output the allowed users input field
	 *
	 * @since 0.1
	 *
	 * @uses get_users() To get all users of the site
	 */
	public function setting_allow_users() {
		$users = get_option( '_guard_allowed_users' );

		if ( ! is_array( $users ) )
			$users = array();

		?>
			<select id="_guard_allowed_users" class="chzn-select" name="_guard_allowed_users[]" multiple style="width:25em;" data-placeholder="<?php _e( 'Select a user', 'guard' ); ?>">
			<?php foreach ( get_users() as $user ) : ?>
				<option value="<?php echo $user->ID; ?>" <?php selected( in_array( $user->ID, $users ) ); ?>><?php echo $user->user_login; ?></option>
			<?php endforeach; ?>

			</select>
			<span class="description float"><?php _e( 'Select which users you want to have access.', 'guard' ); ?></span>
		<?php
	}

	/**
	 * Output the custom message input field
	 *
	 * @since 0.1
	 */
	public function setting_custom_message() {
		?>
			<textarea name="_guard_custom_message" style="width:25em;" rows="3"><?php echo esc_textarea( get_option( '_guard_custom_message' ) ); ?></textarea>
			<span class="description float"><?php printf( __( 'Serve site guests a nice heads up on the login page. Leave empty if not applicable. This message will only be shown if <strong>Protect my site</strong> is activated.<br/>Allowed HTML tags %s, %s and %s.', 'guard' ), '&#60;a&#62;', '&#60;em&#62;', '&#60;strong&#62;' ); ?></span>
		<?php
	}

	/**
	 * Sanitize the allowed users input field
	 *
	 * @since 0.1
	 *
	 * @param string $input The submitted value
	 * @return array $input
	 */
	public function setting_allow_users_sanitize( $input ) {
		if ( empty( $input ) )
			return array();

		return array_unique( array_map( 'intval', (array) $input ) );
	}

	/**
	 * Sanitize the custom message input field
	 *
	 * @since 0.1
	 *
	 * @uses wp_kses() To filter out all non allowed HTML tags
	 *
	 * @param string $input The submitted value
	 * @return string $input
	 */
	public function setting_custom_message_sanitize( $input ) {
		return wp_unslash( wp_kses( $input, array(
			'a'      => array( 'href' ),
			'em'     => array(),
			'strong' => array()
		) ) );
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

		// Only add settings link for our plugin
		if ( $file == $this->basename ) {
			$links['settings'] = '<a href="' . add_query_arg( 'page', 'guard', 'options-general.php' ) . '">' . __( 'Settings' ) . '</a>';
		}

		return $links;
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

		// Only display message if site protection is activated
		if ( ! get_option( '_guard_site_protect' ) )
			return $message;

		$custom_message = get_option( '_guard_custom_message' );

		// Do not add anything if no message is set
		if ( empty( $custom_message ) )
			return $message;

		$message .= '<p class="message">'. $custom_message .'<p>';

		return $message;
	}

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
	 * Clean up when this plugin is deleted
	 *
	 * @since 0.2
	 *
	 * @uses Guard::settings()
	 * @uses delete_option()
	 */
	public function uninstall() {

		// Delete all settings
		foreach ( $this->settings() as $option => $args ) {
			delete_option( $option );
		}
	}
}

/**
 * The main public function responsible for returning the one true Plugin Instance
 * to functions everywhere.
 *
 * @since 1.0.0
 *
 * @return The one true Plugin Instance
 */
function guard() {
	return Guard::instance();
}

// Do the magic
guard();

endif; // class_exists
