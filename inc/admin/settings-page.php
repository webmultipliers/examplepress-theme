<?php
/**
 * ExamplePress Settings Page
 *
 * Read-only admin dashboard surfacing the resolved state of the
 * feature registry, design tokens, guards, and system health.
 */

// ── Menu Registration ──────────────────────────────────────────────

add_action( 'admin_menu', 'examplepress_register_settings_page' );

function examplepress_register_settings_page() {
	$hook = add_menu_page(
		__( 'ExamplePress', 'examplepress-theme' ),
		'ExamplePress',
		'manage_options',
		'examplepress-settings',
		'examplepress_render_settings_page',
		'dashicons-layout',
		60
	);

	add_action( 'admin_enqueue_scripts', function ( $hook_suffix ) use ( $hook ) {
		if ( $hook_suffix !== $hook ) {
			return;
		}
		examplepress_enqueue_settings_assets();
	} );
}

// ── Asset Enqueuing ────────────────────────────────────────────────

function examplepress_enqueue_settings_assets() {
	wp_enqueue_style(
		'ep-settings-fonts',
		'https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@400;500;600&display=swap',
		[],
		null
	);

	wp_enqueue_style(
		'ep-settings',
		EP_THEME_URI . '/assets/css/admin-settings.css',
		[ 'ep-settings-fonts' ],
		EP_THEME_VERSION
	);

	wp_enqueue_script(
		'ep-settings',
		EP_THEME_URI . '/assets/js/admin-settings.js',
		[],
		EP_THEME_VERSION,
		true
	);

	wp_add_inline_script(
		'ep-settings',
		'window.ExamplePressData = ' . wp_json_encode( examplepress_settings_gather_data() ) . ';',
		'before'
	);
}

// ── Data Gathering ─────────────────────────────────────────────────

function examplepress_settings_gather_data() {
	return [
		'themeVersion' => EP_THEME_VERSION,
		'features'     => examplepress_settings_get_features(),
		'colors'       => (array) examplepress_feature_option( 'theme-colors', 'palette', [] ),
		'layout'       => [
			'wideSize'    => (string) examplepress_feature_option( 'theme-layout', 'wide_size', '1200px' ),
			'contentSize' => (string) examplepress_feature_option( 'theme-layout', 'content_size', '800px' ),
		],
		'fonts'        => examplepress_settings_get_fonts(),
		'sizes'        => examplepress_settings_get_sizes(),
		'blocks'       => examplepress_settings_get_blocks(),
		'pluginsReq'   => examplepress_settings_get_plugins( 'required' ),
		'pluginsRec'   => examplepress_settings_get_plugins( 'recommended' ),
		'configFiles'  => examplepress_settings_get_config_files(),
		'healthChecks' => examplepress_settings_get_health(),
		'docs'         => examplepress_settings_get_docs(),
		'hooks'        => examplepress_settings_get_hooks(),
	];
}

/**
 * Detect whether a feature's resolved value comes from a PHP filter,
 * the JSON config, or the registration default.
 */
function examplepress_settings_detect_source( $id ) {
	$config = examplepress_get_config();

	if ( has_filter( "examplepress_feature_{$id}" ) ) {
		return 'php';
	}
	if ( isset( $config['features'][ $id ] ) ) {
		return 'json';
	}
	return 'default';
}

/**
 * Build the features array grouped by UI category.
 */
