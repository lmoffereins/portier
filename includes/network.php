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
		$this->setup_actions();
	}

	/** Private Methods **********************************************/

	/**
	 * Setup network actions and filters
	 *
	 * @since 1.0.0
	 */
	private function setup_actions() {
		$prtr = portier();

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
		add_action( 'admin_init',                 array( $this, 'register_settings'     ) );
		add_action( 'network_admin_menu',         array( $this, 'admin_menu'            ) );
		add_action( 'network_admin_notices',      array( $this, 'admin_notices'         ) );
		add_action( 'portier_network_load_admin', array( $this, 'load_admin_page_sites' ) );
		add_action( 'portier_network_admin_head', array( $prtr, 'enqueue_admin_scripts' ) );
		add_action( 'portier_network_admin_head', array( $this, 'admin_head_page_sites' ) );
		add_action( 'portier_network_page_sites', array( $this, 'admin_page_sites'      ) );

		// Plugin links
		add_filter( 'network_admin_plugin_action_links', array( $this, 'settings_link' ), 10, 2 );
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

		$portier = portier();

		// Protection
		remove_action( 'template_redirect', array( $portier, 'site_protect'   ), 1 );
		remove_filter( 'login_message',     array( $portier, 'login_message'  ), 1 );
		remove_action( 'admin_bar_menu',    array( $portier, 'admin_bar_menu' )    );

		// Admin
		remove_action( 'admin_init', array( $portier, 'register_settings' ) );
		remove_action( 'admin_menu', array( $portier, 'admin_menu'        ) );
	}

	/** Protection ***************************************************/

	/**
	 * Redirect user when network is protected
	 *
	 * @since 1.0.0
	 */
	public function network_protect() {

		// Bail when network protection is not active
		if ( ! portier_is_network_protected() )
			return;

		// Redirect when the user is not logged in or is not allowed
		if ( ! is_user_logged_in() || ! portier_network_is_user_allowed() ) {
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
		if ( $all || portier_is_network_only() ) {
			return $sites;
		}

		// Walk all sites
		foreach ( $sites as $site_id => $details ) {

			// Site protection is active and user is not allowed
			if ( portier_is_site_protected( $site_id ) && ! portier_is_user_allowed( $user_id, $site_id ) ) {

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

	/** Admin ********************************************************/

	/**
	 * Create the plugin network admin page menu item
	 *
	 * @since 1.0.0
	 */
	public function admin_menu() {

		// Create Settings submenu
		$hook = add_submenu_page(
			'settings.php',
			esc_html__( 'Portier Network Settings', 'portier' ),
			esc_html__( 'Portier', 'portier' ),
			'manage_network',
			'portier',
			array( $this, 'admin_page' )
		);

		add_action( "load-$hook",         array( $this, 'load_admin'   ) );
		add_action( "admin_head-$hook",   array( $this, 'admin_head'   ) );
		add_action( "admin_footer-$hook", array( $this, 'admin_footer' ) );
	}

	/**
	 * Provide a hook for loading the portier network settings page
	 *
	 * @since 1.1.0
	 * 
	 * @uses do_action() Calls 'portier_network_load_admin'
	 */
	public function load_admin() {
		do_action( 'portier_network_load_admin' );
	}

	/**
	 * Provide a hook for the portier network settings page head
	 *
	 * @since 1.0.0
	 * 
	 * @uses do_action() Calls 'portier_network_admin_head'
	 */
	public function admin_head() {
		do_action( 'portier_network_admin_head' );
	}

	/**
	 * Provide a hook for the portier network settings page footer
	 *
	 * @since 1.0.0
	 * 
	 * @uses do_action() Calls 'portier_network_admin_footer'
	 */
	public function admin_footer() {
		do_action( 'portier_network_admin_footer' );
	}

	/**
	 * Output plugin network admin page contents
	 *
	 * @since 1.0.0
	 *
	 * @uses apply_filters() Calls 'portier_network_admin_tabs'
	 * @uses do_action() Calls 'portier_network_page_{$page_tab}'
	 */
	public function admin_page() {

		// Get the admin tabs
		$tabs = apply_filters( 'portier_network_admin_tabs', array(
			'main'  => esc_html__( 'Main',  'portier' ),
			'sites' => esc_html__( 'Sites', 'portier' )
		) );

		// Remove Sites tab when Portier is only active for the network
		if ( portier_is_network_only() ) {
			unset( $tabs['sites'] );
		}

		$page_tab = isset( $_GET['tab'] ) && in_array( $_GET['tab'], array_keys( $tabs ) ) ? $_GET['tab'] : 'main'; ?>

		<div class="wrap">
			<h1 class="nav-tab-wrapper">
				<?php esc_html_e( 'Portier Network', 'portier' ); ?>
				<?php foreach ( $tabs as $tab => $label ) :
					printf( '<a class="nav-tab%s" href="%s">%s</a>',
						( $tab == $page_tab ) ? ' nav-tab-active' : '', 
						add_query_arg( array( 'page' => 'portier', 'tab' => $tab ), network_admin_url( 'settings.php' ) ),
						esc_html( $label )
					);
				endforeach; ?>
			</h1>

			<?php

			// Output the settings form on the main page
			if ( 'main' == $page_tab ) : ?>

			<form method="post" action="<?php echo network_admin_url( 'edit.php?action=portier_network' ); ?>">
				<?php settings_fields( 'portier_network' ); ?>
				<?php do_settings_sections( 'portier_network' ); ?>
				<?php submit_button(); ?>
			</form>

			<?php

			// Custom settings page
			else :
				do_action( "portier_network_page_{$page_tab}" );
			endif; ?>
		</div>

		<?php
	}

	/**
	 * Setup the plugin network settings
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {

		// Create settings sections
		add_settings_section( 'portier-options-main',   esc_html__( 'Main Settings',   'portier' ), 'portier_network_main_settings_info',   'portier_network' );
		add_settings_section( 'portier-options-access', esc_html__( 'Access Settings', 'portier' ), 'portier_network_access_settings_info', 'portier_network' );

		// Loop all network settings to register
		foreach ( portier_network_settings() as $setting => $args ) {

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
		add_action( 'network_admin_edit_portier_network',       array( $this, 'handle_network_settings' ) );
		add_action( 'network_admin_edit_portier_network_sites', array( $this, 'handle_sites_settings'   ) );
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
	 * @uses apply_filters() Calls 'option_page_capability_{$option_page}'
	 * @uses apply_filters() Calls 'whitelist_options'
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
	 * @uses apply_filters() Calls 'portier_network_admin_notice'
	 * @uses apply_filters() Calls 'portier_network_bulk_site_updated_counts'
	 * @uses apply_filters() Calls 'portier_network_bulk_site_updated_messages'
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
				$messages[] = esc_html__( 'Settings saved.', 'portier' );
			} else {
				$messages[] = apply_filters( 'portier_network_admin_notice', esc_html__( 'Something went wrong.', 'portier' ), $_GET['settings-updated'] );
			}

		// Bulk sites settings
		} elseif ( isset( $GLOBALS['wp_list_table'] ) ) {

			$bulk_counts = apply_filters( 'portier_network_bulk_site_updated_counts', array(
				'enabled'  => isset( $_REQUEST['enabled']  ) ? absint( $_REQUEST['enabled']  ) : 0,
				'disabled' => isset( $_REQUEST['disabled'] ) ? absint( $_REQUEST['disabled'] ) : 0,
			) );

			$bulk_messages = apply_filters( 'portier_network_bulk_site_updated_messages', array(
				'enabled'  => _n( 'Protection enabled for %d site.',  'Protection enabled for %d sites.',  $bulk_counts['enabled'],  'portier' ),
				'disabled' => _n( 'Protection disabled for %d site.', 'Protection disabled for %d sites.', $bulk_counts['disabled'], 'portier' ),
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
	 *
	 * @return bool This is the network page
	 */
	public function is_network_page() {
		return is_network_admin() && ( isset( $GLOBALS['hook_suffix'] ) && 'settings_page_portier' == $GLOBALS['hook_suffix'] );
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
		if ( $file == portier()->basename ) {
			$links['settings'] = '<a href="' . add_query_arg( 'page', 'portier', 'settings.php' ) . '">' . esc_html__( 'Settings', 'portier' ) . '</a>';
		}

		return $links;
	}

	/** Network Manage Sites ***************************************/

	/**
	 * Setup the sites list table when loading the Network Sites admin page
	 *
	 * @since 1.1.0
	 */
	public function load_admin_page_sites() {
		global $wp_list_table, $pagenum;

		// Bail when not loading the Network Sites page
		if ( ! isset( $_GET['tab'] ) || 'sites' != $_GET['tab'] )
			return;

		// Setup list table globals
		$wp_list_table = _get_portier_network_sites_list_table();
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
		if ( ! is_a( $wp_list_table, 'Portier_Network_Sites_List_Table' ) )
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
			}

				.widefat th.column-protected {
					font-size: 0; /* Hide column label */
				}

				.widefat .column-protected i.dashicons {
					color: #ddd;
				}

				.widefat .site-protected .column-protected i.dashicons {
					color: #0074a2;
				}

			/* For count columns */
			.widefat [class*="column-allowed-"] {
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
	 * @global object $wp_list_table
	 *
	 * @uses apply_filters() Calls 'portier_network_sites_uri_args'
	 */
	public function admin_page_sites() {
		global $wp_list_table;

		// Bail when Portier is only active for the network
		if ( portier_is_network_only() ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Portier is only active for the network. There are no site settings here.', 'portier' ) . '</p></div>';
			return;
		} 

		// Load list table items
		$wp_list_table->prepare_items(); 

		// Clean REQUEST_URI
		$_SERVER['REQUEST_URI'] = remove_query_arg( apply_filters( 'portier_network_sites_uri_args', array( 'enabled', 'disabled' ) ), $_SERVER['REQUEST_URI'] ); ?>

		<h2><?php esc_html_e( 'Manage Protection', 'portier' ); ?></h2>

		<form action="<?php echo network_admin_url( 'settings.php' ); ?>" method="get" id="ms-search">
			<input type="hidden" name="page" value="portier" />
			<input type="hidden" name="tab" value="<?php echo $_GET['tab']; ?>" />
			<?php $wp_list_table->search_box( __( 'Search Sites' ), 'site' ); ?>
		</form>

		<form method="post" action="<?php echo network_admin_url( 'edit.php?action=portier_network_sites' ); ?>">
			<?php $wp_list_table->display(); ?>
		</form>

		<?php
	}

	/**
	 * Update network sites settings
	 *
	 * @since 1.0.0
	 *
	 * @uses apply_filters() Calls 'option_page_capability_{$option_page}'
	 * @uses apply_filters() Calls 'portier_network_sites_edit'
	 */
	public function handle_sites_settings() {

		// Define local variable(s)
		$option_page = 'portier_network_sites';

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
		$wp_list_table = _get_portier_network_sites_list_table();
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
						if ( update_option( '_portier_site_protect', ( 'enable' === $doaction ) ? 1 : 0 ) ) {
							$sites++;
						}
						restore_current_blog();
					}
					$goback = add_query_arg( ( 'enable' === $doaction ) ? 'enabled' : 'disabled', $sites, $goback );
				break;

				default :
					$goback = apply_filters( 'portier_network_sites_edit', $goback, $doaction, $site_ids );
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
 * Return the plugin's sites list table class
 *
 * @since 1.1.0
 */
function _get_portier_network_sites_list_table( $args = array() ) {

	// Load list table classes
	require_once( ABSPATH . 'wp-admin/includes/class-wp-ms-sites-list-table.php' );
	require_once( portier()->includes_dir . 'classes/class-portier-network-sites-list-table.php' );

	// Setup the screen argument
	if ( isset( $args['screen'] ) ) {
		$args['screen'] = convert_to_screen( $args['screen'] );
	} elseif ( isset( $GLOBALS['hook_suffix'] ) ) {
		$args['screen'] = get_current_screen();
	} else {
		$args['screen'] = null;
	}

	return new Portier_Network_Sites_List_Table( $args );
}
