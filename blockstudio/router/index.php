<?php

use ExamplePress\MU\Infrastructure\Router;
use ExamplePress\MU\Infrastructure\RouteRegistry;

// Bail gracefully if the MU Kernel is not active.
if ( ! class_exists( Router::class ) ) {
	?>
	<div useBlockProps style="max-width:640px;margin:4rem auto;padding:2rem;font-family:system-ui,sans-serif;background:#1a1a1e;color:#e8e8ed;border:1px solid #ff6b6b33;border-radius:8px;">
		<p style="margin:0 0 0.5rem"><strong style="color:#ff6b6b">ExamplePress Platform Kernel Missing</strong></p>
		<p style="margin:0;color:#a0a0b2;font-size:0.9rem;line-height:1.6">The <code style="font-family:monospace;font-size:0.85em;background:#222;padding:0.15em 0.4em;border-radius:4px">examplepress-mu</code> must-use plugin is required. Install it in <code style="font-family:monospace;font-size:0.85em;background:#222;padding:0.15em 0.4em;border-radius:4px">wp-content/mu-plugins/</code> to activate the platform.</p>
	</div>
	<?php
	return;
}

$template_prefix = Router::templatePrefix();

// ── Route Resolution ─────────────────────────────────────────────
$resolved        = Router::resolveRoute();
$theme_ns        = $resolved['namespace'];
$target_slug     = $resolved['slug'];
$full_block_name = Router::templateBlockName( $target_slug, $template_prefix, $theme_ns );

/**
 * Filter the data payload passed to the resolved template block.
 *
 * @param array  $data            Key/value pairs passed to bs_block().
 * @param string $target_slug     The resolved route slug.
 * @param string $full_block_name The fully-qualified block name.
 */
$route_data = apply_filters( 'examplepress_route_data', [], $target_slug, $full_block_name );

/**
 * Fires immediately before the router dispatches to a template block.
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
