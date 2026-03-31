<?php
/**
 * ExamplePress Notification Hub
 *
 * Aggregates system warnings (missing dependencies, failing health
 * checks) into a structured notification array. Users can archive
 * notifications via a REST endpoint — archived state is stored in
 * user_meta so it's per-user.
 *
 * This replaces the WordPress admin_notices area for ExamplePress
 * messages, keeping the dashboard clean.
 */

// ── REST Endpoint ──────────────────────────────────────────────────

add_action( 'rest_api_init', function () {
	register_rest_route( 'examplepress/v1', '/notifications/archive', [
		'methods'             => 'POST',
		'callback'            => 'examplepress_archive_notification',
		'permission_callback' => function () {
			return current_user_can( 'manage_options' );
		},
	] );
} );

/**
 * Handle archive/restore requests for individual notifications.
 */
function examplepress_archive_notification( WP_REST_Request $request ) {
	$id     = sanitize_text_field( $request->get_param( 'id' ) );
	$action = sanitize_text_field( $request->get_param( 'action' ) );

	if ( empty( $id ) || ! in_array( $action, [ 'archive', 'restore' ], true ) ) {
		return new WP_Error( 'invalid_request', 'Missing id or invalid action.', [ 'status' => 400 ] );
	}

	$user_id  = get_current_user_id();
	$archived = get_user_meta( $user_id, 'ep_archived_notifications', true );
	$archived = is_array( $archived ) ? $archived : [];

	if ( $action === 'archive' && ! in_array( $id, $archived, true ) ) {
		$archived[] = $id;
	} elseif ( $action === 'restore' ) {
		$archived = array_values( array_diff( $archived, [ $id ] ) );
	}

	update_user_meta( $user_id, 'ep_archived_notifications', $archived );

	return rest_ensure_response( [ 'success' => true, 'archived' => $archived ] );
}

/**
 * Get the current user's archived notification IDs.
 */
function examplepress_get_archived_notifications() {
	$meta = get_user_meta( get_current_user_id(), 'ep_archived_notifications', true );
	return is_array( $meta ) ? $meta : [];
}

// ── Notification Aggregator ────────────────────────────────────────

/**
 * Gather all active system notifications.
 *
 * Sources:
 *   1. Missing or inactive required dependencies
 *   2. Failing or warning health checks
 *   3. Developer mode active
 */
function examplepress_gather_notifications() {
	$notifications = [];

	// 1. Missing required dependencies.
	$deps = examplepress_get_dependencies();
	foreach ( $deps as $dep ) {
		if ( ( $dep['tier'] ?? '' ) !== 'required' ) {
			continue;
		}
		if ( $dep['status'] === 'active' ) {
			continue;
		}

		$msg = sprintf( '%s is required but %s.', $dep['name'], $dep['status'] === 'installed' ? 'not activated' : 'not installed' );
		if ( $dep['status'] === 'fallback' ) {
			$msg = sprintf( '%s is using the free alternative (%s). The full version is recommended.', $dep['name'], $dep['fallback']['slug'] ?? '' );
		}

		$notifications[] = [
			'id'      => 'dep_' . $dep['slug'],
			'type'    => $dep['status'] === 'fallback' ? 'warn' : 'error',
			'title'   => 'Missing Dependency',
			'message' => $msg,
		];
	}

	// 2. Developer mode warning.
	if ( defined( 'EP_DEV_MODE' ) && EP_DEV_MODE ) {
		$notifications[] = [
			'id'      => 'dev_mode',
			'type'    => 'warn',
			'title'   => 'Developer Mode Active',
			'message' => 'All template guards are bypassed. Remove EP_DEV_MODE from wp-config.php before deploying to production.',
		];
	}

	// 3. No routing configured (neither registry nor legacy namespace).
	$has_origins = examplepress_has_route_origins();

	if ( ! $has_origins && examplepress_get_theme_namespace() === 'examplepress-theme' ) {
		$notifications[] = [
			'id'      => 'namespace_default',
			'type'    => 'info',
			'title'   => 'No Routing Configured',
			'message' => 'No companion plugin has registered route origins or overridden the namespace. Use examplepress_register_route_origin() or hook examplepress_theme_namespace.',
		];
	}

	// 4. Health check failures.
	if ( function_exists( 'examplepress_settings_get_health' ) ) {
		$health = examplepress_settings_get_health();
		foreach ( $health as $checks ) {
			foreach ( $checks as $check ) {
				if ( ( $check['status'] ?? 'pass' ) === 'fail' ) {
					$notifications[] = [
						'id'      => 'health_' . sanitize_title( $check['name'] ),
						'type'    => 'error',
						'title'   => 'Health Check Failed',
						'message' => sprintf( '%s: %s (requires %s)', $check['name'], $check['detail'] ?? '', $check['req'] ?? '' ),
					];
				}
			}
		}
	}

	return $notifications;
}
