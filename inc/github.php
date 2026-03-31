<?php
/**
 * ExamplePress GitHub Provisioning
 *
 * Direct GitHub API integration for repo creation and scaffold pushing.
 * Used by the orchestrated scaffold flow when a GitHub PAT is configured.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Create a GitHub repo under the configured org.
 *
 * @param string $slug        Repo name (e.g. "my-app").
 * @param string $description Repo description.
 * @return array{owner_repo: string, repo_id: int, html_url: string}|WP_Error
 */
function examplepress_github_create_repo( string $slug, string $description ) {
	$pat = examplepress_github_get_write_token();
	$org = get_option( 'ep_github_org', 'webmultipliers' );

	if ( ! $pat ) {
		return new WP_Error( 'no_github_token', 'No GitHub write token available. Install the GitHub App or configure a write access token.' );
	}

	$response = wp_remote_post( "https://api.github.com/orgs/{$org}/repos", [
		'headers' => [
			'Authorization' => "Bearer {$pat}",
			'Accept'        => 'application/vnd.github.v3+json',
			'User-Agent'    => 'ExamplePress/' . EP_THEME_VERSION,
		],
		'body'    => wp_json_encode( [
			'name'        => $slug,
			'description' => $description,
			'private'     => false,
			'auto_init'   => false,
		] ),
		'timeout' => 30,
	] );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code === 422 && ! empty( $body['errors'] ) ) {
		foreach ( $body['errors'] as $err ) {
			if ( ( $err['message'] ?? '' ) === 'name already exists on this account' ) {
				return new WP_Error( 'repo_exists', "GitHub repo \"{$org}/{$slug}\" already exists." );
			}
		}
	}

	if ( $code !== 201 ) {
		$msg = $body['message'] ?? "GitHub API returned HTTP {$code}.";
		return new WP_Error( 'github_api_error', $msg );
	}

	return [
		'owner_repo' => $body['full_name'],
		'repo_id'    => (int) $body['id'],
		'html_url'   => $body['html_url'],
	];
}

/**
 * Push scaffold files to a GitHub repo using the Git Trees API.
 *
 * Creates blobs for each file, assembles a tree, creates a commit,
 * and updates refs/heads/main — all via REST, no git CLI needed.
 *
 * @param string $owner_repo  "org/repo-name"
 * @param string $plugin_path Local path to the scaffolded plugin directory.
 * @return true|WP_Error
 */
function examplepress_github_push_scaffold( string $owner_repo, string $plugin_path ) {
	$pat = examplepress_github_get_write_token();

	if ( ! $pat ) {
		return new WP_Error( 'no_github_token', 'No GitHub write token available.' );
	}

	$headers = [
		'Authorization' => "Bearer {$pat}",
		'Accept'        => 'application/vnd.github.v3+json',
		'User-Agent'    => 'ExamplePress/' . EP_THEME_VERSION,
		'Content-Type'  => 'application/json',
	];

	$base_url = "https://api.github.com/repos/{$owner_repo}";

	// Collect all files from the plugin directory.
	$files = examplepress_github_collect_files( $plugin_path, $plugin_path );

	if ( empty( $files ) ) {
		return new WP_Error( 'no_files', 'No files found in scaffold directory.' );
	}

	// Step 1: Create blobs for each file.
	$tree_items = [];
	foreach ( $files as $relative_path => $absolute_path ) {
		$content = file_get_contents( $absolute_path );

		if ( $content === false ) {
			continue;
		}

		$blob_response = wp_remote_post( "{$base_url}/git/blobs", [
			'headers' => $headers,
			'body'    => wp_json_encode( [
				'content'  => base64_encode( $content ),
				'encoding' => 'base64',
			] ),
			'timeout' => 30,
		] );

		if ( is_wp_error( $blob_response ) ) {
			return $blob_response;
		}

		$blob = json_decode( wp_remote_retrieve_body( $blob_response ), true );

		if ( empty( $blob['sha'] ) ) {
			return new WP_Error( 'blob_failed', "Failed to create blob for {$relative_path}." );
		}

		$tree_items[] = [
			'path' => $relative_path,
			'mode' => '100644',
			'type' => 'blob',
			'sha'  => $blob['sha'],
		];
	}

	// Step 2: Create a tree from the blobs.
	$tree_response = wp_remote_post( "{$base_url}/git/trees", [
		'headers' => $headers,
		'body'    => wp_json_encode( [ 'tree' => $tree_items ] ),
		'timeout' => 30,
	] );

	if ( is_wp_error( $tree_response ) ) {
		return $tree_response;
	}

	$tree = json_decode( wp_remote_retrieve_body( $tree_response ), true );

	if ( empty( $tree['sha'] ) ) {
		return new WP_Error( 'tree_failed', 'Failed to create git tree.' );
	}

	// Step 3: Create an initial commit (no parent).
	$commit_response = wp_remote_post( "{$base_url}/git/commits", [
		'headers' => $headers,
		'body'    => wp_json_encode( [
			'message' => 'Initial scaffold from ExamplePress',
			'tree'    => $tree['sha'],
		] ),
		'timeout' => 30,
	] );

	if ( is_wp_error( $commit_response ) ) {
		return $commit_response;
	}

	$commit = json_decode( wp_remote_retrieve_body( $commit_response ), true );

	if ( empty( $commit['sha'] ) ) {
		return new WP_Error( 'commit_failed', 'Failed to create initial commit.' );
	}

	// Step 4: Create refs/heads/main pointing to the commit.
	$ref_response = wp_remote_post( "{$base_url}/git/refs", [
		'headers' => $headers,
		'body'    => wp_json_encode( [
			'ref' => 'refs/heads/main',
			'sha' => $commit['sha'],
		] ),
		'timeout' => 30,
	] );

	if ( is_wp_error( $ref_response ) ) {
		return $ref_response;
	}

	$ref_code = wp_remote_retrieve_response_code( $ref_response );

	if ( $ref_code !== 201 ) {
		$ref_body = json_decode( wp_remote_retrieve_body( $ref_response ), true );
		return new WP_Error( 'ref_failed', $ref_body['message'] ?? 'Failed to create main branch ref.' );
	}

	return true;
}

