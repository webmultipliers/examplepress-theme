<?php
/**
 * ExamplePress Admin — Apps Page
 *
 * Companion app management: scaffold, connect, health, and connections.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connection state helper for the apps page.
 */
function examplepress_apps_connection_state(): array {
	return [
		'hasGithubPat'     => (bool) get_option( 'ep_github_pat', '' ),
		'hasGithubApp'     => function_exists( 'examplepress_github_app_is_installed' ) && examplepress_github_app_is_installed(),
		'githubAppAvail'   => function_exists( 'examplepress_github_app_is_configured' ) && examplepress_github_app_is_configured(),
		'githubAppSlug'    => function_exists( 'examplepress_get_github_app_slug' ) ? examplepress_get_github_app_slug() : '',
		'githubOrg'        => get_option( 'ep_github_org', 'webmultipliers' ),
		'appTemplateRepo'  => get_option( 'ep_app_template_repo', defined( 'EP_DEFAULT_TEMPLATE_REPO' ) ? EP_DEFAULT_TEMPLATE_REPO : '' ),
		'hasTroyUrl'       => (bool) get_option( 'ep_troy_server_url', '' ),
		'hasTroyCreds'     => (bool) get_option( 'ep_troy_credentials', '' ),
		'hasTroyGithubPat' => (bool) get_option( 'ep_troy_github_pat', '' ),
		'troyServerUrl'    => get_option( 'ep_troy_server_url', '' ),
		'testGithubUrl'    => esc_url_raw( rest_url( 'examplepress/v1/settings/test-github' ) ),
		'testTroyUrl'      => esc_url_raw( rest_url( 'examplepress/v1/settings/test-troy' ) ),
	];
}

/**
 * Apps page data payload.
 */
function examplepress_apps_data(): array {
	return [
		'themeVersion'      => EP_THEME_VERSION,
		'devMode'           => defined( 'EP_DEV_MODE' ) && EP_DEV_MODE,
		'page'              => 'apps',
		'apps'              => function_exists( 'examplepress_get_apps' ) ? examplepress_get_apps() : [],
		'appsScaffoldUrl'   => esc_url_raw( rest_url( 'examplepress/v1/apps/scaffold' ) ),
		'appsTroyBindUrl'   => esc_url_raw( rest_url( 'examplepress/v1/apps' ) ),
		'appsDeactivateUrl' => esc_url_raw( rest_url( 'examplepress/v1/apps' ) ),
		'appsHealthUrl'     => esc_url_raw( rest_url( 'examplepress/v1/apps' ) ),
		'connectionsUrl'    => esc_url_raw( rest_url( 'examplepress/v1/settings/connections' ) ),
		'connections'       => examplepress_apps_connection_state(),
		'editorUrl'         => examplepress_admin_page_url( 'editor', [ 'app' => '__SLUG__' ] ),
		'troyCloudUrl'      => examplepress_get_troy_cloud_url(),
		'adminUrl'          => esc_url( admin_url() ),
		'nonce'             => wp_create_nonce( 'wp_rest' ),
	];
}

/**
 * Render the app modals (scaffold, troy, codespace).
 */
