<?php

/**
 * Guard Network Functions
 *
 * @package Guard
 * @subpackage Multisite
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Guard_Network' ) ) :
/**
 * Guard Network Class
 *
 * @since 1.0.0
 */
final class Guard_Network {

	/**
	 * Setup class
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->setup_actions();
	}

	/** Private Methods **********************************************/

	/**
	 * Setup network actions and filters
	 *
	 * @since 1.0.0
	 */
	private function setup_actions() {

		// Plugin
		add_action( 'plugins_loaded',        array( $this, 'network_only'      ), 20    );

		// Protection
		add_action( 'template_redirect',     array( $this, 'network_protect'   ), 0     );
		add_action( 'guard_site_protect',    array( $this, 'network_redirect'  )        );
		add_action( 'admin_bar_menu',        array( $this, 'filter_admin_bar'  ), 99    );
		add_action( 'admin_menu',            array( $this, 'filter_admin_menu' ), 99    );
		add_action( 'get_blogs_of_user',     array( $this, 'sites_of_user'     ), 10, 3 );
		add_filter( 'user_has_cap',          array( $this, 'user_has_cap'      ), 10, 3 );

		// Admin
		add_action( 'admin_init',            array( $this, 'register_settings' ) );
		add_action( 'network_admin_menu',    array( $this, 'admin_menu'        ) );
		add_action( 'network_admin_notices', array( $this, 'admin_notices'     ) );

		// Uninstall hook
		register_uninstall_hook( guard()->file, array( $this, 'network_uninstall' ) );
	}

	/** Plugin *******************************************************/

	/**
	 * Ensure Guard is only used for the network
	 *
	 * @since 0.2
	 *
	 * @uses guard_is_network_only()
	 * @uses remove_action()
	 * @uses remove_filter()
	 */
	public function network_only() {

		// Bail if not marked as network only
		if ( ! guard_is_network_only() )
			return;

		// Get Guard and remove plugin hooks for the single site context
		$guard = guard();

		// Protection
		remove_action( 'template_redirect', array( $guard, 'site_protect'  ), 1 );
		remove_filter( 'login_message',     array( $guard, 'login_message' ), 1 );

		// Admin
		remove_action( 'admin_init', array( $guard, 'register_settings' ) );
		remove_action( 'admin_menu', array( $guard, 'admin_menu'        ) );
	}

	/** Protection ***************************************************/

	/**
	 * Redirect user if network is protected
	 *
	 * @since 0.2
	 *
	 * @uses guard_is_network_protected()
	 * @uses is_user_logged_in() To check if the user is logged in
	 * @uses guard_network_is_user_allowed() To check if the network user is allowed
	 * @uses auth_redirect() To log the user out and redirect to wp-login.php
	 */
	public function network_protect() {

		// Bail when network protection is not active
		if ( ! guard_is_network_protected() )
			return;

		// Redirect when the user is not logged in or is not allowed
		if ( ! is_user_logged_in() || ! guard_network_is_user_allowed() ) {
			auth_redirect();
		}
	}