function examplepress_settings_get_features() {
	$features = examplepress_get_features();

	$categories = [
		'theme-support' => [ 'title-tag', 'responsive-embeds', 'post-thumbnails', 'wp-block-styles', 'html5' ],
		'editor'        => [ 'disable-remote-block-patterns', 'disable-core-block-patterns', 'restrict-block-types', 'disable-redirect-guess-404', 'openverse', 'post-lock-window' ],
		'guards'        => [ 'guard-template-redirect', 'guard-template-rest', 'guard-template-resolution' ],
		'admin'         => [ 'remove-dashboard-widgets', 'login-branding' ],
		'options'       => [ 'permalink-structure', 'managed-options' ],
	];

	$opt_display = [
		'html5'                => [ 'features', fn( $v ) => implode( ', ', (array) $v ) ],
		'post-lock-window'     => [ 'duration', fn( $v ) => $v . 's' ],
		'restrict-block-types' => [ 'types', fn( $v ) => implode( ', ', (array) $v ) ],
		'permalink-structure'  => [ 'structure', fn( $v ) => (string) $v ],
		'managed-options'      => [ 'values', function ( $v ) {
			$parts = [];
			foreach ( (array) $v as $k => $val ) {
				$parts[] = "$k: $val";
			}
			return empty( $parts ) ? '(none)' : implode( ', ', $parts );
		} ],
	];

	$result = [];
	foreach ( $categories as $cat => $ids ) {
		$result[ $cat ] = [];
		foreach ( $ids as $id ) {
			if ( ! isset( $features[ $id ] ) ) {
				continue;
			}
			$item = [
				'id'   => $id,
				'name' => $features[ $id ]['label'],
				'on'   => examplepress_feature_enabled( $id ),
				'src'  => examplepress_settings_detect_source( $id ),
			];
			if ( isset( $opt_display[ $id ] ) ) {
				[ $key, $formatter ] = $opt_display[ $id ];
				$val = examplepress_feature_option( $id, $key, null );
				if ( $val !== null ) {
					$item['opts'] = $key . ' → ' . $formatter( $val );
				}
			}
			$result[ $cat ][] = $item;
		}
	}
	return $result;
}

/**
 * Static font family definitions.
 */
