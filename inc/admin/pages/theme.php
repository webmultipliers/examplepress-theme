<?php
/**
 * ExamplePress Admin — Theme Page
 *
 * Features, design tokens, and config files.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Theme page data payload.
 */
function examplepress_theme_data(): array {
	return [
		'themeVersion'   => EP_THEME_VERSION,
		'devMode'        => defined( 'EP_DEV_MODE' ) && EP_DEV_MODE,
		'page'           => 'theme',
		'features'       => examplepress_settings_get_features(),
		'featureDetails' => examplepress_settings_get_feature_details(),
		'colors'         => (array) examplepress_feature_option( 'theme-colors', 'palette', [] ),
		'layout'         => [
			'wideSize'    => (string) examplepress_feature_option( 'theme-layout', 'wide_size', '1200px' ),
			'contentSize' => (string) examplepress_feature_option( 'theme-layout', 'content_size', '800px' ),
		],
		'fonts'          => examplepress_settings_get_fonts(),
		'sizes'          => examplepress_settings_get_sizes(),
		'configFiles'    => examplepress_settings_get_config_files(),
		'nonce'          => wp_create_nonce( 'wp_rest' ),
	];
}

/**
 * Theme page render.
 */
function examplepress_render_theme_page(): void {
	$is_dev = defined( 'EP_DEV_MODE' ) && EP_DEV_MODE;
	?>
	<div class="ep-settings-wrapper">
		<div class="ep-settings">

			<?php examplepress_render_page_header( $is_dev ); ?>

			<div class="ep-layout">
			<nav class="ep-tabs" role="tablist">
				<button class="ep-tab" role="tab" aria-selected="true"  aria-controls="p-features" id="t-features" data-tab-id="features">Features<span class="ep-tab-count"></span></button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-design"   id="t-design"   data-tab-id="design">Design</button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-config"   id="t-config"   data-tab-id="config">Config</button>
			</nav>

			<div class="ep-panels">

			<!-- Features -->
			<div class="ep-panel" id="p-features" role="tabpanel" aria-hidden="false">
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Feature Registry</span><div class="ep-section-line"></div></div>
					<p class="ep-section-desc">All registered features &mdash; theme support, editor controls, guards, admin tweaks, and design tokens. Click any row for details.</p>
					<div id="tbl-features"></div>
				</section>
			</div>

			<!-- Design -->
			<div class="ep-panel" id="p-design" role="tabpanel" aria-hidden="true">
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Color Palette</span><div class="ep-section-line"></div></div>
					<p class="ep-section-desc">Resolved from examplepress.json — design.colors. Translated to theme.json settings.color.palette at runtime.</p>
					<div class="ep-color-grid" id="color-grid"></div>
				</section>
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Layout</span><div class="ep-section-line"></div></div>
					<div class="ep-layout-preview" id="layout-preview"></div>
				</section>
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Typography</span><div class="ep-section-line"></div></div>
					<div class="ep-type-stack" id="type-stack"></div>
				</section>
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Size Scale</span><div class="ep-section-line"></div></div>
					<div class="ep-size-scale" id="size-scale"></div>
				</section>
			</div>

			<!-- Config -->
			<div class="ep-panel" id="p-config" role="tabpanel" aria-hidden="true">
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Configuration Files</span><div class="ep-section-line"></div></div>
					<p class="ep-section-desc">Explore the configuration files that drive the theme. All values are read-only — edit the files directly in your project.</p>
					<div class="ep-config-tabs" id="config-switcher"></div>
					<div id="config-viewer"></div>
				</section>
			</div>

			</div><!-- /.ep-panels -->
			</div><!-- /.ep-layout -->

		</div>

		<?php examplepress_render_detail_modal(); ?>

	</div>
	<?php
}
