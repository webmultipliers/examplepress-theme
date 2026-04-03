<?php
/**
 * ExamplePress Admin — System Page
 *
 * Health checks, environment info, and support.
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
		'themeVersion'  => EP_THEME_VERSION,
		'devMode'       => defined( 'EP_DEV_MODE' ) && EP_DEV_MODE,
		'page'          => 'system',
		'healthChecks'  => examplepress_settings_get_health(),
		'nonce'         => wp_create_nonce( 'wp_rest' ),
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
				<button class="ep-tab" role="tab" aria-selected="true"  aria-controls="p-health"  id="t-health"  data-tab-id="health">Health</button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-support" id="t-support" data-tab-id="support">Support</button>
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

			<!-- Support -->
			<div class="ep-panel" id="p-support" role="tabpanel" aria-hidden="true">
				<section class="ep-section">
					<div class="ep-doc-section-title">Support &amp; Resources</div>
					<p class="ep-doc-section-desc">Get help, contribute, and connect with the ExamplePress ecosystem.</p>
					<div class="ep-support-grid">
						<a class="ep-support-card" href="https://github.com/flavor/flavor" target="_blank" rel="noopener">
							<div class="ep-support-card-eyebrow">Framework</div>
							<div class="ep-support-card-title">Blockstudio</div>
							<div class="ep-support-card-desc">The open-source block framework ExamplePress is built on. Block registration, fields, rendering, SCSS/Tailwind, and developer tools.</div>
							<div class="ep-support-card-link">GitHub &rarr;</div>
						</a>
						<div class="ep-support-card">
							<div class="ep-support-card-eyebrow">Coming Soon</div>
							<div class="ep-support-card-title">ExamplePress Support</div>
							<div class="ep-support-card-desc">Advanced support packages for teams building on ExamplePress. Dedicated onboarding, architecture review, and priority issue resolution.</div>
							<span class="ep-badge badge-info"><span class="ep-dot"></span>Coming Soon</span>
						</div>
						<a class="ep-support-card" href="https://github.com/webmultipliers/examplepress-theme" target="_blank" rel="noopener">
							<div class="ep-support-card-eyebrow">Open Source</div>
							<div class="ep-support-card-title">Contribute</div>
							<div class="ep-support-card-desc">Report bugs, suggest features, or submit pull requests. ExamplePress is open source and built for the community.</div>
							<div class="ep-support-card-link">GitHub &rarr;</div>
						</a>
						<a class="ep-support-card" href="https://examplepress.com/contact" target="_blank" rel="noopener">
							<div class="ep-support-card-eyebrow">Contact</div>
							<div class="ep-support-card-title">Get In Touch</div>
							<div class="ep-support-card-desc">Questions about licensing, partnerships, or enterprise deployments? Reach out to the team directly.</div>
							<div class="ep-support-card-link">examplepress.com &rarr;</div>
						</a>
					</div>
				</section>
			</div>

			</div><!-- /.ep-panels -->
			</div><!-- /.ep-layout -->

		</div>
	</div>
	<?php
}
