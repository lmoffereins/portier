<?php

/**
 * Portier Admin Functions
 *
 * @package Portier
 * @subpackage Administration
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Portier_Admin' ) ) :
/**
 * The Portier Admin class
 *
 * @since 1.2.0
 */
class Portier_Admin {

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
		add_action( 'admin_init',         array( $this, 'register_settings'     ) );
		add_action( 'admin_menu',         array( $this, 'admin_menu'            ) );
		add_action( 'portier_admin_head', array( $this, 'enqueue_admin_scripts' ) );

		// Plugin links
		add_filter( 'plugin_action_links', array( $this, 'settings_link' ), 10, 2 );

		// Updater
		add_action( 'admin_init', 'portier_setup_updater', 999 );
	}

	/** Public methods ********************************************************/

	/**
	 * Create the plugin admin page menu item
	 *
	 * @since 1.0.0
	 */
	public function admin_menu() {

		// Setup settings page
		$hook = add_options_page(
			esc_html__( 'Portier Settings', 'portier' ),
			esc_html__( 'Portier', 'portier' ),
			'manage_options',
			'portier',
			array( $this, 'admin_page' )
		);

		add_action( "admin_head-$hook",   array( $this, 'admin_head'   ) );
		add_action( "admin_footer-$hook", array( $this, 'admin_footer' ) );
	}

	/**
	 * Enqueue script and style in plugin admin page head
	 *
	 * @since 1.0.0
	 *
	 * @uses do_action() Calls 'portier_admin_head'
	 */
	public function admin_head() {
		do_action( 'portier_admin_head' );
	}

	/**
	 * Output plugin admin page footer contents
	 *
	 * @since 1.0.0
	 *
	 * @uses do_action() Calls 'portier_admin_footer'
	 */
	public function admin_footer() { 
		do_action( 'portier_admin_footer' );
	}

	/**
	 * Output plugin admin page contents
	 *
	 * @since 1.0.0
	 */
	public function admin_page() { ?>

		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Portier', 'portier' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'portier' ); ?>
				<?php do_settings_sections( 'portier' ); ?>
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
	public static function enqueue_admin_scripts() {
		$prtr = portier();

		// Register Chosen when not done already
		if ( ! wp_script_is( 'chosen', 'registered' ) ) {
			wp_register_script( 'chosen', $prtr->assets_url . 'js/chosen/chosen.jquery.min.js', array( 'jquery' ), '1.2.0' );
		}
		wp_enqueue_script( 'chosen' );

		if ( ! wp_style_is( 'chosen', 'registered' ) ) {
			wp_register_style( 'chosen', $prtr->assets_url . 'js/chosen/chosen.min.css', false, '1.2.0' );
		}
		wp_enqueue_style( 'chosen' );

		// WP pointer
		wp_enqueue_script( 'wp-pointer' );
		wp_enqueue_style ( 'wp-pointer' ); 

		// Plugin admin
		wp_register_script( 'portier-admin', $prtr->assets_url . 'js/portier-admin.js', array( 'jquery', 'chosen', 'wp-pointer' ), portier_get_version() );
		wp_enqueue_script ( 'portier-admin' );
		wp_localize_script( 'portier-admin', 'portierAdminL10n', array(
			'pointerContent' => sprintf( '<h3>%s</h3><p>%s</p>',
				esc_html__( 'Site Protection', 'portier' ),
				esc_html__( 'The shield icon will show the current state of the protection of this site. When site protection is active, it is colored accordingly.', 'portier' )
			),
			'settings' => array(
				'showPointer' => is_admin_bar_showing() && current_user_can( 'manage_options' ) && ! in_array( 'portier_protection', explode( ',', get_user_meta( get_current_user_id(), 'dismissed_wp_pointers', true ) ) ),
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
		add_settings_section( 'portier-options-access', esc_html__( 'Access Settings', 'portier' ), 'portier_access_settings_info', 'portier' );

		// Loop all settings to register
		foreach ( portier_settings() as $setting => $args ) {

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
		if ( $file == portier()->basename ) {
			$links['settings'] = '<a href="' . add_query_arg( 'page', 'portier', 'options-general.php' ) . '">' . esc_html__( 'Settings', 'portier' ) . '</a>';
		}

		return $links;
	}
}

/**
 * Setup the admin class
 *
 * @since 1.2.0
 *
 * @uses Portier_Admin
 */
function portier_admin() {
	portier()->admin = new Portier_Admin;
}

endif; // class_exists
