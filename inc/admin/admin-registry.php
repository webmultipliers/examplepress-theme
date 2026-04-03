<?php
/**
 * ExamplePress Admin Page Registry
 *
 * Declarative registry for admin submenus — same pattern as the feature
 * registry and route origin registry. The theme registers its own pages,
 * companion plugins inject additional submenus via the
 * `examplepress_register_admin_pages` action.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Parent menu slug. All submenus are children of this slug.
 */
define( 'EP_ADMIN_MENU_SLUG', 'examplepress' );

/**
 * Global page store.
 *
 * @var array<string, array>
 */
$examplepress_admin_pages = [];

// ── Public API ──────────────────────────────────────────────────

/**
 * Register an admin page.
 *
 * @param string $id   Unique page identifier.
 * @param array  $args {
 *     @type string   $page_title  Browser tab title (required).
 *     @type string   $menu_title  Sidebar label (required).
 *     @type string   $capability  Required capability. Default 'manage_options'.
 *     @type int      $position    Sort order. Lower = higher. Default 50.
 *     @type callable $render      Render callback (required).
 *     @type callable $enqueue     Fires on admin_enqueue_scripts when page is active.
 *     @type bool     $hidden      If true, page is accessible but not in sidebar.
 * }
 */
function examplepress_register_admin_page( string $id, array $args ): void {
	global $examplepress_admin_pages;

	if ( isset( $examplepress_admin_pages[ $id ] ) ) {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf( 'Admin page "%s" is already registered.', esc_html( $id ) ),
			EP_THEME_VERSION
		);
		return;
	}

	$examplepress_admin_pages[ $id ] = wp_parse_args( $args, [
		'page_title' => '',
		'menu_title' => '',
		'capability' => 'manage_options',
		'position'   => 50,
		'render'     => '__return_empty_string',
		'enqueue'    => null,
		'hidden'     => false,
	] );
}

/**
 * Get all registered pages sorted by position.
 *
 * @return array<string, array>
 */
function examplepress_get_admin_pages(): array {
	global $examplepress_admin_pages;

	$pages = $examplepress_admin_pages;

	/**
	 * Filter the admin page registry before menu building.
	 *
	 * @param array $pages Registered pages keyed by ID.
	 */
	$pages = apply_filters( 'examplepress_admin_pages', $pages );

	uasort( $pages, fn( $a, $b ) => ( $a['position'] ?? 50 ) <=> ( $b['position'] ?? 50 ) );

	return $pages;
}

/**
 * Get the WordPress menu slug for a page ID.
 *
 * The first page (lowest position) shares the parent slug to avoid
 * WordPress's duplicate first-submenu behaviour.
 *
 * @param string $id Page ID.
 * @return string Menu slug.
 */
function examplepress_admin_page_slug( string $id ): string {
	$pages = examplepress_get_admin_pages();
	$first = array_key_first( $pages );

	if ( $id === $first ) {
		return EP_ADMIN_MENU_SLUG;
	}

	return EP_ADMIN_MENU_SLUG . '-' . $id;
}

/**
 * Get the full admin URL for a page.
 *
 * @param string $id   Page ID.
 * @param array  $args Optional query args.
 * @return string Admin URL.
 */
function examplepress_admin_page_url( string $id, array $args = [] ): string {
	$slug = examplepress_admin_page_slug( $id );
	$url  = admin_url( 'admin.php?page=' . $slug );

	if ( $args ) {
		$url = add_query_arg( $args, $url );
	}

	return $url;
}

// ── Menu Builder ────────────────────────────────────────────────

add_action( 'admin_menu', 'examplepress_build_admin_menu' );

function examplepress_build_admin_menu(): void {
	// 1. Register the theme's own pages.
	examplepress_register_core_admin_pages();

	// 2. Let companion plugins add their pages.
	do_action( 'examplepress_register_admin_pages' );

	// 3. Build the WordPress menu.
	$pages = examplepress_get_admin_pages();

	if ( empty( $pages ) ) {
		return;
	}

	$first_id = array_key_first( $pages );
	$first    = $pages[ $first_id ];

	// Create the top-level menu from the first page.
	$parent_hook = add_menu_page(
		$first['page_title'],
		'ExamplePress',
		$first['capability'],
		EP_ADMIN_MENU_SLUG,
		$first['render'],
		'dashicons-layout',
		60
	);

	// Register enqueue for the first page.
	if ( $first['enqueue'] ) {
		$enqueue_cb = $first['enqueue'];
		add_action( 'admin_enqueue_scripts', function ( $hook_suffix ) use ( $parent_hook, $enqueue_cb ) {
			if ( $hook_suffix === $parent_hook ) {
				$enqueue_cb();
			}
		} );
	}

	// Register all pages as submenus (including the first, so it appears correctly).
	foreach ( $pages as $id => $page ) {
		$menu_slug = examplepress_admin_page_slug( $id );

		// The first page is already registered as the parent — just add the submenu label.
		$parent = $page['hidden'] ? null : EP_ADMIN_MENU_SLUG;

		$hook = add_submenu_page(
			$parent,
			$page['page_title'],
			$page['menu_title'],
			$page['capability'],
			$menu_slug,
			$page['render'],
			$page['position']
		);

		// Register enqueue callback (skip first — already done above).
		if ( $id !== $first_id && $page['enqueue'] ) {
			$enqueue_cb = $page['enqueue'];
			add_action( 'admin_enqueue_scripts', function ( $hook_suffix ) use ( $hook, $enqueue_cb ) {
				if ( $hook_suffix === $hook ) {
					$enqueue_cb();
				}
			} );
		}
	}
}

