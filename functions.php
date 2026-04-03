<?php
/**
 * ExamplePress functions and definitions.
 *
 * This file is pure bootstrap: constants, autoloader, requires,
 * and the after_setup_theme hook. No loose logic belongs here.
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
 * Core includes.
 */
require_once EP_THEME_PATH . '/inc/config.php';
require_once EP_THEME_PATH . '/inc/feature-registry.php';
require_once EP_THEME_PATH . '/inc/features.php';
require_once EP_THEME_PATH . '/inc/route-registry.php';
require_once EP_THEME_PATH . '/inc/router.php';
require_once EP_THEME_PATH . '/inc/dependencies.php';
require_once EP_THEME_PATH . '/inc/notifications.php';
require_once EP_THEME_PATH . '/inc/apps.php';
require_once EP_THEME_PATH . '/inc/app-registry.php';
require_once EP_THEME_PATH . '/inc/app-cpt.php';
require_once EP_THEME_PATH . '/inc/github.php';
require_once EP_THEME_PATH . '/inc/github-app.php';
require_once EP_THEME_PATH . '/inc/scaffolder.php';
require_once EP_THEME_PATH . '/inc/api.php';

if ( is_admin() ) {
	require_once EP_THEME_PATH . '/inc/admin/admin-assets.php';
	require_once EP_THEME_PATH . '/inc/admin/settings-page.php';  // data helpers
	require_once EP_THEME_PATH . '/inc/admin/admin-registry.php';
	require_once EP_THEME_PATH . '/inc/admin/pages/shared.php';
	require_once EP_THEME_PATH . '/inc/admin/pages/dashboard.php';
	require_once EP_THEME_PATH . '/inc/admin/pages/apps.php';
	require_once EP_THEME_PATH . '/inc/admin/pages/theme.php';
	require_once EP_THEME_PATH . '/inc/admin/pages/routing.php';
	require_once EP_THEME_PATH . '/inc/admin/pages/reference.php';
	require_once EP_THEME_PATH . '/inc/admin/pages/system.php';
	require_once EP_THEME_PATH . '/inc/admin/pages/editor.php';
}

require_once EP_THEME_PATH . '/inc/cli.php';
require_once EP_THEME_PATH . '/inc/updater.php';

new ExamplePress_Updater();

/**
 * Theme setup.
 */
add_action( 'after_setup_theme', function () {
	load_theme_textdomain( 'examplepress-theme', EP_THEME_PATH . '/languages' );
} );

add_action( 'after_setup_theme', 'examplepress_boot_features' );

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

	$template_prefix = examplepress_get_template_prefix();

	// Exempt template blocks from all registered origin namespaces + the theme itself.
	$namespaces   = examplepress_get_route_origin_namespaces();
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