function examplepress_settings_get_fonts() {
	return [
		[ 'name' => 'System',    'slug' => 'system', 'stack' => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" ],
		[ 'name' => 'Monospace', 'slug' => 'mono',   'stack' => "'JetBrains Mono', ui-monospace, monospace" ],
		[ 'name' => 'Serif',    'slug' => 'serif',   'stack' => "'Instrument Serif', Georgia, serif" ],
	];
}

/**
 * Static type size scale.
 */
function examplepress_settings_get_sizes() {
	return [
		[ 'slug' => 'sm',  'size' => '0.875rem', 'name' => 'Small' ],
		[ 'slug' => 'md',  'size' => '1rem',     'name' => 'Medium' ],
		[ 'slug' => 'lg',  'size' => '1.25rem',  'name' => 'Large' ],
		[ 'slug' => 'xl',  'size' => '1.5rem',   'name' => 'Extra Large' ],
		[ 'slug' => '2xl', 'size' => '2rem',      'name' => '2X Large' ],
	];
}

/**
 * Discover all Blockstudio blocks from the WP block registry.
 *
 * Queries WP_Block_Type_Registry instead of scanning the filesystem
 * so companion plugin blocks are included alongside theme blocks.
 */
function examplepress_settings_get_blocks() {
	$blocks     = [];
	$theme_ns   = examplepress_get_theme_namespace();
	$registry   = WP_Block_Type_Registry::get_instance();
	$registered = $registry->get_all_registered();

	foreach ( $registered as $name => $block ) {
		if ( empty( $block->blockstudio ) ) {
			continue;
		}

		$blocks[] = [
			'name'   => $name,
			'title'  => $block->title ?? $name,
			'cat'    => $block->category ?? 'uncategorized',
			'source' => str_starts_with( $name, 'examplepress-theme/' ) ? 'theme' : 'plugin',
			'type'   => str_contains( $name, '/router' ) ? 'system' : ( str_contains( $name, '/template-' ) ? 'template' : 'block' ),
		];
	}

	return $blocks;
}

/**
 * Check installed/active status for plugin dependencies.
 */
function examplepress_settings_get_plugins( $tier ) {
	$config = examplepress_get_config();
	$slugs  = $config['plugins'][ $tier ] ?? [];

	if ( empty( $slugs ) ) {
		return [];
	}

	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$installed = get_plugins();
	$active    = array_map( 'plugin_basename', wp_get_active_and_valid_plugins() );
	$result    = [];

	foreach ( $slugs as $slug ) {
		$item = [
			'name'   => $slug,
			'slug'   => $slug,
			'status' => 'missing',
			'url'    => "https://wordpress.org/plugins/{$slug}/",
		];

		foreach ( $installed as $file => $data ) {
			if ( str_starts_with( $file, $slug . '/' ) ) {
				$item['name']   = $data['Name'];
				$item['status'] = in_array( $file, $active, true ) ? 'active' : 'installed';
				if ( ! empty( $data['PluginURI'] ) ) {
					$item['url'] = $data['PluginURI'];
				}
				break;
			}
		}

		$result[] = $item;
	}

	return $result;
}

/**
 * Read the raw configuration files for the Config viewer.
 */
function examplepress_settings_get_config_files() {
	$files = [];
	$paths = [
		'examplepress.json' => EP_THEME_PATH . '/examplepress.json',
		'theme.json'        => EP_THEME_PATH . '/theme.json',
		'blockstudio.json'  => EP_THEME_PATH . '/blockstudio.json',
	];

	foreach ( $paths as $name => $path ) {
		if ( file_exists( $path ) ) {
			$files[ $name ] = json_decode( file_get_contents( $path ), true ) ?? []; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
	}

	return $files;
}

/**
 * Run health checks against the current environment.
 */
function examplepress_settings_get_health() {
	$wp_ver       = get_bloginfo( 'version' );
	$php_ver      = phpversion();
	$has_autoload = file_exists( EP_THEME_PATH . '/vendor/autoload.php' );
	$memory       = ini_get( 'memory_limit' );

	$has_config = file_exists( EP_THEME_PATH . '/examplepress.json' );
	$has_theme  = file_exists( EP_THEME_PATH . '/theme.json' );
	$has_bs     = file_exists( EP_THEME_PATH . '/blockstudio.json' );

	$theme_json = $has_theme
		? ( json_decode( file_get_contents( EP_THEME_PATH . '/theme.json' ), true ) ?? [] ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		: [];

	$index_content = file_exists( EP_THEME_PATH . '/templates/index.html' )
		? trim( file_get_contents( EP_THEME_PATH . '/templates/index.html' ) ) // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		: '';
	$router_only   = $index_content === '<!-- wp:examplepress-theme/router /-->';

	$bs_active = class_exists( 'Blockstudio\\Build' );

	return [
		'env'      => [
			[ 'name' => 'WordPress Version',  'detail' => $wp_ver,                                          'req' => "\u{2265} 6.9",   'status' => version_compare( $wp_ver, '6.9', '>=' ) ? 'pass' : 'fail' ],
			[ 'name' => 'PHP Version',         'detail' => $php_ver,                                         'req' => "\u{2265} 8.4",   'status' => version_compare( $php_ver, '8.4', '>=' ) ? 'pass' : 'fail' ],
			[ 'name' => 'Blockstudio',         'detail' => $bs_active ? 'Active' : 'Not detected',           'req' => 'Active',         'status' => $bs_active ? 'pass' : 'fail' ],
			[ 'name' => 'Composer Autoload',   'detail' => $has_autoload ? 'Loaded' : 'Missing',             'req' => 'File exists',    'status' => $has_autoload ? 'pass' : 'warn' ],
			[ 'name' => 'Memory Limit',        'detail' => $memory,                                          'req' => "\u{2265} 128M",  'status' => wp_convert_hr_to_bytes( $memory ) >= 134217728 ? 'pass' : 'warn' ],
		],
		'theme'    => [
			[ 'name' => 'examplepress.json',   'detail' => $has_config ? 'Found and valid' : 'Not found',    'req' => 'File exists',           'status' => $has_config ? 'pass' : 'warn' ],
			[ 'name' => 'theme.json',           'detail' => $has_theme ? 'Version ' . ( $theme_json['version'] ?? '?' ) : 'Not found', 'req' => "Version \u{2265} 3", 'status' => ( $theme_json['version'] ?? 0 ) >= 3 ? 'pass' : 'fail' ],
			[ 'name' => 'blockstudio.json',     'detail' => $has_bs ? 'Found and valid' : 'Not found',       'req' => 'File exists',           'status' => $has_bs ? 'pass' : 'warn' ],
			[ 'name' => 'templates/index.html', 'detail' => $router_only ? 'Router block only' : 'Non-standard', 'req' => 'Single router block', 'status' => $router_only ? 'pass' : 'warn' ],
		],
		'router'   => [
			[
				'name'   => 'Active Namespace',
				'detail' => examplepress_get_theme_namespace(),
				'req'    => 'Changed via filter',
				'status' => examplepress_get_theme_namespace() !== 'examplepress-theme' ? 'pass' : 'warn',
				'note'   => examplepress_get_theme_namespace() === 'examplepress-theme' ? 'Still using the theme default — a companion plugin should override this' : '',
			],
			[ 'name' => 'Current Route',     'detail' => examplepress_get_current_route(),     'req' => 'Non-empty string', 'status' => ! empty( examplepress_get_current_route() ) ? 'pass' : 'fail' ],
			[ 'name' => 'Template Prefix',   'detail' => examplepress_get_template_prefix(),   'req' => 'Non-empty string', 'status' => 'pass' ],
		],
		'security' => [
			[ 'name' => 'REST Template Guard',       'detail' => examplepress_feature_enabled( 'guard-template-rest' ) ? 'Active' : 'Disabled',       'req' => 'Enabled', 'status' => examplepress_feature_enabled( 'guard-template-rest' ) ? 'pass' : 'warn' ],
			[ 'name' => 'Template Resolution Guard', 'detail' => examplepress_feature_enabled( 'guard-template-resolution' ) ? 'Active' : 'Disabled', 'req' => 'Enabled', 'status' => examplepress_feature_enabled( 'guard-template-resolution' ) ? 'pass' : 'warn' ],
			[ 'name' => 'Editor Redirect Guard',     'detail' => examplepress_feature_enabled( 'guard-template-redirect' ) ? 'Active' : 'Disabled',   'req' => 'Enabled', 'status' => examplepress_feature_enabled( 'guard-template-redirect' ) ? 'pass' : 'warn' ],
			[
				'name'   => 'Block Type Restriction',
				'detail' => examplepress_feature_enabled( 'restrict-block-types' ) ? 'Active' : 'Disabled',
				'req'    => 'Opt-in',
				'status' => 'info',
				'note'   => examplepress_feature_enabled( 'restrict-block-types' ) ? '' : 'Not active — companion plugin can enable',
			],
		],
	];
}

/**
 * Static documentation links.
 */
function examplepress_settings_get_docs() {
	return [
		[ 'eyebrow' => 'Architecture', 'title' => 'Router & Routing',          'desc' => 'How the single-entry-point router works, the filter chain, and how to implement your routing cascade.', 'link' => 'https://examplepress.com/docs/routing',          'label' => 'Read docs' ],
		[ 'eyebrow' => 'Architecture', 'title' => 'Guard System',              'desc' => 'Why template lockdown exists, what each guard prevents, and how to disable guards for development.',     'link' => 'https://examplepress.com/docs/guards',           'label' => 'Read docs' ],
		[ 'eyebrow' => 'Configuration', 'title' => 'examplepress.json Schema', 'desc' => 'Full schema reference for the configuration file — features, design tokens, plugin dependencies.',       'link' => 'https://examplepress.com/schema/examplepress-theme', 'label' => 'View schema' ],
		[ 'eyebrow' => 'Configuration', 'title' => 'Feature Registry API',     'desc' => 'Register features, check state, read options. The filterable flag system that powers theme behavior.',    'link' => 'https://examplepress.com/docs/feature-registry', 'label' => 'Read docs' ],
		[ 'eyebrow' => 'Blockstudio',   'title' => 'Blockstudio Documentation', 'desc' => 'The block framework ExamplePress is built on. Covers block registration, fields, rendering, and hooks.', 'link' => 'https://blockstudio.dev/documentation/',         'label' => 'blockstudio.dev' ],
	];
}

/**
 * Static hook reference.
 */
function examplepress_settings_get_hooks() {
	return [
		[ 'name' => 'examplepress_route_context',        'type' => 'filter', 'desc' => 'Override the resolved route slug. This is the primary routing hook for companion plugins.' ],
		[ 'name' => 'examplepress_route_data',           'type' => 'filter', 'desc' => 'Enrich the data payload passed to template blocks via bs_block().' ],
		[ 'name' => 'examplepress_route_resolved',       'type' => 'action', 'desc' => 'Fires after route resolution, before dispatch. Set up route-specific state here.' ],
		[ 'name' => 'examplepress_theme_namespace',      'type' => 'filter', 'desc' => 'Override the block namespace. Companion plugins use this to point the router at their own blocks.' ],
		[ 'name' => 'examplepress_template_prefix',      'type' => 'filter', 'desc' => 'Override the template block prefix. Default: "template".' ],
		[ 'name' => 'examplepress_template_block_name',  'type' => 'filter', 'desc' => 'Override the fully assembled block name before dispatch.' ],
		[ 'name' => 'examplepress_allowed_block_types',  'type' => 'filter', 'desc' => 'Allowlist of block types when restrict-block-types feature is enabled.' ],
		[ 'name' => 'examplepress_feature_{id}',         'type' => 'filter', 'desc' => 'Toggle any registered feature on or off. Highest priority override.' ],
		[ 'name' => 'examplepress_feature_{id}_{key}',   'type' => 'filter', 'desc' => 'Override a specific option value for a feature.' ],
		[ 'name' => 'examplepress_features',             'type' => 'filter', 'desc' => 'Filter the entire feature registry array. Use for bulk modifications.' ],
	];
}

// ── HTML Shell ─────────────────────────────────────────────────────

function examplepress_render_settings_page() {
	?>
	<div class="ep-settings-wrapper">
		<div class="ep-settings">

			<header class="ep-header">
				<div class="ep-header-top">
					<div class="ep-logo"><span>&lt;</span>ExamplePress<span>/&gt;</span></div>
					<div class="ep-header-right">
						<button class="ep-copy-report" id="ep-copy-report">Copy System Report</button>
						<div class="ep-version">v<?php echo esc_html( EP_THEME_VERSION ); ?></div>
					</div>
				</div>
				<p class="ep-subtitle">Configuration dashboard — all values resolved from examplepress.json and PHP filters. Read-only.</p>
			</header>

			<nav class="ep-tabs" role="tablist">
				<button class="ep-tab" role="tab" aria-selected="true"  aria-controls="p-features" id="t-features">Features<span class="ep-tab-count"></span></button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-design"   id="t-design">Design</button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-blocks"   id="t-blocks">Blocks<span class="ep-tab-count"></span></button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-plugins"  id="t-plugins">Plugins<span class="ep-tab-count"></span></button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-config"   id="t-config">Config</button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-health"   id="t-health">Health</button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-docs"     id="t-docs">Docs</button>
			</nav>

			<!-- ═══ Features ═══ -->
			<div class="ep-panel" id="p-features" role="tabpanel" aria-hidden="false">
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Theme Support</span><div class="ep-section-line"></div></div>
					<div class="ep-table" id="tbl-theme-support"></div>
				</section>
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Editor &amp; Content Controls</span><div class="ep-section-line"></div></div>
					<div class="ep-table" id="tbl-editor"></div>
				</section>
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Guards</span><div class="ep-section-line"></div></div>
					<p class="ep-section-desc">Guards protect the router architecture by preventing site editor template creation. Disable individually via examplepress.json or PHP filters.</p>
					<div class="ep-table" id="tbl-guards"></div>
				</section>
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Admin Customization</span><div class="ep-section-line"></div></div>
					<div class="ep-table" id="tbl-admin"></div>
				</section>
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Site Options</span><div class="ep-section-line"></div></div>
					<div class="ep-table" id="tbl-options"></div>
				</section>
			</div>

			<!-- ═══ Design ═══ -->
			<div class="ep-panel" id="p-design" role="tabpanel" aria-hidden="true">
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Color Palette</span><div class="ep-section-line"></div></div>
					<p class="ep-section-desc">Resolved from examplepress.json — design.colors. Translated to theme.json settings.color.palette at runtime.</p>
					<div class="ep-colors-grid" id="colors-grid"></div>
				</section>
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Layout</span><div class="ep-section-line"></div></div>
					<div class="ep-layout-visual" id="layout-visual"></div>
				</section>
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Font Families</span><div class="ep-section-line"></div></div>
					<div class="ep-type-stack" id="type-stack"></div>
				</section>
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Size Scale</span><div class="ep-section-line"></div></div>
					<div class="ep-size-scale" id="size-scale"></div>
				</section>
			</div>

			<!-- ═══ Blocks ═══ -->
			<div class="ep-panel" id="p-blocks" role="tabpanel" aria-hidden="true">
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Registered Blocks</span><div class="ep-section-line"></div></div>
					<p class="ep-section-desc">All Blockstudio blocks discovered in the active theme and companion plugins. Template blocks are blocks the router can dispatch to.</p>
					<div class="ep-table" id="tbl-blocks"></div>
				</section>
			</div>

			<!-- ═══ Plugins ═══ -->
			<div class="ep-panel" id="p-plugins" role="tabpanel" aria-hidden="true">
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Required</span><div class="ep-section-line"></div></div>
					<p class="ep-section-desc">Must be installed and active for the theme to function.</p>
					<div class="ep-table" id="tbl-plugins-req"></div>
				</section>
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Recommended</span><div class="ep-section-line"></div></div>
					<div class="ep-table" id="tbl-plugins-rec"></div>
				</section>
			</div>

			<!-- ═══ Config ═══ -->
			<div class="ep-panel" id="p-config" role="tabpanel" aria-hidden="true">
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Configuration Files</span><div class="ep-section-line"></div></div>
					<p class="ep-section-desc">Explore the configuration files that drive the theme. All values are read-only — edit the files directly in your project.</p>
					<div class="ep-config-tabs" id="config-switcher"></div>
					<div id="config-viewer"></div>
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

			<!-- ═══ Health ═══ -->
			<div class="ep-panel" id="p-health" role="tabpanel" aria-hidden="true">
				<section class="ep-section">
					<div class="ep-health-summary" id="health-summary"></div>
				</section>
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Environment Checks</span><div class="ep-section-line"></div></div>
					<div class="ep-table" id="tbl-health-env"></div>
				</section>
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Theme Integrity</span><div class="ep-section-line"></div></div>
					<div class="ep-table" id="tbl-health-theme"></div>
				</section>
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Router Health</span><div class="ep-section-line"></div></div>
					<div class="ep-table" id="tbl-health-router"></div>
				</section>
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Security &amp; Guards</span><div class="ep-section-line"></div></div>
					<div class="ep-table" id="tbl-health-security"></div>
				</section>
			</div>

			<!-- ═══ Docs ═══ -->
			<div class="ep-panel" id="p-docs" role="tabpanel" aria-hidden="true">
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
			</div>

		</div>
	</div>
	<?php
}
