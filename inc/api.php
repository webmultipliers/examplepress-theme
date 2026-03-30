<?php
/**
 * ExamplePress REST API
 *
 * Secure proxy endpoints that forward requests from the WordPress
 * admin JS to the Troy scaffolding server. This prevents the browser
 * from communicating directly with Troy (avoiding CORS issues and
 * token exposure).
 */

// ── Build / Scaffold Endpoint ─────────────────────────────────────

add_action( 'rest_api_init', 'examplepress_register_build_routes' );

function examplepress_register_build_routes() {
	register_rest_route( 'examplepress/v1', '/build', [
		'methods'             => 'POST',
		'callback'            => 'examplepress_handle_build',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
	] );

	register_rest_route( 'examplepress/v1', '/build/status', [
		'methods'             => 'GET',
		'callback'            => 'examplepress_handle_build_status',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
	] );
}

/**
 * Handle the scaffold request: validate, sanitize, and proxy to Troy.
 */
function examplepress_handle_build( WP_REST_Request $request ) {
	$app_name     = sanitize_text_field( $request->get_param( 'appName' ) );
	$app_slug     = sanitize_title( $request->get_param( 'appSlug' ) );
	$troy_server  = sanitize_text_field( $request->get_param( 'troyServer' ) );
	$custom_url   = esc_url_raw( $request->get_param( 'customServerUrl' ) );
	$github_token = sanitize_text_field( $request->get_param( 'githubToken' ) );

	// Staging SFTP (optional).
	$staging_enabled = (bool) $request->get_param( 'stagingEnabled' );
	$sftp_host       = sanitize_text_field( $request->get_param( 'sftpHost' ) );
	$sftp_port       = absint( $request->get_param( 'sftpPort' ) ) ?: 22;
	$sftp_user       = sanitize_text_field( $request->get_param( 'sftpUser' ) );
	$sftp_pass       = $request->get_param( 'sftpPass' ); // Not sanitized beyond type — password.
	$sftp_path       = sanitize_text_field( $request->get_param( 'sftpPath' ) );

	// ── Validation ────────────────────────────────────────────────

	if ( empty( $app_name ) ) {
		return new WP_Error( 'missing_app_name', 'App Name is required.', [ 'status' => 400 ] );
	}
	if ( empty( $app_slug ) ) {
		return new WP_Error( 'missing_app_slug', 'App Slug is required.', [ 'status' => 400 ] );
	}
	if ( empty( $github_token ) ) {
		return new WP_Error( 'missing_github_token', 'A GitHub token is required.', [ 'status' => 400 ] );
	}
	if ( empty( $troy_server ) ) {
		return new WP_Error( 'missing_troy_server', 'Please select a Troy server.', [ 'status' => 400 ] );
	}
	if ( $troy_server === 'custom' && empty( $custom_url ) ) {
		return new WP_Error( 'missing_custom_url', 'A custom server URL is required when using a custom Troy server.', [ 'status' => 400 ] );
	}
	if ( $troy_server === 'custom' && ! str_starts_with( $custom_url, 'https://' ) ) {
		return new WP_Error( 'insecure_url', 'Custom Troy server URL must use HTTPS. Credentials cannot be transmitted over plain HTTP.', [ 'status' => 400 ] );
	}
	if ( $staging_enabled && empty( $sftp_host ) ) {
		return new WP_Error( 'missing_sftp_host', 'SFTP Host is required when staging is enabled.', [ 'status' => 400 ] );
	}

	// ── Resolve Troy endpoint ─────────────────────────────────────

	$troy_url = $troy_server === 'custom'
		? trailingslashit( $custom_url ) . 'api/scaffold'
		: 'https://cloud.troy.dev/api/scaffold';

	// ── Build payload ─────────────────────────────────────────────

	$payload = [
		'appName'  => $app_name,
		'appSlug'  => $app_slug,
		'siteUrl'  => home_url(),
	];

	if ( $staging_enabled ) {
		$payload['staging'] = [
			'host'       => $sftp_host,
			'port'       => $sftp_port,
			'username'   => $sftp_user,
			'password'   => $sftp_pass,
			'remotePath' => $sftp_path ?: '/wp-content/plugins/' . $app_slug,
		];
	}

	// ── Proxy to Troy ─────────────────────────────────────────────

	$response = wp_remote_post( $troy_url, [
		'timeout' => 60,
		'headers' => [
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . $github_token,
			'Accept'        => 'application/json',
		],
		'body'    => wp_json_encode( $payload ),
	] );

	if ( is_wp_error( $response ) ) {
		return new WP_Error(
			'troy_connection_failed',
			'Could not connect to the Troy server: ' . $response->get_error_message(),
			[ 'status' => 502 ]
		);
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code === 401 ) {
		return new WP_Error(
			'github_auth_failed',
			'GitHub authentication failed. Please reconnect your account.',
			[ 'status' => 401 ]
		);
	}

	if ( $code === 409 ) {
		return new WP_Error(
			'repo_exists',
			$body['message'] ?? 'Repository name already exists. Please choose a different slug.',
			[ 'status' => 409 ]
		);
	}

	if ( $code >= 400 ) {
		return new WP_Error(
			'troy_error',
			$body['message'] ?? 'The Troy server returned an error (HTTP ' . $code . ').',
			[ 'status' => $code ]
		);
	}

	return rest_ensure_response( [
		'success'      => true,
		'repoUrl'      => $body['repoUrl'] ?? '',
		'codespacesUrl' => $body['codespacesUrl'] ?? '',
		'message'      => $body['message'] ?? 'Repository created successfully.',
	] );
}

