<?php

$theme_ns        = examplepress_get_theme_namespace();
$target_slug     = examplepress_get_current_route();
$template_prefix = examplepress_get_template_prefix();
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

?>
<?php if ( $block_content ) : ?>
	<?php echo $block_content; ?>
<?php else : ?>
	<div useBlockProps class="ep-missing-template" style="max-width:640px;margin:4rem auto;padding:2rem;font-family:system-ui,sans-serif;background:#1a1a1e;color:#e8e8ed;border:1px solid #ff6b6b33;border-radius:8px;">
		<p style="margin:0 0 0.5rem"><strong style="color:#ff6b6b">Missing Template:</strong> <code style="font-family:monospace;font-size:0.85em;background:#222;padding:0.15em 0.4em;border-radius:4px"><?php echo esc_html( $full_block_name ); ?></code></p>
		<p style="margin:0;color:#a0a0b2;font-size:0.9rem;line-height:1.6">The router resolved this block name but no matching template block is registered. Create the block in your companion plugin or check your routing logic.</p>
	</div>
<?php endif; ?>
