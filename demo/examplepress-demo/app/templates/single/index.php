<?php
/**
 * Demo: Single Post/Page Template
 *
 * EXAMPLE CODE — Replace with your own single template design.
 *
 * @package ExamplePress Demo
 */

$post = $a['post'] ?? get_queried_object();
?>

<main useBlockProps class="ep-demo-single">
	<nav class="ep-demo-single-nav">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="ep-demo-single-back">&larr; Back</a>
		<span class="ep-demo-single-tag">Demo Plugin</span>
	</nav>

	<article class="ep-demo-single-article">
		<?php if ( $post ) : ?>
			<h1 class="ep-demo-single-title"><?php echo esc_html( $post->post_title ?? '' ); ?></h1>
			<div class="ep-demo-single-meta">
				<span><?php echo esc_html( get_the_date( '', $post ) ); ?></span>
				<span>&middot;</span>
				<span><?php echo esc_html( get_the_author_meta( 'display_name', $post->post_author ?? 0 ) ); ?></span>
			</div>
			<div class="ep-demo-single-content">
				<?php echo apply_filters( 'the_content', $post->post_content ?? '' ); ?>
			</div>
		<?php else : ?>
			<p class="ep-demo-single-empty">No content found.</p>
		<?php endif; ?>
	</article>

	<footer class="ep-demo-single-footer">
		<p>Rendered by <code>examplepress-demo/template-single</code> &middot; Demo companion plugin</p>
	</footer>
</main>
