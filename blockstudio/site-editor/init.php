<?php
/**
 * Site Editor — Template lockdown
 *
 * This theme uses a router pattern where index.html is the only legitimate
 * block template. If a user creates a new template via the site editor,
 * WordPress will use it instead of the router for query matching, silently
 * breaking routing. These three layers prevent that while keeping Styles,
 * Navigation, Pages, and Patterns fully functional.
 */

/**
 * Redirect any site-editor template URL to the Styles panel.
 *
 * Catches direct navigation to site-editor.php?postType=wp_template* or
 * site-editor.php?p=%2Ftemplate* before the page renders.
 */
add_action(
	'current_screen',
	function ( WP_Screen $screen ) {
		if ( $screen->id !== 'site-editor' ) {
			return;
		}

		$post_type = $_GET['postType'] ?? '';
		$p         = urldecode( $_GET['p'] ?? '' );

		$is_template_url = str_starts_with( $post_type, 'wp_template' )
			|| str_starts_with( $p, '/template' );

		if ( $is_template_url ) {
			wp_safe_redirect( admin_url( 'site-editor.php?p=%2Fstyles' ) );
			exit;
		}
	}
);

/**
 * Redirect the "Edit Site" admin bar link to the Styles panel.
 *
 * The default URL opens the template editor, which we've locked down.
 * Pointing it at /styles gives editors a useful landing destination.
 */
add_action(
	'admin_bar_menu',
	function ( WP_Admin_Bar $bar ) {
		$node = $bar->get_node( 'site-editor' );
		if ( ! $node ) {
			return;
		}
		$node->href = admin_url( 'site-editor.php?p=%2Fstyles' );
		$bar->add_node( (array) $node );
	},
	100
);

/**
 * Layer 1: Block template creation and deletion via the REST API.
 *
 * Intercepts POST and DELETE requests to /wp/v2/templates before they're
 * dispatched. This is the most important layer — it prevents creation
 * regardless of whether the request comes from the editor UI, WP-CLI, or
 * a direct API call.
 */
add_filter(
	'rest_pre_dispatch',
	function ( $result, $_server, $request ) {
		if ( ! preg_match( '#^/wp/v2/templates(/|$)#', $request->get_route() ) ) {
			return $result;
		}

		$method = $request->get_method();

		if ( $method === 'POST' ) {
			return new WP_Error(
				'ep_template_creation_disabled',
				__( 'Template creation is disabled. This theme uses a router pattern — add a route instead.', 'examplepress' ),
				[ 'status' => 403 ]
			);
		}

		if ( $method === 'DELETE' ) {
			// Deleting the index template would break the site — block it.
			$id = $request->get_param( 'id' );
			if ( $id && str_contains( (string) $id, 'index' ) ) {
				return new WP_Error(
					'ep_template_delete_disabled',
					__( 'The index template cannot be deleted.', 'examplepress' ),
					[ 'status' => 403 ]
				);
			}
		}

		return $result;
	},
	10,
	3
);

/**
 * Layer 2: Filter out user-created templates at resolution time.
 *
 * Safety net: even if a custom template record exists in the database
 * (e.g. created before this code was added, or via WP-CLI), it will
 * never resolve for a request. Only theme-bundled templates are returned.
 */
add_filter(
	'get_block_templates',
	function ( $templates, $_query, $template_type ) {
		if ( $template_type !== 'wp_template' ) {
			return $templates;
		}

		return array_values(
			array_filter(
				$templates,
				fn( $template ) => $template->source !== 'custom'
			)
		);
	},
	10,
	3
);


/**
 * Filters the list of allowed block types in the block editor.
 *
 * This function restricts the available block types to Heading, List, Image, and Paragraph only.
 *
 * @param array|bool $allowed_block_types Array of block type slugs, or boolean to enable/disable all.
 * @param object     $block_editor_context The current block editor context.
 *
 * @return array The array of allowed block types.
 */
function examplepress_set_allowed_block_types( $allowed_block_types, $block_editor_context ) {

	$allowed_block_types = [];

	return apply_filters( 'examplepress_allowed_block_types', $allowed_block_types, $block_editor_context );
}
add_filter( 'allowed_block_types_all', 'examplepress_set_allowed_block_types', 10, 2 );