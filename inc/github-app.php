<?php
/**
 * ExamplePress GitHub App Integration
 *
 * Handles JWT signing and installation access token generation for the
 * ExamplePress GitHub App. This provides write access (repo creation +
 * code push) without requiring users to create personal access tokens
 * with admin scope.
 *
 * The App's private key is bundled with the theme — this is standard
 * practice for GitHub Apps. The key identifies the *app*, not the user.
 * The *installation* on the user's org is what grants permissions.
 *
 * Token flow:
 *   1. Sign a JWT with the App's private key (RS256)
 *   2. Exchange JWT for a short-lived installation access token (1 hour)
 *   3. Use that token for GitHub API calls
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load App credentials if the key file exists.
$ep_github_app_key_file = EP_THEME_PATH . '/inc/github-app-key.php';
if ( file_exists( $ep_github_app_key_file ) ) {
	require_once $ep_github_app_key_file;
}

/**
 * Check whether the GitHub App is configured (credentials bundled).
 */
function examplepress_github_app_is_configured(): bool {
	return defined( 'EP_GITHUB_APP_ID' ) && defined( 'EP_GITHUB_APP_PEM' );
}

/**
 * Check whether the GitHub App is installed (user has completed installation flow).
 */
function examplepress_github_app_is_installed(): bool {
	return examplepress_github_app_is_configured()
		&& (bool) get_option( 'ep_github_app_installation_id', '' );
}

/**
 * Generate a JWT for GitHub App authentication.
 *
 * The JWT is signed with the App's private key using RS256 and is valid
 * for 10 minutes (GitHub's maximum).
 *
 * @return string|WP_Error The JWT string or error.
 */
function examplepress_github_app_get_jwt() {
	if ( ! examplepress_github_app_is_configured() ) {
		return new WP_Error( 'no_app_config', 'GitHub App credentials are not configured.' );
	}

	$app_id = EP_GITHUB_APP_ID;
	$pem    = EP_GITHUB_APP_PEM;

	$now = time();

	// JWT header.
	$header = examplepress_base64url_encode( wp_json_encode( [
		'alg' => 'RS256',
		'typ' => 'JWT',
	] ) );

	// JWT payload.
	$payload = examplepress_base64url_encode( wp_json_encode( [
		'iat' => $now - 60,        // Issued at (60s clock skew tolerance).
		'exp' => $now + ( 10 * 60 ), // Expires in 10 minutes.
		'iss' => (string) $app_id,
	] ) );

	// Sign with RSA SHA-256.
	$signature = '';
	$key       = openssl_pkey_get_private( $pem );

	if ( ! $key ) {
		return new WP_Error( 'bad_pem', 'Failed to parse GitHub App private key.' );
	}

	$signed = openssl_sign( "{$header}.{$payload}", $signature, $key, OPENSSL_ALGO_SHA256 );

	if ( ! $signed ) {
		return new WP_Error( 'sign_failed', 'Failed to sign JWT.' );
	}

	return "{$header}.{$payload}." . examplepress_base64url_encode( $signature );
}

/**
 * Get a short-lived installation access token from GitHub.
 *
 * Tokens are cached in a transient for 55 minutes (GitHub issues them
 * for 1 hour). Returns the token string or WP_Error.
 *
 * @return string|WP_Error
 */
function examplepress_github_app_get_installation_token() {
	$installation_id = get_option( 'ep_github_app_installation_id', '' );

	if ( ! $installation_id ) {
		return new WP_Error( 'no_installation', 'GitHub App is not installed on any organization.' );
	}

	// Check transient cache first.
	$cached = get_transient( 'ep_github_app_token' );
	if ( $cached ) {
		return $cached;
	}

	// Get a JWT to authenticate as the App.
	$jwt = examplepress_github_app_get_jwt();

	if ( is_wp_error( $jwt ) ) {
		return $jwt;
	}

	// Exchange JWT for an installation token.
	$response = wp_remote_post(
		"https://api.github.com/app/installations/{$installation_id}/access_tokens",
		[
			'headers' => [
				'Authorization' => "Bearer {$jwt}",
				'Accept'        => 'application/vnd.github.v3+json',
				'User-Agent'    => 'ExamplePress/' . EP_THEME_VERSION,
			],
			'timeout' => 15,
		]
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code !== 201 || empty( $body['token'] ) ) {
		$msg = $body['message'] ?? "GitHub API returned HTTP {$code}.";
		return new WP_Error( 'token_failed', $msg );
	}

	$token = $body['token'];

	// Cache for 55 minutes (token is valid for 60).
	set_transient( 'ep_github_app_token', $token, 55 * MINUTE_IN_SECONDS );

	return $token;
}

/**
 * Get a write token for GitHub API operations (repo creation, push).
 *
 * Tries the GitHub App installation token first. Falls back to the
 * stored fine-grained PAT for backward compatibility.
 *
 * @return string Token string, or empty if nothing is configured.
 */
function examplepress_github_get_write_token(): string {
	// Prefer GitHub App installation token.
	if ( examplepress_github_app_is_installed() ) {
		$token = examplepress_github_app_get_installation_token();

		if ( ! is_wp_error( $token ) ) {
			return $token;
		}
		// Fall through to PAT if token minting fails.
	}

	// Fallback: fine-grained PAT with write access.
	return get_option( 'ep_github_pat', '' );
}

/**
 * Get a read-only token for Troy (tag fetching, ZIP downloads).
 *
 * Prefers the dedicated Troy read token. Falls back to the write PAT
 * for backward compatibility (users who haven't configured separate tokens).
 *
 * @return string Token string, or empty if nothing is configured.
 */
function examplepress_get_troy_read_token(): string {
	return get_option( 'ep_troy_github_pat', '' )
		?: get_option( 'ep_github_pat', '' );
}

/**
 * Base64url encode (JWT-safe, no padding).
 */
function examplepress_base64url_encode( string $data ): string {
	return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
}
