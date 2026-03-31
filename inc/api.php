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
	$org         = get_option( 'ep_github_org', 'webmultipliers' );
	$owner_repo  = $org . '/' . $slug;
	$troy_data   = [];
	$github_data = [];
	$warnings    = [];
	$steps       = [ 'scaffold' => true ];

	// ── Step 2: Create GitHub repo ──────────────────────────────

	$write_token = examplepress_github_get_write_token();

	if ( $write_token ) {
		$repo_result = examplepress_github_create_repo( $slug, $description );

		if ( is_wp_error( $repo_result ) ) {
			$err_code = $repo_result->get_error_code();

			if ( $err_code === 'repo_exists' ) {
				// Repo already exists — that's fine, proceed with it.
				$steps['github_repo'] = true;
			} else {
				$warnings[]           = 'GitHub repo: ' . $repo_result->get_error_message();
				$steps['github_repo'] = false;
			}
		} else {
			$github_data          = $repo_result;
			$owner_repo           = $repo_result['owner_repo'];
			$steps['github_repo'] = true;
		}

		// ── Step 3: Push scaffold to repo ───────────────────────
		// Only push if repo was just created (not if it already existed).

		if ( ! empty( $github_data['owner_repo'] ) ) {
			$push_result = examplepress_github_push_scaffold( $github_data['owner_repo'], $plugin_path );

			if ( is_wp_error( $push_result ) ) {
				$warnings[]           = 'GitHub push: ' . $push_result->get_error_message();
				$steps['github_push'] = false;
			} else {
				$steps['github_push'] = true;
			}
		} else {
			$steps['github_push'] = null;
		}
	} else {
		$steps['github_repo'] = null;
		$steps['github_push'] = null;
	}

	// ── Steps 4+5: Register on Troy + connect GitHub ────────────
	// Troy registration is independent of GitHub — always attempt
	// if Troy is configured. Pass owner_repo so Troy can connect
	// the integration (even if we didn't create the repo ourselves).

	$troy_url = get_option( 'ep_troy_server_url', '' );

	if ( $troy_url && get_option( 'ep_troy_credentials', '' ) ) {
		$troy_result = examplepress_troy_register_and_connect(
			$slug,
			$name,
			$description,
			$owner_repo
		);

		if ( is_wp_error( $troy_result ) ) {
			$warnings[]              = 'Troy: ' . $troy_result->get_error_message();
			$steps['troy_register']  = false;
			$steps['troy_connect']   = false;
		} else {
			$steps['troy_register'] = true;

			$integration_ok = ! empty( $troy_result['integration'] );
			$steps['troy_connect'] = $integration_ok;

			if ( ! $integration_ok && ! empty( $troy_result['warning'] ) ) {
				$warnings[] = 'Troy integration: ' . $troy_result['warning'];
			}

			$troy_data = [
				'server_url' => str_replace( [ 'https://', 'http://' ], '', rtrim( $troy_url, '/' ) ),
				'repo'       => $owner_repo,
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

	if ( $app['status'] === 'connected' ) {
		return rest_ensure_response( [
			'success'  => true,
			'message'  => 'App is already connected.',
			'app'      => $app,
			'warnings' => [],
		] );
	}

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
				// Repo already exists — proceed with it.
				$github_data = [ 'owner_repo' => $owner_repo ];
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
		$warnings[] = 'No GitHub write access — install the GitHub App or configure a write token.';
	}

	// ── Step 3: Register on Troy + connect GitHub ───────────────
	// Always attempt if Troy is configured — independent of GitHub.

	$troy_url = get_option( 'ep_troy_server_url', '' );

	if ( $troy_url && get_option( 'ep_troy_credentials', '' ) ) {
		$troy_result = examplepress_troy_register_and_connect(
			$slug, $name, $description, $owner_repo
		);

		if ( is_wp_error( $troy_result ) ) {
			$warnings[] = 'Troy: ' . $troy_result->get_error_message();
		} else {
			$integration_ok = ! empty( $troy_result['integration'] );

			if ( ! $integration_ok && ! empty( $troy_result['warning'] ) ) {
				$warnings[] = 'Troy integration: ' . $troy_result['warning'];
			}

			$troy_data = [
				'server_url' => str_replace( [ 'https://', 'http://' ], '', rtrim( $troy_url, '/' ) ),
				'repo'       => $owner_repo,
				'repo_id'    => (string) ( $github_data['repo_id'] ?? '' ),
			];
		}
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
