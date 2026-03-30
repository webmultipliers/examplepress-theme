<?php
/**
 * ExamplePress Settings Page
 *
 * Read-only admin dashboard surfacing the resolved state of the
 * feature registry, design tokens, dependencies, notifications,
 * guards, and system health.
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
		'themeVersion'   => EP_THEME_VERSION,
		'devMode'        => defined( 'EP_DEV_MODE' ) && EP_DEV_MODE,
		'features'       => examplepress_settings_get_features(),
		'colors'         => (array) examplepress_feature_option( 'theme-colors', 'palette', [] ),
		'layout'         => [
			'wideSize'    => (string) examplepress_feature_option( 'theme-layout', 'wide_size', '1200px' ),
			'contentSize' => (string) examplepress_feature_option( 'theme-layout', 'content_size', '800px' ),
		],
		'fonts'          => examplepress_settings_get_fonts(),
		'sizes'          => examplepress_settings_get_sizes(),
		'blocks'         => examplepress_settings_get_blocks(),
		'dependencies'   => examplepress_get_dependencies(),
		'notifications'  => examplepress_gather_notifications(),
		'archived'       => examplepress_get_archived_notifications(),
		'configFiles'    => examplepress_settings_get_config_files(),
		'healthChecks'   => examplepress_settings_get_health(),
		'docs'           => examplepress_settings_get_docs(),
		'hooks'          => examplepress_settings_get_hooks(),
		'restUrl'        => esc_url_raw( rest_url( 'examplepress/v1/notifications/archive' ) ),
		'buildUrl'       => esc_url_raw( rest_url( 'examplepress/v1/build' ) ),
		'nonce'          => wp_create_nonce( 'wp_rest' ),
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
 * Inspect $wp_filter to identify which function or class hooked a
 * feature filter. Returns a human-readable origin string.
 */
