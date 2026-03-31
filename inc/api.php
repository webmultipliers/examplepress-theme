<?php
/**
 * ExamplePress REST API
 *
 * REST endpoints for the ExamplePress admin dashboard.
 * Handles app scaffolding (with GitHub + Troy orchestration),
 * connection settings, and demo companion plugin management.
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
	] );

	register_rest_route( 'examplepress/v1', '/apps/(?P<slug>[a-z0-9-]+)/troy-bind', [
		'methods'             => 'POST',
		'callback'            => 'examplepress_handle_app_troy_bind',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
	] );

	register_rest_route( 'examplepress/v1', '/apps/(?P<slug>[a-z0-9-]+)/deactivate', [
		'methods'             => 'POST',
		'callback'            => 'examplepress_handle_app_deactivate',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
	] );

	register_rest_route( 'examplepress/v1', '/apps/(?P<slug>[a-z0-9-]+)/connect', [
		'methods'             => 'POST',
		'callback'            => 'examplepress_handle_app_connect',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
	] );

	register_rest_route( 'examplepress/v1', '/settings/connections', [
		'methods'             => 'POST',
		'callback'            => 'examplepress_handle_save_connections',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
	] );

	register_rest_route( 'examplepress/v1', '/settings/test-github', [
		'methods'             => 'POST',
		'callback'            => 'examplepress_handle_test_github',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
	] );

	register_rest_route( 'examplepress/v1', '/settings/test-troy', [
		'methods'             => 'POST',
		'callback'            => 'examplepress_handle_test_troy',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
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

// ── Connection Settings ──────────────────────────────────────────

/**
 * Save connection settings (GitHub PAT, org, Troy URL, Troy GitHub read token).
 */
function examplepress_handle_save_connections( WP_REST_Request $request ) {
	$fields = [
		'ep_github_pat'       => 'github_pat',
		'ep_github_org'       => 'github_org',
		'ep_troy_server_url'  => 'troy_server_url',
		'ep_troy_github_pat'  => 'troy_github_pat',
	];

	$updated = [];

	foreach ( $fields as $option_key => $param_key ) {
		$value = $request->get_param( $param_key );

		if ( $value === null ) {
			continue;
		}

		$value = sanitize_text_field( $value );

		// Normalize Troy URL to always have https://.
		if ( $option_key === 'ep_troy_server_url' && $value ) {
			if ( ! str_starts_with( $value, 'http' ) ) {
				$value = 'https://' . $value;
			}
			$value = rtrim( $value, '/' );
		}

		update_option( $option_key, $value );
		$updated[] = $param_key;
	}

	return rest_ensure_response( [
		'success' => true,
		'updated' => $updated,
		'message' => 'Connection settings saved.',
	] );
}

// ── Connection Tests ─────────────────────────────────────────────

/**
 * Test the GitHub connection.
 *
 * Verifies the write token can authenticate and access the configured org.
 * Also checks the Troy read token if configured.
 */
