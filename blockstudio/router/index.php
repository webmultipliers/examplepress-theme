<?php

$template_prefix = examplepress_get_template_prefix();

// ── Route Resolution ─────────────────────────────────────────────
// Resolve via the multi-origin registry first, then legacy filters.
$resolved        = examplepress_resolve_route();
$theme_ns        = $resolved['namespace'];
$target_slug     = $resolved['slug'];
$full_block_name = examplepress_get_template_block_name( $target_slug, $template_prefix, $theme_ns );

/**
 * Filter the data payload passed to the resolved template block.
 *
 * Companion plugins can enrich this array with queried objects,
 * breadcrumbs, or any context the template block needs.
 *
 * @param array  $data            Key/value pairs passed to bs_block().
 * @param string $target_slug     The resolved route slug.
 * @param string $full_block_name The fully-qualified block name.
 */
$route_data = apply_filters( 'examplepress_route_data', [], $target_slug, $full_block_name );

/**
 * Fires immediately before the router dispatches to a template block.
 *
 * Companion plugins can use this to enqueue assets, set global state,
 * or register sidebars for the resolved route.
 *
 * @param string $target_slug     The resolved route slug.
 * @param string $full_block_name The fully-qualified block name.
 */
do_action( 'examplepress_route_resolved', $target_slug, $full_block_name );

$block_content = bs_block(
	[
		'id'   => $full_block_name,
		'data' => $route_data,
	]
);

// ── Fallback: try theme default if companion block missing ───────
// If the resolved block produced no content, fall back to the theme's
// own template for this slug (e.g. examplepress-theme/template-front).
// This prevents a new companion app from breaking routing before its
// templates are built.
if ( ! $block_content && $theme_ns !== 'examplepress-theme' ) {
	$fallback_block_name = examplepress_get_template_block_name(
		$target_slug,
		$template_prefix,
		'examplepress-theme'
	);

	$block_content = bs_block(
		[
			'id'   => $fallback_block_name,
			'data' => $route_data,
		]
	);

	// Update for the error display if fallback also fails.
	if ( ! $block_content ) {
		$full_block_name = $full_block_name . ' → ' . $fallback_block_name;
	}
}

?>
<?php if ( $block_content ) : ?>
	<?php echo $block_content; ?>
<?php else : ?>
	<div useBlockProps class="ep-missing-template" style="max-width:640px;margin:4rem auto;padding:2rem;font-family:system-ui,sans-serif;background:#1a1a1e;color:#e8e8ed;border:1px solid #ff6b6b33;border-radius:8px;">
		<p style="margin:0 0 0.5rem"><strong style="color:#ff6b6b">Missing Template:</strong> <code style="font-family:monospace;font-size:0.85em;background:#222;padding:0.15em 0.4em;border-radius:4px"><?php echo esc_html( $full_block_name ); ?></code></p>
		<p style="margin:0;color:#a0a0b2;font-size:0.9rem;line-height:1.6">The router resolved this block name but no matching template block is registered. Create the block in your companion plugin or check your routing logic.</p>
	</div>
<?php endif; ?>
