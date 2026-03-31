<?php
/**
 * Site Option Features
 *
 * 404 redirect guessing, permalink structure enforcement,
 * and managed wp_options overrides.
 */

examplepress_register_feature( 'disable-redirect-guess-404', [
	'label'    => 'Disable 404 Redirect Guessing',
	'group'    => 'site',
	'default'  => true,
	'hook'     => 'do_redirect_guess_404_permalink',
	'callback' => '__return_false',
] );

examplepress_register_feature( 'permalink-structure', [
	'label'   => 'Enforce Permalink Structure',
	'group'   => 'site',
	'default' => true,
	'options' => [ 'structure' => '/%postname%/' ],
	'setup'   => function ( $id ) {
		examplepress_guarded_setup( $id, function ( $id ) {
			$structure = (string) examplepress_feature_option( $id, 'structure', '/%postname%/' );
			add_filter( 'pre_option_permalink_structure', function () use ( $structure ) {
				return $structure;
			} );
		} );
	},
] );

examplepress_register_feature( 'managed-options', [
	'label'   => 'Managed Site Options',
	'group'   => 'site',
	'default' => true,
	'options' => [ 'values' => [] ],
	'setup'   => function ( $id ) {
		examplepress_guarded_setup( $id, function ( $id ) {
			$values = (array) examplepress_feature_option( $id, 'values', [] );

			foreach ( $values as $option => $value ) {
				add_filter( "pre_option_{$option}", function () use ( $value ) {
					return $value;
				} );
			}
		} );
	},
] );
