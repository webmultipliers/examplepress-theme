<?php
/**
 * ExamplePress App Registry
 *
 * Persistent record of every app created through the scaffold flow.
 * Stored as a Custom Post Type (ep_app) — see inc/app-cpt.php for
 * the CPT definition and helpers.
 *
 * The registry is the source of truth for "what apps have been created."
 * Filesystem discovery (inc/apps.php) provides the live local state.
 * The two are merged at read time — the registry supplies remote records,
 * the filesystem supplies local presence and activation status.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── CRUD ──────────────────────────────────────────────────────────

/**
 * Get the full app registry.
 *
 * @return array<string, array> Map of slug => record.
 */
function examplepress_registry_all(): array {
	$posts = get_posts( [
		'post_type'      => 'ep_app',
		'posts_per_page' => -1,
		'post_status'    => 'any',
		'no_found_rows'  => true,
	] );

	$registry = [];

	foreach ( $posts as $post ) {
		$record = examplepress_cpt_to_record( $post );
		$registry[ $record['slug'] ] = $record;
	}

	return $registry;
}

/**
 * Get a single registry record by slug.
 *
 * @param string $slug App slug.
 * @return array|null Record or null if not found.
 */
function examplepress_registry_get( string $slug ): ?array {
	$post = examplepress_registry_get_post( $slug );

	if ( ! $post ) {
		return null;
	}

	return examplepress_cpt_to_record( $post );
}

/**
 * Register or update an app in the registry.
 *
 * Merges the provided data with any existing record. Never overwrites
 * fields that aren't provided — callers pass only what changed.
 *
 * @param string $slug App slug (primary key).
 * @param array  $data Fields to set or merge.
 * @return array The full merged record.
 */
function examplepress_registry_set( string $slug, array $data ): array {
	$post = examplepress_registry_get_post( $slug );

	if ( $post ) {
		// Update existing post.
		$update_args = [ 'ID' => $post->ID ];

		if ( isset( $data['name'] ) && $data['name'] !== $post->post_title ) {
			$update_args['post_title'] = $data['name'];
		}

		if ( count( $update_args ) > 1 ) {
			wp_update_post( $update_args );
		}

		examplepress_cpt_write_meta( $post->ID, $data );

		return examplepress_cpt_to_record( get_post( $post->ID ) );
	}

	// Create new post.
	$post_id = wp_insert_post( [
		'post_type'   => 'ep_app',
		'post_title'  => $data['name'] ?? $slug,
		'post_name'   => $slug,
		'post_status' => 'draft',
	] );

	if ( is_wp_error( $post_id ) ) {
		// Fallback: return a minimal record.
		return array_merge( [ 'slug' => $slug ], $data );
	}

	update_post_meta( $post_id, '_ep_plugin_slug', $slug );
	examplepress_cpt_write_meta( $post_id, $data );

	return examplepress_cpt_to_record( get_post( $post_id ) );
}

/**
 * Remove an app from the registry entirely.
 *
 * Only removes the record — does NOT delete local files, GitHub repos,
 * or Troy registrations. Use examplepress_destroy_app() for that.
 *
 * @param string $slug App slug.
 * @return bool True if the record existed and was removed.
 */
function examplepress_registry_forget( string $slug ): bool {
	$post = examplepress_registry_get_post( $slug );

	if ( ! $post ) {
		return false;
	}

	wp_delete_post( $post->ID, true );

	return true;
}

// ── Queries ───────────────────────────────────────────────────────

/**
 * Get all apps with merged local + registry state.
 *
 * Combines the persistent registry with live filesystem discovery.
 * Each returned record includes:
 *   - `local`  — whether the plugin directory exists and is active
 *   - `github` — owner_repo, repo_id, html_url from scaffold
 *   - `troy`   — server_url, registered state
 *   - `orphan` — true if the app exists remotely but not locally
 *
 * @return array[] List of merged app records.
 */
