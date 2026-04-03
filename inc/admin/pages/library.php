<?php
/**
 * ExamplePress Admin — Library Page
 *
 * Component library for browsing and importing companion plugins.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Library page data payload.
 */
function examplepress_library_data(): array {
	return [
		'themeVersion' => EP_THEME_VERSION,
		'devMode'      => defined( 'EP_DEV_MODE' ) && EP_DEV_MODE,
		'page'         => 'library',
		'nonce'        => wp_create_nonce( 'wp_rest' ),
	];
}

/**
 * Library page render.
 */
function examplepress_render_library_page(): void {
	$is_dev = defined( 'EP_DEV_MODE' ) && EP_DEV_MODE;
	?>
	<div class="ep-settings-wrapper">
		<div class="ep-settings">

			<?php examplepress_render_page_header( $is_dev ); ?>

			<div class="ep-panels">

			<div id="p-library">
				<section class="ep-section ep-library-hero">
					<div class="ep-library-icon">&#9783;</div>
					<div class="ep-doc-section-title">The Component Library is arriving soon.</div>
					<p class="ep-section-desc">Browse, preview, and import companion plugins built for the ExamplePress ecosystem. Install with one click and extend your site with pre-built functionality.</p>
					<span class="ep-badge badge-info"><span class="ep-dot"></span>Coming Soon</span>
				</section>
			</div>

			</div><!-- /.ep-panels -->

		</div>
	</div>
	<?php
}