/**
 * Optional: check the status of a previously created build.
 */
function examplepress_handle_build_status( WP_REST_Request $request ) {
	return rest_ensure_response( [
		'status'  => 'ready',
		'message' => 'Build endpoint is available.',
	] );
}

// ── Demo Companion Plugin Endpoints ──────────────────────────────

add_action( 'rest_api_init', 'examplepress_register_demo_routes' );

function examplepress_register_demo_routes() {
	register_rest_route( 'examplepress/v1', '/demo/install', [
		'methods'             => 'POST',
		'callback'            => 'examplepress_handle_demo_install',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
	] );

	register_rest_route( 'examplepress/v1', '/demo/uninstall', [
		'methods'             => 'POST',
		'callback'            => 'examplepress_handle_demo_uninstall',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
	] );
}

/**
 * Check if a plugin directory contains the ExamplePress Demo marker header.
 */
function examplepress_is_demo_plugin( $plugin_dir ) {
	$main_file = $plugin_dir . '/examplepress-demo.php';
	if ( ! file_exists( $main_file ) ) {
		return false;
	}
	$header = get_file_data( $main_file, [ 'demo' => 'ExamplePress Demo' ] );
	return ! empty( $header['demo'] ) && strtolower( $header['demo'] ) === 'true';
}

/**
 * Get the current demo plugin status.
 */
function examplepress_get_demo_status() {
	$dest = WP_PLUGIN_DIR . '/examplepress-demo';

	if ( ! is_dir( $dest ) ) {
		return 'not-installed';
	}

	if ( ! examplepress_is_demo_plugin( $dest ) ) {
		return 'foreign';
	}

	if ( is_plugin_active( 'examplepress-demo/examplepress-demo.php' ) ) {
		return 'active';
	}

	return 'installed';
}

/**
 * Recursively copy a directory.
 */
function examplepress_copy_dir( $src, $dst ) {
	if ( ! is_dir( $src ) ) {
		return false;
	}

	wp_mkdir_p( $dst );

	$dir = opendir( $src );
	if ( ! $dir ) {
		return false;
	}

	while ( false !== ( $entry = readdir( $dir ) ) ) {
		if ( $entry === '.' || $entry === '..' ) {
			continue;
		}

		$src_path = $src . '/' . $entry;
		$dst_path = $dst . '/' . $entry;

		if ( is_dir( $src_path ) ) {
			if ( ! examplepress_copy_dir( $src_path, $dst_path ) ) {
				closedir( $dir );
				return false;
			}
		} else {
			if ( ! copy( $src_path, $dst_path ) ) {
				closedir( $dir );
				return false;
			}
		}
	}

	closedir( $dir );
	return true;
}

/**
 * Recursively delete a directory.
 */
