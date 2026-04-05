<?php
/**
 * ExamplePress functions and definitions.
 *
 * The theme is strictly a presentation layer.
 * All platform infrastructure — routing, features, configuration,
 * admin UI, security — lives in the MU Kernel (examplepress-mu).
 */

define( 'EP_THEME_VERSION', wp_get_theme()->get( 'Version' ) ?? '1.0.0' );
define( 'EP_THEME_PATH', get_template_directory() );
define( 'EP_THEME_URI', get_template_directory_uri() );

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

	$template_prefix = function_exists( 'examplepress_get_template_prefix' )
		? examplepress_get_template_prefix()
		: 'template';

	$namespaces   = function_exists( 'examplepress_get_route_origin_namespaces' )
		? examplepress_get_route_origin_namespaces()
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
