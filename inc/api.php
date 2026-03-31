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

	register_rest_route( 'examplepress/v1', '/settings/connections', [
		'methods'             => 'POST',
		'callback'            => 'examplepress_handle_save_connections',
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
 * Scaffold a new ExamplePress app from the template.
 *
 * Orchestrates up to six steps in one request:
 *   1. Scaffold plugin locally
 *   2. Create GitHub repo (if PAT configured)
 *   3. Push scaffold code to GitHub
 *   4. Register plugin on Troy
 *   5. Connect Troy ↔ GitHub
 *   6. Write back Troy data to local JSON
 *
 * Degrades gracefully — if GitHub PAT or Troy credentials are missing,
 * those steps are skipped and reported as warnings.
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

	$slug   = examplepress_slugify_app_name( $name );
	$source = EP_THEME_PATH . '/demo/app-scaffold';
	$dest   = WP_PLUGIN_DIR . '/' . $slug;

	if ( ! $slug ) {
		return new WP_Error(
			'invalid_name',
			'Could not generate a valid slug from the provided name.',
			[ 'status' => 400 ]
		);
	}

	if ( is_dir( $dest ) ) {
		return new WP_Error(
			'app_exists',
			'A plugin directory with this slug already exists.',
			[ 'status' => 409 ]
		);
	}

	if ( ! is_dir( $source ) ) {
		return new WP_Error(
			'scaffold_missing',
			'App scaffold template not found in the theme.',
			[ 'status' => 500 ]
		);
	}

	// ── Step 1: Scaffold locally ────────────────────────────────

	if ( ! examplepress_copy_dir( $source, $dest ) ) {
		examplepress_delete_dir( $dest );
		return new WP_Error(
			'scaffold_failed',
			'Failed to copy the scaffold template.',
			[ 'status' => 500 ]
		);
	}

	$description = $desc ?: 'An ExamplePress app.';

	examplepress_scaffold_replace_placeholders( $dest, $name, $slug, $description );

	$template_file = $dest . '/__SLUG__.php';
	$plugin_file   = $dest . '/' . $slug . '.php';

	if ( file_exists( $template_file ) ) {
		rename( $template_file, $plugin_file );
	}

	$block_json = $dest . '/app/templates/front/block.json';
	if ( file_exists( $block_json ) ) {
		$block_content = file_get_contents( $block_json );
		$block_content = str_replace( '__SLUG__', $slug, $block_content );
		file_put_contents( $block_json, $block_content );
	}

	$plugin_path = $dest;
	$troy_data   = [];
	$github_data = [];
	$warnings    = [];
	$steps       = [ 'scaffold' => true ];

	// ── Step 2: Create GitHub repo ──────────────────────────────

	$pat = get_option( 'ep_github_pat', '' );

	if ( $pat ) {
		$repo_result = examplepress_github_create_repo( $slug, $description );

		if ( is_wp_error( $repo_result ) ) {
			$warnings[]           = 'GitHub repo creation failed: ' . $repo_result->get_error_message();
			$steps['github_repo'] = false;
		} else {
			$github_data          = $repo_result;
			$steps['github_repo'] = true;

			// ── Step 3: Push scaffold to repo ───────────────────

			$push_result = examplepress_github_push_scaffold( $repo_result['owner_repo'], $plugin_path );

			if ( is_wp_error( $push_result ) ) {
				$warnings[]           = 'GitHub push failed: ' . $push_result->get_error_message();
				$steps['github_push'] = false;
			} else {
				$steps['github_push'] = true;
			}
		}
	} else {
		$steps['github_repo'] = null;
		$steps['github_push'] = null;
	}

	// ── Steps 4+5: Register on Troy + connect GitHub ────────────

	$troy_url = get_option( 'ep_troy_server_url', '' );

	if ( $troy_url && ! empty( $github_data['owner_repo'] ) ) {
		$troy_result = examplepress_troy_register_and_connect(
			$slug,
			$name,
			$description,
			$github_data['owner_repo']
		);

		if ( is_wp_error( $troy_result ) ) {
			$warnings[]              = 'Troy registration failed: ' . $troy_result->get_error_message();
			$steps['troy_register']  = false;
			$steps['troy_connect']   = false;
		} else {
			$steps['troy_register'] = true;

			// Troy may create the plugin but fail the integration.
			// integration: null + warning field means slug is reserved
			// but GitHub connect didn't work (Troy's own auth issue, etc.).
			$integration_ok = ! empty( $troy_result['integration'] );
			$steps['troy_connect'] = $integration_ok;

			if ( ! $integration_ok && ! empty( $troy_result['warning'] ) ) {
				$warnings[] = 'Troy integration: ' . $troy_result['warning'];
			}

			$troy_data = [
				'server_url' => str_replace( [ 'https://', 'http://' ], '', rtrim( $troy_url, '/' ) ),
				'repo'       => $github_data['owner_repo'],
				'repo_id'    => (string) ( $github_data['repo_id'] ?? '' ),
			];
		}
	} else {
		$steps['troy_register'] = null;
		$steps['troy_connect']  = null;
	}

	// ── Step 6: Write back Troy data to local JSON ──────────────

	if ( ! empty( $troy_data ) ) {
		$write_ok              = examplepress_update_app_troy_data( $slug, $troy_data );
		$steps['troy_writeback'] = $write_ok;

		if ( ! $write_ok ) {
			$warnings[] = 'Failed to write Troy data back to examplepress.json.';
		}
	} else {
		$steps['troy_writeback'] = null;
	}

	// ── Activate the plugin ─────────────────────────────────────

	$relative = $slug . '/' . $slug . '.php';
	$result   = activate_plugin( $relative );
	$status   = is_wp_error( $result ) ? 'installed' : 'active';

	// Re-read the app data (now includes Troy connection if successful).
	$app = examplepress_parse_app( $slug, $dest . '/examplepress.json', $dest );

	return rest_ensure_response( [
		'success'  => true,
		'status'   => $status,
		'message'  => "App \"{$name}\" created.",
		'app'      => $app,
		'warnings' => $warnings,
		'steps'    => $steps,
		'github'   => $github_data,
	] );
}

/**
 * Replace placeholders in all scaffold files.
 */
function examplepress_scaffold_replace_placeholders( string $dir, string $name, string $slug, string $description ): void {
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $iterator as $file ) {
		if ( $file->isDir() ) {
			continue;
		}

		$path    = $file->getPathname();
		$content = file_get_contents( $path );

		if ( $content === false ) {
			continue;
		}

		$replaced = str_replace(
			[ '__NAME__', '__SLUG__', '__DESC__' ],
			[ $name, $slug, $description ],
			$content
		);

		if ( $replaced !== $content ) {
			file_put_contents( $path, $replaced );
		}
	}
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

// ── Connection Settings ──────────────────────────────────────────

/**
 * Save connection settings (GitHub PAT, org, Troy credentials).
 */
function examplepress_handle_save_connections( WP_REST_Request $request ) {
	$fields = [
		'ep_github_pat'      => 'github_pat',
		'ep_github_org'      => 'github_org',
		'ep_troy_server_url' => 'troy_server_url',
		'ep_troy_credentials' => 'troy_credentials',
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
