<?php
/**
 * ExamplePress REST API — Updater Plugin Endpoints
 *
 * Handles installing, activating, and removing the companion
 * updater plugin (examplepress-theme-update) from GitHub.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', 'examplepress_register_updater_routes' );

function examplepress_register_updater_routes(): void {
	register_rest_route( 'examplepress/v1', '/updater/install', [
		'methods'             => 'POST',
		'callback'            => 'examplepress_handle_updater_install',
		'permission_callback' => function () {
			return current_user_can( 'install_plugins' );
		},
	] );

	register_rest_route( 'examplepress/v1', '/updater/uninstall', [
		'methods'             => 'POST',
		'callback'            => 'examplepress_handle_updater_uninstall',
		'permission_callback' => function () {
			return current_user_can( 'delete_plugins' );
		},
	] );
}

/**
 * Get the current updater plugin status.
 *
 * @return string 'not-installed' | 'installed' | 'active'
 */
function examplepress_get_updater_status(): string {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plugin_file = EP_UPDATER_PLUGIN_FILE;

	if ( is_plugin_active( $plugin_file ) ) {
		return 'active';
	}

	if ( array_key_exists( $plugin_file, get_plugins() ) ) {
		return 'installed';
	}

	return 'not-installed';
}

/**
 * Install the updater companion plugin from GitHub.
 */
function examplepress_handle_updater_install( WP_REST_Request $request ) {
	$status = examplepress_get_updater_status();

	// Already installed — just activate if needed.
	if ( 'active' === $status ) {
		return rest_ensure_response( [
			'success' => true,
			'status'  => 'active',
			'message' => 'Updater plugin is already installed and active.',
		] );
	}

	if ( 'installed' === $status ) {
		$result = activate_plugin( EP_UPDATER_PLUGIN_FILE );

		if ( is_wp_error( $result ) ) {
			return rest_ensure_response( [
				'success' => false,
				'status'  => 'installed',
				'message' => 'Updater plugin is installed but could not be activated: ' . $result->get_error_message(),
			] );
		}

		return rest_ensure_response( [
			'success' => true,
			'status'  => 'active',
			'message' => 'Updater plugin activated.',
		] );
	}

	// Not installed — download from GitHub.
	$installed = examplepress_install_updater_from_github();

	if ( is_wp_error( $installed ) ) {
		return new WP_Error(
			'updater_install_failed',
			'Failed to install updater plugin: ' . $installed->get_error_message(),
			[ 'status' => 500 ]
		);
	}

	$result = activate_plugin( EP_UPDATER_PLUGIN_FILE );
	$final  = is_wp_error( $result ) ? 'installed' : 'active';

	// Clear the throttle transient on success.
	delete_transient( EP_UPDATER_THROTTLE_KEY );

	return rest_ensure_response( [
		'success' => true,
		'status'  => $final,
		'message' => 'active' === $final
			? 'Updater plugin installed and activated.'
			: 'Updater plugin installed but could not be activated: ' . $result->get_error_message(),
	] );
}

/**
 * Uninstall the updater companion plugin.
 */
function examplepress_handle_updater_uninstall( WP_REST_Request $request ) {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$plugin_file = EP_UPDATER_PLUGIN_FILE;
	$plugin_dir  = WP_PLUGIN_DIR . '/examplepress-theme-update';

	if ( ! is_dir( $plugin_dir ) ) {
		return rest_ensure_response( [
			'success' => true,
			'status'  => 'not-installed',
			'message' => 'Updater plugin is not installed.',
		] );
	}

	// Deactivate if active.
	if ( is_plugin_active( $plugin_file ) ) {
		deactivate_plugins( $plugin_file, true );
	}

	// Delete the directory.
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	global $wp_filesystem;

	if ( ! $wp_filesystem instanceof WP_Filesystem_Base || ! $wp_filesystem->delete( $plugin_dir, true ) ) {
		return new WP_Error(
			'updater_delete_failed',
			'Failed to remove the updater plugin directory.',
			[ 'status' => 500 ]
		);
	}

	return rest_ensure_response( [
		'success' => true,
		'status'  => 'not-installed',
		'message' => 'Updater plugin removed.',
	] );
}
