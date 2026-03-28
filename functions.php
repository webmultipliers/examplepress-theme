<?php
/**
 * ExamplePress functions and definitions.
 */

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
} else {
	error_log( 'Composer autoload file not found. Please run "composer install".' );
}


/**
 * Core Router Logic
 */
function examplepress_get_current_route() {
	$slug = 'index'; // Fallback

	// Allow developers to override the mapping
	// e.g. mapping 'category-news' to 'template-archive'
	return apply_filters( 'examplepress_route_context', "template-{$slug}", $slug );
}