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

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

/**
 * Theme setup.
 */
add_action( 'after_setup_theme', function () {
	load_theme_textdomain( 'examplepress-theme', EP_THEME_PATH . '/languages' );
} );

/**
 * Blockstudio integration.
 */
add_filter( 'blockstudio/patterns/paths', function ( $paths ) {
	$paths[] = EP_THEME_PATH . '/blockstudio/patterns';
	return $paths;
} );

add_filter( 'blockstudio/blocks/components/inner_blocks/frontend/wrap', function ( $render, $block ) {

	if ( strpos( $block->name, 'examplepress-theme/router' ) === 0 ) {
		$render = false;
	}

	$template_prefix = class_exists( Router::class )
		? Router::templatePrefix()
		: 'template';

	$namespaces   = class_exists( RouteRegistry::class )
		? RouteRegistry::namespaces()
		: [];
	$namespaces[] = 'examplepress-theme';
	$namespaces   = array_unique( $namespaces );

	foreach ( $namespaces as $ns ) {
		if ( strpos( $block->name, sprintf( '%s/%s-', $ns, $template_prefix ) ) === 0 ) {
			$render = false;
			break;
		}
	}

	return $render;
}, 10, 2 );
