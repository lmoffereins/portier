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

	}

	/**
	 * Setup the default hooks and actions
	 *
	 * @since 1.0.0
	 */
	private function setup_actions() {

		/**
		 * Setup all plugin actions and filters
		 */
		add_action( 'plugins_loaded',      'load_textdomain'      );
		add_action( 'admin_init',          'register_settings'    );
		add_action( 'admin_menu',          'admin_menu'           );
		add_action( 'template_redirect',   'site_protect',  1     );
		add_filter( 'plugin_action_links', 'settings_link', 10, 2 );
		add_action( 'login_message',       'login_message', 1     );
		register_uninstall_hook( __FILE__, 'uninstall'            );

		/**
		 * Setup Multisite actions and filters
		 */
		if ( is_multisite() ) {
			add_action( 'plugins_loaded',      'network_only'                 );
			add_action( 'admin_init',          'register_network_settings'    );
			add_action( 'network_admin_menu',  'network_admin_menu'           );
			add_action( 'template_redirect',   'network_protect',       0     );
			add_action( 'guard_site_protect',  'network_redirect'             );
			add_action( 'get_blogs_of_user',   'network_blogs_of_user', 10, 3 );
			add_action( 'admin_bar_menu',      'network_admin_bar',     99    );
			add_action( 'admin_menu',          'network_admin_menus',   99    );
			add_filter( 'user_has_cap',        'network_user_has_cap',  10, 3 );
			register_uninstall_hook( __FILE__, 'network_uninstall'            );
		}
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
		foreach ( $this->settings() as $option => $args )
			delete_option( $option );
	}

	/** Multisite ****************************************************/

	/**
	 * Ensure Guard is only used for the network
	 *
	 * @since 0.2
	 *
	 * @uses remove_action()
	 * @uses remove_filter()
	 */
	public function network_only() {
		if ( ! get_site_option( '_guard_network_only' ) )
			return;

		// Unset all Guard single site actions and filters
		remove_action( 'admin_init',        array( $this, 'register_settings' )    );
		remove_action( 'admin_menu',        array( $this, 'admin_menu'        )    );
		remove_action( 'template_redirect', array( $this, 'site_protect'      ), 1 );
		remove_action( 'login_message',     array( $this, 'login_message'     ), 1 );
	}

	/**
	 * Redirect user if network is protected
	 *
	 * @since 0.2
	 *
	 * @uses is_user_logged_in() To check if the user is logged in
	 * @uses guard_network_user_is_allowed() To check if the network user is allowed
	 * @uses auth_redirect() To log the user out and redirect to wp-login.php
	 */
	public function network_protect() {

		// Only redirect if network protection is activated
		if ( ! get_site_option( '_guard_network_protect' ) )
			return;

		// Redirect user if not logged in or if not allowed
		if ( ! is_user_logged_in() || ! $this->network_user_is_allowed() )
			auth_redirect();
	}

	/**
	 * Returns whether the current network user is allowed to enter
	 *
	 * @since 0.2
	 *
	 * @uses apply_filters() Calls 'guard_network_user_is_allowed' hook
	 *                        for plugins to override the access granted
	 * @uses is_super_admin() To check if the current user is super admin
	 *
	 * @return boolean The user is allowed
	 */
	public function network_user_is_allowed() {
		global $current_user;

		// Get allowed users array
		$allowed = (array) get_site_option( '_guard_network_allowed_users', array() );

		// Filter if user is in it
		$allow = apply_filters( 'guard_network_user_is_allowed', in_array( $current_user->ID, $allowed ) );

		// Super admins are ALLWAYS allowed
		return is_super_admin( $current_user->ID ) || $allow;
	}

	/**
	 * Redirect users to network home site if sub site is protected
	 *
	 * @since 0.2
	 *
	 * @uses get_site_option()
	 * @uses network_home_url()
	 */
	public function network_redirect() {

		// Only alter redirection
		if ( ! get_site_option( '_guard_network_redirect' ) )
			return;

		// Redirect user to network home site
		wp_redirect( network_home_url() );
		exit;
	}

	/**
	 * Remove user blogs that are not allowed for given user
	 *
	 * @since 0.2
	 *
	 * @uses switch_to_blog()
	 * @uses guard_user_is_allowed()
	 * @uses restore_current_blog()
	 *
	 * @param array $blogs Blogs of user
	 * @param int $user_id User ID
	 * @param boolean $all Whether to return also all hidden blogs
	 * @return array $blogs
	 */
	public function network_blogs_of_user( $blogs, $user_id, $all ) {

		// All blogs requested
		if ( $all )
			return $blogs;

		foreach ( $blogs as $blog_id => $details ) {
			switch_to_blog( $blog_id );

			if ( get_option( '_guard_site_protect' ) ) {
				if ( ! guard_user_is_allowed() ) {
					unset( $blogs[$blog_id] );
				}
			}

			restore_current_blog();
		}

		return $blogs;
	}

	/**
	 * Modify the admin bar for protected sites
	 *
	 * @since 0.2
	 *
	 * @uses guard_network_hide_my_sites()
	 * @uses WP_Admin_Bar::remove_menu()
	 *
	 * @param WP_Admin_Bar $wp_admin_bar
	 */
	public function network_admin_bar( $wp_admin_bar ) {
		if ( $this->network_hide_my_sites() ) {
			$wp_admin_bar->remove_menu( 'my-sites' );
		}
	}

	/**
	 * Modify the admin menu for protected sites
	 *
	 * @since 0.2
	 *
	 * @uses guard_network_hide_my_sites()
	 * @uses remove_submenu_page()
	 */
	public function network_admin_menus() {

		// Only removes menu item, not admin page itself
		if ( $this->network_hide_my_sites() ) {
			remove_submenu_page( 'index.php', 'my-sites.php' );
		}
	}

	/**
	 * Modify the user capabilities by filtering 'user_has_cap'
	 *
	 * @since 0.x
	 *
	 * @uses guard_network_hide_my_sites()
	 *
	 * @param array $allcaps All user caps
	 * @param array $caps Required caps
	 * @param array $args User ID and public function arguments
	 * @return array $allcaps
	 */
	public function network_user_has_cap( $allcaps, $caps, $args ) {

		// Prevent access to "My Sites" admin page by blocking user cap
		if (   is_admin()
			&& function_exists( 'get_current_screen' )
			&& is_object( get_current_screen() )
			&& 'my-sites' == get_current_screen()->id
			&& $this->network_hide_my_sites()
			&& in_array( 'read', $caps )
		) {
			$allcaps['read'] = false;
		}

		return $allcaps;
	}

	/**
	 * Return whether to hide "My Sites" page for the current user
	 *
	 * @since 0.2
	 *
	 * @uses get_site_option()
	 * @uses get_current_user_id()
	 * @uses is_super_admin()
	 * @uses get_blogs_of_user()
	 * @uses get_current_user_id()
	 *
	 * @return boolean Hide "My Sites" page
	 */
	public function network_hide_my_sites() {
		if ( ! get_site_option( '_guard_network_hide_my_sites' ) )
			return false;

		$user_id = get_current_user_id();
		if ( is_super_admin( $user_id ) )
			return false;

		$blogs = get_blogs_of_user( $user_id );

		return apply_filters( 'guard_network_hide_my_sites', 1 == count( $blogs ) );
	}

	/**
	 * Create the plugin network admin page menu item
	 *
	 * @since 0.2
	 *
	 * @uses add_options_page() To add the menu to the options pane
	 * @uses add_action() To enable functions hooking into admin page
	 *                     head en footer
	 */
	public function network_admin_menu() {
		$hook = add_submenu_page(
			'settings.php',
			__( 'Guard Network Settings', 'guard' ),
			__( 'Guard Network',          'guard' ),
			'manage_network',
			'guard_network',
			array( $this, 'network_page' )
		);

		add_action( "admin_head-$hook",   array( $this, 'admin_head'   ) );
		add_action( "admin_footer-$hook", array( $this, 'admin_footer' ) );
	}

	/**
	 * Output plugin network admin page contents
	 *
	 * @since 0.2
	 *
	 * @uses screen_icon() To output the screen icon
	 * @uses settings_fields() To output the form validation inputs
	 * @uses do_settings_section() To output all form fields
	 * @uses submit_button() To output the form submit button
	 * @uses guard_network_page_sites()
	 * @uses do_action() Calls 'guard_network_page' with the tab
	 */
	public function network_page() {

		// Fetch tab
		$tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'main';

		?>
			<div class="wrap">
				<?php screen_icon( 'options-general' ); ?>
				<h2><?php _e( 'Guard Network Settings', 'guard' ); ?></h2>

				<?php switch ( $tab ) :

					// Main settings page
					case 'main' :
					?>
				<form method="post" action="<?php echo network_admin_url( 'edit.php?action=guard_network' ); ?>">
					<?php settings_fields( 'guard_network' ); ?>
					<?php do_settings_sections( 'guard_network' ); ?>
					<?php submit_button(); ?>
				</form>
					<?php break;

					// Sites settings page
					case 'sites' :
						$this->network_page_sites();
						break;

					// Hookable settings page
					default :
						do_action( 'guard_network_page', $tab );
						break;

				endswitch; ?>
			</div>
		<?php
	}

	/**
	 * Return the plugin network settings
	 *
	 * @since 0.x
	 *
	 * @uses apply_filters() Calls 'guard_network_settings' hook on the settings
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
	public function network_settings() {
		$settings = array(

			/** Main Settings ************************************************/

			// Network only
			'_guard_network_only' => array(
				'label'       => __( 'Network only', 'guard' ),
				'field_cb'    => array( $this, 'network_setting_network_only' ),
				'section'     => 'guard-options-main',
				'page'        => 'guard_network',
				'sanitize_cb' => 'intval'
			),

			// Network redirect
			'_guard_network_redirect' => array(
				'label'       => __( 'Redirect to main site', 'guard' ),
				'field_cb'    => array( $this, 'network_setting_network_redirect' ),
				'section'     => 'guard-options-main',
				'page'        => 'guard_network',
				'sanitize_cb' => 'intval'
			),

			// Hide "My Sites"
			'_guard_network_hide_my_sites' => array(
				'label'       => __( 'Hide "My Sites"', 'guard' ),
				'field_cb'    => array( $this, 'network_setting_network_hide_my_sites' ),
				'section'     => 'guard-options-main',
				'page'        => 'guard_network',
				'sanitize_cb' => 'intval'
			),

			/** Access Settings **********************************************/

			// Network protect switch
			'_guard_network_protect' => array(
				'label'       => __( 'Protect this network', 'guard' ),
				'field_cb'    => array( $this, 'network_setting_network_protect' ),
				'section'     => 'guard-options-access',
				'page'        => 'guard_network',
				'sanitize_cb' => 'intval'
			),

			// Allowed network users
			'_guard_network_allowed_users' => array(
				'label'       => __( 'Allowed network users', 'guard' ),
				'field_cb'    => array( $this, 'network_setting_allow_users' ),
				'section'     => 'guard-options-access',
				'page'        => 'guard_network',
				'sanitize_cb' => array( $this, 'setting_allow_users_sanitize' )
			),

			/** Additional Settings ******************************************/

			// Custom network login message
			'_guard_network_custom_message' => array(
				'label'       => __( 'Custom login message', 'guard' ),
				'field_cb'    => array( $this, 'network_setting_custom_message' ),
				'section'     => 'guard-options-additional',
				'page'        => 'guard_network',
			    'sanitize_cb' => array( $this, 'setting_custom_message_sanitize' )
			),

		);

		return apply_filters( 'guard_network_settings', $settings );
	}

	/**
	 * Setup the plugin network settings
	 *
	 * @since 0.2
	 *
	 * @uses add_settings_section() To create the settings sections
	 * @uses add_settings_field() To create a setting with it's field
	 * @uses register_setting() To enable the setting being saved to the DB
	 */
	public function register_network_settings() {
		add_settings_section( 'guard-options-main',       __( 'Network Main Settings',       'guard' ), array( $this, 'network_main_settings_info'       ), 'guard_network' );
		add_settings_section( 'guard-options-access',     __( 'Network Access Settings',     'guard' ), array( $this, 'network_access_settings_info'     ), 'guard_network' );
		add_settings_section( 'guard-options-additional', __( 'Additional Network Settings', 'guard' ), array( $this, 'network_additional_settings_info' ), 'guard_network' );

		// Loop all network settings to register
		foreach ( $this->network_settings() as $setting => $args ) {
			add_settings_field( $setting, $args['label'], $args['field_cb'], $args['page'], $args['section'] );
		}

		/**
		 * There's no valid Network Settings API available ìn WP so we'll have to
		 * do the sanitization and storing manually through wp-admin/network/edit.php
		 *
		 * @link http://core.trac.wordpress.org/ticket/15691
		 */
		add_action( 'network_admin_edit_guard_network',       array( $this, 'network_settings_api'       ) );
		add_action( 'network_admin_edit_guard_network_sites', array( $this, 'network_sites_settings_api' ) );
		add_action( 'network_admin_notices',                  array( $this, 'network_admin_notice'       ) );
	}

	/**
	 * Output network main settings section information header
	 *
	 * @since 0.x
	 */
	public function network_main_settings_info() {
		?>
			<p>
				<?php _e( 'Here you activate the main network functionality of Guard. For activating the network protection, see the Network Access Settings.', 'guard' ); ?>
			</p>
		<?php
	}

	/**
	 * Output network access settings section information header
	 *
	 * @since 0.2
	 */
	public function network_access_settings_info() {
		?>
			<p>
				<?php _e( 'Here you activate your network protection. By checking the <em>Protect this network</em> input, this network will only be accessible for admins and allowed users, specified by you in the select option below. No one else shall pass!', 'guard' ); ?>
			</p>
		<?php
	}

	/**
	 * Output network additional settings section information header
	 *
	 * @since 0.2
	 */
	public function network_additional_settings_info() {
		?>
			<p>
				<?php _e( 'Below you can set additional Network Guard options.', 'guard' ); ?>
			</p>
		<?php
	}

	/**
	 * Output the network only input field
	 *
	 * @since 0.2
	 */
	public function network_setting_network_only() {
		?>
			<p>
				<label>
					<input type="checkbox" name="_guard_network_only" <?php checked( get_site_option( '_guard_network_only' ), 1 ) ?> value="1" />
					<span class="description"><?php _e( 'Disable this plugin for individual sites.', 'guard' ); ?></span>
				</label>
			</p>
		<?php
	}

	/**
	 * Output the enable network protection input field
	 *
	 * @since 0.2
	 */
	public function network_setting_network_protect() {
		?>
			<p>
				<label>
					<input type="checkbox" name="_guard_network_protect" <?php checked( get_site_option( '_guard_network_protect' ), 1 ) ?> value="1" />
					<span class="description"><?php _e( 'Enable network protection.', 'guard' ); ?></span>
				</label>
			</p>
		<?php
	}

	/**
	 * Output the redirect to main site input field
	 *
	 * @since 0.2
	 */
	public function network_setting_network_redirect() {
		?>
			<p>
				<label>
					<input type="checkbox" name="_guard_network_redirect" <?php checked( get_site_option( '_guard_network_redirect' ), 1 ) ?> value="1" />
					<span class="description"><?php _e( 'Redirect users from protected sites to the main site.', 'guard' ); ?></span>
				</label>
			</p>
		<?php
	}

	/**
	 * Output the hide my sites input field
	 *
	 * @since 0.2
	 */
	public function network_setting_network_hide_my_sites() {
		?>
			<p>
				<label>
					<input type="checkbox" name="_guard_network_hide_my_sites" <?php checked( get_site_option( '_guard_network_hide_my_sites' ), 1 ) ?> value="1" />
					<span class="description"><?php _e( 'Hide "My Sites" links and page when a user has access to only one site.', 'guard' ); ?></span>
				</label>
			</p>
		<?php
	}

	/**
	 * Output the allowed network users input field
	 *
	 * @since 0.2
	 *
	 * @todo Does get_users() fetch all network users?
	 *
	 * @uses guard_get_network_users() To get all users of the network
	 */
	public function network_setting_allow_users() {
		$users = get_site_option( '_guard_network_allowed_users' );

		if ( ! is_array( $users ) )
			$users = array();

		?>
			<select id="_guard_network_allowed_users" class="chzn-select" name="_guard_network_allowed_users[]" multiple style="width:25em;" data-placeholder="<?php _e( 'Select a user', 'guard' ); ?>">

			<?php foreach ( $this->get_network_users() as $user ) : ?>
				<option value="<?php echo $user->ID; ?>" <?php selected( in_array( $user->ID, $users ) ); ?>><?php echo $user->user_login; ?></option>
			<?php endforeach; ?>

			</select>
			<span class="description float"><?php _e( 'Select which network users you want to have access.', 'guard' ); ?></span>
		<?php
	}

		/**
		 * Return array of all network users
		 *
		 * @since 0.2
		 *
		 * @uses get_current_user_id()
		 * @uses get_blogs_of_user()
		 * @uses switch_to_blog()
		 * @uses get_users()
		 * @uses restore_current_blog()
		 *
		 * @return array Network users
		 */
		public function get_network_users() {
			$users = array();
			$user_id = get_current_user_id(); // Always super admin?

			foreach ( get_blogs_of_user( $user_id ) as $blog_id => $details ) {
				switch_to_blog( $blog_id );

				// array( 0 => WP_User ) becomes array( $user_id => WP_User )
				foreach ( get_users() as $user )
					$users[$user->ID] = $user;

				restore_current_blog();
			}

			return apply_filters( 'guard_get_network_users', $users );
		}

	/**
	 * Output the custom network message input field
	 *
	 * @since 0.2
	 */
	public function network_setting_custom_message() {
		?>
			<textarea name="_guard_network_custom_message" style="width:25em;" rows="3"><?php echo esc_textarea( get_site_option( '_guard_network_custom_message' ) ); ?></textarea>
			<span class="description float"><?php printf( __( 'Serve network guests a nice heads up on the login page. Leave empty if not applicable. This message will only be shown if <strong>Protect this network</strong> is activated.<br/>Allowed HTML tags %s, %s and %s.', 'guard' ), '&#60;a&#62;', '&#60;em&#62;', '&#60;strong&#62;' ); ?></span>
		<?php
	}

	/**
	 * Handle updating network settings
	 *
	 * @since 0.2
	 *
	 * @uses wp_verify_nonce()
	 * @uses Guard::network_settings()
	 * @uses update_site_option()
	 * @uses wp_redirect()
	 */
	public function network_settings_api() {
		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'guard_network-options' ) )
			return;

		// Loop all network settings to update
		foreach ( $this->network_settings() as $option => $args ) {
			if ( ! isset( $_POST[$option] ) )
				$_POST[$option] = apply_filters( 'guard_network_settings_default', 0, $option );

			$value = call_user_func_array( $args['sanitize_cb'], array( $_POST[$option] ) );

			// Don't catch retval since both non-updates and errors return false
			update_site_option( $option, $value );
		}

		// Build redirect url string
		$args = array( 'page' => 'guard_network', 'settings-updated' => 'true' ); // Allways true?
		wp_redirect( add_query_arg( $args, network_admin_url( 'settings.php' ) ) );
		exit;
	}

	/**
	 * Output network settings update message
	 *
	 * @since 0.2
	 *
	 * @uses guard_is_network_page()
	 */
	public function network_admin_notice() {
		if ( ! guard_is_network_page() )
			return;

		if ( isset( $_GET['settings-updated'] ) ) {
			$type = 'true' == $_GET['settings-updated'] ? 'updated' : 'error';
			if ( 'updated' == $type )
				$message = __( 'Settings saved.' );
			else
				$message = apply_filters( 'guard_network_admin_notice', __( 'Something went wrong', 'guard' ), $_GET['settings-updated'] );

			echo '<div class="message ' . $type . '"><p>' . $message . '</p></div>';
		}
	}

	/**
	 * Return whether we are on the plugin network page
	 *
	 * @since 0.2
	 *
	 * @global string $hook_suffix
	 * @return boolean This is the network page
	 */
	public function is_network_page() {
		global $hook_suffix;

		if ( isset( $hook_suffix ) && 'settings_page_guard_network' == $hook_suffix )
			return true;

		return false;
	}

	/**
	 * Clean up when this plugin is deleted
	 *
	 * @since 0.2
	 *
	 * @uses Guard::network_settings()
	 * @uses delete_site_option()
	 */
	public function network_uninstall() {
		foreach ( $this->network_settings() as $option => $args )
			delete_site_option( $option );
	}

	/** Multisite Manage Sites ***************************************/

	/**
	 * Output network sites management admin panel
	 *
	 * @since 0.x
	 *
	 * @uses get_blog_list() To get all the blogs details. Deprecated.
	 * @uses settings_fields()
	 * @uses switch_to_blog()
	 * @uses do_settings_section()
	 * @uses restore_current_blog()
	 * @uses submit_button()
	 *
	 * @todo Require distinct field ids and input names per blog
	 * @todo Enable blog list paging like '&paged=2'
	 */
	public function network_page_sites() {

		// Fetch all blogs
		$blogs = get_blog_list( 0, 'all' ); // Deprecated, but no alternative available
		usort( $blogs, 'guard_network_blog_order' );

		?>
				<form method="post" action="<?php echo network_admin_url( 'edit.php?action=guard_network_sites' ); ?>">
					<?php settings_fields( 'guard_network_sites' ); ?>
					<?php foreach ( $blogs as $details ) : switch_to_blog( $details['blog_id'] ); ?>

						<h2><?php printf( __( '%1$s at <a href="%2$s">%3$s</a>', 'guard' ), get_option( 'blogname' ), esc_url( 'http://' . $details['domain'] . $details['path'] ), $details['domain'] . $details['path'] ); ?></h2>
						<?php do_settings_sections( 'guard' ); ?>
						<hr />

					<?php restore_current_blog(); endforeach; ?>
					<?php submit_button(); ?>
				</form>
		<?php
	}

	/**
	 * Compare blogs to order an array by blog_id
	 *
	 * @since 1.0.0
	 *
	 * @param array $a Blog to compare
	 * @param array $b Blog to compare
	 * @return int Move position
	 */
	public function network_blog_order( $a, $b ) {
		return $a['blog_id'] > $b['blog_id'];
	}

	/**
	 * Update network sites settings
	 *
	 * @since 0.x
	 *
	 * @uses wp_verify_nonce()
	 * @uses wp_redirect()
	 */
	public function network_sites_settings_api() {
		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'guard_network_sites-options' ) )
			return;

		var_dump( $_POST );
		exit;

		// Loop all network settings to update
		foreach ( $sites_settings as $blog_id => $settings ) {
			// if ( ! isset( $_POST[$option] ) )
				// $_POST[$option] = apply_filters( 'guard_network_settings_default', 0, $option );

			// $value = call_user_func_array( $args['sanitize_cb'], array( $_POST[$option] ) );

			// Don't catch retval since both non-updates and errors return false
			// update_site_option( $option, $value );
		}

		// Build redirect url string
		$args = array( 'page' => 'guard_network', 'tab' => 'sites', 'settings-updated' => 'true' ); // Allways true?
		wp_redirect( add_query_arg( $args, network_admin_url( 'settings.php' ) ) );
		exit;
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