function examplepress_handle_test_github( WP_REST_Request $request ) {
	$org    = get_option( 'ep_github_org', 'webmultipliers' );
	$checks = [];

	// Test write token.
	$write_token = examplepress_github_get_write_token();

	if ( ! $write_token ) {
		$checks['write'] = [
			'ok'      => false,
			'message' => 'No write token. Install the GitHub App or enter a write access token.',
		];
	} else {
		// Step 1: Check token identity.
		$user_resp = wp_remote_get( 'https://api.github.com/user', [
			'headers' => [
				'Authorization' => "Bearer {$write_token}",
				'Accept'        => 'application/vnd.github.v3+json',
				'User-Agent'    => 'ExamplePress/' . EP_THEME_VERSION,
			],
			'timeout' => 10,
		] );

		if ( is_wp_error( $user_resp ) ) {
			$checks['write'] = [ 'ok' => false, 'message' => 'Network error: ' . $user_resp->get_error_message() ];
		} else {
			$code = wp_remote_retrieve_response_code( $user_resp );

			if ( $code !== 200 ) {
				$body = json_decode( wp_remote_retrieve_body( $user_resp ), true );
				$checks['write'] = [ 'ok' => false, 'message' => 'Auth failed: ' . ( $body['message'] ?? "HTTP {$code}" ) ];
			} else {
				$user_body = json_decode( wp_remote_retrieve_body( $user_resp ), true );
				$login     = $user_body['login'] ?? '?';

				// Step 2: Check org membership + role.
				$mem_resp = wp_remote_get( "https://api.github.com/orgs/{$org}/memberships/{$login}", [
					'headers' => [
						'Authorization' => "Bearer {$write_token}",
						'Accept'        => 'application/vnd.github.v3+json',
						'User-Agent'    => 'ExamplePress/' . EP_THEME_VERSION,
					],
					'timeout' => 10,
				] );

				$mem_info = '';
				if ( ! is_wp_error( $mem_resp ) && wp_remote_retrieve_response_code( $mem_resp ) === 200 ) {
					$mem_body = json_decode( wp_remote_retrieve_body( $mem_resp ), true );
					$role     = $mem_body['role'] ?? 'unknown';
					$state    = $mem_body['state'] ?? 'unknown';
					$mem_info = " Role: {$role}. State: {$state}.";
				}

				// Step 3: Try listing org repos to check repo permissions.
				$repo_resp = wp_remote_get( "https://api.github.com/orgs/{$org}/repos?per_page=1&type=all", [
					'headers' => [
						'Authorization' => "Bearer {$write_token}",
						'Accept'        => 'application/vnd.github.v3+json',
						'User-Agent'    => 'ExamplePress/' . EP_THEME_VERSION,
					],
					'timeout' => 10,
				] );

				$repo_info = '';
				if ( ! is_wp_error( $repo_resp ) ) {
					$repo_code = wp_remote_retrieve_response_code( $repo_resp );
					if ( $repo_code === 200 ) {
						$repo_info = ' Can list repos.';
					} else {
						$rbody     = json_decode( wp_remote_retrieve_body( $repo_resp ), true );
						$repo_info = ' Repo list: ' . ( $rbody['message'] ?? "HTTP {$repo_code}" );
					}
				}

				// Step 4: Actually test repo creation with a dry-run-like approach.
				// Try creating a repo with an invalid name to see if we get 403 vs 422.
				$create_resp = wp_remote_post( "https://api.github.com/orgs/{$org}/repos", [
					'headers' => [
						'Authorization' => "Bearer {$write_token}",
						'Accept'        => 'application/vnd.github.v3+json',
						'User-Agent'    => 'ExamplePress/' . EP_THEME_VERSION,
						'Content-Type'  => 'application/json',
					],
					'body'    => wp_json_encode( [
						'name'        => '.ep-test-' . wp_rand( 1000, 9999 ),
						'description' => 'ExamplePress connection test — will be deleted.',
						'private'     => true,
						'auto_init'   => false,
					] ),
					'timeout' => 15,
				] );

				$create_info = '';
				$can_create  = false;

				if ( ! is_wp_error( $create_resp ) ) {
					$create_code = wp_remote_retrieve_response_code( $create_resp );
					$create_body = json_decode( wp_remote_retrieve_body( $create_resp ), true );

					if ( $create_code === 201 ) {
						$can_create  = true;
						$create_info = ' Can create repos.';
						// Clean up: delete the test repo.
						$test_repo = $create_body['full_name'] ?? '';
						if ( $test_repo ) {
							wp_remote_request( "https://api.github.com/repos/{$test_repo}", [
								'method'  => 'DELETE',
								'headers' => [
									'Authorization' => "Bearer {$write_token}",
									'Accept'        => 'application/vnd.github.v3+json',
									'User-Agent'    => 'ExamplePress/' . EP_THEME_VERSION,
								],
								'timeout' => 10,
							] );
						}
					} elseif ( $create_code === 403 ) {
						$create_info = ' CANNOT create repos: ' . ( $create_body['message'] ?? 'Forbidden.' );
					} elseif ( $create_code === 422 ) {
						// 422 = validation error (bad name) but the permission was granted.
						$can_create  = true;
						$create_info = ' Can create repos (name validation confirmed access).';
					} else {
						$create_info = ' Repo create test: ' . ( $create_body['message'] ?? "HTTP {$create_code}" );
					}
				}

				$checks['write'] = [
					'ok'      => $can_create,
					'message' => "User: {$login}.{$mem_info}{$repo_info}{$create_info}",
				];
			}
		}
	}

	// Test read token (for Troy).
	$read_token = get_option( 'ep_troy_github_pat', '' );

	if ( ! $read_token ) {
		$checks['read'] = [ 'ok' => null, 'message' => 'No Troy read token. Will fall back to write token.' ];
	} else {
		$response = wp_remote_get( 'https://api.github.com/user', [
			'headers' => [
				'Authorization' => "Bearer {$read_token}",
				'Accept'        => 'application/vnd.github.v3+json',
				'User-Agent'    => 'ExamplePress/' . EP_THEME_VERSION,
			],
			'timeout' => 10,
		] );

		if ( is_wp_error( $response ) ) {
			$checks['read'] = [ 'ok' => false, 'message' => 'Network error: ' . $response->get_error_message() ];
		} else {
			$code = wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( $code === 200 ) {
				$login = $body['login'] ?? '?';
				$checks['read'] = [ 'ok' => true, 'message' => "Authenticated as {$login}." ];
			} else {
				$msg = $body['message'] ?? "HTTP {$code}";
				$checks['read'] = [ 'ok' => false, 'message' => $msg ];
			}
		}
	}

	$all_ok = ! empty( $checks['write']['ok'] );

	return rest_ensure_response( [
		'success' => $all_ok,
		'checks'  => $checks,
		'message' => $all_ok ? 'GitHub connection OK.' : 'GitHub connection has issues.',
	] );
}

