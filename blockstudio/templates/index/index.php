<?php
/**
 * Template: Index
 *
 * This is the default fallback template — it renders when no other
 * route matches. It's also the first template the router will try
 * to load, so it's a good place to start building.
 *
 * Getting started:
 *
 * 1. Replace this file's markup with your actual layout.
 * 2. Add new routes in functions.php by extending the
 *    examplepress_get_current_route() logic (or use the filter).
 * 3. Create a new directory under blockstudio/templates/ with a
 *    block.json and index.php for each route you need.
 *
 * Available variables (from Blockstudio):
 *   $attributes  — block attributes (defined in block.json)
 *   $block       — block data: $block['postId'], $block['postType'], etc.
 *   $isEditor    — true when rendering inside the block editor
 *   $isPreview   — true when rendering in the block inserter preview
 *   $content     — inner block content (if using InnerBlocks)
 */

?>

<main useBlockProps>
	<h1>ExamplePress</h1>
	<p>Welcome to your new Blockstudio-powered WordPress theme! This is the default template for your site, rendered by
		<code>blockstudio/templates/index/index.php</code>.
	</p>
	<p>Start routing through blocks.</p>
</main>