<?php
/**
 * ExamplePress REST API — App Endpoints
 *
 * Handles app listing, scaffolding (GitHub template + Troy orchestration),
 * Troy binding, deactivation, and connection/reconnection.
 */

// ── Build / Scaffold ─────────────────────────────────────────────
//
// One-click orchestration: scaffold locally → create GitHub repo →
// push scaffold code → register on Troy → connect Troy ↔ GitHub →
// write back to local JSON. Degrades gracefully when credentials
// are not configured (local-only scaffold still works).

// ── App Endpoints ────────────────────────────────────────────────

add_action( 'rest_api_init', 'examplepress_register_app_routes' );

function examplepress_register_app_routes() {
	register_rest_route( 'examplepress/v1', '/apps', [
		'methods'             => 'GET',
		'callback'            => 'examplepress_handle_apps_list',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
	] );

	register_rest_route( 'examplepress/v1', '/apps/scaffold', [
		'methods'             => 'POST',
		'callback'            => 'examplepress_handle_app_scaffold',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
		'args' => [
			'name' => [
				'required'          => true,
				'type'              => 'string',
				'description'       => 'Human-readable app name.',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $value ) {
					if ( ! is_string( $value ) || strlen( $value ) < 2 || strlen( $value ) > 100 ) {
						return new WP_Error( 'invalid_name', 'Name must be between 2 and 100 characters.' );
					}
					return true;
				},
			],
			'description' => [
				'type'              => 'string',
				'description'       => 'Short description for the app.',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $value ) {
					if ( ! is_string( $value ) || strlen( $value ) > 500 ) {
						return new WP_Error( 'invalid_description', 'Description must be 500 characters or fewer.' );
					}
					return true;
				},
			],
		],
	] );

	register_rest_route( 'examplepress/v1', '/apps/(?P<slug>[a-z0-9-]+)/troy-bind', [
		'methods'             => 'POST',
		'callback'            => 'examplepress_handle_app_troy_bind',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
		'args' => [
			'slug' => [
				'required'          => true,
				'type'              => 'string',
				'description'       => 'App slug.',
				'sanitize_callback' => 'sanitize_title',
				'validate_callback' => function ( $value ) {
					return (bool) preg_match( '/^[a-z0-9-]+$/', $value );
				},
			],
			'troy_type' => [
				'type'              => 'string',
				'description'       => 'Troy server type.',
				'default'           => 'cloud',
				'enum'              => [ 'cloud', 'custom' ],
				'sanitize_callback' => 'sanitize_text_field',
			],
			'custom_url' => [
				'type'              => 'string',
				'description'       => 'Custom Troy server URL (required when troy_type is custom).',
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			],
		],
	] );

	register_rest_route( 'examplepress/v1', '/apps/(?P<slug>[a-z0-9-]+)/deactivate', [
		'methods'             => 'POST',
		'callback'            => 'examplepress_handle_app_deactivate',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
		'args' => [
			'slug' => [
				'required'          => true,
				'type'              => 'string',
				'description'       => 'App slug.',
				'sanitize_callback' => 'sanitize_title',
				'validate_callback' => function ( $value ) {
					return (bool) preg_match( '/^[a-z0-9-]+$/', $value );
				},
			],
		],
	] );

	register_rest_route( 'examplepress/v1', '/apps/(?P<slug>[a-z0-9-]+)/connect', [
		'methods'             => 'POST',
		'callback'            => 'examplepress_handle_app_connect',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
		'args' => [
			'slug' => [
				'required'          => true,
				'type'              => 'string',
				'description'       => 'App slug.',
				'sanitize_callback' => 'sanitize_title',
				'validate_callback' => function ( $value ) {
					return (bool) preg_match( '/^[a-z0-9-]+$/', $value );
				},
			],
		],
	] );

}

/**
 * List all discovered ExamplePress apps.
 */
function examplepress_handle_apps_list() {
	return rest_ensure_response( examplepress_get_apps() );
}

/**
 * Scaffold a new ExamplePress app.
 *
 * Creates a repo from the official GitHub template repository
 * (webmultipliers/examplepress-theme-app), replaces placeholders
 * remotely, optionally registers with Troy, and returns the repo
 * URL + Codespaces link. No local files are written.
 *
 * Requires a GitHub write token.
 */
function examplepress_handle_app_scaffold( WP_REST_Request $request ) {
	$name = sanitize_text_field( $request->get_param( 'name' ) ?? '' );
	$desc = sanitize_text_field( $request->get_param( 'description' ) ?? '' );

	if ( ! $name ) {
		return new WP_Error(
			'missing_name',
			'App name is required.',
			[ 'status' => 400 ]
		);
	}

	$slug = examplepress_slugify_app_name( $name );

	if ( ! $slug ) {
		return new WP_Error(
			'invalid_name',
			'Could not generate a valid slug from the provided name.',
			[ 'status' => 400 ]
		);
	}

	if ( ! examplepress_github_get_write_token() ) {
		return new WP_Error(
			'no_github_token',
			'A GitHub write token is required to scaffold apps. Install the GitHub App or configure a write access token.',
			[ 'status' => 403 ]
		);
	}

	return examplepress_scaffold_template_mode( $slug, $name, $desc );
}

