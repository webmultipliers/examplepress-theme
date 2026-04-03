<?php
/**
 * ExamplePress Admin — Reference Page
 *
 * Docs, hooks, and navigation menus.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reference page data payload.
 */
function examplepress_reference_data(): array {
	return [
		'themeVersion' => EP_THEME_VERSION,
		'devMode'      => defined( 'EP_DEV_MODE' ) && EP_DEV_MODE,
		'page'         => 'reference',
		'docs'         => examplepress_settings_get_docs(),
		'hooks'        => examplepress_settings_get_hooks(),
		'navigation'   => examplepress_settings_get_navigation(),
		'nonce'        => wp_create_nonce( 'wp_rest' ),
	];
}

/**
 * Reference page render.
 */
function examplepress_render_reference_page(): void {
	$is_dev = defined( 'EP_DEV_MODE' ) && EP_DEV_MODE;
	?>
	<div class="ep-settings-wrapper">
		<div class="ep-settings">

			<?php examplepress_render_page_header( $is_dev ); ?>

			<div class="ep-layout">
			<nav class="ep-tabs" role="tablist">
				<button class="ep-tab" role="tab" aria-selected="true"  aria-controls="p-docs"       id="t-docs"       data-tab-id="docs">Docs</button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-navigation" id="t-navigation" data-tab-id="navigation">Navigation<span class="ep-tab-count"></span></button>
			</nav>

			<div class="ep-panels">

			<!-- Docs -->
			<div class="ep-panel" id="p-docs" role="tabpanel" aria-hidden="false">
				<section class="ep-section">
					<div class="ep-doc-section-title">Getting Started</div>
					<div class="ep-doc-section-desc">ExamplePress is the FSE theme layer for Blockstudio. It provides a router, guards, and a feature registry. Everything else is built in your companion plugin.</div>
					<div class="ep-docs-grid" id="docs-cards"></div>
				</section>
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Filter &amp; Action Reference</span><div class="ep-section-line"></div></div>
					<p class="ep-section-desc">Every hook the theme exposes. Use these from your companion plugin to control routing, features, guards, and design tokens.</p>
					<div class="ep-hooks-list" id="hooks-list"></div>
				</section>
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Resolution Order</span><div class="ep-section-line"></div></div>
					<p class="ep-section-desc">When the feature registry resolves a value, it checks sources in priority order. The first match wins.</p>
					<div class="ep-table">
						<div class="ep-row ep-row-head ep-cols-3">
							<div class="ep-th">Priority</div><div class="ep-th">Source</div><div class="ep-th">Mechanism</div>
						</div>
						<div class="ep-row ep-cols-3">
							<div class="ep-td-label"><span class="ep-name">1 — Highest</span></div>
							<div><span class="ep-src src-php">PHP filter</span></div>
							<div><span class="ep-id">add_filter()</span></div>
						</div>
						<div class="ep-row ep-cols-3">
							<div class="ep-td-label"><span class="ep-name">2</span></div>
							<div><span class="ep-src src-json">JSON</span></div>
							<div><span class="ep-id">examplepress.json</span></div>
						</div>
						<div class="ep-row ep-cols-3">
							<div class="ep-td-label"><span class="ep-name">3 — Lowest</span></div>
							<div><span class="ep-src">default</span></div>
							<div><span class="ep-id">register_feature()</span></div>
						</div>
					</div>
				</section>
			</div>

			<!-- Navigation -->
			<div class="ep-panel" id="p-navigation" role="tabpanel" aria-hidden="true">
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Registered Locations</span><div class="ep-section-line"></div></div>
					<p class="ep-section-desc">ExamplePress uses the native WordPress menu system. Register locations in your companion plugin and assign menus via Appearance &rarr; Menus or the Navigation block.</p>
					<div class="ep-table" id="tbl-nav-locations"></div>
				</section>
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Menus</span><div class="ep-section-line"></div></div>
					<p class="ep-section-desc">All menus registered in this WordPress installation. Manage items via <a href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>" class="ep-link">Appearance &rarr; Menus</a> or the Navigation block in the editor.</p>
					<div class="ep-table" id="tbl-nav-menus"></div>
				</section>
			</div>

			</div><!-- /.ep-panels -->
			</div><!-- /.ep-layout -->

		</div>

		<!-- Generic Modal (hooks) -->
		<div class="ep-modal-overlay" id="ep-feature-modal" style="display:none">
			<div class="ep-modal">
				<div class="ep-modal-header">
					<div>
						<span class="ep-modal-title" id="ep-modal-title"></span>
						<span class="ep-modal-id" id="ep-modal-id"></span>
					</div>
					<button class="ep-modal-close" id="ep-modal-close">&times;</button>
				</div>
				<div class="ep-modal-body" id="ep-modal-body"></div>
			</div>
		</div>

	</div>
	<?php
}
