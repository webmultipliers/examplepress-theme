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
	/**
	 * Filter the Google Fonts URL used by the settings page.
	 *
	 * Return an empty string to disable external font loading entirely
	 * (the CSS falls back to system fonts). For GDPR-compliant
	 * deployments, return a self-hosted URL or false.
	 *
	 * @param string $url The Google Fonts stylesheet URL.
	 */
	$fonts_url = apply_filters(
		'examplepress_settings_fonts_url',
		'https://fonts.googleapis.com/css2?family=DM+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&family=Instrument+Serif:ital@0;1&family=JetBrains+Mono:wght@400;500;600&display=swap'
	);

	$font_deps = [];
	if ( $fonts_url ) {
		wp_enqueue_style( 'ep-settings-fonts', $fonts_url, [], null );
		$font_deps = [ 'ep-settings-fonts' ];
	}

	wp_enqueue_style(
		'ep-settings',
		EP_THEME_URI . '/assets/css/admin-settings.css',
		$font_deps,
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
		'navigation'     => examplepress_settings_get_navigation(),
		'adminTabs'      => examplepress_settings_get_admin_tabs(),
		'featureDetails' => examplepress_settings_get_feature_details(),
		'demo'           => [ 'status' => function_exists( 'examplepress_get_demo_status' ) ? examplepress_get_demo_status() : 'not-installed' ],
		'restUrl'        => esc_url_raw( rest_url( 'examplepress/v1/notifications/archive' ) ),
		'demoInstallUrl' => esc_url_raw( rest_url( 'examplepress/v1/demo/install' ) ),
		'demoUninstallUrl' => esc_url_raw( rest_url( 'examplepress/v1/demo/uninstall' ) ),
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

/**
 * Gather navigation menus and registered locations.
 */
function examplepress_settings_get_navigation() {
	$menus     = wp_get_nav_menus();
	$locations = get_nav_menu_locations();
	$registered = get_registered_nav_menus();

	$menu_data = [];
	foreach ( $menus as $menu ) {
		$items       = wp_get_nav_menu_items( $menu->term_id ) ?: [];
		$menu_locs   = [];
		foreach ( $locations as $loc_slug => $menu_id ) {
			if ( (int) $menu_id === (int) $menu->term_id && isset( $registered[ $loc_slug ] ) ) {
				$menu_locs[] = $registered[ $loc_slug ];
			}
		}
		$flat_items = [];
		foreach ( $items as $item ) {
			$flat_items[] = [
				'id'     => (int) $item->ID,
				'title'  => $item->title,
				'url'    => $item->url,
				'type'   => $item->type,
				'parent' => (int) $item->menu_item_parent,
			];
		}
		$menu_data[] = [
			'id'        => (int) $menu->term_id,
			'name'      => $menu->name,
			'slug'      => $menu->slug,
			'count'     => count( $items ),
			'locations' => $menu_locs,
			'items'     => $flat_items,
		];
	}

	$location_data = [];
	foreach ( $registered as $slug => $name ) {
		$assigned_menu = '';
		if ( isset( $locations[ $slug ] ) && $locations[ $slug ] ) {
			foreach ( $menus as $menu ) {
				if ( (int) $menu->term_id === (int) $locations[ $slug ] ) {
					$assigned_menu = $menu->name;
					break;
				}
			}
		}
		$location_data[] = [
			'slug'     => $slug,
			'name'     => $name,
			'assigned' => $assigned_menu,
		];
	}

	return [
		'menus'     => $menu_data,
		'locations' => $location_data,
	];
}

/**
 * Get hidden admin tabs from config.
 */
function examplepress_settings_get_admin_tabs() {
	$config = examplepress_get_config();
	$hidden = $config['admin_tabs']['hidden'] ?? [];
	$hidden = (array) apply_filters( 'examplepress_admin_tabs_hidden', $hidden );
	return [ 'hidden' => array_values( array_unique( $hidden ) ) ];
}

/**
 * Static feature detail descriptions, technical notes, and override examples.
 */
function examplepress_settings_get_feature_details() {
	$details = [
		'title-tag' => [
			'description' => 'Adds the document title tag to the HTML head, letting WordPress manage the page title dynamically.',
			'technical'   => 'Calls add_theme_support(\'title-tag\') during after_setup_theme.',
			'override'    => "add_filter( 'examplepress_feature_title-tag', '__return_false' );",
		],
		'responsive-embeds' => [
			'description' => 'Enables responsive wrappers around oEmbed content so videos and iframes scale correctly on all screen sizes.',
			'technical'   => 'Calls add_theme_support(\'responsive-embeds\') during after_setup_theme.',
			'override'    => "add_filter( 'examplepress_feature_responsive-embeds', '__return_false' );",
		],
		'post-thumbnails' => [
			'description' => 'Enables featured image support for posts and pages.',
			'technical'   => 'Calls add_theme_support(\'post-thumbnails\') during after_setup_theme.',
			'override'    => "add_filter( 'examplepress_feature_post-thumbnails', '__return_false' );",
		],
		'wp-block-styles' => [
			'description' => 'Loads the default block stylesheets provided by WordPress core.',
			'technical'   => 'Calls add_theme_support(\'wp-block-styles\') during after_setup_theme.',
			'override'    => "add_filter( 'examplepress_feature_wp-block-styles', '__return_false' );",
		],
		'html5' => [
			'description' => 'Outputs semantic HTML5 markup for search forms, comment forms, comment lists, gallery, and caption elements.',
			'technical'   => 'Calls add_theme_support(\'html5\', [...]) with the configured feature list during after_setup_theme.',
			'override'    => "add_filter( 'examplepress_feature_html5_features', function () {\n    return [ 'search-form', 'comment-form' ];\n} );",
		],
		'disable-remote-block-patterns' => [
			'description' => 'Prevents WordPress from fetching block patterns from the remote pattern directory, keeping the inserter focused on project patterns.',
			'technical'   => 'Calls remove_theme_support(\'core-block-patterns\') and sets the should_load_remote_block_patterns option to false.',
			'override'    => "add_filter( 'examplepress_feature_disable-remote-block-patterns', '__return_false' );",
		],
		'disable-core-block-patterns' => [
			'description' => 'Removes the core block patterns bundled with WordPress, leaving only custom-registered patterns.',
			'technical'   => 'Calls remove_theme_support(\'core-block-patterns\') during after_setup_theme.',
			'override'    => "add_filter( 'examplepress_feature_disable-core-block-patterns', '__return_false' );",
		],
		'restrict-block-types' => [
			'description' => 'Limits which block types are available in the editor to a defined allowlist. Opt-in — disabled by default.',
			'technical'   => 'Hooks allowed_block_types_all and returns only the configured types array. Falls back to the examplepress_allowed_block_types filter.',
			'override'    => "add_filter( 'examplepress_feature_restrict-block-types', '__return_true' );\nadd_filter( 'examplepress_feature_restrict-block-types_types', function () {\n    return [ 'core/paragraph', 'core/heading', 'core/image' ];\n} );",
		],
		'disable-redirect-guess-404' => [
			'description' => 'Stops WordPress from guessing a redirect URL when a 404 occurs, preventing unexpected redirects to unrelated content.',
			'technical'   => 'Hooks do_redirect_guess_404_permalink and returns false.',
			'override'    => "add_filter( 'examplepress_feature_disable-redirect-guess-404', '__return_false' );",
		],
		'openverse' => [
			'description' => 'Controls visibility of the Openverse free media library in the block editor media inserter.',
			'technical'   => 'Hooks the block_editor_settings_all filter and sets enableOpenverseMediaCategory.',
			'override'    => "add_filter( 'examplepress_feature_openverse', '__return_true' );",
		],
		'post-lock-window' => [
			'description' => 'Sets the duration (in seconds) for the post lock heartbeat interval, controlling how often the editor checks for concurrent editing.',
			'technical'   => 'Hooks wp_check_post_lock_window and returns the configured duration value.',
			'override'    => "add_filter( 'examplepress_feature_post-lock-window_duration', function () {\n    return 300;\n} );",
		],
		'remove-dashboard-widgets' => [
			'description' => 'Removes the default WordPress dashboard widgets (Quick Draft, Activity, Events & News, Site Health) for a cleaner admin experience.',
			'technical'   => 'Hooks wp_dashboard_setup and calls remove_meta_box for each default widget.',
			'override'    => "add_filter( 'examplepress_feature_remove-dashboard-widgets', '__return_false' );",
		],
		'login-branding' => [
			'description' => 'Applies custom CSS to the WordPress login page, replacing the default WordPress logo with theme branding.',
			'technical'   => 'Hooks login_enqueue_scripts to inject custom styles on the login page.',
			'override'    => "add_filter( 'examplepress_feature_login-branding', '__return_false' );",
		],
		'permalink-structure' => [
			'description' => 'Enforces a specific permalink structure at runtime, overriding the database setting. Default: /%postname%/.',
			'technical'   => 'Hooks pre_option_permalink_structure to short-circuit the database lookup and return the configured value.',
			'override'    => "add_filter( 'examplepress_feature_permalink-structure_structure', function () {\n    return '/%category%/%postname%/';\n} );",
		],
		'managed-options' => [
			'description' => 'Overrides wp_options values at runtime without modifying the database. Useful for enforcing settings across environments.',
			'technical'   => 'Hooks pre_option_{option_name} for each managed option to short-circuit the database lookup.',
			'override'    => "// Via examplepress.json:\n// \"managed-options\": { \"enabled\": true, \"options\": { \"values\": { \"blogdescription\": \"My Site\" } } }\n\n// Via PHP filter:\nadd_filter( 'examplepress_feature_managed-options_values', function ( \$values ) {\n    \$values['blogdescription'] = 'My Site';\n    return \$values;\n} );",
		],
		'theme-colors' => [
			'description' => 'Injects a colour palette into theme.json at runtime. Prefer using the design.colors shorthand in examplepress.json.',
			'technical'   => 'Hooks wp_theme_json_data_theme and merges the palette array into settings.color.palette.',
			'override'    => "add_filter( 'examplepress_feature_theme-colors', '__return_false' );",
		],
		'theme-layout' => [
			'description' => 'Injects global layout dimensions (wideSize, contentSize) into theme.json at runtime.',
			'technical'   => 'Hooks wp_theme_json_data_theme and merges layout values into settings.layout.',
			'override'    => "add_filter( 'examplepress_feature_theme-layout_wide_size', function () {\n    return '1400px';\n} );",
		],
		'theme-typography' => [
			'description' => 'Injects font families and size presets into theme.json at runtime.',
			'technical'   => 'Hooks wp_theme_json_data_theme and merges typography arrays into settings.typography.',
			'override'    => "add_filter( 'examplepress_feature_theme-typography', '__return_false' );",
		],
		'design-strict' => [
			'description' => 'Locks down all appearance tools in the block editor — disables custom colours, font sizes, spacing, and other visual controls.',
			'technical'   => 'Hooks wp_theme_json_data_theme and forces appearanceTools to false, disabling all editor appearance panels.',
			'override'    => "// Enable via examplepress.json: \"design\": { \"strict\": true }\nadd_filter( 'examplepress_feature_design-strict', '__return_true' );",
		],
		'guard-template-redirect' => [
			'description' => 'Redirects site editor template URLs to the Styles panel, preventing users from accessing the template editor directly.',
			'technical'   => 'Hooks current_screen to detect template URLs and wp_safe_redirect to the Styles panel. Also overrides the admin bar "Edit Site" link.',
			'override'    => "add_filter( 'examplepress_feature_guard-template-redirect', '__return_false' );",
		],
		'guard-template-rest' => [
			'description' => 'Blocks template creation via POST and prevents deletion of the index template via the REST API. The most critical guard layer.',
			'technical'   => 'Hooks rest_pre_dispatch and intercepts POST/DELETE requests to /wp/v2/templates. Returns WP_Error for blocked operations.',
			'override'    => "add_filter( 'examplepress_feature_guard-template-rest', '__return_false' );",
		],
		'guard-template-resolution' => [
			'description' => 'Filters out user-created (custom source) templates at resolution time, ensuring only theme-defined templates are used.',
			'technical'   => 'Hooks get_block_templates and removes templates where source === "custom". Safety net for pre-existing database templates.',
			'override'    => "add_filter( 'examplepress_feature_guard-template-resolution', '__return_false' );",
		],
	];

	return (array) apply_filters( 'examplepress_feature_details', $details );
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

			<div class="ep-layout">
			<nav class="ep-tabs" role="tablist">
				<button class="ep-tab" role="tab" aria-selected="true"  aria-controls="p-overview"       id="t-overview"      data-tab-id="overview">Overview</button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-features"       id="t-features"      data-tab-id="features">Features<span class="ep-tab-count"></span></button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-config"         id="t-config"        data-tab-id="config">Config</button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-design"         id="t-design"        data-tab-id="design">Design</button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-build"          id="t-build"         data-tab-id="build">Build</button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-blocks"         id="t-blocks"        data-tab-id="blocks">Blocks<span class="ep-tab-count"></span></button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-library"        id="t-library"       data-tab-id="library">Library</button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-navigation"     id="t-navigation"    data-tab-id="navigation">Navigation<span class="ep-tab-count"></span></button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-dependencies"   id="t-dependencies"  data-tab-id="dependencies">Dependencies<span class="ep-tab-count"></span></button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-notifications"  id="t-notifications" data-tab-id="notifications">Notifications<span class="ep-tab-count"></span></button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-health"         id="t-health"        data-tab-id="health">Health</button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-docs"           id="t-docs"          data-tab-id="docs">Docs</button>
				<button class="ep-tab" role="tab" aria-selected="false" aria-controls="p-support"        id="t-support"       data-tab-id="support">Support</button>
			</nav>

			<div class="ep-panels">

			<!-- Overview -->
			<div class="ep-panel" id="p-overview" role="tabpanel" aria-hidden="false">
				<section class="ep-section">
					<div class="ep-doc-section-title">Welcome to ExamplePress</div>
					<p class="ep-section-desc" style="max-width:none">ExamplePress is the Full Site Editing theme layer for <strong>Blockstudio</strong>. It provides a template router, guard system, and a feature registry &mdash; everything else is built in your companion plugin.</p>
				</section>
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">What Each Tab Does</span><div class="ep-section-line"></div></div>
					<div class="ep-overview-grid">
						<div class="ep-overview-card">
							<div class="ep-overview-card-title">Features</div>
							<p class="ep-overview-card-desc">View every registered feature flag &mdash; theme support, editor controls, guards, admin tweaks, and design tokens. All values are resolved from examplepress.json and PHP filters.</p>
						</div>
						<div class="ep-overview-card">
							<div class="ep-overview-card-title">Config</div>
							<p class="ep-overview-card-desc">Explore the raw configuration files (examplepress.json, theme.json, blockstudio.json) that drive the theme. Read-only &mdash; edit the files directly in your project.</p>
						</div>
						<div class="ep-overview-card">
							<div class="ep-overview-card-title">Design</div>
							<p class="ep-overview-card-desc">Preview the resolved design tokens: colour palette, layout dimensions, font families, and size scale. All injected into theme.json at runtime.</p>
						</div>
						<div class="ep-overview-card">
							<div class="ep-overview-card-title">Build</div>
							<p class="ep-overview-card-desc">Scaffold a new companion plugin repository via Troy. Pre-configured with CI/CD, optional staging sync, and ready to launch in GitHub Codespaces.</p>
						</div>
						<div class="ep-overview-card">
							<div class="ep-overview-card-title">Blocks</div>
							<p class="ep-overview-card-desc">All Blockstudio blocks discovered in the active theme and companion plugins, grouped by namespace. Template blocks are dispatchable by the router.</p>
						</div>
						<div class="ep-overview-card">
							<div class="ep-overview-card-title">Library</div>
							<p class="ep-overview-card-desc">Browse and import Troy-delivered companion plugins. Perfectly structured components that claim their own routes. Coming soon.</p>
						</div>
						<div class="ep-overview-card">
							<div class="ep-overview-card-title">Navigation</div>
							<p class="ep-overview-card-desc">Review registered menu locations and assigned menus. ExamplePress uses the native WordPress menu system.</p>
						</div>
						<div class="ep-overview-card">
							<div class="ep-overview-card-title">Dependencies</div>
							<p class="ep-overview-card-desc">Plugins, Composer packages, and libraries declared in examplepress.json. Status detected via plugin registry, class_exists, or function_exists.</p>
						</div>
						<div class="ep-overview-card">
							<div class="ep-overview-card-title">Notifications</div>
							<p class="ep-overview-card-desc">Theme-generated notices &mdash; errors, warnings, and informational messages. Archive notifications to keep the dashboard tidy.</p>
						</div>
						<div class="ep-overview-card">
							<div class="ep-overview-card-title">Health</div>
							<p class="ep-overview-card-desc">Environment checks, theme integrity, router health, and security guard status. A quick snapshot of whether everything is running correctly.</p>
						</div>
						<div class="ep-overview-card">
							<div class="ep-overview-card-title">Docs</div>
							<p class="ep-overview-card-desc">Getting started guides and a complete filter &amp; action reference. Every hook the theme exposes for companion plugins.</p>
						</div>
						<div class="ep-overview-card">
							<div class="ep-overview-card-title">Support</div>
							<p class="ep-overview-card-desc">Links to Blockstudio Plus, the GitHub repository, and contact information for enterprise deployments.</p>
						</div>
					</div>
				</section>
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Quick Start</span><div class="ep-section-line"></div></div>
					<p class="ep-section-desc" style="max-width:none">ExamplePress works by routing every front-end request through a single <code>index.html</code> template that contains the router block. Your companion plugin claims a namespace, defines a routing cascade, and registers Blockstudio template blocks. The theme handles resolution, guards, and design tokens &mdash; you focus on building.</p>
					<p class="ep-section-desc" style="max-width:none">Head to the <strong>Build</strong> tab to scaffold your first companion plugin, or explore the <strong>Features</strong> tab to see what&rsquo;s already configured.</p>
				</section>
			</div>

			<!-- Features -->
			<div class="ep-panel" id="p-features" role="tabpanel" aria-hidden="true">
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
					<p class="ep-section-desc">Launch the Troy scaffolding service to create a new companion plugin repository. All configuration — naming, GitHub setup, and deployment options — is handled on the Troy side.</p>
					<button class="ep-build-submit" id="ep-build-open-modal">
						<span class="ep-build-submit-label">Create App</span>
					</button>
				</section>

				<section class="ep-section" id="ep-demo-section">
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

			<!-- Blocks -->
			<div class="ep-panel" id="p-blocks" role="tabpanel" aria-hidden="true">
				<section class="ep-section">
					<div class="ep-section-header"><span class="ep-section-title">Registered Blocks</span><div class="ep-section-line"></div></div>
					<p class="ep-section-desc">All Blockstudio blocks discovered in the active theme and companion plugins, grouped by namespace. Template blocks are blocks the router can dispatch to.</p>
					<div class="ep-table" id="tbl-blocks"></div>
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

			<!-- Support -->
			<div class="ep-panel" id="p-support" role="tabpanel" aria-hidden="true">
				<section class="ep-section">
					<div class="ep-doc-section-title">Support &amp; Resources</div>
					<p class="ep-doc-section-desc">Get help, contribute, and connect with the ExamplePress ecosystem.</p>
					<div class="ep-support-grid">
						<a class="ep-support-card" href="https://blockstudio.dev/plus/" target="_blank" rel="noopener">
							<div class="ep-support-card-eyebrow">Framework</div>
							<div class="ep-support-card-title">Blockstudio Plus</div>
							<div class="ep-support-card-desc">The premium tier of the block framework ExamplePress is built on. Priority support, advanced fields, and early access to new features.</div>
							<div class="ep-support-card-link">blockstudio.dev &rarr;</div>
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

		<!-- Generic Modal (shared by features, hooks, blocks, dependencies) -->
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

		<!-- Build Modal -->
		<div class="ep-modal-overlay" id="ep-build-modal" style="display:none">
			<div class="ep-modal" style="max-width:520px">
				<div class="ep-modal-header">
					<div>
						<span class="ep-modal-title">Scaffold Companion App</span>
						<span class="ep-modal-id">Choose where to create your app</span>
					</div>
					<button class="ep-modal-close" id="ep-build-modal-close">&times;</button>
				</div>
				<div class="ep-modal-body">
					<p class="ep-build-handoff-desc">Select a Troy server to handle the scaffolding. You'll complete setup — including naming, configuration, and repository creation — on the Troy side.</p>

					<div class="ep-build-options">
						<a class="ep-build-option" id="ep-build-option-cloud" href="https://cloud.troy.dev/scaffold" target="_blank" rel="noopener">
							<div class="ep-build-option-icon">&#9729;</div>
							<div class="ep-build-option-content">
								<div class="ep-build-option-title">Troy Cloud</div>
								<div class="ep-build-option-desc">Hosted scaffolding service. No setup required &mdash; authenticate with GitHub and create your companion plugin in seconds.</div>
							</div>
							<span class="ep-build-option-arrow">&rarr;</span>
						</a>

						<div class="ep-build-option ep-build-option-custom" id="ep-build-option-custom">
							<div class="ep-build-option-icon">&#9881;</div>
							<div class="ep-build-option-content">
								<div class="ep-build-option-title">Custom Server</div>
								<div class="ep-build-option-desc">Self-hosted Troy instance for teams with private infrastructure or custom scaffolding requirements.</div>
							</div>
							<span class="ep-build-option-arrow">&rarr;</span>
						</div>
					</div>

					<!-- Custom Server URL (shown when Custom Server is selected) -->
					<div id="ep-build-custom-wrap" class="ep-build-custom-wrap" style="display:none">
						<div class="ep-build-field">
							<label class="ep-build-label" for="ep-custom-server-url">Server URL</label>
							<input type="url" class="ep-build-input" id="ep-custom-server-url" placeholder="https://troy.yourcompany.com" />
							<span class="ep-build-hint">Must use HTTPS. You'll complete setup on your Troy server.</span>
						</div>
						<div id="ep-build-error" class="ep-build-error" style="display:none"></div>
						<a class="ep-build-submit" id="ep-build-custom-go" href="#" target="_blank" rel="noopener">
							<span class="ep-build-submit-label">Continue to Server &rarr;</span>
						</a>
					</div>
				</div>
			</div>
		</div>
	</div>
	<?php
}
