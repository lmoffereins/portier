<?php

/**
 * Portier Sub-action Functions
 *
 * @package Portier
 * @subpackage Main
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/**
 * Run dedicated activation hook for this plugin
 *
 * @since 1.3.0
 *
 * @uses do_action() Calls 'portier_activation'
 */
function portier_activation() {
	do_action( 'portier_activation' );
}

/**
 * Run dedicated deactivation hook for this plugin
 *
 * @since 1.3.0
 *
 * @uses do_action() Calls 'portier_deactivation'
 */
function portier_deactivation() {
	do_action( 'portier_deactivation' );
}

/**
 * Run dedicated loaded hook for this plugin
 *
 * @since 1.3.0
 *
 * @uses do_action() Calls 'portier_loaded'
 */
function portier_loaded() {
	do_action( 'portier_loaded' );
}

/**
 * Run dedicated init hook for this plugin
 *
 * @since 1.3.0
 *
 * @uses do_action() Calls 'portier_init'
 */
function portier_init() {
	do_action( 'portier_init' );
}

/**
 * Run dedicated early registration hook for this plugin
 *
 * @since 1.3.0
 *
 * @uses do_action() Calls 'portier_register'
 */
function portier_register() {
	do_action( 'portier_register' );
}

/**
 * Run dedicated map meta caps filter for this plugin
 *
 * @since 1.3.0
 *
 * @uses apply_filters() Calls 'portier_map_meta_caps'
 *
 * @param array $caps Mapped caps
 * @param string $cap Required capability name
 * @param int $user_id User ID
 * @param array $args Additional arguments
 * @return array Mapped caps
 */
function portier_map_meta_caps( $caps = array(), $cap = '', $user_id = 0, $args = array() ) {
	return apply_filters( 'portier_map_meta_caps', $caps, $cap, $user_id, $args );
}
