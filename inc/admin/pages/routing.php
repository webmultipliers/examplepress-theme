<?php
/**
 * ExamplePress Admin — Routing Page
 *
 * Route aggregator, block registry, and library.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Routing page data payload.
 */
function examplepress_routing_data(): array {
	return [
		'themeVersion'   => EP_THEME_VERSION,
		'devMode'        => defined( 'EP_DEV_MODE' ) && EP_DEV_MODE,
		'page'           => 'routing',
		'blocks'         => examplepress_settings_get_blocks(),
		'routeTopology'  => examplepress_settings_get_route_topology(),
		'nonce'          => wp_create_nonce( 'wp_rest' ),
	];
}

/**
 * Routing page render.
 */
function examplepress_render_routing_page(): void {
	$is_dev = defined( 'EP_DEV_MODE' ) && EP_DEV_MODE;
	?>
	<div class="ep-settings-wrapper">
		<div class="ep-settings">

			<?php examplepress_render_page_header( $is_dev ); ?>

			<div class="ep-layout">
			<nav class="ep-tabs" role="tablist">
				<button class="ep-tab" role="tab" aria-selected="true"  aria-controls="p-routes"  id="t-routes"  data-tab-id="routes">Routes<span class="ep-tab-count"></span></button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-blocks"  id="t-blocks"  data-tab-id="blocks">Blocks<span class="ep-tab-count"></span></button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-library" id="t-library" data-tab-id="library">Library</button>
			</nav>

			<div class="ep-panels">

			<!-- Routes -->
			<div class="ep-panel" id="p-routes" role="tabpanel" aria-hidden="false">
				<section class="ep-section">
					<div class="ep-doc-section-title">Route Aggregator</div>
					<div class="ep-section-desc">
						Visualizing the dispatch topology across all registered companion apps.
						Each app declares route slugs with condition closures and a priority.
						The router evaluates origins in priority order and dispatches the first match.
					</div>
				</section>
				<section class="ep-section">
					<div class="ep-section-header">
						<span class="ep-section-title">Topology</span>
						<div class="ep-section-line"></div>
					</div>
					<div id="ep-routes-stats" class="ep-overview-grid" style="grid-template-columns: repeat(4, 1fr);"></div>
				</section>
				<section class="ep-section">
					<div class="ep-section-header">
						<span class="ep-section-title">Route Origins</span>
						<div class="ep-section-line"></div>
					</div>
					<div id="ep-routes-origins"></div>
				</section>
				<section class="ep-section">
					<div class="ep-section-header">
						<span class="ep-section-title">Active Route</span>
						<div class="ep-section-line"></div>
					</div>
					<div id="ep-routes-resolved"></div>
				</section>
				<section class="ep-section">
					<div class="ep-section-header">
						<span class="ep-section-title">Sitemap</span>
						<div class="ep-section-line"></div>
					</div>
					<div id="ep-routes-sitemap"></div>
				</section>
			</div>

			<!-- Blocks -->
			<div class="ep-panel" id="p-blocks" role="tabpanel" aria-hidden="true">
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Block Registry</span><div class="ep-section-line"></div></div>
					<p class="ep-section-desc">All Blockstudio blocks discovered in the active theme and companion plugins, grouped by namespace. Template blocks are blocks the router can dispatch to.</p>
					<div class="ep-table" id="tbl-blocks"></div>
				</section>
			</div>

			<!-- Library -->
			<div class="ep-panel" id="p-library" role="tabpanel" aria-hidden="true">
				<section class="ep-section ep-library-hero">
					<div class="ep-library-icon">&#9783;</div>
					<div class="ep-doc-section-title">The Component Library is arriving soon.</div>
					<p class="ep-doc-section-desc" style="margin: 0 auto 2rem; text-align: center;">Browse, import, and build with companion plugins from the component library. Perfectly structured components that claim their own routes and integrate with the ExamplePress engine.</p>
					<span class="ep-badge badge-info"><span class="ep-dot"></span>Coming in v1.1</span>
				</section>
			</div>

			</div><!-- /.ep-panels -->
			</div><!-- /.ep-layout -->

		</div>

		<!-- Generic Modal (blocks) -->
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
