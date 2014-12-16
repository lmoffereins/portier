<?php

/**
 * Guard BuddyPress Extension Class
 * 
 * @package Guard
 * @subpackage Extend
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Guard_BuddyPress' ) ) :
/**
 * BuddyPress extension for Guard
 *
 * @since 1.0.0
 */
class Guard_BuddyPress {

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
	 *
	 * @uses add_filter()
	 * @uses bp_is_active()
	 */
	public function setup_actions() {

		// Settings
		add_filter( 'guard_settings',         array( $this, 'register_settings' ) );
		add_filter( 'guard_network_settings', array( $this, 'register_settings' ) );

		// Groups component
		if ( bp_is_active( 'groups' ) ) {

			// Filter user access
			add_filter( 'guard_is_user_allowed',         array( $this, 'is_user_allowed' ), 10, 3 );
			add_filter( 'guard_network_is_user_allowed', array( $this, 'is_user_allowed' ), 10, 2 );

			// Admin
			add_filter( 'guard_get_protection_details',      array( $this, 'protection_details'  )        );
			add_filter( 'guard_network_sites_columns',       array( $this, 'sites_columns'       )        );
			add_action( 'guard_network_sites_custom_column', array( $this, 'sites_custom_column' ), 10, 2 );
		}
	}

	/**
	 * Register BuddyPress extension settings
	 *
	 * @since 1.0.0
	 *
	 * @uses current_filter()
	 * @uses bp_is_active()
	 * 
	 * @param array $settings Settings
	 * @return array Settings
	 */
	public function register_settings( $settings ) {

		// Get whether these are the network settings
		$network = current_filter() == 'guard_network_settings';

		// Groups component
		if ( bp_is_active( 'groups' ) ) {

			// Allowed groups
			$settings['_guard_bp_allowed_groups'] = array(
				'label'             => __( 'Allowed groups', 'guard' ),
				'callback'          => array( $this, 'setting_allowed_groups' ),
				'section'           => 'guard-options-access',
				'page'              => $network ? 'guard_network' : 'guard',
				'sanitize_callback' => 'guard_setting_sanitize_ids'
			);
		}

		return $settings;
	}

	/**
	 * Output the settings field for allowed groups
	 *
	 * @since 1.0.0
	 *
	 * @uses groups_get_groups()
	 * @uses Guard_Network::is_network_page()
	 * @uses get_site_option()
	 * @uses get_option()
	 */
	public function setting_allowed_groups() { 

		// Get available groups
		$groups = groups_get_groups( array( 'show_hidden' => true ) );
		$groups = $groups['groups'];
		
		// Get selected groups
		$getter   = guard()->network->is_network_page() ? 'get_site_option' : 'get_option';
		$selected = call_user_func_array( $getter, array( '_guard_bp_allowed_groups', array() ) ); ?>

		<select id="_guard_bp_allowed_groups" name="_guard_bp_allowed_groups[]" class="chzn-select" multiple style="width:25em;" data-placeholder="<?php _e( 'Select a group', 'guard' ); ?>">

			<?php foreach ( $groups as $group ) : ?>
				<option value="<?php echo $group->id; ?>" <?php selected( in_array( $group->id, (array) $selected ) ); ?>><?php echo $group->name; ?></option>
			<?php endforeach; ?>

		</select>
		<label for="_guard_bp_allowed_groups"><?php _e( "Select the groups whose members will have access", 'guard' ); ?></label>

		<?php
	}

	/**
	 * Return the site's allowed user groups
	 *
	 * @since 1.0.0
	 *
	 * @uses is_multisite()
	 * @uses switch_to_blog()
	 * @uses get_option()
	 * @uses restore_current_blog()
	 * @uses apply_filters() Calls 'guard_bp_get_allowed_groups'
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

		$groups = (array) get_option( '_guard_bp_allowed_groups', array() );

		// Restore switched site
		if ( ! empty( $site_id ) && is_multisite() ) {
			restore_current_blog();
		}

		return (array) apply_filters( 'guard_bp_get_allowed_groups', $groups, $site_id );
	}

	/**
	 * Return the network's allowed user groups
	 *
	 * @since 1.0.0
	 *
	 * @uses get_site_option()
	 * @uses apply_filters() Calls 'guard_bp_get_network_allowed_groups'
	 * 
	 * @return array Network's allowed groups
	 */
	public function get_network_allowed_groups() {
		return (array) apply_filters( 'guard_bp_get_network_allowed_groups', (array) get_site_option( '_guard_bp_allowed_groups', array() ) );
	}

	/**
	 * Filter whether the user is allowed based on group membership
	 *
	 * @since 1.0.0
	 * 
	 * @uses current_filter()
	 * @uses Guard_BuddyPress::get_network_allowed_groups()
	 * @uses Guard_BuddyPress::get_allowed_groups()
	 * @uses groups_get_groups()
	 * 
	 * @param bool $allowed Is the user allowed
	 * @param int $user_id User ID
	 * @param int $site_id Optional. Site ID
	 * @return bool User is allowed
	 */
	public function is_user_allowed( $allowed, $user_id, $site_id = 0 ) {

		// Only check when the user isn't allowed already
		if ( ! $allowed ) {

			// Get the allowed groups
			$getter    = current_filter() == 'guard_network_is_user_allowed' ? 'get_network_allowed_groups' : 'get_allowed_groups';
			$group_ids = call_user_func_array( array( $this, $getter ), array( $site_id ) );

			// Only check for selected groups
			if ( ! empty( $group_ids ) ) {

				// Account for group hierarchy
				if ( $this->bp_group_hierarchy ) {

					// Walk hierarchy
					$hierarchy = new ArrayIterator( $group_ids );
					foreach ( $hierarchy as $gid ) {

						// Add child group ids when found
						if ( $children = BP_Groups_Hierarchy::has_children( $gid ) ) {
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

		// Append a dividing sign
		if ( ! empty( $title ) ) {
			$title .= '; ';
		}

		// Get allowed group count
		$allowed_group_count = count( $this->get_allowed_groups() );
		$title .= sprintf( _n( '%d allowed group', '%d allowed groups', $allowed_group_count, 'guard' ), $allowed_group_count );

		return $title;
	}

	/**
	 * Append the Allowed Groups sites column
	 *
	 * @since 1.1.0
	 * 
	 * @param array $columns Columns
	 * @return array Columns
	 */
	public function sites_columns( $columns ) {
		$columns['allowed-groups'] = __( 'Allowed Groups', 'guard' );
		return $columns;
	}

	/**
	 * Output the Allowed Groups column content
	 *
	 * @since 1.1.0
	 * 
	 * @param string $column_name Column name
	 * @param int $site_id Site ID
	 */
	public function sites_custom_column( $column_name, $site_id ) {

		// Bail when this is not our column
		if ( 'allowed-groups' != $column_name )
			return;

		$groups = get_option( '_guard_bp_allowed_groups', array() );
		$count  = count( $groups );
		$title  = implode( ', ', wp_list_pluck( array_map( 'groups_get_group', array_map( function( $id ) { return array( 'group_id' => $id ); }, array_slice( $groups, 0, 5 ) ) ), 'name' ) );
		if ( 0 < $count - 5 ) {
			$title = sprintf( __( '%s and %d more', 'guard' ), $title, $count - 5 );
		} ?>

		<span class="count" title="<?php echo $title; ?>"><?php printf( _n( '%d group', '%d groups', $count, 'guard' ), $count ); ?></span>

		<?php
	}
}

endif;