function examplepress_registry_list_merged(): array {
	$registry  = examplepress_registry_all();
	$local_apps = examplepress_get_apps();

	// Index local apps by slug.
	$local_by_slug = [];
	foreach ( $local_apps as $app ) {
		$local_by_slug[ $app['slug'] ] = $app;
	}

	$merged = [];

	// Start with registry records (includes apps that may have been deleted locally).
	foreach ( $registry as $slug => $record ) {
		$local = $local_by_slug[ $slug ] ?? null;

		$merged[] = examplepress_registry_merge_record( $record, $local );

		// Remove from local index so we don't double-count.
		unset( $local_by_slug[ $slug ] );
	}

	// Adopt any locally-discovered apps that aren't in the registry yet.
	foreach ( $local_by_slug as $slug => $local ) {
		$adopted = [
			'slug'        => $slug,
			'name'        => $local['name'],
			'description' => $local['description'],
			'version'     => $local['version'] ?? '',
			'source'      => 'discovered',
		];

		// Pull Troy/GitHub data from the local examplepress.json.
		if ( ! empty( $local['troy']['server_url'] ) ) {
			$adopted['troy'] = [
				'server_url' => $local['troy']['server_url'],
				'repo'       => $local['troy']['repo'] ?? '',
				'repo_id'    => $local['troy']['repo_id'] ?? '',
			];
		}

		// Persist to registry.
		examplepress_registry_set( $slug, $adopted );

		$merged[] = examplepress_registry_merge_record( $adopted, $local );
	}

	return $merged;
}

/**
 * Merge a registry record with live local state.
 *
 * @param array      $record Registry record.
 * @param array|null $local  Live app data from filesystem, or null.
 * @return array Merged record.
 */
function examplepress_registry_merge_record( array $record, ?array $local ): array {
	$has_local = $local !== null;

	// Troy data: prefer local (it's the live examplepress.json), fall back to registry.
	$troy_server = $has_local
		? ( $local['troy']['server_url'] ?? $record['troy']['server_url'] ?? '' )
		: ( $record['troy']['server_url'] ?? '' );
	$troy_repo = $has_local
		? ( $local['troy']['repo'] ?? $record['troy']['repo'] ?? '' )
		: ( $record['troy']['repo'] ?? '' );
	$troy_repo_id = $has_local
		? ( $local['troy']['repo_id'] ?? $record['troy']['repo_id'] ?? '' )
		: ( $record['troy']['repo_id'] ?? '' );

	// GitHub data: registry is authoritative (local examplepress.json doesn't store GitHub metadata).
	$github_repo    = $record['github']['owner_repo'] ?? '';
	$github_repo_id = $record['github']['repo_id'] ?? '';
	$github_url     = $record['github']['html_url'] ?? '';

	$has_github = ! empty( $github_repo );
	$has_troy   = ! empty( $troy_server );

	// Source — how this app entered the registry.
	$source = $record['source'] ?? 'scaffolded';

	return [
		// Identity.
		'slug'        => $record['slug'],
		'name'        => $has_local ? $local['name'] : ( $record['name'] ?? $record['slug'] ),
		'description' => $has_local ? $local['description'] : ( $record['description'] ?? '' ),
		'version'     => $has_local ? $local['version'] : ( $record['version'] ?? '' ),
		'created_at'  => $record['created_at'] ?? '',
		'source'      => $source,

		// Local state (from filesystem).
		'local' => [
			'installed'   => $has_local,
			'active'      => $has_local && $local['active'],
			'plugin_file' => $has_local ? $local['plugin_file'] : '',
		],

		// GitHub state.
		'github' => [
			'owner_repo' => $github_repo,
			'repo_id'    => $github_repo_id,
			'html_url'   => $github_url,
		],

		// Troy state.
		'troy' => [
			'server_url' => $troy_server,
			'repo'       => $troy_repo,
			'repo_id'    => $troy_repo_id,
		],

		// Connection status (derived). GitHub alone is sufficient for "connected".
		'status' => $has_local && $has_github
			? 'connected'
			: ( $has_local ? 'disconnected' : 'orphan' ),

		// Active state.
		'active' => $has_local && $local['active'],

		// Routing (from local if present).
		'routing' => $has_local ? ( $local['routing'] ?? [] ) : [],

		// Orphan detection.
		'orphan' => ! $has_local && ( $has_github || $has_troy ),

		// What exists where.
		'exists' => [
			'local'  => $has_local,
			'github' => $has_github,
			'troy'   => $has_troy,
		],
	];
}

