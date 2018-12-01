<?php

/**
 * Portier Actions
 *
 * @package Portier
 * @subpackage Main
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

/** Sub-actions ***************************************************************/

add_action( 'init',         'portier_init',       10 );
add_action( 'portier_init', 'portier_register',    0 );

/** Extensions ****************************************************************/

add_action( 'bp_loaded',    'portier_buddypress', 10 );
