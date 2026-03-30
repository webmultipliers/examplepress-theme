<?php
/**
 * Demo: 404 Not Found Template
 *
 * EXAMPLE CODE — Replace with your own 404 design.
 *
 * @package ExamplePress Demo
 */
?>

<main useBlockProps class="ep-demo-404">
	<div class="ep-demo-404-inner">
		<div class="ep-demo-404-code">404</div>
		<h1 class="ep-demo-404-title">Page Not Found</h1>
		<p class="ep-demo-404-body">The page you're looking for doesn't exist or has been moved.</p>
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="ep-demo-404-link">&larr; Return Home</a>
	</div>
	<footer class="ep-demo-404-footer">
		<p>Rendered by <code>examplepress-demo/template-404</code> &middot; Demo companion plugin</p>
	</footer>
</main>
