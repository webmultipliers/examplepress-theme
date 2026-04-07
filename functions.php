<?php
/**
 * ExamplePress functions and definitions.
 *
 * The theme is strictly a presentation layer.
 * All platform infrastructure — routing, features, configuration,
 * admin UI, security — lives in the MU Kernel (examplepress-mu).
 */

use ExamplePress\MU\Infrastructure\Router;
use ExamplePress\MU\Infrastructure\RouteRegistry;

define( 'EP_THEME_VERSION', wp_get_theme()->get( 'Version' ) ?? '1.0.0' );

if ( ! defined( 'EP_THEME_PATH' ) ) {
	define( 'EP_THEME_PATH', get_template_directory() );
}

if ( ! defined( 'EP_THEME_URI' ) ) {
	define( 'EP_THEME_URI', get_template_directory_uri() );
}

/**
 * Theme setup.
 */
add_action( 'after_setup_theme', function () {
	load_theme_textdomain( 'examplepress-theme', EP_THEME_PATH . '/languages' );
} );
