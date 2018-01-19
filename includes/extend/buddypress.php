<?php

/**
 * Portier BuddyPress Extension Class
 * 
 * @package Portier
 * @subpackage Extend
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Portier_BuddyPress' ) ) :
/**
 * BuddyPress extension for Portier
 *
 * @since 1.0.0
 */
class Portier_BuddyPress {

	/**
	 * Class constructor
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->setup_globals();
		$this->setup_actions();
	}

	/**
	 * Define default class globals
	 *
	 * @since 1.0.0
	 */
	public function setup_globals() {
		$this->bp_group_hierarchy = defined( 'BP_GROUP_HIERARCHY_VERSION' );
	}

	/**
	 * Define default class hooks
	 * 
	 * @since 1.0.0
	 */
	public function setup_actions() {

		// Settings
		add_filter( 'portier_settings',         array( $this, 'register_settings' ) );
		add_filter( 'portier_network_settings', array( $this, 'register_settings' ) );

		// Filter user access
		add_filter( 'portier_is_user_allowed',         array( $this, 'is_user_allowed' ), 10, 3 );
		add_filter( 'portier_network_is_user_allowed', array( $this, 'is_user_allowed' ), 10, 2 );

		// Admin
		add_filter( 'portier_get_protection_details',      array( $this, 'protection_details'  )        );
		add_filter( 'portier_network_sites_columns',       array( $this, 'sites_columns'       )        );
		add_action( 'portier_network_sites_custom_column', array( $this, 'sites_custom_column' ), 10, 2 );
	}

	/**
	 * Register BuddyPress extension settings
	 *
	 * @since 1.0.0
	 * 
	 * @param array $settings Settings
	 * @return array Settings
	 */
	public function register_settings( $settings ) {

		// Get whether these are the network settings
		$network = current_filter() == 'portier_network_settings';

		// Member types
		if ( bp_get_member_types() ) {

			// Allowed member types
			$settings['_portier_bp_allowed_member_types'] = array(
				'label'             => __( 'Allowed Member Types', 'portier' ),
				'callback'          => array( $this, 'setting_allowed_member_types' ),
				'section'           => 'portier-options-access',
				'page'              => $network ? 'portier_network' : 'portier',
				'sanitize_callback' => false
			);
		}

		// Groups component
		if ( bp_is_active( 'groups' ) ) {

			// Allowed groups
			$settings['_portier_bp_allowed_groups'] = array(
				'label'             => __( 'Allowed groups', 'portier' ),
				'callback'          => array( $this, 'setting_allowed_groups' ),
				'section'           => 'portier-options-access',
				'page'              => $network ? 'portier_network' : 'portier',
				'sanitize_callback' => 'portier_setting_sanitize_ids'
			);
		}

		return $settings;
	}

	/**
	 * Output the settings field for allowed member types
	 *
	 * @since 1.2.0
	 */
	public function setting_allowed_member_types() {

		// Get available types
		$types = bp_get_member_types( array(), 'objects' );

		// Get selected types
		$getter   = is_network_admin() ? 'get_site_option' : 'get_option';
		$selected = (array) call_user_func_array( $getter, array( '_portier_bp_allowed_member_types', array() ) ); ?>

		<select id="_portier_bp_allowed_member_types" name="_portier_bp_allowed_member_types[]" class="chzn-select" multiple style="width:25em;" data-placeholder="<?php _e( 'Select a type', 'portier' ); ?>">

			<?php foreach ( $types as $type => $args ) : ?>
				<option value="<?php echo $type; ?>" <?php selected( in_array( $type, $selected ) ); ?>><?php echo $args->labels['name']; ?></option>
			<?php endforeach; ?>

		</select>
		<label for="_portier_bp_allowed_member_types"><?php _e( "Select the member types of which users will have access", 'portier' ); ?></label>

		<?php
	}

