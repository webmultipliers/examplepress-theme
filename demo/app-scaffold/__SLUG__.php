<?php
/**
 * Plugin Name: __NAME__
 * Description: __DESC__
 * Version: 0.1.0
 * Theme: examplepress-theme
 * Troy:
 * Requires at least: 6.9
 * Requires PHP: 8.4
 * Author: ExamplePress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── Namespace Handoff ─────────────────────────────────────────────
// Point the theme router at this plugin's blocks instead of the theme's.

add_filter( 'examplepress_theme_namespace', fn() => '__SLUG__' );

// ── Routing Cascade ───────────────────────────────────────────────
// Every slug returned here must have a matching template block:
//   app/templates/{slug}/block.json → __SLUG__/template-{slug}

add_filter( 'examplepress_route_context', function ( $slug ) {
	if ( is_front_page() || is_home() ) {
		return 'front';
	}

	return $slug;
} );

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
