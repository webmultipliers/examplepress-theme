<?php
/**
 * Design Token Features
 *
 * Inject colours, layout dimensions, and typography into WordPress
 * theme.json at runtime via the wp_theme_json_data_theme filter.
 * Values are resolved through the standard feature-option chain
 * (PHP filter > examplepress.json > registration default).
 */

examplepress_register_feature( 'theme-colors', [
	'label'   => 'Theme Color Palette',
	'default' => true,
	'options' => [
		'palette' => [
			[ 'name' => 'Primary',     'slug' => 'primary',          'color' => '#a3122e' ],
			[ 'name' => 'Secondary',   'slug' => 'secondary',        'color' => '#205375' ],
			[ 'name' => 'Background',  'slug' => 'bg',               'color' => '#f4f6fb' ],
			[ 'name' => 'Separator',   'slug' => 'separator',        'color' => '#cfd8dc' ],
			[ 'name' => 'Dark',        'slug' => 'dark',             'color' => '#181a20' ],
			[ 'name' => 'Light',       'slug' => 'light',            'color' => '#f9fafb' ],
			[ 'name' => 'Black',       'slug' => 'black',            'color' => '#1b1b1b' ],
			[ 'name' => 'Danger',      'slug' => 'danger',           'color' => '#e63946' ],
			[ 'name' => 'Info',        'slug' => 'info',             'color' => '#3a86ff' ],
			[ 'name' => 'Success',     'slug' => 'success',          'color' => '#43aa8b' ],
			[ 'name' => 'Warning',     'slug' => 'warning',          'color' => '#ffd166' ],
			[ 'name' => 'Content',     'slug' => 'text-content',     'color' => '#23272f' ],
			[ 'name' => 'Highlight',   'slug' => 'text-highlight',   'color' => '#a3122e' ],
			[ 'name' => 'Muted',       'slug' => 'text-muted',       'color' => '#7b8a8b' ],
			[ 'name' => 'Placeholder', 'slug' => 'text-placeholder', 'color' => '#b0bec5' ],
		],
	],
	'setup'   => function ( $id ) {
		if ( ! examplepress_feature_enabled( $id ) ) {
			return;
		}
		add_filter( 'wp_theme_json_data_theme', function ( $theme_json ) use ( $id ) {
			$data                                 = $theme_json->get_data();
			$data['version']                      = 3;
			$data['settings']['color']['palette'] = (array) examplepress_feature_option( $id, 'palette', [] );
			return $theme_json->update_with( $data );
		}, 39 );
	},
] );

examplepress_register_feature( 'theme-layout', [
	'label'   => 'Theme Layout Dimensions',
	'default' => true,
	'options' => [
		'wide_size'    => '1200px',
		'content_size' => '800px',
	],
	'setup'   => function ( $id ) {
		if ( ! examplepress_feature_enabled( $id ) ) {
			return;
		}
		add_filter( 'wp_theme_json_data_theme', function ( $theme_json ) use ( $id ) {
			$data            = $theme_json->get_data();
			$data['version'] = 3;

			$data['settings']['layout']['wideSize']    = (string) examplepress_feature_option( $id, 'wide_size', '1200px' );
			$data['settings']['layout']['contentSize'] = (string) examplepress_feature_option( $id, 'content_size', '800px' );

			return $theme_json->update_with( $data );
		}, 39 );
	},
] );

examplepress_register_feature( 'theme-typography', [
	'label'   => 'Theme Typography',
	'default' => true,
	'options' => [
		'font_families' => [],
		'font_sizes'    => [],
	],
	'setup'   => function ( $id ) {
		if ( ! examplepress_feature_enabled( $id ) ) {
			return;
		}
		add_filter( 'wp_theme_json_data_theme', function ( $theme_json ) use ( $id ) {
			$data            = $theme_json->get_data();
			$data['version'] = 3;

			$families = (array) examplepress_feature_option( $id, 'font_families', [] );
			if ( ! empty( $families ) ) {
				$data['settings']['typography']['fontFamilies'] = $families;
			}

			$sizes = (array) examplepress_feature_option( $id, 'font_sizes', [] );
			if ( ! empty( $sizes ) ) {
				$data['settings']['typography']['fontSizes'] = $sizes;
			}

			return $theme_json->update_with( $data );
		}, 39 );
	},
] );

examplepress_register_feature( 'design-strict', [
	'label'   => 'Strict Design Mode',
	'default' => false,
	'setup'   => function ( $id ) {
		if ( ! examplepress_feature_enabled( $id ) ) {
			return;
		}
		add_filter( 'wp_theme_json_data_theme', function ( $theme_json ) {
			$data                                = $theme_json->get_data();
			$data['version']                     = 3;
			$data['settings']['appearanceTools'] = false;
			return $theme_json->update_with( $data );
		}, 99 );
	},
] );
