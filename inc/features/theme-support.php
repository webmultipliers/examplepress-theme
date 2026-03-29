<?php
/**
 * Theme Support Features
 *
 * Core WordPress theme supports: title-tag, responsive-embeds,
 * post-thumbnails, wp-block-styles, html5.
 */

examplepress_register_feature( 'title-tag', [
	'label'   => 'Title Tag',
	'default' => true,
	'setup'   => function ( $id ) {
		if ( examplepress_feature_enabled( $id ) ) {
			add_theme_support( 'title-tag' );
		}
	},
] );

examplepress_register_feature( 'responsive-embeds', [
	'label'   => 'Responsive Embeds',
	'default' => true,
	'setup'   => function ( $id ) {
		if ( examplepress_feature_enabled( $id ) ) {
			add_theme_support( 'responsive-embeds' );
		}
	},
] );

examplepress_register_feature( 'post-thumbnails', [
	'label'   => 'Post Thumbnails',
	'default' => true,
	'setup'   => function ( $id ) {
		if ( examplepress_feature_enabled( $id ) ) {
			add_theme_support( 'post-thumbnails' );
		}
	},
] );

examplepress_register_feature( 'wp-block-styles', [
	'label'   => 'Block Styles',
	'default' => true,
	'setup'   => function ( $id ) {
		if ( examplepress_feature_enabled( $id ) ) {
			add_theme_support( 'wp-block-styles' );
		}
	},
] );

examplepress_register_feature( 'html5', [
	'label'   => 'HTML5 Markup',
	'default' => true,
	'options' => [
		'features' => [
			'caption',
			'comment-form',
			'comment-list',
			'gallery',
			'search-form',
			'script',
			'style',
		],
	],
	'setup'   => function ( $id ) {
		if ( ! examplepress_feature_enabled( $id ) ) {
			return;
		}
		$features = (array) examplepress_feature_option( $id, 'features', [] );
		if ( ! empty( $features ) ) {
			add_theme_support( 'html5', $features );
		}
	},
] );