// ── Core Page Registration ──────────────────────────────────────

function examplepress_register_core_admin_pages(): void {
	examplepress_register_admin_page( 'dashboard', [
		'page_title' => __( 'ExamplePress — Dashboard', 'examplepress-theme' ),
		'menu_title' => __( 'Dashboard', 'examplepress-theme' ),
		'position'   => 0,
		'render'     => 'examplepress_render_dashboard_page',
		'enqueue'    => function () {
			examplepress_vite_enqueue( 'dashboard' );
			wp_add_inline_script(
				'ep-dashboard',
				'window.ExamplePressData = ' . wp_json_encode( examplepress_dashboard_data() ) . ';',
				'before'
			);
		},
	] );

	examplepress_register_admin_page( 'apps', [
		'page_title' => __( 'ExamplePress — Apps', 'examplepress-theme' ),
		'menu_title' => __( 'Apps', 'examplepress-theme' ),
		'position'   => 10,
		'render'     => 'examplepress_render_apps_page',
		'enqueue'    => function () {
			examplepress_vite_enqueue( 'build' );
			wp_add_inline_script(
				'ep-build',
				'window.ExamplePressData = ' . wp_json_encode( examplepress_apps_data() ) . ';',
				'before'
			);
		},
	] );

	examplepress_register_admin_page( 'theme', [
		'page_title' => __( 'ExamplePress — Theme', 'examplepress-theme' ),
		'menu_title' => __( 'Theme', 'examplepress-theme' ),
		'position'   => 20,
		'render'     => 'examplepress_render_theme_page',
		'enqueue'    => function () {
			examplepress_vite_enqueue( 'theme' );
			wp_add_inline_script(
				'ep-theme',
				'window.ExamplePressData = ' . wp_json_encode( examplepress_theme_data() ) . ';',
				'before'
			);
		},
	] );

	examplepress_register_admin_page( 'routing', [
		'page_title' => __( 'ExamplePress — Routing', 'examplepress-theme' ),
		'menu_title' => __( 'Routing', 'examplepress-theme' ),
		'position'   => 30,
		'render'     => 'examplepress_render_routing_page',
		'enqueue'    => function () {
			examplepress_vite_enqueue( 'routing' );
			wp_add_inline_script(
				'ep-routing',
				'window.ExamplePressData = ' . wp_json_encode( examplepress_routing_data() ) . ';',
				'before'
			);
		},
	] );

	examplepress_register_admin_page( 'reference', [
		'page_title' => __( 'ExamplePress — Reference', 'examplepress-theme' ),
		'menu_title' => __( 'Reference', 'examplepress-theme' ),
		'position'   => 40,
		'render'     => 'examplepress_render_reference_page',
		'enqueue'    => function () {
			examplepress_vite_enqueue( 'reference' );
			wp_add_inline_script(
				'ep-reference',
				'window.ExamplePressData = ' . wp_json_encode( examplepress_reference_data() ) . ';',
				'before'
			);
		},
	] );

	examplepress_register_admin_page( 'system', [
		'page_title' => __( 'ExamplePress — System', 'examplepress-theme' ),
		'menu_title' => __( 'System', 'examplepress-theme' ),
		'position'   => 50,
		'render'     => 'examplepress_render_system_page',
		'enqueue'    => function () {
			examplepress_vite_enqueue( 'system' );
			wp_add_inline_script(
				'ep-system',
				'window.ExamplePressData = ' . wp_json_encode( examplepress_system_data() ) . ';',
				'before'
			);
		},
	] );

	examplepress_register_admin_page( 'editor', [
		'page_title' => __( 'ExamplePress — Editor', 'examplepress-theme' ),
		'menu_title' => __( 'Editor', 'examplepress-theme' ),
		'position'   => 999,
		'hidden'     => true,
		'render'     => 'examplepress_render_editor_page',
		'enqueue'    => function () {
			$slug = sanitize_title( $_GET['app'] ?? '' );
			examplepress_vite_enqueue( 'editor' );
			wp_add_inline_script(
				'ep-editor',
				'window.ExamplePressData = ' . wp_json_encode( examplepress_editor_data( $slug ) ) . ';',
				'before'
			);
		},
	] );
}
