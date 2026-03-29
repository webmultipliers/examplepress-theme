<?php
/**
 * ExamplePress Routing Helpers
 *
 * Centralised routing API used by the Blockstudio router block
 * to resolve the current request to a template block.
 *
 * All return values are filterable so companion plugins can
 * intercept routing without modifying the theme.
 */

/**
 * Get the current route slug.
 *
 * Companion plugins override this via the `examplepress_route_context` filter
 * to implement their own routing logic (e.g. is_front_page → 'front').
 *
 * @return string Route slug. Defaults to 'get-started'.
 */
function examplepress_get_current_route() {
	return apply_filters( 'examplepress_route_context', 'get-started' );
}

/**
 * Get the theme's Blockstudio namespace.
 *
 * Companion plugins override this to point the router at their own blocks.
 *
 * @return string Namespace. Defaults to 'examplepress-theme'.
 */
function examplepress_get_theme_namespace() {
	return apply_filters( 'examplepress_theme_namespace', 'examplepress-theme' );
}

/**
 * Get the template block prefix.
 *
 * @return string Prefix. Defaults to 'template'.
 */
function examplepress_get_template_prefix() {
	return apply_filters( 'examplepress_template_prefix', 'template' );
}

/**
 * Build the fully-qualified block name for a template slug.
 *
 * @param string $slug     Template slug (e.g. 'front').
 * @param string $prefix   Template prefix (e.g. 'template').
 * @param string $theme_ns Theme namespace (e.g. 'examplepress-core').
 * @return string Full block name (e.g. 'examplepress-core/template-front').
 */
function examplepress_get_template_block_name( $slug, $prefix, $theme_ns ) {
	return apply_filters(
		'examplepress_template_block_name',
		sprintf( '%s/%s-%s', $theme_ns, $prefix, $slug ),
		$slug,
		$prefix,
		$theme_ns
	);
}