/**
 * Register a plugin on the Troy Server and connect its GitHub integration.
 *
 * Calls the Troy provision endpoint which creates the CPT post,
 * registers the slug, and connects the GitHub integration in one call.
 *
 * @param string $slug        Plugin slug.
 * @param string $name        Plugin display name.
 * @param string $description Plugin description.
 * @param string $owner_repo  GitHub owner/repo string.
 * @return array{plugin_id: int, post_id: int, slug: string}|WP_Error
 */
function examplepress_troy_register_and_connect(
	string $slug,
	string $name,
	string $description,
	string $owner_repo
) {
	$troy_url  = get_option( 'ep_troy_server_url', '' );
	$troy_auth = get_option( 'ep_troy_credentials', '' );

	if ( ! $troy_url || ! $troy_auth ) {
		return new WP_Error( 'no_troy_config', 'Troy Server URL or credentials are not configured.' );
	}

	$troy_url = rtrim( $troy_url, '/' );

	$response = wp_remote_post( "{$troy_url}/wp-json/troy-server/v1/plugins/manage/provision", [
		'headers' => [
			'Authorization' => 'Basic ' . base64_encode( $troy_auth ),
			'Content-Type'  => 'application/json',
		],
		'body'    => wp_json_encode( array_filter( [
			'name'        => $name,
			'slug'        => $slug,
			'description' => $description,
			'owner_repo'  => $owner_repo,
			'github_pat'  => examplepress_get_troy_read_token(),
		] ) ),
		'timeout' => 30,
	] );

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	// 409 = slug already registered. Return a recognizable error so
	// callers can treat this as "registered" and still proceed.
	if ( $code === 409 ) {
		$msg = $body['message'] ?? 'Slug already registered on Troy.';
		return new WP_Error( 'troy_slug_exists', $msg );
	}

	if ( $code < 200 || $code >= 300 ) {
		$msg = $body['message'] ?? "Troy API returned HTTP {$code}.";
		return new WP_Error( 'troy_api_error', $msg );
	}

	return $body;
}

/**
 * Update Troy connection data in a plugin's examplepress.json.
 *
 * @param string $slug      Plugin slug.
 * @param array  $troy_data Troy connection data to merge.
 * @return bool True on success.
 */
function examplepress_update_app_troy_data( string $slug, array $troy_data ): bool {
	$json_path = WP_PLUGIN_DIR . '/' . $slug . '/examplepress.json';

	if ( ! file_exists( $json_path ) ) {
		return false;
	}

	$config = json_decode( file_get_contents( $json_path ), true );

	if ( ! is_array( $config ) ) {
		return false;
	}

	$config['troy'] = array_merge( $config['troy'] ?? [], $troy_data );

	return (bool) file_put_contents(
		$json_path,
		wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n"
	);
}

/**
 * Recursively collect files from a directory.
 *
 * @param string $dir  Current directory to scan.
 * @param string $base Base directory for relative paths.
 * @return array<string, string> Map of relative_path => absolute_path.
 */
function examplepress_github_collect_files( string $dir, string $base ): array {
	$files = [];

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $iterator as $file ) {
		if ( $file->isDir() ) {
			continue;
		}

		$absolute = $file->getPathname();
		$relative = ltrim( str_replace( $base, '', $absolute ), '/' );
		$files[ $relative ] = $absolute;
	}

	return $files;
}
