<?php
/**
 * ExamplePress Admin — Dashboard Page
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard data payload.
 */
function examplepress_dashboard_data(): array {
	return [
		'themeVersion'  => EP_THEME_VERSION,
		'devMode'       => defined( 'EP_DEV_MODE' ) && EP_DEV_MODE,
		'page'          => 'dashboard',
		'notifications' => examplepress_gather_notifications(),
		'archived'      => examplepress_get_archived_notifications(),
		'restUrl'       => esc_url_raw( rest_url( 'examplepress/v1/notifications/archive' ) ),
		'demo'          => [
			'status' => function_exists( 'examplepress_get_demo_status' ) ? examplepress_get_demo_status() : 'not-installed',
		],
		'demoInstallUrl'   => esc_url_raw( rest_url( 'examplepress/v1/demo/install' ) ),
		'demoUninstallUrl' => esc_url_raw( rest_url( 'examplepress/v1/demo/uninstall' ) ),
		'healthChecks'     => examplepress_settings_get_health(),
		'adminPages'       => [
			'apps'      => examplepress_admin_page_url( 'apps' ),
			'theme'     => examplepress_admin_page_url( 'theme' ),
			'routing'   => examplepress_admin_page_url( 'routing' ),
			'reference' => examplepress_admin_page_url( 'reference' ),
			'system'    => examplepress_admin_page_url( 'system' ),
		],
		'nonce' => wp_create_nonce( 'wp_rest' ),
	];
}

/**
 * Dashboard render.
 */
function examplepress_render_dashboard_page(): void {
	$is_dev = defined( 'EP_DEV_MODE' ) && EP_DEV_MODE;
	?>
	<div class="ep-settings-wrapper">
		<div class="ep-settings">

			<?php examplepress_render_page_header( $is_dev ); ?>

			<div class="ep-panels">

			<!-- Overview -->
			<div class="ep-panel" id="p-overview" role="tabpanel" aria-hidden="false">
				<section class="ep-section">
					<div class="ep-doc-section-title">Welcome to ExamplePress</div>
					<p class="ep-section-desc" style="max-width:none">ExamplePress is the Full Site Editing theme layer for <strong>Blockstudio</strong>. It provides a template router, guard system, and a feature registry &mdash; everything else is built in your companion plugin.</p>
				</section>
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Quick Start</span><div class="ep-section-line"></div></div>
					<p class="ep-section-desc" style="max-width:none">ExamplePress works by routing every front-end request through a single <code>index.html</code> template that contains the router block. Your companion plugin claims a namespace, defines a routing cascade, and registers Blockstudio template blocks. The theme handles resolution, guards, and design tokens &mdash; you focus on building.</p>
					<p class="ep-section-desc" style="max-width:none">Head to the <strong>Apps</strong> page to scaffold your first companion plugin, or explore the <strong>Theme</strong> page to see what&rsquo;s already configured.</p>
				</section>
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Navigation</span><div class="ep-section-line"></div></div>
					<div class="ep-overview-grid">
						<a class="ep-overview-card" href="<?php echo esc_url( examplepress_admin_page_url( 'theme' ) ); ?>">
							<div class="ep-overview-card-title">Theme</div>
							<p class="ep-overview-card-desc">Feature registry, design tokens, config files, and dependencies. All values resolved from examplepress.json and PHP filters.</p>
						</a>
						<a class="ep-overview-card" href="<?php echo esc_url( examplepress_admin_page_url( 'apps' ) ); ?>">
							<div class="ep-overview-card-title">Apps</div>
							<p class="ep-overview-card-desc">Scaffold a new companion plugin from a GitHub template. Pre-configured with CI/CD, GitHub Releases updates, and ready to launch in Codespaces.</p>
						</a>
						<a class="ep-overview-card" href="<?php echo esc_url( examplepress_admin_page_url( 'routing' ) ); ?>">
							<div class="ep-overview-card-title">Routing</div>
							<p class="ep-overview-card-desc">Aggregated view of every route registered by companion apps. Sitemap tree, route table, priority cascade, and conflict detection.</p>
						</a>
						<a class="ep-overview-card" href="<?php echo esc_url( examplepress_admin_page_url( 'reference' ) ); ?>">
							<div class="ep-overview-card-title">Reference</div>
							<p class="ep-overview-card-desc">Getting started guides, filter &amp; action reference, and navigation menus. Every hook the theme exposes for companion plugins.</p>
						</a>
						<a class="ep-overview-card" href="<?php echo esc_url( examplepress_admin_page_url( 'system' ) ); ?>">
							<div class="ep-overview-card-title">System</div>
							<p class="ep-overview-card-desc">Environment checks, theme integrity, router health, and security guard status. A quick snapshot of whether everything is running correctly.</p>
						</a>
					</div>
				</section>
			</div>

			<!-- Notifications -->
			<div class="ep-panel" id="p-notifications">
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Notifications</span><div class="ep-section-line"></div></div>
					<div class="ep-notif-subtabs" id="notif-subtabs">
						<button class="ep-notif-subtab active" data-target="notices-active">Active<span class="ep-tab-count" id="t-notifications"></span></button>
						<button class="ep-notif-subtab" data-target="notices-archived">Archived</button>
					</div>
					<div id="notices-active" class="ep-notif-list"></div>
					<div id="notices-archived" class="ep-notif-list" style="display:none"></div>
				</section>
			</div>

			<!-- Demo -->
			<div class="ep-panel" id="ep-demo-section">
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Demo Companion Plugin</span><div class="ep-section-line"></div></div>
					<p class="ep-section-desc">Install a working demo companion plugin to see the routing contract in action. The demo claims its own namespace, defines a routing cascade, and renders distinct template blocks. Inspect the source, then remove it when you're ready to scaffold your own.</p>

					<div class="ep-demo-panel" id="ep-demo-panel">
						<div class="ep-demo-status">
							<div class="ep-demo-status-label">Status</div>
							<span class="ep-badge" id="ep-demo-badge"></span>
						</div>
						<p class="ep-demo-message" id="ep-demo-message"></p>
						<div class="ep-demo-actions" id="ep-demo-actions"></div>
					</div>
				</section>
			</div>

			</div><!-- /.ep-panels -->

		</div>
	</div>
	<?php
}
