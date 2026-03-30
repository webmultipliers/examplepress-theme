<?php
/**
 * Demo: Front Page Template
 *
 * EXAMPLE CODE — This is rendered by the demo companion plugin.
 * When you scaffold your own plugin, replace this with your design.
 *
 * @package ExamplePress Demo
 */

$namespace = examplepress_get_theme_namespace();
$route     = examplepress_get_current_route();
$block     = examplepress_get_template_block_name( $route );
?>

<main useBlockProps class="ep-demo">
	<nav class="ep-demo-nav">
		<div class="ep-demo-logo"><span>&lt;</span>ExamplePress<span>/&gt;</span> <span class="ep-demo-tag">Demo</span></div>
	</nav>

	<section class="ep-demo-hero">
		<div class="ep-demo-badge">Companion Plugin Active</div>
		<h1 class="ep-demo-headline">This is rendered by the<br><em>demo companion plugin.</em></h1>
		<p class="ep-demo-body">
			The theme's <code>get-started</code> fallback has been completely bypassed.
			The <code>examplepress-demo</code> plugin claimed the namespace, defined a routing
			cascade, and this template block is now handling the front page.
		</p>
	</section>

	<section class="ep-demo-info">
		<h2 class="ep-demo-info-title">What Changed</h2>

		<div class="ep-demo-grid">
			<div class="ep-demo-card">
				<div class="ep-demo-card-label">Before (Theme Only)</div>
				<div class="ep-demo-card-row">
					<span class="ep-demo-dim">Namespace</span>
					<code>examplepress-theme</code>
				</div>
				<div class="ep-demo-card-row">
					<span class="ep-demo-dim">Route</span>
					<code>get-started</code>
				</div>
				<div class="ep-demo-card-row">
					<span class="ep-demo-dim">Block</span>
					<code>examplepress-theme/template-get-started</code>
				</div>
			</div>

			<div class="ep-demo-card ep-demo-card-active">
				<div class="ep-demo-card-label">After (Demo Plugin)</div>
				<div class="ep-demo-card-row">
					<span class="ep-demo-dim">Namespace</span>
					<code><?php echo esc_html( $namespace ); ?></code>
				</div>
				<div class="ep-demo-card-row">
					<span class="ep-demo-dim">Route</span>
					<code><?php echo esc_html( $route ); ?></code>
				</div>
				<div class="ep-demo-card-row">
					<span class="ep-demo-dim">Block</span>
					<code><?php echo esc_html( $block ); ?></code>
				</div>
			</div>
		</div>
	</section>

	<section class="ep-demo-next">
		<h2 class="ep-demo-info-title">Next Steps</h2>
		<p class="ep-demo-body">
			Inspect the demo plugin source at <code>wp-content/plugins/examplepress-demo/</code>.
			When you're ready, scaffold your own companion plugin from the
			<strong>Build</strong> tab in the ExamplePress settings, then remove this demo.
		</p>
	</section>

	<footer class="ep-demo-footer">
		<p>Demo companion plugin &middot; Safe to remove at any time</p>
	</footer>
</main>