	/**
	 * Redirect the unauthorized user to the network home instead of the login page
	 *
	 * @since 0.2
	 *
	 * @uses guard_network_redirect()
	 * @uses wp_redirect()
	 * @uses network_home_url()
	 */
	public function network_redirect() {

		// When network redirection is active
		if ( guard_network_redirect() ) {

			// Define local variable(s)
			$user_id  = get_current_user_id();
			$location = '';

			// Redirect user to network home site when it's not protected. Prevents a loophole
			if ( ! guard_is_site_protected( BLOG_ID_CURRENT_SITE ) ) {
				$location = network_home_url();

			// Find another allowed location when the user is loggedin
			} elseif ( ! empty( $user_id ) ) {

				// Define local variable(s)
				$sites = get_blogs_of_user( get_current_user_id() );

				// Get the first allowed site
				if ( ! empty( $sites ) ) {
					$site     = reset( $sites );
					$location = $site->siteurl . $site->path;
				}
			}

			// Provide hook
			$location = apply_filters( 'guard_network_redirect_location', $location, $user_id );

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
	 * @since 0.2
	 *
	 * @uses guard_is_site_protected()
	 * @uses guard_is_user_allowed()
	 *
	 * @param array $sites Sites where user is registered
	 * @param int $user_id User ID
	 * @param boolean $all Whether to return also all hidden sites
	 * @return array Sites
	 */
	public function sites_of_user( $sites, $user_id, $all ) {

		// Do not change site list when requesting all
		if ( $all ) {
			return $sites;
		}

		// Walk all sites
		foreach ( $sites as $k => $details ) {

			// Get the site's ID
			$site_id = $details->userblog_id;

			// Site protection is active and user is not allowed
			if ( guard_is_site_protected( $site_id ) && ! guard_is_user_allowed( $user_id, $site_id ) ) {

				// Remove site from collection
				unset( $sites[ $k ] );
			}
		}

		return $sites;
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
	public function filter_admin_bar( $wp_admin_bar ) {

		// Hiding 'My Sites'
		if ( guard_network_hide_my_sites() ) {

			// Remove admin bar menu top item
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
	public function filter_admin_menu() {

		// Hiding 'My Sites'
		if ( guard_network_hide_my_sites() ) {

			// This only removes the admin menu item, not the page
			remove_submenu_page( 'index.php', 'my-sites.php' );
		}
	}

	/**
	 * Modify the user capabilities by filtering 'user_has_cap'
	 *
	 * @since 1.0.0
	 *
	 * @uses get_current_screen()
	 * @uses guard_network_hide_my_sites()
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
			&& guard_network_hide_my_sites()

			// Requesting the 'read' cap
			&& in_array( 'read', $caps )
		) {

			// Disable the read user cap
			$allcaps['read'] = false;
		}

		return $allcaps;
	}

	/** Admin ********************************************************/

	/**
	 * Create the plugin network admin page menu item
	 *
	 * @since 0.2
	 *
	 * @uses add_submenu_page() To add the menu to the options pane
	 * @uses add_action() To enable functions hooking into admin page
	 *                     head en footer
	 */
	public function admin_menu() {

		// Create Settings submenu
		$hook = add_submenu_page(
			'settings.php',
			__( 'Guard Network Settings', 'guard' ),
			__( 'Guard', 'guard' ),
			'manage_network',
			'guard_network',
			array( $this, 'network_page' )
		);

		add_action( "admin_head-$hook",   array( $this, 'admin_head'   ) );
		add_action( "admin_footer-$hook", array( $this, 'admin_footer' ) );
	}

	/**
	 * Provide a hook for the guard network settings page head
	 *
	 * @since 1.0.0
	 * 
	 * @uses do_action() Calls 'guard_network_admin_head'
	 */
	public function admin_head() {
		do_action( 'guard_network_admin_head' );
	}

	/**
	 * Provide a hook for the guard network settings page footer
	 *
	 * @since 1.0.0
	 * 
	 * @uses do_action() Calls 'guard_network_admin_footer'
	 */
	public function admin_footer() {
		do_action( 'guard_network_admin_footer' );
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
	 * @uses Guard_Network::network_page_sites()
	 * @uses do_action() Calls 'guard_network_page' with the tab
	 */
	public function network_page() {

		// Fetch tab
		$tab = isset( $_GET['tab'] ) ? $_GET['tab'] : 'main'; ?>

			<div class="wrap">
				<?php screen_icon( 'options-general' ); ?>
				<h2><?php _e( 'Guard Network Settings', 'guard' ); ?></h2>

				<?php switch ( $tab ) :

					// Main settings page
					case 'main' : ?>

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
	 * Setup the plugin network settings
	 *
	 * @since 0.2
	 *
	 * @uses add_settings_section() To create the settings sections
	 * @uses add_settings_field() To create a setting with it's field
	 * @uses guard_network_settings()
	 * @uses register_setting() To enable the setting being saved to the DB
	 */
	public function register_settings() {

		// Create settings sections
		add_settings_section( 'guard-options-main',       __( 'Network Main Settings',       'guard' ), 'guard_network_main_settings_info',       'guard_network' );
		add_settings_section( 'guard-options-access',     __( 'Network Access Settings',     'guard' ), 'guard_network_access_settings_info',     'guard_network' );
		add_settings_section( 'guard-options-additional', __( 'Additional Network Settings', 'guard' ), 'guard_network_additional_settings_info', 'guard_network' );

		// Loop all network settings to register
		foreach ( guard_network_settings() as $setting => $args ) {

			// Only render field when label and callback are present
			if ( isset( $args['label'] ) && isset( $args['field_cb'] ) ) {
				add_settings_field( $setting, $args['label'], $args['field_cb'], $args['page'], $args['section'] );
			}

			register_setting( $args['page'], $setting, $args['sanitize_cb'] );
		}

		/**
		 * There is no valid Network Settings API available in WP Multisite so we will have
		 * to do the sanitization and storing manually through wp-admin/network/edit.php
		 *
		 * @link http://core.trac.wordpress.org/ticket/15691
		 */
		add_action( 'network_admin_edit_guard_network',       array( $this, 'handle_network_settings' ) );
		add_action( 'network_admin_edit_guard_network_sites', array( $this, 'update_sites_settings'   ) );
	}

	/**
	 * Handle updating network settings
	 *
	 * @since 1.0.0
	 *
	 * @uses wp_reset_vars()
	 * @uses is_multisite()
	 * @uses apply_filters() Calls 'option_page_capability_{$option_page}'
	 * @uses current_user_can()
	 * @uses is_super_admin()
	 * @uses wp_die()
	 * @uses check_admin_referer()
	 * @uses apply_filters() Calls 'whitelist_options'
	 * @uses update_site_option()
	 * @uses get_settings_errors()
	 * @uses add_settings_error()
	 * @uses set_transient()
	 * @uses add_query_arg()
	 * @uses wp_get_referer()
	 * @uses wp_redirect()
	 */
	public function handle_network_settings() {
		global $action, $option_page;

		// Redefine global variable(s)
		wp_reset_vars( array( 'action', 'option_page' ) );

		// Bail when not using within multisite
		if ( ! is_multisite() )
			return;

		/* This filter is documented in wp-admin/options.php */
		$capability = apply_filters( "option_page_capability_{$option_page}", 'manage_options' );

		// Bail when current user is not allowed
		if ( ! current_user_can( $capability ) || ( is_multisite() && ! is_super_admin() ) )
			wp_die( __( 'Cheatin&#8217; uh?' ), 403 );

		// We are saving settings sent from a settings page
		if ( 'update' == $action ) {

			// Check admin referer
			check_admin_referer( $option_page . '-options' );

			/* This filter is documented in wp-admin/options.php */
			$whitelist_options = apply_filters( 'whitelist_options', '' );

			// Bail when settings page is not registered
			if ( ! isset( $whitelist_options[ $option_page ] ) )
				wp_die( __( '<strong>ERROR</strong>: options page not found.' ) );

			$options = $whitelist_options[ $option_page ];

			if ( $options ) {
				foreach ( $options as $option ) {
					$option = trim( $option );
					$value = null;
					if ( isset( $_POST[ $option ] ) ) {
						$value = $_POST[ $option ];
						if ( ! is_array( $value ) )
							$value = trim( $value );
						$value = wp_unslash( $value );
					}
					update_site_option( $option, $value );
				}
			}

			/**
			 * Handle settings errors and return to options page
			 */
			// If no settings errors were registered add a general 'updated' message.
			if ( !count( get_settings_errors() ) )
				add_settings_error('general', 'settings_updated', __('Settings saved.'), 'updated');
			set_transient('settings_errors', get_settings_errors(), 30);

			/**
			 * Redirect back to the settings page that was submitted
			 */
			$goback = add_query_arg( 'settings-updated', 'true',  wp_get_referer() );
			wp_redirect( $goback );
			exit;
		}
	}

	/**
	 * Output network settings update message
	 *
	 * @since 0.2
	 *
	 * @uses Guard_Network::is_network_page()
	 */
	public function admin_notices() {

		// Bail when not on the settings page
		if ( ! $this->is_network_page() )
			return;

		// Settings were updated
		if ( isset( $_GET['settings-updated'] ) ) {
			$type = 'true' == $_GET['settings-updated'] ? 'updated' : 'error';
			if ( 'updated' == $type ) {
				$message = __( 'Settings saved.', 'guard' );
			} else {
				$message = apply_filters( 'guard_network_admin_notice', __( 'Something went wrong', 'guard' ), $_GET['settings-updated'] );
			}

			echo '<div class="message ' . $type . '"><p>' . $message . '</p></div>';
		}
	}

	/**
	 * Return whether we are on the plugin network settings page
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
	 * @uses guard_network_settings()
	 * @uses delete_site_option()
	 */
	public function network_uninstall() {

		// Delete all settings
		foreach ( guard_network_settings() as $option => $args ) {
			delete_site_option( $option );
		}
	}

	/** Network Manage Sites ***************************************/

	/**
	 * Output network sites management admin panel
	 *
	 * @since 0.x
	 *
	 * @uses get_blog_list() To get all the sites details. Deprecated.
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

		// Fetch all sites
		$sites = get_blog_list( 0, 'all' ); // Deprecated, but no alternative available
		usort( $sites, 'guard_network_blog_order' ); ?>

			<form method="post" action="<?php echo network_admin_url( 'edit.php?action=guard_network_sites' ); ?>">
				<?php settings_fields( 'guard_network_sites' ); ?>

				<?php // Walk all sites ?>
				<?php foreach ( $sites as $details ) : switch_to_blog( $details['blog_id'] ); ?>

					<h2><?php printf( __( '%1$s at <a href="%2$s">%3$s</a>', 'guard' ), get_option( 'blogname' ), esc_url( 'http://' . $details['domain'] . $details['path'] ), $details['domain'] . $details['path'] ); ?></h2>
					<?php do_settings_sections( 'guard' ); ?>
					<hr />

				<?php restore_current_blog(); endforeach; ?>
				<?php submit_button(); ?>
			</form>

		<?php
	}

	/**
	 * Compare sites to order an array by blog_id
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
	 * @since 1.0.0
	 *
	 * @uses wp_verify_nonce()
	 */
	public function update_sites_settings() {

		// Bail when not verified
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

endif; // class_exists