	/**
	 * Output the settings field for allowed groups
	 *
	 * @since 1.0.0
	 */
	public function setting_allowed_groups() {

		// Get available groups
		$groups = groups_get_groups( array( 'show_hidden' => true ) );
		$groups = $groups['groups'];

		// Get selected groups
		$getter   = is_network_admin() ? 'get_site_option' : 'get_option';
		$selected = (array) call_user_func_array( $getter, array( '_portier_bp_allowed_groups', array() ) ); ?>

		<select id="_portier_bp_allowed_groups" name="_portier_bp_allowed_groups[]" class="chzn-select" multiple style="width:25em;" data-placeholder="<?php _e( 'Select a group', 'portier' ); ?>">

			<?php foreach ( $groups as $group ) : ?>
				<option value="<?php echo $group->id; ?>" <?php selected( in_array( $group->id, $selected ) ); ?>><?php echo $group->name; ?></option>
			<?php endforeach; ?>

		</select>
		<label for="_portier_bp_allowed_groups"><?php _e( "Select the groups whose members will have access", 'portier' ); ?></label>

		<?php
	}

	/**
	 * Return the site's allowed member types
	 *
	 * @since 1.2.0
	 *
	 * @uses apply_filters() Calls 'portier_bp_get_allowed_member_types'
	 *
	 * @param int $site_id Optional. Site ID
	 * @return array The site's types
	 */
	public function get_allowed_member_types( $site_id = 0 ) {

		// Switch to site
		if ( ! empty( $site_id ) && is_multisite() ) {
			$site_id = (int) $site_id;
			switch_to_blog( $site_id );
		}

		$types = (array) get_option( '_portier_bp_allowed_member_types', array() );

		// Restore switched site
		if ( ! empty( $site_id ) && is_multisite() ) {
			restore_current_blog();
		}

		return (array) apply_filters( 'portier_bp_get_allowed_member_types', $types, $site_id );
	}

	/**
	 * Return the network's allowed member types
	 *
	 * @since 1.2.0
	 *
	 * @uses apply_filters() Calls 'portier_bp_get_network_allowed_member_types'
	 * 
	 * @return array Network's allowed types
	 */
	public function get_network_allowed_member_types() {
		return (array) apply_filters( 'portier_bp_get_network_allowed_member_types', (array) get_site_option( '_portier_bp_allowed_member_types', array() ) );
	}

	/**
	 * Return the site's allowed user groups
	 *
	 * @since 1.0.0
	 *
	 * @uses apply_filters() Calls 'portier_bp_get_allowed_groups'
	 * 
	 * @param int $site_id Optional. Site ID
	 * @return array The site's groups
	 */
	public function get_allowed_groups( $site_id = 0 ) {

		// Switch to site
		if ( ! empty( $site_id ) && is_multisite() ) {
			$site_id = (int) $site_id;
			switch_to_blog( $site_id );
		}

		$groups = (array) get_option( '_portier_bp_allowed_groups', array() );

		// Restore switched site
		if ( ! empty( $site_id ) && is_multisite() ) {
			restore_current_blog();
		}

		return (array) apply_filters( 'portier_bp_get_allowed_groups', $groups, $site_id );
	}

	/**
	 * Return the network's allowed user groups
	 *
	 * @since 1.0.0
	 *
	 * @uses apply_filters() Calls 'portier_bp_get_network_allowed_groups'
	 * 
	 * @return array Network's allowed groups
	 */
	public function get_network_allowed_groups() {
		return (array) apply_filters( 'portier_bp_get_network_allowed_groups', (array) get_site_option( '_portier_bp_allowed_groups', array() ) );
	}