/**
 * Template mode: create repo from GitHub template, replace placeholders remotely.
 */
function examplepress_scaffold_template_mode( string $slug, string $name, string $desc ) {
	$description = $desc ?: 'An ExamplePress companion plugin.';
	$org         = get_option( 'ep_github_org', 'webmultipliers' );
	$warnings    = [];
	$steps       = [];

	// ── Step 1: Create repo from template ───────────────────────

	$repo_result = examplepress_scaffold_from_template( $slug, $description, $org );

	if ( is_wp_error( $repo_result ) ) {
		// If repo already exists, this is not fatal — report and continue.
		if ( $repo_result->get_error_code() === 'repo_exists' ) {
			$steps['template_create'] = false;
			$warnings[] = $repo_result->get_error_message();

			return rest_ensure_response( [
				'success'  => false,
				'message'  => $repo_result->get_error_message(),
				'warnings' => $warnings,
				'steps'    => $steps,
			] );
		}

		return $repo_result;
	}

	$steps['template_create'] = true;

	$full_name = $repo_result['full_name'];
	$repo_id   = $repo_result['id'];
	$html_url  = $repo_result['html_url'];

	// ── Step 2: Replace placeholders in the new repo ────────────

	$troy_url     = get_option( 'ep_troy_server_url', '' );
	$troy_display = $troy_url
		? str_replace( [ 'https://', 'http://' ], '', rtrim( $troy_url, '/' ) )
		: '';

	$replace_result = examplepress_scaffold_replace_remote_placeholders(
		$full_name, $slug, $name, $description, $troy_display
	);

	if ( is_wp_error( $replace_result ) ) {
		$warnings[]                     = 'Placeholder replacement: ' . $replace_result->get_error_message();
		$steps['placeholder_replace'] = false;
	} else {
		$steps['placeholder_replace'] = true;
	}

	// ── Step 3: Register with Troy (optional) ───────────────────

	if ( $troy_url && get_option( 'ep_troy_credentials', '' ) ) {
		$troy_result = examplepress_troy_register_and_connect(
			$slug, $name, $description, $full_name
		);

		if ( is_wp_error( $troy_result ) ) {
			if ( $troy_result->get_error_code() === 'troy_slug_exists' ) {
				$steps['troy_register'] = true;
			} else {
				$warnings[]             = 'Troy: ' . $troy_result->get_error_message();
				$steps['troy_register'] = false;
			}
		} else {
			$steps['troy_register'] = true;
		}
	} else {
		$steps['troy_register'] = null;
	}

	return rest_ensure_response( [
		'success'        => true,
		'mode'           => 'template',
		'message'        => "App \"{$name}\" created from template.",
		'repo_url'       => $html_url,
		'codespaces_url' => examplepress_codespaces_url( $full_name, $repo_id ),
		'owner_repo'     => $full_name,
		'warnings'       => $warnings,
		'steps'          => $steps,
	] );
}

/**
 * Prepare Troy binding for an app.
 *
 * Returns the Troy scaffold URL for client-side redirect.
 * The actual repo provisioning happens on Troy's side.
 */
function examplepress_handle_app_troy_bind( WP_REST_Request $request ) {
	$slug      = $request->get_param( 'slug' );
	$troy_type = sanitize_text_field( $request->get_param( 'troy_type' ) ?? 'cloud' );
	$custom_url = esc_url_raw( $request->get_param( 'custom_url' ) ?? '' );

	$plugin_path = WP_PLUGIN_DIR . '/' . $slug;
	$json_path   = $plugin_path . '/examplepress.json';

	if ( ! file_exists( $json_path ) ) {
		return new WP_Error(
			'app_not_found',
			'App not found or missing examplepress.json.',
			[ 'status' => 404 ]
		);
	}

	$troy_server = 'cloud' === $troy_type
		? 'internal.repo.mustuse.com'
		: rtrim( str_replace( [ 'https://', 'http://' ], '', $custom_url ), '/' );

	if ( empty( $troy_server ) ) {
		return new WP_Error(
			'missing_troy_url',
			'Troy server URL is required for custom server binding.',
			[ 'status' => 400 ]
		);
	}

	$redirect_url = 'https://' . $troy_server . '/scaffold?' . http_build_query( [
		'slug'  => $slug,
		'theme' => 'examplepress-theme',
	] );

	return rest_ensure_response( [
		'success'      => true,
		'troy_server'  => $troy_server,
		'redirect_url' => $redirect_url,
	] );
}

/**
 * Deactivate an ExamplePress app.
 */
