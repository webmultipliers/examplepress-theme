<?php
/**
 * ExamplePress Admin — Editor Page
 *
 * In-browser code editor for companion plugins using Monaco.
 * Hidden from the sidebar menu — accessed via direct URL from the Apps table.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Editor page data payload.
 *
 * @param string $slug App slug from query string.
 */
function examplepress_editor_data( string $slug ): array {
	$base = [
		'themeVersion' => EP_THEME_VERSION,
		'devMode'      => defined( 'EP_DEV_MODE' ) && EP_DEV_MODE,
		'page'         => 'editor',
		'nonce'        => wp_create_nonce( 'wp_rest' ),
		'appsUrl'      => examplepress_admin_page_url( 'apps' ),
	];

	$plugin_dir = WP_PLUGIN_DIR . '/' . $slug;

	if ( ! $slug || ! is_dir( $plugin_dir ) ) {
		return array_merge( $base, [
			'valid' => false,
			'slug'  => $slug,
		] );
	}

	// Read app name from examplepress.json if available.
	$json_path = $plugin_dir . '/examplepress.json';
	$app_name  = $slug;
	$app_json  = [];

	if ( file_exists( $json_path ) ) {
		$app_json = json_decode( file_get_contents( $json_path ), true ) ?: []; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$app_name = $app_json['name'] ?? $slug;
	}

	// Get GitHub data from registry.
	$record = function_exists( 'examplepress_registry_get' ) ? examplepress_registry_get( $slug ) : null;
	$github = $record['github'] ?? [];

	return array_merge( $base, [
		'valid'     => true,
		'slug'      => $slug,
		'appName'   => $app_name,
		'fsTreeUrl' => esc_url_raw( rest_url( "examplepress/v1/fs/{$slug}/tree" ) ),
		'fsFileUrl' => esc_url_raw( rest_url( "examplepress/v1/fs/{$slug}/file" ) ),
		'github'    => $github,
	] );
}

/**
 * Editor page render.
 */
function examplepress_render_editor_page(): void {
	$slug       = sanitize_title( $_GET['app'] ?? '' );
	$plugin_dir = WP_PLUGIN_DIR . '/' . $slug;
	$valid      = $slug && is_dir( $plugin_dir );
	$apps_url   = examplepress_admin_page_url( 'apps' );
	?>
	<div class="ep-editor-wrapper" style="display:flex;flex-direction:column;height:calc(100vh - 32px);background:#fff;">

		<?php if ( ! $valid ) : ?>
			<div style="padding:40px;text-align:center;">
				<h2>App not found</h2>
				<p>The plugin directory <code><?php echo esc_html( $slug ?: '(empty)' ); ?></code> does not exist.</p>
				<a href="<?php echo esc_url( $apps_url ); ?>" class="button button-primary">&larr; Back to Apps</a>
			</div>
		<?php else : ?>
			<!-- Toolbar -->
			<div class="ep-editor-toolbar" style="display:flex;align-items:center;gap:12px;padding:6px 16px;border-bottom:1px solid #c3c4c7;flex-shrink:0;background:#f0f0f1;">
				<a href="<?php echo esc_url( $apps_url ); ?>" style="text-decoration:none;color:#2271b1;font-size:13px;">&larr; Apps</a>
				<span id="ep-editor-app-name" style="font-weight:600;font-size:13px;"></span>
				<span id="ep-editor-file-path" style="color:#646970;font-size:12px;font-family:monospace;"></span>
				<span style="flex:1;"></span>
				<span id="ep-editor-status" style="font-size:12px;color:#646970;"></span>
				<button id="ep-editor-save" class="button button-primary" disabled style="font-size:12px;padding:2px 12px;">Save</button>
			</div>
			<!-- Body: tree + editor -->
			<div style="display:flex;flex:1;overflow:hidden;">
				<div id="ep-editor-tree" style="width:240px;border-right:1px solid #c3c4c7;overflow-y:auto;font-size:12px;background:#f6f7f7;padding:8px 0;"></div>
				<div id="ep-editor-container" style="flex:1;overflow:hidden;"></div>
			</div>
		<?php endif; ?>

	</div>
	<?php
}
