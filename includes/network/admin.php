<?php

/**
 * Portier Admin Functions
 *
 * @package Portier
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Portier_Network_Admin' ) ) :
/**
 * The Portier Admin class
 *
 * @since 1.2.0
 */
class Portier_Network_Admin {

	/**
	 * Setup this class
	 *
	 * @since 1.2.0
	 */
	public function __construct() {
		$this->setup_actions();
	}

	/**
	 * Define default actions and filters
	 *
	 * @since 1.2.0
	 */
	private function setup_actions() {

		// Admin
		add_action( 'admin_init',                 array( $this,           'register_settings'     ) );
		add_action( 'network_admin_menu',         array( $this,           'admin_menu'            ) );
		add_action( 'network_admin_notices',      array( $this,           'admin_notices'         ) );
		add_action( 'portier_network_load_admin', array( $this,           'load_admin_page_sites' ) );
		add_action( 'portier_network_admin_head', array( 'Portier_Admin', 'enqueue_admin_scripts' ) );
		add_action( 'portier_network_admin_head', array( $this,           'admin_head_page_sites' ) );
		add_action( 'portier_network_page_sites', array( $this,           'admin_page_sites'      ) );

		// Plugin links
		add_filter( 'network_admin_plugin_action_links', array( $this, 'settings_link' ), 10, 2 );
	}

	/** Public methods ********************************************************/

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

		add_action( "load-$hook",         'portier_network_load_admin'   );
		add_action( "admin_head-$hook",   'portier_network_admin_head'   );
		add_action( "admin_footer-$hook", 'portier_network_admin_footer' );
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
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Portier Network', 'portier' ); ?></h1>

			<?php if ( count( $tabs ) > 1 ) : ?>
			<div class="nav-tab-wrapper">
				<?php foreach ( $tabs as $tab => $label ) :
					printf( '<a class="nav-tab%s" href="%s">%s</a>',
						( $tab == $page_tab ) ? ' nav-tab-active' : '', 
						add_query_arg( array( 'page' => 'portier', 'tab' => $tab ), network_admin_url( 'settings.php' ) ),
						esc_html( $label )
					);
				endforeach; ?>
			</div>
			<?php endif; ?>

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

			.widefat .column-blogname strong {
				display: block;
				margin-bottom: .2em;
				font-size: 14px;
			}

			.widefat .column-blogname strong ~ span {
				font-size: 13px;
				font-style: italic;
				line-height: 1.5em;
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

			/* For detail columns */
			.widefat [class*="column-default_access"],
			.widefat [class*="column-allowed_"] {
				width: 15%;
			}

			.widefat .site-not-protected [class*="column-default_access"] span,
			.widefat .site-not-protected [class*="column-allowed_"] span {
				opacity: .5;
			}

		@media screen and (max-width:782px) {
			.widefat th.column-protected {
				display: none;
			}

			.wp-list-table td.column-protected {
				position: absolute !important;
			}

			.wp-list-table td.column-protected:before {
				content: '' !important;
			}

			.wp-list-table td.column-protected i {
				margin-top: 5px;
			}

			.widefat td.column-blogname {
				padding-left: 38px;
			}
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

/**
 * Setup the extension logic for BuddyPress
 *
 * @since 1.2.0
 *
 * @uses Portier_Network_Admin
 */
function portier_network_admin() {
	portier()->network->admin = new Portier_Network_Admin;
}

endif; // class_exists