function examplepress_settings_get_filter_origin( $id ) {
	global $wp_filter;

	$tag = "examplepress_feature_{$id}";
	if ( empty( $wp_filter[ $tag ] ) ) {
		return '';
	}

	$callbacks = $wp_filter[ $tag ]->callbacks ?? [];
	foreach ( $callbacks as $hooks ) {
		foreach ( $hooks as $hook ) {
			$fn = $hook['function'] ?? null;
			if ( is_string( $fn ) ) {
				return $fn . '()';
			}
			if ( is_array( $fn ) && count( $fn ) === 2 ) {
				$class = is_object( $fn[0] ) ? get_class( $fn[0] ) : (string) $fn[0];
				return $class . '::' . $fn[1] . '()';
			}
			if ( $fn instanceof Closure ) {
				$ref = new ReflectionFunction( $fn );
				return basename( $ref->getFileName() ) . ':' . $ref->getStartLine();
			}
		}
	}
	return '';
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
		'design'        => [ 'theme-colors', 'theme-layout', 'theme-typography', 'design-strict' ],
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
			$src  = examplepress_settings_detect_source( $id );
			$item = [
				'id'   => $id,
				'name' => $features[ $id ]['label'],
				'on'   => examplepress_feature_enabled( $id ),
				'src'  => $src,
			];
			if ( $src === 'php' ) {
				$origin = examplepress_settings_get_filter_origin( $id );
				if ( $origin ) {
					$item['srcDetail'] = $origin;
				}
			}
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
 * Get font families from the feature registry, falling back to system defaults.
 */
function examplepress_settings_get_fonts() {
	$families = (array) examplepress_feature_option( 'theme-typography', 'font_families', [] );
	if ( ! empty( $families ) ) {
		return array_map( function ( $f ) {
			return [
				'name'  => $f['name'] ?? '',
				'slug'  => $f['slug'] ?? '',
				'stack' => $f['fontFamily'] ?? '',
			];
		}, $families );
	}
	return [
		[ 'name' => 'System',    'slug' => 'system', 'stack' => "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif" ],
		[ 'name' => 'Monospace', 'slug' => 'mono',   'stack' => "'JetBrains Mono', ui-monospace, monospace" ],
		[ 'name' => 'Serif',    'slug' => 'serif',   'stack' => "'Instrument Serif', Georgia, serif" ],
	];
}

/**
 * Get font sizes from the feature registry, falling back to system defaults.
 */
function examplepress_settings_get_sizes() {
	$sizes = (array) examplepress_feature_option( 'theme-typography', 'font_sizes', [] );
	if ( ! empty( $sizes ) ) {
		return $sizes;
	}
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
 */
function examplepress_settings_get_blocks() {
	$blocks     = [];
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
 * Documentation links — read from examplepress.json docs section.
 */
function examplepress_settings_get_docs() {
	$config = examplepress_get_config();
	$docs   = $config['docs'] ?? [];

	if ( empty( $docs ) ) {
		return [];
	}

	return array_map( function ( $doc ) {
		$url   = $doc['url'] ?? '';
		$label = 'Read docs';

		if ( $url ) {
			$host = wp_parse_url( $url, PHP_URL_HOST ) ?? '';
			if ( $host && ! str_contains( $host, 'github.com' ) ) {
				$label = str_replace( 'www.', '', $host );
			}
		}

		return [
			'eyebrow' => $doc['category'] ?? '',
			'title'   => $doc['title'] ?? '',
			'desc'    => $doc['description'] ?? '',
			'link'    => $url,
			'label'   => $label,
		];
	}, $docs );
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
	$is_dev = defined( 'EP_DEV_MODE' ) && EP_DEV_MODE;
	?>
	<div class="ep-settings-wrapper">
		<div class="ep-settings">

			<?php if ( $is_dev ) : ?>
				<div class="ep-dev-banner">Developer Mode is active — all guards are bypassed. Remove <code>EP_DEV_MODE</code> from wp-config.php before deploying.</div>
			<?php endif; ?>

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
				<button class="ep-tab" role="tab" aria-selected="true"  aria-controls="p-features"      id="t-features">Features<span class="ep-tab-count"></span></button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-design"         id="t-design">Design</button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-build"          id="t-build">Build</button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-blocks"         id="t-blocks">Blocks<span class="ep-tab-count"></span></button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-dependencies"   id="t-dependencies">Dependencies<span class="ep-tab-count"></span></button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-notifications"  id="t-notifications">Notifications<span class="ep-tab-count"></span></button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-library"        id="t-library">Library</button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-config"         id="t-config">Config</button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-health"         id="t-health">Health</button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-docs"           id="t-docs">Docs</button>
			</nav>

			<!-- Features -->
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
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Design Tokens</span><div class="ep-section-line"></div></div>
					<div class="ep-table" id="tbl-design-features"></div>
				</section>
			</div>

			<!-- Design -->
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

			<!-- Build -->
			<div class="ep-panel" id="p-build" role="tabpanel" aria-hidden="true">
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Scaffold Companion App</span><div class="ep-section-line"></div></div>
					<p class="ep-section-desc">Create a new companion plugin repository on GitHub via Troy. Your app will be pre-configured with the ExamplePress foundation, a CI/CD pipeline, and optional staging auto-sync.</p>

					<!-- Build Form -->
					<div id="ep-build-form-wrap">
						<div class="ep-build-form">
							<!-- GitHub Authentication -->
							<div class="ep-build-field">
								<label class="ep-build-label" for="ep-github-token">GitHub Personal Access Token</label>
								<input type="password" class="ep-build-input" id="ep-github-token" placeholder="ghp_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" autocomplete="off" />
								<span class="ep-build-hint">Requires <code>repo</code> and <code>codespace</code> scopes. <a href="https://github.com/settings/tokens/new?scopes=repo,codespace&description=ExamplePress+Build" target="_blank" rel="noopener" class="ep-link">Generate token &rarr;</a></span>
							</div>

							<!-- App Configuration -->
							<div class="ep-build-row">
								<div class="ep-build-field ep-build-field-half">
									<label class="ep-build-label" for="ep-app-name">App Name</label>
									<input type="text" class="ep-build-input" id="ep-app-name" placeholder="Acme Corp Core" />
								</div>
								<div class="ep-build-field ep-build-field-half">
									<label class="ep-build-label" for="ep-app-slug">App Slug</label>
									<input type="text" class="ep-build-input" id="ep-app-slug" placeholder="acme-corp-core" />
								</div>
							</div>

							<div class="ep-build-row">
								<div class="ep-build-field ep-build-field-half">
									<label class="ep-build-label" for="ep-troy-server">Troy Server</label>
									<select class="ep-build-select" id="ep-troy-server">
										<option value="cloud">Troy Cloud (Hosted)</option>
										<option value="custom">Custom URL</option>
									</select>
								</div>
								<div class="ep-build-field ep-build-field-half" id="ep-custom-url-wrap" style="display:none">
									<label class="ep-build-label" for="ep-custom-url">Custom Server URL</label>
									<input type="url" class="ep-build-input" id="ep-custom-url" placeholder="https://troy.yourcompany.com" />
								</div>
							</div>

							<!-- Staging Toggle -->
							<div class="ep-build-field">
								<label class="ep-build-checkbox-label">
									<input type="checkbox" id="ep-staging-toggle" />
									<span>Connect Staging Environment (SFTP Auto-Sync on Save)</span>
								</label>
							</div>

							<!-- Staging SFTP Fields (hidden by default) -->
							<div id="ep-staging-fields" style="display:none">
								<div class="ep-build-row">
									<div class="ep-build-field ep-build-field-half">
										<label class="ep-build-label" for="ep-sftp-host">SFTP Host</label>
										<input type="text" class="ep-build-input" id="ep-sftp-host" placeholder="staging.example.com" />
									</div>
									<div class="ep-build-field ep-build-field-quarter">
										<label class="ep-build-label" for="ep-sftp-port">Port</label>
										<input type="number" class="ep-build-input" id="ep-sftp-port" value="22" />
									</div>
								</div>
								<div class="ep-build-row">
									<div class="ep-build-field ep-build-field-half">
										<label class="ep-build-label" for="ep-sftp-user">Username</label>
										<input type="text" class="ep-build-input" id="ep-sftp-user" placeholder="deploy" />
									</div>
									<div class="ep-build-field ep-build-field-half">
										<label class="ep-build-label" for="ep-sftp-pass">Password</label>
										<input type="password" class="ep-build-input" id="ep-sftp-pass" placeholder="••••••••" autocomplete="off" />
									</div>
								</div>
								<div class="ep-build-field">
									<label class="ep-build-label" for="ep-sftp-path">Remote Path</label>
									<input type="text" class="ep-build-input" id="ep-sftp-path" placeholder="/wp-content/plugins/acme-corp-core" />
									<span class="ep-build-hint">Auto-filled from App Slug if left empty.</span>
								</div>
							</div>

							<!-- Error display -->
							<div id="ep-build-error" class="ep-build-error" style="display:none"></div>

							<!-- Submit -->
							<button class="ep-build-submit" id="ep-build-submit">
								<span class="ep-build-submit-label">Scaffold &amp; Create Repo</span>
								<span class="ep-build-spinner" style="display:none"></span>
							</button>
						</div>
					</div>

					<!-- Success Card (hidden by default) -->
					<div id="ep-build-success" class="ep-build-success" style="display:none">
						<div class="ep-build-success-icon">&#10003;</div>
						<div class="ep-build-success-title">Repository Created</div>
						<p class="ep-build-success-msg" id="ep-build-success-msg"></p>
						<div class="ep-build-success-actions">
							<a id="ep-build-codespaces-link" class="ep-build-btn-primary" href="#" target="_blank" rel="noopener">Launch in Codespaces &#8599;</a>
							<a id="ep-build-repo-link" class="ep-build-btn-secondary" href="#" target="_blank" rel="noopener">View Repository &rarr;</a>
						</div>
						<button class="ep-build-reset" id="ep-build-reset">Create Another App</button>
					</div>

				</section>
			</div>

			<!-- Blocks -->
			<div class="ep-panel" id="p-blocks" role="tabpanel" aria-hidden="true">
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Registered Blocks</span><div class="ep-section-line"></div></div>
					<p class="ep-section-desc">All Blockstudio blocks discovered in the active theme and companion plugins. Template blocks are blocks the router can dispatch to.</p>
					<div class="ep-table" id="tbl-blocks"></div>
				</section>
			</div>

			<!-- Dependencies -->
			<div class="ep-panel" id="p-dependencies" role="tabpanel" aria-hidden="true">
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Dependency Directory</span><div class="ep-section-line"></div></div>
					<p class="ep-section-desc">Plugins, Composer packages, and libraries declared in examplepress.json. Detected via plugin registry, class_exists, or function_exists.</p>
					<div class="ep-table" id="tbl-dependencies"></div>
				</section>
			</div>

			<!-- Notifications -->
			<div class="ep-panel" id="p-notifications" role="tabpanel" aria-hidden="true">
				<section class="ep-section">
					<div class="ep-notif-subtabs" id="notif-subtabs">
						<button class="ep-notif-subtab active" data-target="notices-active">Active</button>
						<button class="ep-notif-subtab" data-target="notices-archived">Archived</button>
					</div>
					<div id="notices-active" class="ep-notif-list"></div>
					<div id="notices-archived" class="ep-notif-list" style="display:none"></div>
				</section>
			</div>

			<!-- Library -->
			<div class="ep-panel" id="p-library" role="tabpanel" aria-hidden="true">
				<section class="ep-section ep-library-hero">
					<div class="ep-library-icon">&#9783;</div>
					<div class="ep-doc-section-title">The Component Library is arriving soon.</div>
					<p class="ep-doc-section-desc" style="margin: 0 auto 2rem; text-align: center;">Browse, import, and build with Troy-delivered companion plugins. Perfectly structured components that claim their own routes and integrate with the ExamplePress engine.</p>
					<span class="ep-badge badge-info"><span class="ep-dot"></span>Coming in v1.1</span>
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

			<!-- Health -->
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

			<!-- Docs -->
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
