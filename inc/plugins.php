<?php
/**
 * Plugin Dependency Checker
 *
 * Reads the "plugins" object from examplepress.json and surfaces a
 * single dismissible admin notice when required plugins are missing.
 * Does not force installation — informational only.
 */

add_action( 'admin_notices', 'examplepress_check_plugin_dependencies' );

/**
 * Display an admin notice if any required plugins are missing or inactive.
 */
function examplepress_check_plugin_dependencies() {
	$config   = examplepress_get_config();
	$required = $config['plugins']['required'] ?? [];

	if ( empty( $required ) ) {
		return;
	}

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$installed = get_plugins();
	$active    = wp_get_active_and_valid_plugins();
	$active    = array_map( 'plugin_basename', $active );
	$missing   = [];

	foreach ( $required as $slug ) {
		$found = false;
		foreach ( $installed as $file => $data ) {
			if ( str_starts_with( $file, $slug . '/' ) ) {
				$found = true;
				if ( ! in_array( $file, $active, true ) ) {
					$missing[] = sprintf( '<strong>%s</strong> (installed but inactive)', esc_html( $data['Name'] ) );
				}
				break;
			}
		}
		if ( ! $found ) {
			$missing[] = sprintf( '<strong>%s</strong> (not installed)', esc_html( $slug ) );
		}
	}

	if ( empty( $missing ) ) {
		return;
	}

	printf(
		'<div class="notice notice-warning is-dismissible"><p><strong>ExamplePress</strong> requires the following plugins: %s</p></div>',
		implode( ', ', $missing )
	);
}
