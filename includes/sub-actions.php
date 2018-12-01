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
 * @since 1.2.2
 *
 * @uses do_action() Calls 'portier_activation'
 */
function portier_activation() {
	do_action( 'portier_activation' );
}

/**
 * Run dedicated deactivation hook for this plugin
 *
 * @since 1.2.2
 *
 * @uses do_action() Calls 'portier_deactivation'
 */
function portier_deactivation() {
	do_action( 'portier_deactivation' );
}

/**
 * Run dedicated init hook for this plugin
 *
 * @since 1.2.2
 *
 * @uses do_action() Calls 'portier_init'
 */
function portier_init() {
	do_action( 'portier_init' );
}

/**
 * Run dedicated early registration hook for this plugin
 *
 * @since 1.2.2
 *
 * @uses do_action() Calls 'portier_register'
 */
function portier_register() {
	do_action( 'portier_register' );
}
