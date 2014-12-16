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
		add_action( 'admin_init',               array( $this,  'register_settings'     ) );
		add_action( 'network_admin_menu',       array( $this,  'admin_menu'            ) );
		add_action( 'network_admin_notices',    array( $this,  'admin_notices'         ) );
		add_action( 'guard_network_admin_head', array( $guard, 'enqueue_scripts'       ) );
		add_action( 'guard_network_load_admin', array( $this,  'load_admin_page_sites' ) );
		add_action( 'guard_network_admin_head', array( $this,  'admin_head_page_sites' ) );
		add_action( 'guard_network_page_sites', array( $this,  'admin_page_sites'      ) );

		// Plugin links
		add_filter( 'network_admin_plugin_action_links', array( $this, 'settings_link' ), 10, 2 );
	}

	/** Plugin *******************************************************/

	/**
	 * Ensure Guard is only used for the network
	 *
	 * Remove plugin hooks for the single site context.
	 * 
	 * @since 1.0.0
	 *
	 * @uses guard_is_network_only()
	 * @uses remove_action()
	 * @uses remove_filter()
	 */
	public function network_only() {

		// Bail when not marked as network only
		if ( ! guard_is_network_only() )
			return;

		$guard = guard();

		// Protection
		remove_action( 'template_redirect', array( $guard, 'site_protect'   ), 1 );
		remove_filter( 'login_message',     array( $guard, 'login_message'  ), 1 );
		remove_action( 'admin_bar_menu',    array( $guard, 'admin_bar_menu' )    );

		// Admin
		remove_action( 'admin_init', array( $guard, 'register_settings' ) );
		remove_action( 'admin_menu', array( $guard, 'admin_menu'        ) );
	}

	/** Protection ***************************************************/

	/**
	 * Redirect user when network is protected
	 *
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
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
	 * @since 1.0.0
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
			'guard',
			array( $this, 'admin_page' )
		);

		add_action( "load-$hook",         array( $this, 'load_admin'   ) );
		add_action( "admin_head-$hook",   array( $this, 'admin_head'   ) );
		add_action( "admin_footer-$hook", array( $this, 'admin_footer' ) );
	}

	/**
	 * Provide a hook for loading the guard network settings page
	 *
	 * @since 1.1.0
	 * 
	 * @uses do_action() Calls 'guard_network_load_admin'
	 */
	public function load_admin() {
		do_action( 'guard_network_load_admin' );
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
	 * @since 1.0.0
	 *
	 * @uses apply_filters() Calls 'guard_network_admin_tabs'
	 * @uses add_query_arg()
	 * @uses network_admin_url()
	 * @uses settings_fields() To output the form validation inputs
	 * @uses do_settings_section() To output all form fields
	 * @uses submit_button() To output the form submit button
	 * @uses do_action() Calls 'guard_network_page_{$page_tab}'
	 */
	public function admin_page() {

		// Get the admin tabs
		$tabs = apply_filters( 'guard_network_admin_tabs', array( 'main' => __( 'Main', 'guard' ), 'sites' => __( 'Sites', 'guard' ) ) );
		// Remove Sites tab when Guard is only active for the network
		if ( guard_is_network_only() )
			unset( $tabs['sites'] );
		$page_tab = isset( $_GET['tab'] ) && in_array( $_GET['tab'], array_keys( $tabs ) ) ? $_GET['tab'] : 'main'; ?>

		<div class="wrap">
			<h2 class="nav-tab-wrapper">
				<?php esc_html_e( 'Guard Network', 'guard' ); ?>
				<?php foreach ( $tabs as $tab => $label ) :
					printf( '<a class="nav-tab%s" href="%s">%s</a>',
						( $tab == $page_tab ) ? ' nav-tab-active' : '', 
						add_query_arg( array( 'page' => 'guard', 'tab' => $tab ), network_admin_url( 'settings.php' ) ),
						$label
					);
				endforeach; ?>
			</h2>

			<?php // Output the settings form on the main page ?>
			<?php if ( 'main' == $page_tab ) { ?>

			<form method="post" action="<?php echo network_admin_url( 'edit.php?action=guard_network' ); ?>">
				<?php settings_fields( 'guard_network' ); ?>
				<?php do_settings_sections( 'guard_network' ); ?>
				<?php submit_button(); ?>
			</form>

			<?php // Custom settings page ?>
			<?php } else { 
				do_action( "guard_network_page_{$page_tab}" );
			} ?>
		</div>

		<?php
	}

	/**
	 * Setup the plugin network settings
	 *
	 * @since 1.0.0
	 *
	 * @uses add_settings_section() To create the settings sections
	 * @uses guard_network_settings()
	 * @uses add_settings_field() To create a setting with it's field
	 * @uses register_setting() To enable the setting being saved to the DB
	 * @uses add_action()
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
	 * This method follows the logic of the Settings API for single sites
	 * very closely as it is in {@link wp-admin/options.php}.
	 *
	 * @since 1.0.0
	 *
	 * @link http://core.trac.wordpress.org/ticket/15691
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
	 * @since 1.0.0
	 *
	 * @uses Guard_Network::is_network_page()
	 * @uses apply_filters() Calls 'guard_network_admin_notice'
	 * @uses apply_filters() Calls 'guard_network_bulk_site_updated_counts'
	 * @uses apply_filters() Calls 'guard_network_bulk_site_updated_messages'
	 *
	 */
	public function admin_notices() {

		// Bail when not on the settings page
		if ( ! $this->is_network_page() )
			return;

		// Define local variable(s)
		$messages = array();
		$type = 'updated';

		// Settings were updated
		if ( isset( $_GET['settings-updated'] ) ) {
			$type = 'true' == $_GET['settings-updated'] ? 'updated' : 'error';
			if ( 'updated' == $type ) {
				$messages[] = __( 'Settings saved.', 'guard' );
			} else {
				$messages[] = apply_filters( 'guard_network_admin_notice', __( 'Something went wrong.', 'guard' ), $_GET['settings-updated'] );
			}

		// Bulk sites settings
		} elseif ( isset( $GLOBALS['wp_list_table'] ) ) {

			$bulk_counts = apply_filters( 'guard_network_bulk_site_updated_counts', array(
				'enabled'  => isset( $_REQUEST['enabled']  ) ? absint( $_REQUEST['enabled']  ) : 0,
				'disabled' => isset( $_REQUEST['disabled'] ) ? absint( $_REQUEST['disabled'] ) : 0,
			) );

			$bulk_messages = apply_filters( 'guard_network_bulk_site_updated_messages', array(
				'enabled'  => _n( 'Protection enabled for %d site.',  'Protection enabled for %d sites.',  $bulk_counts['enabled'],  'guard' ),
				'disabled' => _n( 'Protection disabled for %d site.', 'Protection disabled for %d sites.', $bulk_counts['disabled'], 'guard' ),
			) );

			foreach ( $bulk_counts as $action => $count ) {
				if ( isset( $bulk_messages[ $action ] ) && ! empty( $count ) ) {
					$messages[] = sprintf( $bulk_messages[ $action], number_format_i18n( $count ) );
				}
			}
		}

		// Message(s) provided
		if ( ! empty( $messages ) ) {
			echo '<div class="notice ' . $type . '"><p>' . join( ' ', $messages ) . '</p></div>';
		}
	}

	/**
	 * Return whether we are on the plugin network settings page
	 *
	 * @since 1.0.0
	 *
	 * @global string $hook_suffix
	 * @return bool This is the network page
	 */
	public function is_network_page() {
		return ( isset( $GLOBALS['hook_suffix'] ) && 'settings_page_guard' == $GLOBALS['hook_suffix'] );
	}

	/**
	 * Add a settings link to the plugin actions on plugin.php
	 *
	 * @since 1.0.0
	 *
	 * @uses add_query_arg() To create the url to the settings page
	 *
	 * @param array $links The current plugin action links
	 * @param string $file The current plugin file
	 * @return array $links All current plugin action links
	 */
	public function settings_link( $links, $file ) {

		// Add settings link for our plugin
		if ( $file == guard()->basename ) {
			$links['settings'] = '<a href="' . add_query_arg( 'page', 'guard', 'settings.php' ) . '">' . __( 'Settings', 'guard' ) . '</a>';
		}

		return $links;
	}

	/** Network Manage Sites ***************************************/

	/**
	 * Setup the sites list table when loading the Network Sites admin page
	 *
	 * @since 1.1.0
	 *
	 * @uses _get_guard_network_sites_list_table()
	 */
	public function load_admin_page_sites() {
		global $wp_list_table, $pagenum;

		// Bail when not loading the Network Sites page
		if ( ! isset( $_GET['tab'] ) || 'sites' != $_GET['tab'] )
			return;

		// Setup list table globals
		$wp_list_table = _get_guard_network_sites_list_table();
		$pagenum = $wp_list_table->get_pagenum();
	}

	/**
	 * Output scripts in the Network Sites admin page head
	 *
	 * @since 1.1.0
	 *
	 * @global object $wp_list_table
	 */
	public function admin_head_page_sites() {
		global $wp_list_table;

		// Bail when not loading the Network Sites page
		if ( ! isset( $_GET['tab'] ) || 'sites' != $_GET['tab'] )
			return;

		// Bail when the specified list table isn't loaded
		if ( ! is_a( $wp_list_table, 'Guard_Network_Sites_List_Table' ) )
			return; ?>

		<style type="text/css">
			h3 {
				float: left;
			}

			h3, p.search-box {
				margin: 1em 0 0;
			}

			.widefat .column-blogname .edit {
				font-weight: 600;
			}

				.widefat .column-blogname .edit ~ span {
					font-style: italic;
				}

			.widefat .column-protected {
				width: 20px;
				padding-right: 5px;
				font-size: 0; /* Hide column label */
			}

				.widefat .column-protected:before {
					text-indent: 0;
					display: inline-block;
					width: 20px;
					height: 20px;
					font-size: 20px;
					line-height: 1;
					font-family: dashicons;
					font-weight: 400;
					font-style: normal;
					vertical-align: top;
					text-align: center;
					content: "\f332"; /* dashicons-yes */
					color: #ddd;
				}

				.widefat thead .column-protected:before,
				.widefat tfoot .column-protected:before {
					content: '';
				}

				.widefat .site-protected .column-protected:before {
					color: #0074a2;
				}

			.widefat .column-allowed_users {
				width: 10%;
			}
		</style>

		<?php
	}

	/**
	 * Output network sites management admin panel
	 *
	 * @since 1.0.0
	 *
	 * @uses guard_is_network_only()
	 * @uses remove_query_arg()
	 * @uses apply_filters() Calls 'guard_network_sites_uri_args'
	 * @global object $wp_list_table
	 */
	public function admin_page_sites() {
		global $wp_list_table;

		// Bail when Guard is only active for the network
		if ( guard_is_network_only() ) {
			echo '<div class="notice notice-error"><p>' . __( 'Guard is only active for the network. There are no site settings here.', 'guard' ) . '</p></div>';
			return;
		} 

		// Load list table items
		$wp_list_table->prepare_items(); 

		// Clean REQUEST_URI
		$_SERVER['REQUEST_URI'] = remove_query_arg( apply_filters( 'guard_network_sites_uri_args', array( 'enabled', 'disabled' ) ), $_SERVER['REQUEST_URI'] ); ?>

		<h3><?php _e( 'Manage Sites Protection', 'guard' ); ?></h3>

		<form action="<?php echo network_admin_url( 'settings.php' ); ?>" method="get" id="ms-search">
			<?php $wp_list_table->search_box( __( 'Search Sites' ), 'site' ); ?>
			<input type="hidden" name="page" value="guard" />
			<input type="hidden" name="tab" value="<?php echo $_GET['tab']; ?>" />
		</form>

		<form method="post" action="<?php echo network_admin_url( 'edit.php?action=guard_network_sites' ); ?>">
			<?php $wp_list_table->display(); ?>
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
	 * @uses wp_die()
	 * @uses wp_get_referer()
	 * @uses _get_guard_network_sites_list_table()
	 * @uses check_admin_referer()
	 * @uses wp_parse_id_list()
	 * @uses wp_redirect()
	 * @uses switch_to_blog()
	 * @uses update_option()
	 * @uses restore_current_blog()
	 * @uses add_query_arg()
	 * @uses apply_filters() Calls 'guard_network_sites_edit'
	 * @uses get_settings_errors()
	 * @uses add_settings_error()
	 * @uses set_transient()
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

		// Setup redirect location
		$goback = wp_get_referer();

		// Get the bulk action
		$wp_list_table = _get_guard_network_sites_list_table();
		$doaction = $wp_list_table->current_action();

		if ( $doaction ) {
			check_admin_referer( 'bulk-sites' );

			// Get site ids to walk
			$site_ids = isset( $_POST[ 'allblogs' ] ) ? wp_parse_id_list( $_POST[ 'allblogs' ] ) : array();

			// Bail when site ids are not provided
			if ( empty( $site_ids ) ) {
				wp_redirect( $goback );
				exit;
			}

			// Check current action
			switch ( $doaction ) {
				case 'enable' :
				case 'disable' :
					$sites = 0;
					foreach ( $site_ids as $site_id ) {
						switch_to_blog( $site_id );
						if ( update_option( '_guard_site_protect', ( 'enable' === $doaction ) ? 1 : 0 ) ) {
							$sites++;
						}
						restore_current_blog();
					}
					$goback = add_query_arg( ( 'enable' === $doaction ) ? 'enabled' : 'disabled', $sites, $goback );
				break;

				default :
					$goback = apply_filters( 'guard_network_sites_edit', $goback, $doaction, $site_ids );
				break;
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
		wp_redirect( $goback );
		exit;
	}
}

endif; // class_exists

/**
 * Define the plugin's sites list table class
 *
 * @since 1.1.0
 */
function _get_guard_network_sites_list_table( $args = array() ) {

	// Bail when the class already exists
	if ( ! class_exists( 'Guard_Network_Sites_List_Table' ) ) :

	// We depend on the WP MS Sites List Table
	require_once( ABSPATH . 'wp-admin/includes/class-wp-ms-sites-list-table.php' );

	/**
	 * The Guard Network Sites List Table
	 *
	 * @since 1.1.0
	 */
	class Guard_Network_Sites_List_Table extends WP_MS_Sites_List_Table {

		/**
		 * Setup the list table's columns
		 *
		 * @since 1.1.0
		 *
		 * @uses WP_MS_Sites_List_Table::get_columns()
		 * @uses apply_filters() Calls 'guard_network_sites_columns'
		 * 
		 * @return array Columns
		 */
		public function get_columns() {
			$columns = parent::get_columns();

			return (array) apply_filters( 'guard_network_sites_columns', array( 
				'cb'            => $columns['cb'],
				'protected'     => __( 'Protected', 'guard' ),
				'blogname'      => $columns['blogname'],
				'allowed_users' => __( 'Allowed Users', 'guard' ),
			) );
		}

		/**
		 * Setup the list table's bulk actions
		 * 
		 * @since 1.1.0
		 *
		 * @uses apply_filters() Calls 'guard_network_sites_bulk_actions'
		 * 
		 * @return array Bulk actions
		 */
		public function get_bulk_actions() {
			return (array) apply_filters( 'guard_network_sites_bulk_actions', array(
				'enable'  => __( 'Enable',  'guard' ),
				'disable' => __( 'Disable', 'guard' ),
			) );
		}

		/**
		 * Output the list table's pagination handles
		 *
		 * Removes the mode switcher from inheritance.
		 *
		 * @since 1.1.0
		 * @access protected
		 *
		 * @uses WP_List_Table::pagination()
		 *
		 * @param string $which
		 */
		protected function pagination( $which ) {
			WP_List_Table::pagination( $which );
		}

		/**
		 * Output the site row contents
		 *
		 * @since 1.1.0
		 *
		 * @uses guard_is_site_protected()
		 * @uses is_subdomain_intall()
		 * @uses switch_to_blog()
		 * @uses do_action() Calls 'guard_network_sites_custom_column'
		 * @uses restore_current_blog()
		 * @uses convert_to_screen()
		 * @uses get_current_screen()
		 */
		public function display_rows() {
			$class = '';
			foreach ( $this->items as $blog ) {
				$class = ( 'alternate' == $class ) ? '' : 'alternate';
				$protected = guard_is_site_protected( $blog['blog_id'] ) ? ' site-protected' : '';

				echo "<tr class='$class$protected'>";

				$blogname = ( is_subdomain_install() ) ? str_replace( '.' . get_current_site()->domain, '', $blog['domain'] ) : $blog['path'];

				list( $columns, $hidden ) = $this->get_column_info();

				foreach ( $columns as $column_name => $column_display_name ) {
					switch_to_blog( $blog['blog_id'] );

					$style = '';
					if ( in_array( $column_name, $hidden ) )
						$style = ' style="display:none;"';

					switch ( $column_name ) {
						case 'cb' : ?>
							<th scope="row" class="check-column">
								<label class="screen-reader-text" for="blog_<?php echo $blog['blog_id']; ?>"><?php printf( __( 'Select %s' ), $blogname ); ?></label>
								<input type="checkbox" id="blog_<?php echo $blog['blog_id'] ?>" name="allblogs[]" value="<?php echo esc_attr( $blog['blog_id'] ) ?>" />
							</th>

							<?php
							break;

						case 'blogname' :
							echo "<td class='column-$column_name $column_name'$style>"; ?>
								<a href="<?php echo esc_url( add_query_arg( 'page', 'guard', admin_url( 'options-general.php' ) ) ); ?>" class="edit"><?php echo get_option( 'blogname' ); ?></a>
								<br/><span><?php echo $blogname; ?></span>
							</td>

							<?php
							break;

						case 'allowed_users' :
							$users = get_option( '_guard_allowed_users', array() );
							$count = count( $users );
							$title = implode( ', ', wp_list_pluck( array_map( 'get_userdata', array_slice( $users, 0, 5 ) ), 'user_login' ) );
							if ( 0 < $count - 5 ) {
								$title = sprintf( __( '%s and %d more', 'guard' ), $title, $count - 5 );
							}

							echo "<td class='column-$column_name $column_name'$style>"; ?>
								<span class="count" title="<?php echo $title; ?>"><?php printf( _n( '%d user', '%d users', $count, 'guard' ), $count ); ?></span>
							</td>

							<?php
							break;

						default:
							echo "<td class='$column_name column-$column_name'$style>";
							/**
							 * Fires for each registered custom column in the Sites list table.
							 *
							 * @since 1.1.0
							 *
							 * @param string $column_name The name of the column to display.
							 * @param int    $blog_id     The site ID.
							 */
							do_action( 'guard_network_sites_custom_column', $column_name, $blog['blog_id'] );
							echo "</td>";
							break;
						}
					}
				?>
				</tr>
				<?php

				// Restore site context
				restore_current_blog();
			}
		}
	}

	endif; // class_exists

	// Setup the screen argument
	if ( isset( $args['screen'] ) ) {
		$args['screen'] = convert_to_screen( $args['screen'] );
	} elseif ( isset( $GLOBALS['hook_suffix'] ) ) {
		$args['screen'] = get_current_screen();
	} else {
		$args['screen'] = null;
	}

	return new Guard_Network_Sites_List_Table( $args );
}