function examplepress_delete_dir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return true;
	}

	$items = scandir( $dir );
	foreach ( $items as $item ) {
		if ( $item === '.' || $item === '..' ) {
			continue;
		}

		$path = $dir . '/' . $item;
		if ( is_dir( $path ) ) {
			examplepress_delete_dir( $path );
		} else {
			unlink( $path );
		}
	}

	return rmdir( $dir );
}

/**
 * Install the demo companion plugin from the theme's demo/ directory.
 */
function examplepress_handle_demo_install( WP_REST_Request $request ) {
	$source = EP_THEME_PATH . '/demo/examplepress-demo';
	$dest   = WP_PLUGIN_DIR . '/examplepress-demo';

	if ( ! is_dir( $source ) ) {
		return new WP_Error(
			'demo_source_missing',
			'Demo plugin source not found in the theme.',
			[ 'status' => 500 ]
		);
	}

	// If already installed, check ownership.
	if ( is_dir( $dest ) ) {
		if ( ! examplepress_is_demo_plugin( $dest ) ) {
			return new WP_Error(
				'demo_conflict',
				'A plugin named examplepress-demo already exists but is not the ExamplePress demo. Refusing to overwrite.',
				[ 'status' => 409 ]
			);
		}

		// It's our demo — check if already active.
		if ( is_plugin_active( 'examplepress-demo/examplepress-demo.php' ) ) {
			return rest_ensure_response( [
				'success' => true,
				'status'  => 'active',
				'message' => 'Demo plugin is already installed and active.',
			] );
		}

		// Installed but not active — activate it.
		$result = activate_plugin( 'examplepress-demo/examplepress-demo.php' );
		if ( is_wp_error( $result ) ) {
			return rest_ensure_response( [
				'success' => true,
				'status'  => 'installed',
				'message' => 'Demo plugin is already installed but could not be activated: ' . $result->get_error_message(),
			] );
		}

		return rest_ensure_response( [
			'success' => true,
			'status'  => 'active',
			'message' => 'Demo plugin activated.',
		] );
	}

	// Copy from theme to plugins directory.
	if ( ! examplepress_copy_dir( $source, $dest ) ) {
		// Clean up partial copy.
		examplepress_delete_dir( $dest );
		return new WP_Error(
			'demo_copy_failed',
			'Failed to copy the demo plugin to the plugins directory.',
			[ 'status' => 500 ]
		);
	}

	// Auto-activate.
	$result = activate_plugin( 'examplepress-demo/examplepress-demo.php' );
	$status = is_wp_error( $result ) ? 'installed' : 'active';

	return rest_ensure_response( [
		'success' => true,
		'status'  => $status,
		'message' => $status === 'active'
			? 'Demo plugin installed and activated. Refresh the page to see it in action.'
			: 'Demo plugin installed but could not be auto-activated: ' . $result->get_error_message(),
	] );
}

/**
 * Uninstall the demo companion plugin.
 */
function examplepress_handle_demo_uninstall( WP_REST_Request $request ) {
	$dest = WP_PLUGIN_DIR . '/examplepress-demo';

	if ( ! is_dir( $dest ) ) {
		return rest_ensure_response( [
			'success' => true,
			'status'  => 'not-installed',
			'message' => 'Demo plugin is not installed.',
		] );
	}

	if ( ! examplepress_is_demo_plugin( $dest ) ) {
		return new WP_Error(
			'demo_conflict',
			'The examplepress-demo plugin is not the ExamplePress demo. Refusing to delete.',
			[ 'status' => 409 ]
		);
	}

	// Deactivate if active.
	if ( is_plugin_active( 'examplepress-demo/examplepress-demo.php' ) ) {
		deactivate_plugins( 'examplepress-demo/examplepress-demo.php' );
	}

	// Delete the directory.
	if ( ! examplepress_delete_dir( $dest ) ) {
		return new WP_Error(
			'demo_delete_failed',
			'Failed to remove the demo plugin directory.',
			[ 'status' => 500 ]
		);
	}

	return rest_ensure_response( [
		'success' => true,
		'status'  => 'not-installed',
		'message' => 'Demo plugin removed. Refresh the page to see the get-started fallback.',
	] );
}
