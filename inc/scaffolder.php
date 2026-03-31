<?php
/**
 * ExamplePress Scaffolder — GitHub Template Repository
 *
 * Creates companion plugins from the official GitHub template repository
 * (webmultipliers/examplepress-theme-app) using GitHub's "Generate from
 * template" API. Falls back to local scaffold copy when no GitHub token
 * is available.
 *
 * Flow:
 *   1. Create repo from template via GitHub API
 *   2. Replace __SLUG__, __NAME__, __DESC__ placeholders in the new repo
 *   3. Optionally register with Troy
 *   4. Return repo URL + Codespaces link
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Template repository used for "Generate from template" API calls.
 */
define( 'EP_TEMPLATE_REPO', 'webmultipliers/examplepress-theme-app' );

/**
 * Placeholder tokens used in the template repository files.
 */
define( 'EP_SCAFFOLD_PLACEHOLDERS', [ '__NAME__', '__SLUG__', '__DESC__', '__TROY__' ] );

/**
 * Create a new repo from the GitHub template repository.
 *
 * Uses the "Generate from template" API which creates a clean repo
 * (no fork relationship, no template commit history).
 *
 * @param string $slug        Repo/plugin slug.
 * @param string $description Repo description.
 * @param string $owner       GitHub org or user to create under.
 * @return array{full_name: string, id: int, html_url: string}|WP_Error
 */
function examplepress_scaffold_from_template( string $slug, string $description, string $owner ) {
	$pat = examplepress_github_get_write_token();

	if ( ! $pat ) {
		return new WP_Error(
			'no_github_token',
			'No GitHub write token available. Install the GitHub App or configure a write access token.'
		);
	}

	$response = wp_remote_post(
		'https://api.github.com/repos/' . EP_TEMPLATE_REPO . '/generate',
		[
			'headers' => [
				'Authorization' => "Bearer {$pat}",
				'Accept'        => 'application/vnd.github.v3+json',
				'User-Agent'    => 'ExamplePress/' . EP_THEME_VERSION,
			],
			'body'    => wp_json_encode( [
				'owner'       => $owner,
				'name'        => $slug,
				'description' => $description,
				'private'     => false,
			] ),
			'timeout' => 30,
		]
	);

	if ( is_wp_error( $response ) ) {
		return $response;
	}

	$code = wp_remote_retrieve_response_code( $response );
	$body = json_decode( wp_remote_retrieve_body( $response ), true );

	if ( $code === 422 ) {
		$msg = $body['message'] ?? 'Validation failed';
		if ( str_contains( $msg, 'already exists' ) || str_contains( $msg, 'name already exists' ) ) {
			return new WP_Error( 'repo_exists', "Repository \"{$owner}/{$slug}\" already exists." );
		}
		return new WP_Error( 'github_validation', $msg );
	}

	if ( $code !== 201 ) {
		$msg = $body['message'] ?? "GitHub API returned HTTP {$code}.";
		return new WP_Error( 'github_api_error', $msg );
	}

	return [
		'full_name' => $body['full_name'] ?? "{$owner}/{$slug}",
		'id'        => (int) ( $body['id'] ?? 0 ),
		'html_url'  => $body['html_url'] ?? '',
	];
}

/**
 * Replace placeholder tokens in files within a GitHub repository.
 *
 * Uses the Contents API to read, transform, and commit each file.
 * Only processes files that contain at least one placeholder token.
 *
 * @param string $full_name   GitHub "owner/repo" string.
 * @param string $slug        Plugin slug.
 * @param string $name        Plugin display name.
 * @param string $description Plugin description.
 * @param string $troy_url    Troy server URL (optional).
 * @return true|WP_Error
 */
