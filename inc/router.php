<?php
/**
 * ExamplePress Routing Helpers
 *
 * Centralised routing API used by the Blockstudio router block
 * to resolve the current request to a template block.
 *
 * Supports two routing modes:
 *
 * 1. **Route Origin Registry** (preferred for multi-origin)
 *    Companion plugins call examplepress_register_route_origin() to declare
 *    which routes they handle. The router evaluates conditions and resolves
 *    the namespace per-route. Multiple plugins can coexist.
 *
 * 2. **Legacy filter mode** (single-origin, backward compatible)
 *    Companion plugins hook `examplepress_theme_namespace` and
 *    `examplepress_route_context`. Works when only one plugin owns routing.
 *
 * The router tries the registry first. If no origin matches, it falls
 * through to the legacy filter chain.
 */

/**
 * Resolve the current route using the multi-origin registry first,
 * then falling back to legacy filters.
 *
 * Returns an array with 'namespace' and 'slug' keys. Consumers should
 * use examplepress_resolve_route() instead of calling the individual
 * helpers directly — this function encapsulates the full resolution
 * pipeline with proper fallback.
 *
 * @return array{namespace: string, slug: string}
 */
function examplepress_resolve_route(): array {
	// 1. Try the route origin registry (multi-origin).
	$origin = examplepress_resolve_route_origin();

	if ( $origin ) {
		/**
		 * Filters the registry-resolved route before dispatch.
		 *
		 * Allows plugins to override or transform the registry result.
		 *
		 * @param array $origin { namespace: string, slug: string }
		 */
		return apply_filters( 'examplepress_resolved_origin', $origin );
	}

	// 2. Fall back to legacy single-origin filters.
	return [
		'namespace' => examplepress_get_theme_namespace(),
		'slug'      => examplepress_get_current_route(),
	];
}

/**
 * Get the current route slug (legacy filter mode).
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
 * Get the theme's Blockstudio namespace (legacy filter mode).
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
