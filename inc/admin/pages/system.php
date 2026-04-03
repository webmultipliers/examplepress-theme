<?php
/**
 * ExamplePress Admin — System Page
 *
 * Health checks, routes, blocks, and notifications.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * System page data payload.
 */
function examplepress_system_data(): array {
	return [
		'themeVersion'   => EP_THEME_VERSION,
		'devMode'        => defined( 'EP_DEV_MODE' ) && EP_DEV_MODE,
		'page'           => 'system',
		'healthChecks'   => examplepress_settings_get_health(),
		'blocks'         => examplepress_settings_get_blocks(),
		'routeTopology'  => examplepress_settings_get_route_topology(),
		'notifications'  => examplepress_gather_notifications(),
		'archived'       => examplepress_get_archived_notifications(),
		'restUrl'        => esc_url_raw( rest_url( 'examplepress/v1/notifications/archive' ) ),
		'nonce'          => wp_create_nonce( 'wp_rest' ),
	];
}

/**
 * System page render.
 */
function examplepress_render_system_page(): void {
	$is_dev = defined( 'EP_DEV_MODE' ) && EP_DEV_MODE;
	?>
	<div class="ep-settings-wrapper">
		<div class="ep-settings">

			<?php
			examplepress_render_page_header(
				$is_dev,
				'<button class="ep-copy-report" id="ep-copy-report">Copy System Report</button>'
			);
			?>

			<div class="ep-layout">
			<nav class="ep-tabs" role="tablist">
				<button class="ep-tab" role="tab" aria-selected="true"  aria-controls="p-health"        id="t-health"        data-tab-id="health">Health</button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-routes"         id="t-routes"        data-tab-id="routes">Routes<span class="ep-tab-count"></span></button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-blocks"         id="t-blocks"        data-tab-id="blocks">Blocks<span class="ep-tab-count"></span></button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-notifications"  id="t-notifications" data-tab-id="notifications">Notifications</button>
			</nav>

			<div class="ep-panels">

			<!-- Health -->
			<div class="ep-panel" id="p-health" role="tabpanel" aria-hidden="false">
				<section class="ep-section">
					<div class="ep-health-summary" id="health-summary"></div>
				</section>
				<section class="ep-section">
					<div class="ep-datatable-search">
						<input type="text" id="ep-health-search" placeholder="Search health checks..." aria-label="Search health checks" />
					</div>
				</section>
				<section class="ep-section ep-collapsible" data-health-section="env">
					<div class="ep-section-header ep-collapsible-header">
						<button class="ep-collapse-toggle" aria-expanded="true" aria-label="Toggle section"><span class="ep-collapse-icon"></span></button>
						<span class="ep-section-title">Environment Checks</span><div class="ep-section-line"></div>
					</div>
					<div class="ep-collapsible-body">
						<div class="ep-table" id="tbl-health-env"></div>
					</div>
				</section>
				<section class="ep-section ep-collapsible" data-health-section="theme">
					<div class="ep-section-header ep-collapsible-header">
						<button class="ep-collapse-toggle" aria-expanded="true" aria-label="Toggle section"><span class="ep-collapse-icon"></span></button>
						<span class="ep-section-title">Theme Integrity</span><div class="ep-section-line"></div>
					</div>
					<div class="ep-collapsible-body">
						<div class="ep-table" id="tbl-health-theme"></div>
					</div>
				</section>
				<section class="ep-section ep-collapsible" data-health-section="router">
					<div class="ep-section-header ep-collapsible-header">
						<button class="ep-collapse-toggle" aria-expanded="true" aria-label="Toggle section"><span class="ep-collapse-icon"></span></button>
						<span class="ep-section-title">Router Health</span><div class="ep-section-line"></div>
					</div>
					<div class="ep-collapsible-body">
						<div class="ep-table" id="tbl-health-router"></div>
					</div>
				</section>
				<section class="ep-section ep-collapsible" data-health-section="security">
					<div class="ep-section-header ep-collapsible-header">
						<button class="ep-collapse-toggle" aria-expanded="true" aria-label="Toggle section"><span class="ep-collapse-icon"></span></button>
						<span class="ep-section-title">Security &amp; Guards</span><div class="ep-section-line"></div>
					</div>
					<div class="ep-collapsible-body">
						<div class="ep-table" id="tbl-health-security"></div>
					</div>
				</section>
				<section class="ep-section ep-collapsible" data-health-section="connections">
					<div class="ep-section-header ep-collapsible-header">
						<button class="ep-collapse-toggle" aria-expanded="true" aria-label="Toggle section"><span class="ep-collapse-icon"></span></button>
						<span class="ep-section-title">Connections</span><div class="ep-section-line"></div>
					</div>
					<div class="ep-collapsible-body">
						<div class="ep-table" id="tbl-health-connections"></div>
					</div>
				</section>
			</div>

			<!-- Routes -->
			<div class="ep-panel" id="p-routes" role="tabpanel" aria-hidden="true">
				<section class="ep-section">
					<div class="ep-doc-section-title">Route Aggregator</div>
					<div class="ep-section-desc">
						Visualizing the dispatch topology across all registered companion apps.
						Each app declares route slugs with condition closures and a priority.
						The router evaluates origins in priority order and dispatches the first match.
					</div>
				</section>
				<section class="ep-section">
					<div id="ep-routes-stats" class="ep-overview-grid" style="grid-template-columns: repeat(4, 1fr);"></div>
				</section>
				<section class="ep-section">
					<div id="ep-routes-filters" style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px;"></div>
					<div id="ep-routes-view-toggle" style="display:flex;gap:2px;background:var(--surface-alt,#f0f0f1);border-radius:6px;padding:2px;width:fit-content;margin-bottom:16px;"></div>
					<div id="ep-routes-content"></div>
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

			<!-- Notifications -->
			<div class="ep-panel" id="p-notifications" role="tabpanel" aria-hidden="true">
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Notifications</span><div class="ep-section-line"></div></div>
					<div class="ep-notif-subtabs" id="notif-subtabs">
						<button class="ep-notif-subtab active" data-target="notices-active">Active</button>
						<button class="ep-notif-subtab" data-target="notices-archived">Archived</button>
					</div>
					<div id="notices-active" class="ep-notif-list"></div>
					<div id="notices-archived" class="ep-notif-list" style="display:none"></div>
				</section>
			</div>

			</div><!-- /.ep-panels -->
			</div><!-- /.ep-layout -->

		</div>

		<?php examplepress_render_detail_modal(); ?>

	</div>
	<?php
}
