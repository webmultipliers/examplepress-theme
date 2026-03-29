<?php
/**
 * ExamplePress Configuration
 *
 * Reads and caches examplepress.json. Normalises the "design" shorthand
 * into feature-option overrides so the registry can resolve them
 * transparently via the standard resolution order:
 *
 *   PHP Filter  >  examplepress.json  >  Registration Default
 */

/**
 * Return the parsed examplepress.json configuration.
 *
 * The result is cached for the lifetime of the request.
 */
function examplepress_get_config() {
	static $config = null;

	if ( $config !== null ) {
		return $config;
	}

	$path = EP_THEME_PATH . '/examplepress.json';

	if ( ! file_exists( $path ) ) {
		$config = [];
		return $config;
	}

	$data   = json_decode( file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	$config = is_array( $data ) ? $data : [];

	$config = examplepress_normalise_design_config( $config );
	$config = examplepress_normalise_plugins_config( $config );

	return $config;
}

/**
 * Map "design" shorthand keys into their corresponding feature options.
 *
 * design.colors                    → features.theme-colors.options.palette
 * design.layout.wideSize           → features.theme-layout.options.wide_size
 * design.layout.contentSize        → features.theme-layout.options.content_size
 * design.typography.fontFamilies   → features.theme-typography.options.font_families
 * design.typography.fontSizes      → features.theme-typography.options.font_sizes
 * design.strict                    → features.design-strict.enabled
 */
function examplepress_normalise_design_config( array $config ) {
	$design = $config['design'] ?? [];

	if ( empty( $design ) ) {
		return $config;
	}

	$features = $config['features'] ?? [];

	// Colors → theme-colors.options.palette
	if ( isset( $design['colors'] ) ) {
		$features['theme-colors']['options']['palette'] = $design['colors'];
	}

	// Layout → theme-layout.options
	if ( isset( $design['layout']['wideSize'] ) ) {
		$features['theme-layout']['options']['wide_size'] = $design['layout']['wideSize'];
	}
	if ( isset( $design['layout']['contentSize'] ) ) {
		$features['theme-layout']['options']['content_size'] = $design['layout']['contentSize'];
	}

	// Typography → theme-typography.options
	if ( isset( $design['typography']['fontFamilies'] ) ) {
		$features['theme-typography']['options']['font_families'] = $design['typography']['fontFamilies'];
	}
	if ( isset( $design['typography']['fontSizes'] ) ) {
		$features['theme-typography']['options']['font_sizes'] = $design['typography']['fontSizes'];
	}

	// Strict mode → design-strict.enabled
	if ( ! empty( $design['strict'] ) ) {
		$features['design-strict']['enabled'] = true;
	}

	$config['features'] = $features;

	return $config;
}

/**
 * Normalise the plugins config to the v1 array-of-objects format.
 *
 * Accepts both the legacy { required: [], recommended: [] } format
 * and the new flat array of plugin objects.
 */
function examplepress_normalise_plugins_config( array $config ) {
	$plugins = $config['plugins'] ?? [];

	// Already new format (indexed array) or empty.
	if ( empty( $plugins ) || isset( $plugins[0] ) ) {
		return $config;
	}

	// Legacy format: { required: [...], recommended: [...] }.
	$result = [];
	foreach ( [ 'required', 'recommended' ] as $tier ) {
		foreach ( $plugins[ $tier ] ?? [] as $entry ) {
			if ( is_string( $entry ) ) {
				$result[] = [
					'slug'            => $entry,
					'name'            => $entry,
					'tier'            => $tier,
					'pricing'         => 'free',
					'cloud_dependent' => false,
					'source'          => [ 'type' => 'wporg' ],
				];
			} elseif ( is_array( $entry ) ) {
				$entry['tier'] = $entry['tier'] ?? $tier;
				$result[]      = $entry;
			}
		}
	}

	$config['plugins'] = $result;

	return $config;
}

// ── Developer Mode ─────────────────────────────────────────────────
// Define EP_DEV_MODE in wp-config.php to auto-disable all guards
// during companion plugin development.

if ( defined( 'EP_DEV_MODE' ) && EP_DEV_MODE ) {
	add_filter( 'examplepress_feature_guard-template-redirect', '__return_false' );
	add_filter( 'examplepress_feature_guard-template-rest', '__return_false' );
	add_filter( 'examplepress_feature_guard-template-resolution', '__return_false' );
}