function examplepress_scaffold_replace_remote_placeholders(
	string $full_name,
	string $slug,
	string $name,
	string $description,
	string $troy_url = ''
) {
	$pat = examplepress_github_get_write_token();

	if ( ! $pat ) {
		return new WP_Error( 'no_github_token', 'No GitHub write token.' );
	}

	$headers = [
		'Authorization' => "Bearer {$pat}",
		'Accept'        => 'application/vnd.github.v3+json',
		'User-Agent'    => 'ExamplePress/' . EP_THEME_VERSION,
	];

	$base_url = "https://api.github.com/repos/{$full_name}";

	// Resolve the default branch — template repos may use 'development', not 'main'.
	$repo_response = wp_remote_get( $base_url, [
		'headers' => $headers,
		'timeout' => 10,
	] );

	if ( is_wp_error( $repo_response ) ) {
		return $repo_response;
	}

	$repo_data      = json_decode( wp_remote_retrieve_body( $repo_response ), true );
	$default_branch = $repo_data['default_branch'] ?? 'development';

	// Get the full file tree so we know which files to check.
	$tree_response = wp_remote_get( "{$base_url}/git/trees/{$default_branch}?recursive=1", [
		'headers' => $headers,
		'timeout' => 15,
	] );

	if ( is_wp_error( $tree_response ) ) {
		return $tree_response;
	}

	$tree_body = json_decode( wp_remote_retrieve_body( $tree_response ), true );
	$tree      = $tree_body['tree'] ?? [];

	if ( empty( $tree ) ) {
		return new WP_Error( 'empty_tree', 'Repository tree is empty — template may not have been fully generated yet.' );
	}

	$replacements = [
		'__NAME__' => $name,
		'__SLUG__' => $slug,
		'__DESC__' => $description,
		'__TROY__' => $troy_url,
	];

	// Collect files to process (skip directories, images, etc.).
	$processable_extensions = [ 'php', 'json', 'md', 'yml', 'yaml', 'txt', 'xml', 'css', 'js', 'html' ];
	$files_to_check         = [];

	foreach ( $tree as $entry ) {
		if ( $entry['type'] !== 'blob' ) {
			continue;
		}

		$ext = strtolower( pathinfo( $entry['path'], PATHINFO_EXTENSION ) );
		if ( in_array( $ext, $processable_extensions, true ) ) {
			$files_to_check[] = $entry['path'];
		}
	}

	// Also handle the __SLUG__.php rename.
	$slug_file_path = '__SLUG__.php';
	$has_slug_file  = in_array( $slug_file_path, $files_to_check, true );

	foreach ( $files_to_check as $file_path ) {
		$get_response = wp_remote_get( "{$base_url}/contents/{$file_path}", [
			'headers' => $headers,
			'timeout' => 10,
		] );

		if ( is_wp_error( $get_response ) || wp_remote_retrieve_response_code( $get_response ) !== 200 ) {
			continue;
		}

		$file_data = json_decode( wp_remote_retrieve_body( $get_response ), true );

		if ( empty( $file_data['content'] ) || empty( $file_data['sha'] ) ) {
			continue;
		}

		$content  = base64_decode( $file_data['content'] );
		$replaced = str_replace(
			array_keys( $replacements ),
			array_values( $replacements ),
			$content
		);

		if ( $replaced === $content && $file_path !== $slug_file_path ) {
			continue; // No placeholders found, skip.
		}

		// If this is __SLUG__.php, we need to delete and recreate with new name.
		if ( $file_path === $slug_file_path ) {
			// Create the renamed file.
			$create_response = wp_remote_request( "{$base_url}/contents/{$slug}.php", [
				'method'  => 'PUT',
				'headers' => $headers,
				'body'    => wp_json_encode( [
					'message' => "Rename {$slug_file_path} to {$slug}.php",
					'content' => base64_encode( $replaced ),
				] ),
				'timeout' => 15,
			] );

			if ( is_wp_error( $create_response ) ) {
				continue;
			}

			// Delete the old __SLUG__.php.
			wp_remote_request( "{$base_url}/contents/{$slug_file_path}", [
				'method'  => 'DELETE',
				'headers' => $headers,
				'body'    => wp_json_encode( [
					'message' => "Remove placeholder file {$slug_file_path}",
					'sha'     => $file_data['sha'],
				] ),
				'timeout' => 15,
			] );

			continue;
		}

		// Update file in place.
		wp_remote_request( "{$base_url}/contents/{$file_path}", [
			'method'  => 'PUT',
			'headers' => $headers,
			'body'    => wp_json_encode( [
				'message' => "Replace placeholders in {$file_path}",
				'content' => base64_encode( $replaced ),
				'sha'     => $file_data['sha'],
			] ),
			'timeout' => 15,
		] );
	}

	return true;
}

/**
 * Build a Codespaces launch URL for a repository.
 *
 * @param string $full_name GitHub "owner/repo" string.
 * @param int    $repo_id   GitHub repo ID.
 * @return string URL to open Codespaces for this repo.
 */
function examplepress_codespaces_url( string $full_name, int $repo_id = 0 ): string {
	if ( $repo_id ) {
		return 'https://github.com/codespaces/new?repo=' . $repo_id;
	}

	return 'https://github.com/codespaces/new?repo=' . urlencode( $full_name );
}