function examplepress_handle_app_deactivate( WP_REST_Request $request ) {
	$slug = $request->get_param( 'slug' );
	$apps = examplepress_get_apps();
	$app  = null;

	foreach ( $apps as $a ) {
		if ( $a['slug'] === $slug ) {
			$app = $a;
			break;
		}
	}

	if ( ! $app ) {
		return new WP_Error(
			'app_not_found',
			'App not found.',
			[ 'status' => 404 ]
		);
	}

	if ( ! $app['active'] ) {
		return rest_ensure_response( [
			'success' => true,
			'message' => 'App is already inactive.',
		] );
	}

	deactivate_plugins( $app['plugin_file'] );

	return rest_ensure_response( [
		'success' => true,
		'message' => "App \"{$app['name']}\" deactivated.",
	] );
}

/**
 * Connect a disconnected app: create GitHub repo, push code, register on Troy.
 *
 * Runs the same GitHub+Troy flow as the scaffold endpoint but on an
 * existing plugin that's already installed locally.
 */
function examplepress_handle_app_connect( WP_REST_Request $request ) {
	$slug = $request->get_param( 'slug' );
	$apps = examplepress_get_apps();
	$app  = null;

	foreach ( $apps as $a ) {
		if ( $a['slug'] === $slug ) {
			$app = $a;
			break;
		}
	}

	if ( ! $app ) {
		return new WP_Error( 'app_not_found', 'App not found.', [ 'status' => 404 ] );
	}

	// No early return for "connected" apps — this endpoint also heals
	// incomplete connections (e.g. Troy registered but no GitHub repo).

	$plugin_path = WP_PLUGIN_DIR . '/' . $slug;
	$name        = $app['name'];
	$description = $app['description'] ?: 'An ExamplePress app.';
	$org         = get_option( 'ep_github_org', 'webmultipliers' );
	$owner_repo  = $org . '/' . $slug;
	$warnings    = [];
	$troy_data   = [];
	$github_data = [];

	// ── Step 1: Create GitHub repo ──────────────────────────────

	$write_token = examplepress_github_get_write_token();

	if ( $write_token ) {
		$repo_result = examplepress_github_create_repo( $slug, $description );

		if ( is_wp_error( $repo_result ) ) {
			if ( $repo_result->get_error_code() === 'repo_exists' ) {
				// Repo already exists — proceed.
				$github_data = [ 'owner_repo' => $owner_repo ];
				$warnings[]  = 'GitHub repo already exists — skipped creation.';
			} else {
				$warnings[] = 'GitHub repo: ' . $repo_result->get_error_message();
			}
		} else {
			$github_data = $repo_result;
			$owner_repo  = $repo_result['owner_repo'];

			// ── Step 2: Push code to repo ───────────────────────

			$push_result = examplepress_github_push_scaffold( $repo_result['owner_repo'], $plugin_path );

			if ( is_wp_error( $push_result ) ) {
				$warnings[] = 'GitHub push: ' . $push_result->get_error_message();
			}
		}
	} else {
		$warnings[] = 'No GitHub write token. Install the GitHub App or configure a write access token.';
	}

	// ── Step 3: Register on Troy + connect GitHub ───────────────
	// Always attempt if Troy is configured — independent of GitHub.

	$troy_url = get_option( 'ep_troy_server_url', '' );

	if ( $troy_url && get_option( 'ep_troy_credentials', '' ) ) {
		$troy_result = examplepress_troy_register_and_connect(
			$slug, $name, $description, $owner_repo
		);

		if ( is_wp_error( $troy_result ) ) {
			if ( $troy_result->get_error_code() === 'troy_slug_exists' ) {
				// Already registered — that's fine for reconnecting.
			} else {
				$warnings[] = 'Troy: ' . $troy_result->get_error_message();
			}
		} else {
			$integration_ok = ! empty( $troy_result['integration'] );

			if ( ! $integration_ok && ! empty( $troy_result['warning'] ) ) {
				$warnings[] = 'Troy integration: ' . $troy_result['warning'];
			}
		}

		// Always write Troy data to local JSON.
		$troy_data = [
			'server_url' => str_replace( [ 'https://', 'http://' ], '', rtrim( $troy_url, '/' ) ),
			'repo'       => $owner_repo,
			'repo_id'    => (string) ( $github_data['repo_id'] ?? '' ),
		];
	} elseif ( ! $troy_url ) {
		$warnings[] = 'No Troy Server configured.';
	}

	// ── Step 4: Write back Troy data to local JSON ──────────────

	if ( ! empty( $troy_data ) ) {
		if ( ! examplepress_update_app_troy_data( $slug, $troy_data ) ) {
			$warnings[] = 'Failed to write Troy data to examplepress.json.';
		}
	}

	// Re-read the app data.
	$updated_app = examplepress_parse_app( $slug, $plugin_path . '/examplepress.json', $plugin_path );

	return rest_ensure_response( [
		'success'  => true,
		'message'  => empty( $warnings ) ? "App \"{$name}\" connected." : "App \"{$name}\" partially connected.",
		'app'      => $updated_app,
		'warnings' => $warnings,
		'github'   => $github_data,
	] );
}
