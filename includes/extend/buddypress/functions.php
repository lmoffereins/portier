<?php

/**
 * Portier BuddyPress Functions
 *
 * @package Portier
 * @subpackage BuddyPress
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Protection ****************************************************************/

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
function portier_bp_get_allowed_member_types( $site_id = 0 ) {

	// Switch site?
	$switched = ! empty( $site_id ) && is_multisite() ? switch_to_blog( $site_id ) : false;

	// Get allowed member types
	$types = array_filter( (array) get_option( '_portier_bp_allowed_member_types', array() ) );

	// Reset switched site
	if ( $switched ) {
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
 * @return array Network's allowed types
 */
function portier_bp_get_network_allowed_member_types() {
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
function portier_bp_get_allowed_groups( $site_id = 0 ) {

	// Switch site?
	$switched = ! empty( $site_id ) && is_multisite() ? switch_to_blog( $site_id ) : false;

	// Get allowed groups
	$groups = array_filter( (array) get_option( '_portier_bp_allowed_groups', array() ) );

	// Reset switched site
	if ( $switched ) {
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
 * @return array Network's allowed groups
 */
function portier_bp_get_network_allowed_groups() {
	return (array) apply_filters( 'portier_bp_get_network_allowed_groups', (array) get_site_option( '_portier_bp_allowed_groups', array() ) );
}

/** Settings ******************************************************************/

/**
 * Output the settings field for allowed member types
 *
 * @since 1.2.0
 */
function portier_bp_setting_allowed_member_types() {

	// Get available types
	$types = bp_get_member_types( array(), 'objects' );

	// Get selected types
	$getter   = is_network_admin() ? 'get_site_option' : 'get_option';
	$selected = (array) call_user_func_array( $getter, array( '_portier_bp_allowed_member_types', array() ) ); ?>

	<select id="_portier_bp_allowed_member_types" name="_portier_bp_allowed_member_types[]" class="chzn-select" multiple style="width:25em;" data-placeholder="<?php esc_html_e( 'Select a type', 'portier' ); ?>">

		<?php foreach ( $types as $type => $args ) : ?>
			<option value="<?php echo $type; ?>" <?php selected( in_array( $type, $selected ) ); ?>><?php echo $args->labels['name']; ?></option>
		<?php endforeach; ?>

	</select>
	<label for="_portier_bp_allowed_member_types"><?php esc_html_e( 'Select the member types of which users will have access', 'portier' ); ?></label>

	<?php
}

/**
 * Output the settings field for allowed groups
 *
 * @since 1.0.0
 */
function portier_bp_setting_allowed_groups() {

	// Get available groups
	$groups = groups_get_groups( array( 'show_hidden' => true ) );
	$groups = $groups['groups'];

	// Get selected groups
	$getter   = is_network_admin() ? 'get_site_option' : 'get_option';
	$selected = (array) call_user_func_array( $getter, array( '_portier_bp_allowed_groups', array() ) ); ?>

	<select id="_portier_bp_allowed_groups" name="_portier_bp_allowed_groups[]" class="chzn-select" multiple style="width:25em;" data-placeholder="<?php esc_html_e( 'Select a group', 'portier' ); ?>">

		<?php foreach ( $groups as $group ) : ?>
			<option value="<?php echo $group->id; ?>" <?php selected( in_array( $group->id, $selected ) ); ?>><?php echo $group->name; ?></option>
		<?php endforeach; ?>

	</select>
	<label for="_portier_bp_allowed_groups"><?php esc_html_e( 'Select the groups whose members will have access', 'portier' ); ?></label>

	<?php
}