/**
 * Test the Troy Server connection.
 *
 * Verifies the Troy URL is reachable and the stored credentials work.
 */
function examplepress_handle_test_troy( WP_REST_Request $request ) {
	$troy_url  = get_option( 'ep_troy_server_url', '' );
	$troy_auth = get_option( 'ep_troy_credentials', '' );
	$checks    = [];

	if ( ! $troy_url ) {
		return rest_ensure_response( [
			'success' => false,
			'checks'  => [ 'url' => [ 'ok' => false, 'message' => 'No Troy Server URL configured.' ] ],
			'message' => 'No Troy Server URL configured.',
		] );
	}

	$troy_url = rtrim( $troy_url, '/' );

	// Check if the REST API is reachable.
	$discovery = wp_remote_get( "{$troy_url}/wp-json/", [
		'timeout' => 15,
		'headers' => [ 'User-Agent' => 'ExamplePress/' . EP_THEME_VERSION ],
	] );

	if ( is_wp_error( $discovery ) ) {
		$checks['url'] = [ 'ok' => false, 'message' => 'Cannot reach server: ' . $discovery->get_error_message() ];
	} else {
		$code = wp_remote_retrieve_response_code( $discovery );
		if ( $code === 200 ) {
			$body = json_decode( wp_remote_retrieve_body( $discovery ), true );
			$name = $body['name'] ?? 'Unknown';
			$checks['url'] = [ 'ok' => true, 'message' => "Reachable. Site: {$name}." ];
		} else {
			$checks['url'] = [ 'ok' => false, 'message' => "Server returned HTTP {$code}." ];
		}
	}

	// Check authentication.
	if ( ! $troy_auth ) {
		$checks['auth'] = [ 'ok' => false, 'message' => 'No credentials. Click "Authorize with Troy".' ];
	} else {
		$auth_resp = wp_remote_get( "{$troy_url}/wp-json/wp/v2/users/me?context=edit", [
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode( $troy_auth ),
				'User-Agent'    => 'ExamplePress/' . EP_THEME_VERSION,
			],
			'timeout' => 15,
		] );

		if ( is_wp_error( $auth_resp ) ) {
			$checks['auth'] = [ 'ok' => false, 'message' => 'Network error: ' . $auth_resp->get_error_message() ];
		} else {
			$code = wp_remote_retrieve_response_code( $auth_resp );
			$body = json_decode( wp_remote_retrieve_body( $auth_resp ), true );

			if ( $code === 200 && ! empty( $body['id'] ) ) {
				$user = $body['name'] ?? $body['slug'] ?? '?';
				$checks['auth'] = [ 'ok' => true, 'message' => "Authenticated as {$user}." ];
			} elseif ( $code === 401 ) {
				$checks['auth'] = [ 'ok' => false, 'message' => 'Authentication failed. Re-authorize with Troy.' ];
			} else {
				$msg = $body['message'] ?? "HTTP {$code}";
				$checks['auth'] = [ 'ok' => false, 'message' => $msg ];
			}
		}
	}

	$all_ok = ! empty( $checks['url']['ok'] ) && ! empty( $checks['auth']['ok'] );

	return rest_ensure_response( [
		'success' => $all_ok,
		'checks'  => $checks,
		'message' => $all_ok ? 'Troy connection OK.' : 'Troy connection has issues.',
	] );
}

