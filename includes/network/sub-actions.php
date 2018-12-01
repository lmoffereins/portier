<?php

/**
 * Portier Network Sub-action Functions
 *
 * @package Portier
 * @subpackage Multisite
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Provide a hook for loading the portier network settings page
 *
 * @since 1.1.0
 * 
 * @uses do_action() Calls 'portier_network_load_admin'
 */
function portier_network_load_admin() {
	do_action( 'portier_network_load_admin' );
}

/**
 * Provide a hook for the portier network settings page head
 *
 * @since 1.0.0
 * 
 * @uses do_action() Calls 'portier_network_admin_head'
 */
function portier_network_admin_head() {
	do_action( 'portier_network_admin_head' );
}

/**
 * Provide a hook for the portier network settings page footer
 *
 * @since 1.0.0
 * 
 * @uses do_action() Calls 'portier_network_admin_footer'
 */
function portier_network_admin_footer() {
	do_action( 'portier_network_admin_footer' );
}
