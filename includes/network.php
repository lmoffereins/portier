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
		add_action( 'plugins_loaded',      array( $this, 'network_only'      ), 20    );

		// Protection
		add_action( 'template_redirect',   array( $this, 'network_protect'   ), 0     );
		add_action( 'guard_site_protect',  array( $this, 'network_redirect'  )        );
		add_action( 'admin_bar_menu',      array( $this, 'filter_admin_bar'  ), 99    );
		add_action( 'admin_menu',          array( $this, 'filter_admin_menu' ), 99    );
		add_action( 'get_blogs_of_user',   array( $this, 'blogs_of_user'     ), 10, 3 );
		add_filter( 'user_has_cap',        array( $this, 'user_has_cap'      ), 10, 3 );

		// Admin
		add_action( 'admin_init',          array( $this, 'register_settings'  ) );
		add_action( 'network_admin_menu',  array( $this, 'network_admin_menu' ) );

		// Uninstall hook
		register_uninstall_hook( guard()->file, array( $this, 'network_uninstall' ) );
	}

	/** Plugin *******************************************************/

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

		// Get Guard
		$guard = guard();

		// Unset all Guard single site actions and filters
		remove_action( 'admin_init',        array( $guard, 'register_settings' )    );
		remove_action( 'admin_menu',        array( $guard, 'admin_menu'        )    );
		remove_action( 'template_redirect', array( $guard, 'site_protect'      ), 1 );
		remove_filter( 'login_message',     array( $guard, 'login_message'     ), 1 );
	}

	/** Protection ***************************************************/

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
		if ( ! is_user_logged_in() || ! guard_network_user_is_allowed() )
			auth_redirect();
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
	 * @uses Guard::user_is_allowed()
	 * @uses restore_current_blog()
	 *
	 * @param array $blogs Blogs of user
	 * @param int $user_id User ID
	 * @param boolean $all Whether to return also all hidden blogs
	 * @return array $blogs
	 */
	public function blogs_of_user( $blogs, $user_id, $all ) {

		// All blogs requested
		if ( $all )
			return $blogs;

		foreach ( $blogs as $blog_id => $details ) {
			switch_to_blog( $blog_id );

			if ( get_option( '_guard_site_protect' ) ) {
				if ( ! guard()->user_is_allowed() ) {
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
	 * @uses Guard_Network::network_hide_my_sites()
	 * @uses WP_Admin_Bar::remove_menu()
	 *
	 * @param WP_Admin_Bar $wp_admin_bar
	 */
	public function filter_admin_bar( $wp_admin_bar ) {

		// Remove admin bar menu top item
		if ( guard_network_hide_my_sites() ) {
			$wp_admin_bar->remove_menu( 'my-sites' );
		}
	}

	/**
	 * Modify the admin menu for protected sites
	 *
	 * @since 0.2
	 *
	 * @uses Guard_Network::network_hide_my_sites()
	 * @uses remove_submenu_page()
	 */
	public function filter_admin_menu() {

		// Only removes menu item, not admin page itself
		if ( guard_network_hide_my_sites() ) {
			remove_submenu_page( 'index.php', 'my-sites.php' );
		}
	}

	/**
	 * Modify the user capabilities by filtering 'user_has_cap'
	 *
	 * @since 0.x
	 *
	 * @uses Guard_Network::network_hide_my_sites()
	 *
	 * @param array $allcaps All user caps
	 * @param array $caps Required caps
	 * @param array $args User ID and public function arguments
	 * @return array $allcaps
	 */
	public function user_has_cap( $allcaps, $caps, $args ) {

		// Prevent access to "My Sites" admin page by blocking user cap
		if (   is_admin()
			&& function_exists( 'get_current_screen' )
			&& is_object( get_current_screen() )
			&& 'my-sites' == get_current_screen()->id
			&& guard_network_hide_my_sites()
			&& in_array( 'read', $caps )
		) {
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
	 * @uses add_options_page() To add the menu to the options pane
	 * @uses add_action() To enable functions hooking into admin page
	 *                     head en footer
	 */
	public function network_admin_menu() {

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
		add_settings_section( 'guard-options-main',       __( 'Network Main Settings',       'guard' ), 'guard_network_main_settings_info',       'guard_network' );
		add_settings_section( 'guard-options-access',     __( 'Network Access Settings',     'guard' ), 'guard_network_access_settings_info',     'guard_network' );
		add_settings_section( 'guard-options-additional', __( 'Additional Network Settings', 'guard' ), 'guard_network_additional_settings_info', 'guard_network' );

		// Loop all network settings to register
		foreach ( guard_network_settings() as $setting => $args ) {
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
	 * Handle updating network settings
	 *
	 * @since 0.2
	 *
	 * @uses wp_verify_nonce()
	 * @uses guard_network_settings()
	 * @uses update_site_option()
	 * @uses wp_redirect()
	 */
	public function network_settings_api() {
		if ( ! wp_verify_nonce( $_POST['_wpnonce'], 'guard_network-options' ) )
			return;

		// Loop all network settings to update
		foreach ( guard_network_settings() as $option => $args ) {
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
	 * @uses Guard_Network::is_network_page()
	 */
	public function network_admin_notice() {
		if ( ! $this->is_network_page() )
			return;

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
	 * @uses Multisite()
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

endif; // class_exists
