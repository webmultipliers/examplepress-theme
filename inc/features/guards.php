<?php
/**
 * Site Editor Guard Features
 *
 * Three-layer template lockdown preventing users from creating
 * templates that would bypass the router. Each guard is a toggleable
 * feature so companion plugins can selectively disable them during
 * migrations or specific workflows.
 *
 * The JS/CSS layers remain in blockstudio/site-editor/ as block assets.
 */

examplepress_register_feature( 'guard-template-redirect', [
	'label'   => 'Site Editor Template Redirect',
	'default' => true,
	'setup'   => function ( $id ) {
		if ( ! examplepress_feature_enabled( $id ) ) {
			return;
		}

		// Redirect site-editor template URLs to the Styles panel.
		add_action( 'current_screen', function ( WP_Screen $screen ) {
			if ( $screen->id !== 'site-editor' ) {
				return;
			}

			$post_type = sanitize_text_field( wp_unslash( $_GET['postType'] ?? '' ) );
			$p         = sanitize_text_field( wp_unslash( $_GET['p'] ?? '' ) );

			$is_template_url = str_starts_with( $post_type, 'wp_template' )
				|| str_starts_with( $p, '/template' );

			if ( $is_template_url ) {
				wp_safe_redirect( admin_url( 'site-editor.php?p=%2Fstyles' ) );
				exit;
			}
		} );

		// Point the "Edit Site" admin bar link at Styles instead of the template editor.
		add_action( 'admin_bar_menu', function ( WP_Admin_Bar $bar ) {
			$node = $bar->get_node( 'site-editor' );
			if ( ! $node ) {
				return;
			}
			$node->href = admin_url( 'site-editor.php?p=%2Fstyles' );
			$bar->add_node( (array) $node );
		}, 100 );
	},
] );

examplepress_register_feature( 'guard-template-rest', [
	'label'   => 'Block Template REST API Guard',
	'default' => true,
	'setup'   => function ( $id ) {
		if ( ! examplepress_feature_enabled( $id ) ) {
			return;
		}

		add_filter( 'rest_pre_dispatch', function ( $result, $_server, $request ) {
			if ( ! preg_match( '#^/wp/v2/templates(/|$)#', $request->get_route() ) ) {
				return $result;
			}

			$method = $request->get_method();

			if ( $method === 'POST' ) {
				return new WP_Error(
					'ep_template_creation_disabled',
					__( 'Template creation is disabled. This theme uses a router pattern — add a route instead.', 'examplepress-theme' ),
					[ 'status' => 403 ]
				);
			}

			if ( $method === 'DELETE' ) {
				$id = $request->get_param( 'id' );
				if ( $id && preg_match( '/^(examplepress-theme\/\/)?index$/', (string) $id ) ) {
					return new WP_Error(
						'ep_template_delete_disabled',
						__( 'The index template cannot be deleted.', 'examplepress-theme' ),
						[ 'status' => 403 ]
					);
				}
			}

			return $result;
		}, 10, 3 );
	},
] );

examplepress_register_feature( 'guard-template-resolution', [
	'label'   => 'Template Resolution Guard',
	'default' => true,
	'setup'   => function ( $id ) {
		if ( ! examplepress_feature_enabled( $id ) ) {
			return;
		}

		add_filter( 'get_block_templates', function ( $templates, $_query, $template_type ) {
			if ( $template_type !== 'wp_template' ) {
				return $templates;
			}

			return array_values(
				array_filter(
					$templates,
					fn( $template ) => $template->source !== 'custom'
				)
			);
		}, 10, 3 );
	},
] );
