<?php
/**
 * ExamplePress — Updater Plugin Bootstrap
 *
 * Ensures the examplepress-theme-update companion plugin is installed
 * and active. This plugin lives outside the theme directory so it
 * survives theme updates (WordPress deletes the old theme dir during
 * upgrades).
 *
 * Install sources (tried in order):
 *   1. GitHub release asset  (examplepress-theme-update.zip)
 *   2. GitHub repo archive   (fallback if no releases exist yet)
 *
 * @package ExamplePress
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The plugin file path relative to WP_PLUGIN_DIR.
 */
define( 'EP_UPDATER_PLUGIN_FILE', 'examplepress-theme-update/examplepress-theme-update.php' );

/**
 * GitHub repository for the updater plugin.
 */
define( 'EP_UPDATER_GITHUB_REPO', 'webmultipliers/examplepress-theme-update' );

/**
 * Ensure the updater companion plugin is installed and active.
 *
 * Runs early on admin_init so the plugin is in place before any
 * update checks fire.
 */
add_action( 'admin_init', 'examplepress_ensure_updater_plugin', 5 );

function examplepress_ensure_updater_plugin(): void {
	if ( ! current_user_can( 'install_plugins' ) ) {
		return;
	}

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	// Already active — nothing to do.
	if ( is_plugin_active( EP_UPDATER_PLUGIN_FILE ) ) {
		return;
	}

	// Installed but inactive — activate it.
	if ( array_key_exists( EP_UPDATER_PLUGIN_FILE, get_plugins() ) ) {
		activate_plugin( EP_UPDATER_PLUGIN_FILE );
		return;
	}

	// Not installed — download and install from GitHub.
	$installed = examplepress_install_updater_from_github();

	if ( is_wp_error( $installed ) ) {
		add_action( 'admin_notices', function () use ( $installed ) {
			printf(
				'<div class="notice notice-error"><p><strong>ExamplePress:</strong> %s</p></div>',
				esc_html(
					'Failed to auto-install the updater plugin: ' . $installed->get_error_message()
					. ' You can install it manually from https://github.com/' . EP_UPDATER_GITHUB_REPO
				)
			);
		} );
		return;
	}

	activate_plugin( EP_UPDATER_PLUGIN_FILE );
}

/**
 * Download and install the updater plugin from GitHub.
 *
 * Tries the latest release asset first. If no releases exist,
 * falls back to a repo archive download.
 *
 * @return true|WP_Error
 */
function examplepress_install_updater_from_github() {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	$zip_url = examplepress_resolve_updater_zip_url();

	if ( ! $zip_url ) {
		return new WP_Error(
			'ep_updater_no_source',
			'Could not determine a download URL for the updater plugin.'
		);
	}

	// Use WP's built-in plugin upgrader for a clean install.
	$skin     = new WP_Ajax_Upgrader_Skin();
	$upgrader = new Plugin_Upgrader( $skin );

	$result = $upgrader->install( $zip_url );

	if ( is_wp_error( $result ) ) {
		return $result;
	}

	if ( is_wp_error( $skin->result ) ) {
		return $skin->result;
	}

	if ( ! $result ) {
		return new WP_Error(
			'ep_updater_install_failed',
			'The plugin installer returned an unexpected result.'
		);
	}

	return true;
}

/**
 * Resolve the best download URL for the updater plugin zip.
 *
 * Priority:
 *   1. Latest GitHub release asset named "examplepress-theme-update.zip"
 *   2. GitHub repo archive (development branch)
 *
 * @return string|null
 */
function examplepress_resolve_updater_zip_url(): ?string {
	// Try latest release first.
	$release_url = sprintf(
		'https://api.github.com/repos/%s/releases/latest',
		EP_UPDATER_GITHUB_REPO
	);

	$headers = [
		'Accept'     => 'application/vnd.github+json',
		'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
	];

	$token = examplepress_updater_bootstrap_get_token();
	if ( $token ) {
		$headers['Authorization'] = 'Bearer ' . $token;
	}

	$response = wp_remote_get( $release_url, [
		'timeout' => 10,
		'headers' => $headers,
	] );

	if ( ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response ) ) {
		$release = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( is_array( $release ) && ! empty( $release['assets'] ) ) {
			foreach ( $release['assets'] as $asset ) {
				if ( ( $asset['name'] ?? '' ) === 'examplepress-theme-update.zip' ) {
					return $asset['browser_download_url'];
				}
			}
		}
	}

	// Fallback: repo archive from the development branch.
	return sprintf(
		'https://github.com/%s/archive/refs/heads/development.zip',
		EP_UPDATER_GITHUB_REPO
	);
}

/**
 * Get a GitHub API token if one is available.
 *
 * Reuses the same token sources as the theme's GitHub integration.
 *
 * @return string|null
 */
function examplepress_updater_bootstrap_get_token(): ?string {
	$token = get_option( 'ep_troy_github_pat', '' );
	if ( $token ) {
		return $token;
	}

	$token = get_option( 'ep_github_pat', '' );
	if ( $token ) {
		return $token;
	}

	if ( function_exists( 'examplepress_github_app_get_installation_token' ) ) {
		$token = examplepress_github_app_get_installation_token();
		if ( $token && ! is_wp_error( $token ) ) {
			return $token;
		}
	}

	return null;
}
