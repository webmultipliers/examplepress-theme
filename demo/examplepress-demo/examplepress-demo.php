<?php
/**
 * Plugin Name: ExamplePress Demo
 * Description: Disposable demo companion plugin showing the routing contract, namespace handoff, and template block pattern. Install via the ExamplePress settings page, inspect the source, then scaffold your own.
 * Version: 1.0.0
 * Requires at least: 6.9
 * Requires PHP: 8.4
 * Author: ExamplePress
 * ExamplePress Demo: true
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Route Origin Registration ────────────────────────────────────
// Register which routes this plugin handles and under what namespace.
// Multiple companion plugins can coexist — each declares only the
// routes it owns. The router evaluates conditions and picks the first
// matching origin per request.

if ( function_exists( 'examplepress_register_route_origin' ) ) {
	$__ep_config   = json_decode( file_get_contents( __DIR__ . '/examplepress.json' ), true ) ?: [];
	$__ep_priority = (int) ( $__ep_config['routing']['priority'] ?? 10 );

	examplepress_register_route_origin( 'examplepress-demo', [
		'front'  => fn() => is_front_page() || is_home(),
		'single' => fn() => is_singular(),
		'404'    => fn() => is_404(),
	], $__ep_priority );

	unset( $__ep_config, $__ep_priority );
}

// ── Route Data Enrichment ─────────────────────────────────────────

add_filter( 'examplepress_route_data', function ( $data, $slug ) {
	$data['demo'] = true;

	if ( in_array( $slug, [ 'single' ], true ) ) {
		$data['post'] = get_queried_object();
	}

	return $data;
}, 10, 2 );

// ── Blockstudio Init ──────────────────────────────────────────────

add_action( 'init', function () {
	if ( ! class_exists( 'Blockstudio\\Build' ) ) {
		return;
	}

	Blockstudio\Build::init( [
		'dir' => plugin_dir_path( __FILE__ ) . 'app',
	] );
} );
