<?php
/**
 * ExamplePress functions and definitions.
 */

define( 'EP_THEME_VERSION', wp_get_theme()->get( 'Version' ) ?? '1.0.0' );
define( 'EP_THEME_PATH', get_template_directory() );
define( 'EP_THEME_URI', get_template_directory_uri() );

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	error_log( 'Composer autoload file not found. Please run "composer install".' );
}

/**
 * Feature Registry
 */
require_once EP_THEME_PATH . '/inc/feature-registry.php';
require_once EP_THEME_PATH . '/inc/features.php';

/**
 * Theme Setup
 */
add_action( 'after_setup_theme', 'examplepress_boot_features' );

/**
 * Blockstudio
 */
add_filter( 'blockstudio/patterns/paths', function ( $paths ) {
	$paths[] = EP_THEME_PATH . '/blockstudio/patterns';
	return $paths;
} );

add_filter( 'blockstudio/blocks/components/inner_blocks/frontend/wrap', function ( $render, $block ) {

	if ( strpos( $block->name, 'examplepress-theme/router' ) === 0 ) {
		$render = false;
	}

	$template_prefix = examplepress_get_template_prefix();
	$theme_ns        = examplepress_get_theme_namespace();

	if ( strpos( $block->name, sprintf( '%s/%s-', $theme_ns, $template_prefix ) ) === 0 ) {
		$render = false;
	}

	return $render;
}, 10, 2 );

/**
 * Routing Helpers
 */
function examplepress_get_current_route() {
	return apply_filters( 'examplepress_route_context', 'get-started' );
}

function examplepress_get_theme_namespace() {
	return apply_filters( 'examplepress_theme_namespace', 'examplepress-theme' );
}

function examplepress_get_template_prefix() {
	return apply_filters( 'examplepress_template_prefix', 'template' );
}

function examplepress_get_template_block_name( $slug, $prefix, $theme_ns ) {
	return apply_filters( 'examplepress_template_block_name', sprintf( '%s/%s-%s', $theme_ns, $prefix, $slug ), $slug, $prefix, $theme_ns );
}

