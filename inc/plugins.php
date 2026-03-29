<?php
/**
 * Plugin Dependency Checker
 *
 * Reads the "plugins" array from examplepress.json and surfaces a
 * single dismissible admin notice when required plugins are missing.
 * Supports fallback slugs: if a paid plugin is missing but its free
 * WordPress.org alternative is active, the dependency is satisfied.
 */

add_action( 'admin_notices', 'examplepress_check_plugin_dependencies' );

/**
 * Display an admin notice if any required plugins are missing or inactive.
 */
function examplepress_check_plugin_dependencies() {
	$config  = examplepress_get_config();
	$plugins = $config['plugins'] ?? [];

	if ( empty( $plugins ) ) {
		return;
	}

	// Filter to required tier only.
	$required = array_filter( $plugins, fn( $p ) => ( $p['tier'] ?? '' ) === 'required' );

	if ( empty( $required ) ) {
		return;
	}

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$installed = get_plugins();
	$active    = array_map( 'plugin_basename', wp_get_active_and_valid_plugins() );
	$missing   = [];

	foreach ( $required as $plugin ) {
		$slug = $plugin['slug'] ?? '';
		$name = $plugin['name'] ?? $slug;

		if ( empty( $slug ) ) {
			continue;
		}

		// Check primary plugin.
		if ( examplepress_is_plugin_active( $slug, $installed, $active ) ) {
			continue;
		}

		// Check fallback if defined.
		$fallback = $plugin['fallback_slug'] ?? '';
		if ( $fallback && examplepress_is_plugin_active( $fallback, $installed, $active ) ) {
			continue; // Satisfied by free version.
		}

		$is_paid  = ( $plugin['pricing'] ?? 'free' ) === 'paid';
		$url      = $plugin['source']['url'] ?? '';
		$msg      = sprintf( '<strong>%s</strong>', esc_html( $name ) );

		if ( examplepress_is_plugin_installed( $slug, $installed ) ) {
			$msg .= ' (installed but inactive)';
		} else {
			$msg .= ' (not installed)';
			if ( $is_paid && $url ) {
				$msg .= sprintf( ' — <a href="%s" target="_blank" rel="noopener">Purchase</a>', esc_url( $url ) );
			}
			if ( $fallback && ! examplepress_is_plugin_installed( $fallback, $installed ) ) {
				$msg .= sprintf( '. Free alternative: <code>%s</code>', esc_html( $fallback ) );
			}
		}

		$missing[] = $msg;
	}

	if ( empty( $missing ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning is-dismissible"><p><strong>ExamplePress</strong> requires the following plugins: %s</p></div>',
		implode( ', ', $missing )
	);
}

/**
 * Check if a plugin slug is installed and active.
 */
function examplepress_is_plugin_active( $slug, $installed, $active ) {
	foreach ( $installed as $file => $data ) {
		if ( str_starts_with( $file, $slug . '/' ) ) {
			return in_array( $file, $active, true );
		}
	}
	return false;
}

/**
 * Check if a plugin slug is installed (regardless of active state).
 */
function examplepress_is_plugin_installed( $slug, $installed ) {
	foreach ( $installed as $file => $data ) {
		if ( str_starts_with( $file, $slug . '/' ) ) {
			return true;
		}
	}
	return false;
}