	/**
	 * Filter whether the user is allowed based on group membership
	 *
	 * @since 1.0.0
	 * 
	 * @param bool $allowed Is the user allowed
	 * @param int $user_id User ID
	 * @param int $site_id Optional. Site ID
	 * @return bool User is allowed
	 */
	public function is_user_allowed( $allowed, $user_id, $site_id = 0 ) {

		// Check for member types
		if ( ! $allowed && bp_get_member_types() ) {
			$getter = current_filter() == 'portier_network_is_user_allowed' ? 'get_network_allowed_member_types' : 'get_allowed_member_types';
			$types  = call_user_func_array( array( $this, $getter ), array( $site_id ) );

			foreach ( $types as $type ) {
				if ( bp_has_member_type( $user_id, $type ) ) {
					$allowed = true;
					break;
				}
			}
		}

		// Check for groups
		if ( ! $allowed && bp_is_active( 'groups' ) ) {

			// Get the allowed groups
			$getter    = current_filter() == 'portier_network_is_user_allowed' ? 'get_network_allowed_groups' : 'get_allowed_groups';
			$group_ids = call_user_func_array( array( $this, $getter ), array( $site_id ) );

			// Only check for selected groups
			if ( ! empty( $group_ids ) ) {

				// Account for group hierarchy
				if ( $this->bp_group_hierarchy ) {

					// Walk hierarchy
					$hierarchy = new ArrayIterator( $group_ids );
					foreach ( $hierarchy as $gid ) {

						// Add child group ids when found
						if ( $children = @BP_Groups_Hierarchy::has_children( $gid ) ) {
							foreach ( $children as $child_id )
								$hierarchy->append( (int) $child_id );
						}
					}

					// Set hierarchy group id collection
					$group_ids = $hierarchy->getArrayCopy();
				}

				// Find any group memberships
				$groups = groups_get_groups( array(
					'user_id'         => $user_id,
					'include'         => $group_ids,
					'show_hidden'     => true,
					'per_page'        => false,
					'populate_extras' => false,
				) );

				// Allow when the user's group(s) were found
				$allowed = ! empty( $groups['groups'] );
			}
		}

		return $allowed;
	}

	/**
	 * Modify the site's protection details
	 *
	 * @since 1.0.0
	 * 
	 * @param string $title Site protection details
	 * @return string Site protection details
	 */
	public function protection_details( $title ) {

		// Get allowed member type count
		if ( bp_get_member_types() ) {
			$allowed_type_count = count( $this->get_allowed_member_types() );
			$title .= "\n" . sprintf( _n( '%d allowed member type', '%d allowed member types', $allowed_type_count, 'portier' ), $allowed_type_count );
		}

		// Get allowed group count
		if ( bp_is_active( 'groups' ) ) {
			$allowed_group_count = count( $this->get_allowed_groups() );
			$title .= "\n" . sprintf( _n( '%d allowed group', '%d allowed groups', $allowed_group_count, 'portier' ), $allowed_group_count );
		}

		return $title;
	}

	/**
	 * Append custom sites columns
	 *
	 * @since 1.1.0
	 * 
	 * @param array $columns Columns
	 * @return array Columns
	 */
	public function sites_columns( $columns ) {

		// Allowed member types
		if ( bp_get_member_types() ) {
			$columns['allowed-bp-member-types'] = esc_html__( 'Allowed Member Types', 'portier' );
		}

		// Allowed groups
		if ( bp_is_active( 'groups' ) ) {
			$columns['allowed-bp-groups'] = esc_html__( 'Allowed Groups', 'portier' );
		}

		return $columns;
	}

	/**
	 * Output the custom columns content
	 *
	 * @since 1.1.0
	 * 
	 * @param string $column_name Column name
	 * @param int $site_id Site ID
	 */
	public function sites_custom_column( $column_name, $site_id ) {

		switch ( $column_name ) {

			// Allowed member types
			case 'allowed-bp-member-types' :
				$types = get_option( '_portier_bp_allowed_member_types', array() );
				$count = count( $types );
				$title = implode( ', ', wp_list_pluck( wp_list_pluck( array_map( 'bp_get_member_type_object', array_slice( $types, 0, 5 ) ), 'labels' ), 'name' ) );
				if ( 0 < $count - 5 ) {
					$title = sprintf( esc_html__( '%s and %d more', 'portier' ), $title, $count - 5 );
				} ?>

				<span class="count" title="<?php echo $title; ?>"><?php printf( _n( '%d type', '%d types', $count, 'portier' ), $count ); ?></span>
				
				<?php
				break;

			// Allowed groups
			case 'allowed-bp-groups' :
				$groups = get_option( '_portier_bp_allowed_groups', array() );
				$count  = count( $groups );
				$title  = implode( ', ', wp_list_pluck( array_map( 'groups_get_group', array_map( function( $id ) { return array( 'group_id' => $id ); }, array_slice( $groups, 0, 5 ) ) ), 'name' ) );
				if ( 0 < $count - 5 ) {
					$title = sprintf( esc_html__( '%s and %d more', 'portier' ), $title, $count - 5 );
				} ?>

				<span class="count" title="<?php echo $title; ?>"><?php printf( _n( '%d group', '%d groups', $count, 'portier' ), $count ); ?></span>
				
				<?php
			break;
		}
	}
}

endif;
