<?php
/**
 * ExamplePress functions and definitions.
 *
 * The theme is strictly a presentation layer.
 * All platform infrastructure — routing, features, configuration,
 * admin UI, security — lives in the MU Kernel (examplepress-mu).
 */

use ExamplePress\MU\Infrastructure\Router;

define( 'EP_THEME_VERSION', wp_get_theme()->get( 'Version' ) ?? '1.0.0' );

/**
 * Minimum kernel API contract version this theme requires.
 * See Router::API_VERSION in examplepress-mu.
 */
define( 'EP_THEME_MIN_KERNEL_API', 1 );

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

/**
 * Assert kernel API compatibility at boot.
 *
 * If the kernel is too old (or missing API_VERSION), surface an admin notice.
 * The router block has its own per-request fallback for the missing-kernel case.
 */
add_action( 'admin_notices', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( ! class_exists( Router::class ) ) {
		return; // router block already handles this on the front-end
	}
	$kernel_api = defined( Router::class . '::API_VERSION' ) ? Router::API_VERSION : 0;
	if ( $kernel_api < EP_THEME_MIN_KERNEL_API ) {
		printf(
			'<div class="notice notice-error"><p><strong>ExamplePress:</strong> theme requires kernel API version %d or higher (found %d). Update <code>examplepress-mu</code>.</p></div>',
			(int) EP_THEME_MIN_KERNEL_API,
			(int) $kernel_api
		);
	}
} );
