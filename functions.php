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
 * Theme Setup
 */
add_action(
	'after_setup_theme',
	function () {

		add_theme_support( 'title-tag' );
		add_theme_support( 'responsive-embeds' );
		add_theme_support( 'post-thumbnails' );
		add_theme_support( 'wp-block-styles' );

		add_theme_support( 'html5', [
			'caption',
			'comment-form',
			'comment-list',
			'gallery',
			'search-form',
			'script',
			'style',
		] );

		remove_theme_support( 'core-block-patterns' );
	}
);



add_filter( 'blockstudio/patterns/paths', function ( $paths ) {
	$paths[] = EP_THEME_PATH . '/blockstudio/patterns';
	return $paths;
} );


/**
 * Disable Remote Block Patterns
 */
add_filter( 'should_load_remote_block_patterns', '__return_false' );


function examplepress_get_current_route() {

	$slug = 'get-started';

	return apply_filters( 'examplepress_route_context', $slug, $slug );
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