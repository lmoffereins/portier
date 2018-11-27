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

add_action( 'plugins_loaded', 'portier_loaded',        10    );
add_action( 'init',           'portier_init',          10    );
add_action( 'portier_init',   'portier_register',       0    );
add_filter( 'map_meta_cap',   'portier_map_meta_caps', 10, 4 );
