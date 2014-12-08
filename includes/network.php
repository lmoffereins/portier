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

		// Get Guard
		$guard = guard();

		// Plugin
		add_action( 'plugins_loaded', array( $this, 'network_only' ), 20 );

		// Protection
		add_action( 'template_redirect',  array( $this, 'network_protect'   ), 0     );
		add_filter( 'login_message',      array( $this, 'login_message'     ), 0     );
		add_action( 'guard_site_protect', array( $this, 'network_redirect'  )        );
		add_action( 'admin_bar_menu',     array( $this, 'filter_admin_bar'  ), 99    );
		add_action( 'admin_menu',         array( $this, 'filter_admin_menu' ), 99    );
		add_action( 'get_blogs_of_user',  array( $this, 'filter_user_sites' ), 10, 3 );
		add_filter( 'user_has_cap',       array( $this, 'user_has_cap'      ), 10, 3 );

		// Admin
		add_action( 'admin_init',               array( $this,  'register_settings' ) );
		add_action( 'network_admin_menu',       array( $this,  'admin_menu'        ) );
		add_action( 'network_admin_notices',    array( $this,  'admin_notices'     ) );
		add_action( 'guard_network_admin_head', array( $guard, 'enqueue_scripts'   ) );
		add_filter( 'guard_network_admin_tabs', array( $this,  'filter_admin_tabs' ) );
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

		// Bail when not marked as network only
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
	 * Redirect user when network is protected
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
	 * Append our custom network login message to the login messages
	 *
	 * @since 1.0.0
	 *
	 * @param string $message The current login messages
	 * @return string $message
	 */
	public function login_message( $message ) {

		// When network protection is active
		if ( guard_is_network_protected() ) {
			$login_message = get_site_option( '_guard_network_login_message' );

			// Append message when it's provided
			if ( ! empty( $login_message ) ) {
				$message .= '<p class="message">'. $login_message .'<p>';
			}
		}

		return $message;
	}

	/**
	 * Try to rediect the unauthorized user to an allowed site instead of the login page
	 *
	 * @since 0.2
	 *
	 * @uses guard_network_redirect()
	 * @uses get_current_user_id()
	 * @uses get_active_glob_for_user() To get the user's primary (allowed) blog
	 * @uses guard_is_site_protected()
	 * @uses guard_is_user_allowed()
	 * @uses network_home_url()
	 * @uses apply_filters() Calls 'guard_network_redirect_location'
	 * @uses wp_redirect()
	 */
	public function network_redirect() {

		// When network redirection is active
		if ( guard_network_redirect() ) {

			// Define local variable(s)
			$user_id  = get_current_user_id();
			$location = '';

			// Find an allowed location when the user is loggedin
			if ( ! empty( $user_id ) ) {

				// Get the user's primary site
				$site = get_active_blog_for_user( $user_id );

				// Redirect user to their primary site, only when they are allowed there
				if ( ! empty( $site ) && ( ! guard_is_site_protected( $site->blog_id ) || guard_is_user_allowed( $user_id, $site->blog_id ) ) ) {
					$location = $site->siteurl . $site->path;
				}

			// Try to return the anonymous user to the network home
			} elseif ( ! guard_is_site_protected( BLOG_ID_CURRENT_SITE ) ) {
				$location = network_home_url();
			}

			// Provide hook to filter the redirect location
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
	 * @uses guard_is_network_only()
	 * @uses guard_is_site_protected()
	 * @uses guard_is_user_allowed()
	 *
	 * @param array $sites Sites where user is registered
	 * @param int $user_id User ID
	 * @param boolean $all Whether to return also all hidden sites
	 * @return array Sites
	 */
	public function filter_user_sites( $sites, $user_id, $all ) {

		// Do not change site list when requesting all
		if ( $all || guard_is_network_only() ) {
			return $sites;
		}

		// Walk all sites
		foreach ( $sites as $site_id => $details ) {

			// Site protection is active and user is not allowed
			if ( guard_is_site_protected( $site_id ) && ! guard_is_user_allowed( $user_id, $site_id ) ) {

				// Remove site from collection
				unset( $sites[ $site_id ] );
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
	 * Doing so is the only easy way to prevent a user from entering
	 * the My Sites admin page.
	 *
	 * @since 1.0.0
	 *
	 * @uses is_admin()
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
			array( $this, 'admin_page' )
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
	 * @uses settings_fields() To output the form validation inputs
	 * @uses do_settings_section() To output all form fields
	 * @uses submit_button() To output the form submit button
	 * @uses Guard_Network::network_page_sites()
	 * @uses do_action() Calls 'guard_network_page' with the tab
	 */
	public function admin_page() {

		// Get the admin tab(s)
		$tabs     = apply_filters( 'guard_network_admin_tabs', array( 'main' => __( 'Main', 'guard' ), 'sites' => __( 'Sites', 'guard' ) ) );
		$page_tab = isset( $_GET['tab'] ) && in_array( $_GET['tab'], array_keys( $tabs ) ) ? $_GET['tab'] : 'main'; ?>

			<div class="wrap">
				<h2 class="nav-tab-wrapper">
					<?php _e( 'Guard Network Settings', 'guard' ); ?>
					<?php foreach ( $tabs as $tab => $label ) :
						printf( '<a class="nav-tab%s" href="%s">%s</a>',
							( $tab == $page_tab ) ? ' nav-tab-active' : '', 
							add_query_arg( array( 'page' => 'guard_network', 'tab' => $tab ), network_admin_url( 'settings.php' ) ),
							$label
						);
					endforeach; ?>
				</h2>

				<?php switch ( $page_tab ) :

					// Main settings page
					case 'main' : ?>

					<form method="post" action="<?php echo network_admin_url( 'edit.php?action=guard_network' ); ?>">
						<?php settings_fields( 'guard_network' ); ?>
						<?php do_settings_sections( 'guard_network' ); ?>
						<?php submit_button(); ?>
					</form>

					<?php 
						break;

					// Sites settings page
					case 'sites' : 

						// Guard supports sub sites
						if ( ! guard_is_network_only() ) {
							$this->admin_page_sites();

						// Guard is only active for the network
						} else { ?>

					<p class="notice notice-error"><?php _e( 'Guard is only active for the network. There are no sites settings here.', 'guard' ); ?></p>

					<?php }
						break;

					// Hookable settings page
					default :
						do_action( 'guard_network_page', $page_tab );
						break;

				endswitch; ?>
			</div>
		<?php
	}

	/**
	 * Filter the plugin's admin page tabs
	 *
	 * @since 1.0.0
	 * 
	 * @param array $tabs Admin tabs
	 * @return array Admin tabs
	 */
	public function filter_admin_tabs( $tabs ) {

		// Remove Sites tab when Guard is only active for the network
		if ( guard_is_network_only() ) {
			unset( $tabs['sites'] );
		}

		return $tabs;
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
		add_settings_section( 'guard-options-main',   __( 'Main Settings',   'guard' ), 'guard_network_main_settings_info',   'guard_network' );
		add_settings_section( 'guard-options-access', __( 'Access Settings', 'guard' ), 'guard_network_access_settings_info', 'guard_network' );

		// Loop all network settings to register
		foreach ( guard_network_settings() as $setting => $args ) {

			// Only render field when label and callback are present
			if ( isset( $args['label'] ) && isset( $args['callback'] ) ) {
				add_settings_field( $setting, $args['label'], $args['callback'], $args['page'], $args['section'] );
			}

			register_setting( $args['page'], $setting, $args['sanitize_callback'] );
		}

		/**
		 * There is no valid Network Settings API available in WP Multisite so we will have
		 * to do the sanitization and storing manually through wp-admin/network/edit.php
		 *
		 * @link http://core.trac.wordpress.org/ticket/15691
		 */
		add_action( 'network_admin_edit_guard_network',       array( $this, 'handle_network_settings' ) );
		add_action( 'network_admin_edit_guard_network_sites', array( $this, 'handle_sites_settings'   ) );
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

					// Update the network option
					update_site_option( $option, $value );
				}
			}

			/**
			 * Handle settings errors and return to options page
			 */
			// If no settings errors were registered add a general 'updated' message.
			if ( ! count( get_settings_errors() ) )
				add_settings_error( 'general', 'settings_updated', __( 'Settings saved.' ), 'updated' );
			set_transient( 'settings_errors', get_settings_errors(), 30 );

			/**
			 * Redirect back to the settings page that was submitted
			 */
			$goback = add_query_arg( 'settings-updated', 'true', wp_get_referer() );
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
	 * @uses apply_filters() Calls 'guard_network_admin_notice'
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
	 * @return bool This is the network page
	 */
	public function is_network_page() {
		return ( isset( $GLOBALS['hook_suffix'] ) && 'settings_page_guard_network' == $GLOBALS['hook_suffix'] );
	}

	/** Network Manage Sites ***************************************/

	/**
	 * Output network sites management admin panel
	 *
	 * @since 1.0.0
	 *
	 * @uses wp_get_sites()
	 * @uses switch_to_blog()
	 * @uses get_option()
	 * @uses add_query_arg()
	 * @uses restore_current_blog()
	 * @uses wp_nonce_field()
	 * @uses submit_button()
	 */
	public function admin_page_sites() { 

		// Get all sites of this network
		$sites = wp_get_sites(); ?>

		<form method="post" action="<?php echo network_admin_url( 'edit.php?action=guard_network_sites' ); ?>">
			<input type="hidden" name="guard_network_sites" value="<?php echo implode( ',', wp_list_pluck( $sites, 'blog_id' ) ); ?>" />

			<h3><?php _e( 'Manage Site Protection', 'guard' ); ?></h3>
			<table class="form-table">

				<?php // Walk all sites of this network ?>
				<?php foreach ( $sites as $details ) : 
					$blog_id = (int) $details['blog_id']; 
					switch_to_blog( $blog_id ); 

					// Define site details
					$site_name          = get_option( 'blogname' );
					$allowed_user_count = count( get_option( '_guard_allowed_users' ) );
					$site_link_title    = apply_filters( 'guard_network_sites_protect_details', sprintf( _n( '%d allowed user', '%d allowed users', $allowed_user_count, 'guard' ), $allowed_user_count ) );
					$site_link          = sprintf( '<a href="%s" title="%s">%s</a>', add_query_arg( 'page', 'guard', admin_url( 'options-general.php' ) ), $site_link_title, $details['domain'] . $details['path'] );
				?>

				<tr>
					<td>
						<input type="checkbox" id="_guard_site_protect_<?php echo $blog_id; ?>" name="_guard_site_protect[<?php echo $blog_id; ?>]" value="1" <?php checked( get_option( '_guard_site_protect' ) ); ?>/>
						<label for="_guard_site_protect_<?php echo $blog_id; ?>"><?php printf( _x( '%1$s at %2$s', 'Site at url', 'guard' ), $site_name, $site_link ); ?></label>
					</td>
				</tr>

				<?php restore_current_blog(); endforeach; ?>
			</table>

			<?php wp_nonce_field( 'guard_network_sites' ); ?>
			<?php submit_button(); ?>
		</form>

		<?php
	}

	/**
	 * Update network sites settings
	 *
	 * @since 1.0.0
	 *
	 * @uses is_multisite()
	 * @uses apply_filters() Calls 'option_page_capability_{$option_page}'
	 * @uses current_user_can()
	 * @uses is_super_admin()
	 * @uses check_admin_referer()
	 * @uses wp_parse_id_list()
	 * @uses wp_die()
	 * @uses switch_to_blog()
	 * @uses update_option()
	 * @uses restore_current_blog()
	 * @uses get_settings_errors()
	 * @uses add_settings_error()
	 * @uses set_transient()
	 * @uses add_query_arg()
	 * @uses wp_get_referer()
	 * @uses wp_redirect()
	 */
	public function handle_sites_settings() {

		// Define local variable(s)
		$option_page = 'guard_network_sites';

		// Bail when not using within multisite
		if ( ! is_multisite() )
			return;

		/* This filter is documented in wp-admin/options.php */
		$capability = apply_filters( "option_page_capability_{$option_page}", 'manage_options' );

		// Bail when current user is not allowed
		if ( ! current_user_can( $capability ) || ( is_multisite() && ! is_super_admin() ) )
			wp_die( __( 'Cheatin&#8217; uh?' ), 403 );

		// Check admin referer
		check_admin_referer( $option_page );

		// Get site ids to walk
		$sites = isset( $_POST[ $option_page ] ) ? wp_parse_id_list( $_POST[ $option_page ] ) : array();

		// Bail when site ids are not provided
		if ( empty( $sites ) )
			wp_die( __( '<strong>ERROR</strong>: site ids not found.', 'guard' ) );

		// Walk sites
		foreach ( $sites as $site_id ) {

			// Switch to site
			switch_to_blog( $site_id );

			// Update settings
			update_option( '_guard_site_protect', in_array( $site_id, array_keys( $_POST['_guard_site_protect'] ) ) );

			// Switch back
			restore_current_blog();
		}

		/**
		 * Handle settings errors and return to options page
		 */
		// If no settings errors were registered add a general 'updated' message.
		if ( ! count( get_settings_errors() ) )
			add_settings_error( 'general', 'settings_updated', __( 'Settings saved.' ), 'updated' );
		set_transient( 'settings_errors', get_settings_errors(), 30 );

		/**
		 * Redirect back to the settings page that was submitted
		 */
		$goback = add_query_arg( 'settings-updated', 'true', wp_get_referer() );
		wp_redirect( $goback );
		exit;
	}
}

endif; // class_exists
