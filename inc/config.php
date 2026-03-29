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

	return $config;
}

/**
 * Map "design" shorthand keys into their corresponding feature options.
 *
 * design.colors             → features.theme-colors.options.palette
 * design.layout.wideSize    → features.theme-layout.options.wide_size
 * design.layout.contentSize → features.theme-layout.options.content_size
 */
function examplepress_normalise_design_config( array $config ) {
	$design = $config['design'] ?? [];

	if ( empty( $design ) ) {
		return $config;
	}

	$features = $config['features'] ?? [];

	if ( isset( $design['colors'] ) ) {
		$features['theme-colors']['options']['palette'] = $design['colors'];
	}

	if ( isset( $design['layout']['wideSize'] ) ) {
		$features['theme-layout']['options']['wide_size'] = $design['layout']['wideSize'];
	}

	if ( isset( $design['layout']['contentSize'] ) ) {
		$features['theme-layout']['options']['content_size'] = $design['layout']['contentSize'];
	}

	$config['features'] = $features;

	return $config;
}
