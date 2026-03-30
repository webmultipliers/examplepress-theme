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