function examplepress_render_app_modals(): void {
	?>
	<!-- App Scaffold Modal -->
	<div class="ep-modal-overlay" id="ep-apps-scaffold-modal" style="display:none">
		<div class="ep-modal" style="max-width:480px">
			<div class="ep-modal-header">
				<div>
					<span class="ep-modal-title">New App</span>
					<span class="ep-modal-id">Scaffolds a plugin in <code>wp-content/plugins/</code></span>
				</div>
				<button class="ep-modal-close" data-modal="ep-apps-scaffold-modal">&times;</button>
			</div>
			<div class="ep-modal-body">
				<div class="ep-build-field">
					<label class="ep-build-label" for="ep-apps-scaffold-name">App Name</label>
					<input type="text" class="ep-build-input" id="ep-apps-scaffold-name" placeholder="e.g., ExamplePress Analytics" />
				</div>
				<div class="ep-build-field">
					<label class="ep-build-label" for="ep-apps-scaffold-desc">Description</label>
					<input type="text" class="ep-build-input" id="ep-apps-scaffold-desc" placeholder="What does this app do?" />
				</div>
				<div class="ep-scaffold-steps" id="ep-scaffold-steps"></div>
				<div id="ep-apps-scaffold-error" class="ep-build-error" style="display:none"></div>
				<div id="ep-apps-scaffold-warnings" class="ep-scaffold-warnings" style="display:none"></div>
			</div>
			<div class="ep-apps-modal-foot">
				<button class="ep-apps-btn ep-apps-btn-cancel" data-modal="ep-apps-scaffold-modal">Cancel</button>
				<button class="ep-apps-btn ep-apps-btn-primary" id="ep-apps-scaffold-submit">Create App</button>
			</div>
		</div>
	</div>

	<!-- Troy Connect Modal -->
	<div class="ep-modal-overlay" id="ep-apps-troy-modal" style="display:none">
		<div class="ep-modal" style="max-width:440px">
			<div class="ep-modal-header">
				<div>
					<span class="ep-modal-title">Manage App</span>
					<span class="ep-modal-id">Connect to Troy for repo provisioning and automatic updates</span>
				</div>
				<button class="ep-modal-close" data-modal="ep-apps-troy-modal">&times;</button>
			</div>
			<div class="ep-modal-body">
				<div class="ep-apps-troy-info">
					<div class="ep-apps-troy-info-label">Troy &mdash; Decentralized Directory</div>
					<div class="ep-apps-troy-info-sub">Tagged GitHub releases will serve updates automatically after initialization.</div>
				</div>
				<div class="ep-apps-radio-group">
					<label class="ep-apps-radio-label">
						<input type="radio" name="ep-apps-troy-target" value="cloud" checked />
						Troy Cloud (hosted)
					</label>
					<label class="ep-apps-radio-label">
						<input type="radio" name="ep-apps-troy-target" value="custom" />
						Self-hosted Troy instance
					</label>
					<input type="url" class="ep-build-input" id="ep-apps-troy-custom-url" placeholder="https://troy.yourdomain.com" style="display:none; margin-top:4px;" />
				</div>
				<div id="ep-apps-troy-error" class="ep-build-error" style="display:none"></div>
				<input type="hidden" id="ep-apps-troy-target-slug" />
			</div>
			<div class="ep-apps-modal-foot">
				<button class="ep-apps-btn ep-apps-btn-cancel" data-modal="ep-apps-troy-modal">Cancel</button>
				<button class="ep-apps-btn ep-apps-btn-troy" id="ep-apps-troy-submit">Connect to Troy &rarr;</button>
			</div>
		</div>
	</div>

	<!-- Codespace Modal -->
	<div class="ep-modal-overlay" id="ep-apps-codespace-modal" style="display:none">
		<div class="ep-modal" style="max-width:440px">
			<div class="ep-modal-body" style="padding:24px 20px;text-align:center;">
				<svg width="40" height="40" viewBox="0 0 98 96" xmlns="http://www.w3.org/2000/svg" style="margin-bottom:12px"><path fill-rule="evenodd" clip-rule="evenodd" d="M48.854 0C21.839 0 0 22 0 49.217c0 21.756 13.993 40.172 33.405 46.69 2.427.49 3.316-1.059 3.316-2.362 0-1.141-.08-5.052-.08-9.127-13.59 2.934-16.42-5.867-16.42-5.867-2.184-5.704-5.42-7.17-5.42-7.17-4.448-3.015.324-3.015.324-3.015 4.934.326 7.523 5.052 7.523 5.052 4.367 7.496 11.404 5.378 14.235 4.074.404-3.178 1.699-5.378 3.074-6.6-10.839-1.141-22.243-5.378-22.243-24.283 0-5.378 1.94-9.778 5.014-13.2-.485-1.222-2.184-6.275.486-13.038 0 0 4.125-1.304 13.426 5.052a46.97 46.97 0 0 1 12.214-1.63c4.125 0 8.33.571 12.213 1.63 9.302-6.356 13.427-5.052 13.427-5.052 2.67 6.763.97 11.816.485 13.038 3.155 3.422 5.015 7.822 5.015 13.2 0 18.905-11.404 23.06-22.324 24.283 1.78 1.548 3.316 4.481 3.316 9.126 0 6.6-.08 11.897-.08 13.526 0 1.304.89 2.853 3.316 2.364 19.412-6.52 33.405-24.935 33.405-46.691C97.707 22 75.788 0 48.854 0z" fill="#24292f"/></svg>
				<h2 style="margin:0 0 6px;font-size:17px;font-weight:600;">Launching Codespace</h2>
				<p style="color:#646970;font-size:13px;margin:0 0 14px;">You'll be redirected to GitHub with the repo pre-selected.</p>
				<div class="ep-apps-codespace-url" id="ep-apps-codespace-url"></div>
				<button class="ep-apps-btn ep-apps-btn-cancel" data-modal="ep-apps-codespace-modal">Close</button>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Apps page render.
 */
function examplepress_render_apps_page(): void {
	$is_dev = defined( 'EP_DEV_MODE' ) && EP_DEV_MODE;
	$ep_github_app_available = function_exists( 'examplepress_github_app_is_configured' ) && examplepress_github_app_is_configured();
	$ep_github_app_installed = function_exists( 'examplepress_github_app_is_installed' ) && examplepress_github_app_is_installed();
	?>
	<div class="ep-settings-wrapper">
		<div class="ep-settings">

			<?php examplepress_render_page_header( $is_dev ); ?>

			<div class="ep-layout">
			<nav class="ep-tabs" role="tablist">
				<button class="ep-tab" role="tab" aria-selected="true"  aria-controls="p-build"       id="t-build"       data-tab-id="build">Apps</button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-connections"  id="t-connections"  data-tab-id="connections">Connections</button>
			</nav>

			<div class="ep-panels">

			<!-- Build / Apps -->
			<div class="ep-panel" id="p-build" role="tabpanel" aria-hidden="false">

				<!-- Workflow Explanation -->
				<section class="ep-section">
					<div class="ep-apps-workflow">
						<div class="ep-apps-workflow-step">
							<div class="ep-apps-step-num">1</div>
							<div class="ep-apps-step-title">Scaffold App</div>
							<p class="ep-apps-step-desc">Create a new local plugin directory inheriting ExamplePress design standards.</p>
							<span class="ep-apps-step-sys ep-apps-sys-wp">Local WordPress</span>
						</div>
						<div class="ep-apps-workflow-step">
							<div class="ep-apps-step-num">2</div>
							<div class="ep-apps-step-title">Push to GitHub</div>
							<p class="ep-apps-step-desc">Create a GitHub repo from the template, replace placeholders, and tag the initial release.</p>
							<span class="ep-apps-step-sys ep-apps-sys-gh">GitHub</span>
						</div>
						<div class="ep-apps-workflow-step">
							<div class="ep-apps-step-num">3</div>
							<div class="ep-apps-step-title">Edit Code</div>
							<p class="ep-apps-step-desc">Launch a GitHub Codespace to write code without local dev environments.</p>
							<span class="ep-apps-step-sys ep-apps-sys-codespace">Codespaces</span>
						</div>
						<div class="ep-apps-workflow-step">
							<div class="ep-apps-step-num">4</div>
							<div class="ep-apps-step-title">Ship Updates</div>
							<p class="ep-apps-step-desc">Tag a GitHub Release. WordPress checks GitHub directly and installs the update natively.</p>
							<span class="ep-apps-step-sys ep-apps-sys-gh">GitHub Releases &rarr; WP Update</span>
						</div>
					</div>
				</section>

				<!-- Platform Stats -->
				<section class="ep-section">
					<div class="ep-apps-stats" id="ep-apps-stats"></div>
				</section>

				<!-- Toolbar -->
				<section class="ep-section">
					<button class="ep-build-submit" id="ep-apps-new-btn">
						<span class="ep-build-submit-label">+ New App</span>
					</button>
				</section>

				<!-- Apps Table -->
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Apps</span><div class="ep-section-line"></div></div>
					<div id="ep-apps-table"></div>
				</section>

			</div>

			<!-- Connections -->
			<div class="ep-panel" id="p-connections" role="tabpanel" aria-hidden="true">
				<section class="ep-section" id="ep-connections-section">
					<div class="ep-section-header"><span class="ep-section-title">Connections</span><div class="ep-section-line"></div></div>
					<p class="ep-section-desc">Configure credentials for the automated scaffold pipeline. Without these, the "+ New App" flow scaffolds locally only.</p>
					<div class="ep-connections-grid" id="ep-connections-grid">
						<div class="ep-conn-group">
							<div class="ep-conn-group-title">GitHub</div>
							<div class="ep-conn-field">
								<label class="ep-build-label" for="ep-conn-github-org">Organization</label>
								<input type="text" id="ep-conn-github-org" placeholder="webmultipliers" />
							</div>
							<div class="ep-conn-field">
								<label class="ep-build-label" for="ep-conn-app-template">App Template Repository</label>
								<input type="text" id="ep-conn-app-template" placeholder="<?php echo esc_attr( EP_DEFAULT_TEMPLATE_REPO ); ?>" />
								<span class="ep-build-hint">GitHub template repo used when scaffolding new apps. Use your own to customize the boilerplate.</span>
							</div>
							<?php if ( $ep_github_app_available ) : ?>
							<div class="ep-conn-field">
								<label class="ep-build-label">App Authorization</label>
								<div class="ep-troy-auth-row">
									<button class="ep-demo-btn ep-demo-btn-primary" id="ep-conn-github-app-btn" type="button">Install GitHub App</button>
									<span class="ep-troy-auth-status" id="ep-github-app-status"></span>
								</div>
								<span class="ep-build-hint">Grants repo creation + code push on your org. No shared secrets.</span>
							</div>
							<div class="ep-conn-field" style="border-top:1px solid #c3c4c7;padding-top:0.6rem;margin-top:0.2rem;">
								<label class="ep-build-label" style="color:#50575e;font-size:0.68rem;">Or use a token instead</label>
							<?php else : ?>
							<div class="ep-conn-field">
								<label class="ep-build-label">Write Access Token</label>
							<?php endif; ?>
								<input type="password" id="ep-conn-github-pat" placeholder="github_pat_..." autocomplete="off" />
								<span class="ep-build-hint">Fine-grained PAT. Permissions: <code>Administration</code> (R/W) + <code>Contents</code> (R/W). <a href="https://github.com/settings/personal-access-tokens/new" target="_blank" rel="noopener">Create token &rarr;</a></span>
							</div>
							<div class="ep-conn-field">
								<div class="ep-troy-auth-row">
									<button class="ep-demo-btn" id="ep-test-github-btn" type="button">Test GitHub</button>
									<span class="ep-troy-auth-status" id="ep-test-github-status"></span>
								</div>
							</div>
						</div>
						<details class="ep-conn-group ep-conn-group-troy">
							<summary class="ep-conn-group-title" style="cursor:pointer;">Troy Server <span class="ep-build-hint" style="font-weight:normal;margin-left:6px;">Optional &mdash; for multi-site distribution</span></summary>
							<div class="ep-conn-field">
								<label class="ep-build-label" for="ep-conn-troy-url">Server URL</label>
								<input type="url" id="ep-conn-troy-url" placeholder="https://internal.repo.mustuse.com" />
							</div>
							<div class="ep-conn-field">
								<label class="ep-build-label">Authorization</label>
								<div class="ep-troy-auth-row">
									<button class="ep-demo-btn ep-demo-btn-primary" id="ep-conn-troy-auth-btn" type="button">Authorize with Troy</button>
									<span class="ep-troy-auth-status" id="ep-troy-auth-status"></span>
								</div>
								<span class="ep-build-hint">Opens the Troy Server to create an application password automatically.</span>
							</div>
							<div class="ep-conn-field">
								<label class="ep-build-label" for="ep-conn-troy-github-pat">GitHub Read Token</label>
								<input type="password" id="ep-conn-troy-github-pat" placeholder="github_pat_..." autocomplete="off" />
								<span class="ep-build-hint">Fine-grained PAT with <code>Contents</code> (Read). Passed to Troy for tag fetching and ZIP downloads from private repos.</span>
							</div>
							<div class="ep-conn-field">
								<div class="ep-troy-auth-row">
									<button class="ep-demo-btn" id="ep-test-troy-btn" type="button">Test Troy</button>
									<span class="ep-troy-auth-status" id="ep-test-troy-status"></span>
								</div>
							</div>
						</details>
					</div>
					<div class="ep-conn-actions">
						<button class="ep-build-submit" id="ep-conn-save-btn" type="button">Save Connections</button>
						<span class="ep-conn-status" id="ep-conn-status"></span>
					</div>
				</section>
			</div>

			</div><!-- /.ep-panels -->
			</div><!-- /.ep-layout -->

		</div>

		<?php examplepress_render_app_modals(); ?>

	</div>
	<?php
}