// ── Troy Application Password Authorization ─────────────────────

/**
 * Handle the Troy Application Password callback.
 *
 * When Troy redirects back with user_login + password, this renders
 * a minimal page that stores the credentials via REST and closes
 * the popup window.
 */
add_action( 'admin_init', 'examplepress_handle_troy_auth_callback' );

function examplepress_handle_troy_auth_callback() {
	if ( ! isset( $_GET['ep_troy_auth_cb'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized.', 403 );
	}

	// Rejected.
	if ( $_GET['ep_troy_auth_cb'] === 'rejected' ) {
		examplepress_troy_auth_close_popup( false, 'Authorization was rejected.' );
		return;
	}

	$user_login = sanitize_text_field( $_GET['user_login'] ?? '' );
	$password   = sanitize_text_field( $_GET['password'] ?? '' );

	if ( ! $user_login || ! $password ) {
		examplepress_troy_auth_close_popup( false, 'Missing credentials in callback.' );
		return;
	}

	// Store the credentials.
	update_option( 'ep_troy_credentials', $user_login . ':' . $password );

	examplepress_troy_auth_close_popup( true, 'Connected to Troy.' );
}

/**
 * Render a minimal HTML page that communicates result to the opener
 * and closes itself.
 */
function examplepress_troy_auth_close_popup( bool $success, string $message ) {
	$data = wp_json_encode( [
		'success' => $success,
		'message' => $message,
	] );

	// postMessage targetOrigin must be just the scheme+host, not a full URL.
	$origin = home_url( '', 'https' );
	$parsed = wp_parse_url( $origin );
	$target_origin = ( $parsed['scheme'] ?? 'https' ) . '://' . ( $parsed['host'] ?? '' );
	if ( ! empty( $parsed['port'] ) ) {
		$target_origin .= ':' . $parsed['port'];
	}

	?>
	<!DOCTYPE html>
	<html>
	<head><title>ExamplePress — Troy Authorization</title></head>
	<body>
	<script>
		if ( window.opener ) {
			window.opener.postMessage(<?php echo $data; ?>, <?php echo wp_json_encode( $target_origin ); ?>);
		}
		window.close();
	</script>
	<p><?php echo esc_html( $message ); ?></p>
	<p><small>This window should close automatically. If it doesn't, you can close it manually.</small></p>
	</body>
	</html>
	<?php
	exit;
}

// ── GitHub App Installation Callback ─────────────────────────────

/**
 * Handle the GitHub App installation callback.
 *
 * After a user installs the ExamplePress GitHub App on their org,
 * GitHub redirects to our setup_url with ?installation_id=X.
 * We store the installation ID and close the popup.
 */
add_action( 'admin_init', 'examplepress_handle_github_app_callback' );

function examplepress_handle_github_app_callback() {
	if ( ! isset( $_GET['ep_github_app_cb'] ) ) {
		return;
	}

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( 'Unauthorized.', 403 );
	}

	$installation_id = sanitize_text_field( $_GET['installation_id'] ?? '' );

	if ( ! $installation_id ) {
		examplepress_troy_auth_close_popup( false, 'No installation ID received from GitHub.' );
		return;
	}

	update_option( 'ep_github_app_installation_id', $installation_id );

	// Clear any cached installation token since the installation changed.
	delete_transient( 'ep_github_app_token' );

	examplepress_troy_auth_close_popup( true, 'GitHub App installed.' );
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
