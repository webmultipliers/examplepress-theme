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

// ── Namespace Handoff ─────────────────────────────────────────────
// Point the theme router at this plugin's blocks instead of the theme's.

add_filter( 'examplepress_theme_namespace', fn() => 'examplepress-demo' );

// ── Routing Cascade ───────────────────────────────────────────────
// EXAMPLE CODE — Replace this with your own routing logic.
// Every slug returned here must have a matching template block:
//   app/templates/{slug}/block.json → examplepress-demo/template-{slug}

add_filter( 'examplepress_route_context', function ( $slug ) {
	// Front page / blog home.
	if ( is_front_page() || is_home() ) {
		return 'front';
	}

	// Single posts & pages.
	if ( is_singular() ) {
		return 'single';
	}

	// 404.
	if ( is_404() ) {
		return '404';
	}

	// Fall back to whatever the theme resolved (get-started).
	return $slug;
} );

// ── Route Data Enrichment ─────────────────────────────────────────
// Pass the queried object into template blocks so they can render it.

add_filter( 'examplepress_route_data', function ( $data, $slug ) {
	$data['demo'] = true;

	if ( in_array( $slug, [ 'single' ], true ) ) {
		$data['post'] = get_queried_object();
	}

	return $data;
}, 10, 2 );

// ── Blockstudio Init ──────────────────────────────────────────────
// Register this plugin's block directory with Blockstudio.

add_action( 'init', function () {
	if ( ! class_exists( 'Blockstudio\\Build' ) ) {
		return;
	}

	Blockstudio\Build::init( [
		'dir' => plugin_dir_path( __FILE__ ) . 'app',
	] );
} );