// ── Destructive Operations ────────────────────────────────────────

/**
 * Delete an app everywhere: local plugin, GitHub repo, Troy registration.
 *
 * Each step is attempted independently. Failures in one step don't
 * block the others. Returns a report of what was deleted and what failed.
 *
 * @param string $slug App slug.
 * @return array{ deleted: string[], failed: string[], warnings: string[] }
 */
function examplepress_destroy_app( string $slug ): array {
	$record  = examplepress_registry_get( $slug );
	$deleted = [];
	$failed  = [];
	$warnings = [];

	// Also check filesystem in case the app isn't in the registry.
	$plugin_dir = WP_PLUGIN_DIR . '/' . $slug;

	// ── 1. Deactivate + delete local plugin ────────────────────

	if ( is_dir( $plugin_dir ) ) {
		$plugin_file = $slug . '/' . $slug . '.php';

		if ( is_plugin_active( $plugin_file ) ) {
			deactivate_plugins( $plugin_file );
		}

		$fs = examplepress_get_filesystem();
		if ( $fs && $fs->delete( $plugin_dir, true ) ) {
			$deleted[] = 'local';
		} else {
			$failed[] = 'local';
			$warnings[] = 'Could not delete plugin directory.';
		}
	}

	// ── 2. Delete GitHub repo ──────────────────────────────────

	$owner_repo = $record['github']['owner_repo'] ?? '';

	if ( $owner_repo ) {
		$pat = examplepress_github_get_write_token();

		if ( $pat ) {
			$response = wp_remote_request( "https://api.github.com/repos/{$owner_repo}", [
				'method'  => 'DELETE',
				'headers' => [
					'Authorization' => "Bearer {$pat}",
					'Accept'        => 'application/vnd.github.v3+json',
					'User-Agent'    => 'ExamplePress/' . EP_THEME_VERSION,
				],
				'timeout' => 15,
			] );

			$code = wp_remote_retrieve_response_code( $response );

			if ( $code === 204 || $code === 404 ) {
				$deleted[] = 'github';
			} else {
				$failed[] = 'github';
				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				$warnings[] = 'GitHub delete: ' . ( $body['message'] ?? "HTTP {$code}" );
			}
		} else {
			$failed[] = 'github';
			$warnings[] = 'No GitHub write token — cannot delete repo.';
		}
	}

	// ── 3. Unregister from Troy (only if configured) ───────────

	$troy_server = $record['troy']['server_url'] ?? '';

	if ( $troy_server ) {
		$troy_url  = 'https://' . $troy_server;
		$troy_auth = get_option( 'ep_troy_credentials', '' );

		if ( $troy_auth ) {
			$response = wp_remote_request(
				"{$troy_url}/wp-json/troy-server/v1/plugins/manage/unregister",
				[
					'method'  => 'POST',
					'headers' => [
						'Authorization' => 'Basic ' . base64_encode( $troy_auth ),
						'Content-Type'  => 'application/json',
					],
					'body'    => wp_json_encode( [ 'slug' => $slug ] ),
					'timeout' => 15,
				]
			);

			$code = wp_remote_retrieve_response_code( $response );

			if ( $code >= 200 && $code < 300 || $code === 404 ) {
				$deleted[] = 'troy';
			} else {
				$failed[] = 'troy';
				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				$warnings[] = 'Troy unregister: ' . ( $body['message'] ?? "HTTP {$code}" );
			}
		} else {
			$failed[] = 'troy';
			$warnings[] = 'No Troy credentials — cannot unregister.';
		}
	}

	// ── 4. Remove from registry ────────────────────────────────

	examplepress_registry_forget( $slug );

	return [
		'deleted'  => $deleted,
		'failed'   => $failed,
		'warnings' => $warnings,
	];
}
