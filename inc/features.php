<?php
/**
 * ExamplePress Feature Definitions
 *
 * Every feature is filterable:
 *   - Toggle:  add_filter( 'examplepress_feature_{id}', '__return_false' );
 *   - Option:  add_filter( 'examplepress_feature_{id}_{key}', fn() => $val );
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

// ── Editor & Content Controls ───────────────────────────────────────

examplepress_register_feature( 'disable-remote-block-patterns', [
	'label'    => 'Disable Remote Block Patterns',
	'default'  => true,
	'hook'     => 'should_load_remote_block_patterns',
	'callback' => '__return_false',
] );

examplepress_register_feature( 'disable-core-block-patterns', [
	'label'   => 'Disable Core Block Patterns',
	'default' => true,
	'setup'   => function ( $id ) {
		if ( examplepress_feature_enabled( $id ) ) {
			remove_theme_support( 'core-block-patterns' );
		}
	},
] );


examplepress_register_feature( 'disable-redirect-guess-404', [
	'label'    => 'Disable 404 Redirect Guessing',
	'default'  => true,
	'hook'     => 'do_redirect_guess_404_permalink',
	'callback' => '__return_false',
] );

examplepress_register_feature( 'openverse', [
	'label'   => 'Openverse Media Category',
	'default' => true,
	'setup'   => function ( $id ) {
		add_filter( 'block_editor_settings_all', function ( $settings ) use ( $id ) {
			$settings['enableOpenverseMediaCategory'] = examplepress_feature_enabled( $id );
			return $settings;
		} );
	},
] );


examplepress_register_feature( 'post-lock-window', [
	'label'   => 'Custom Post Lock Window',
	'default' => true,
	'options' => [ 'duration' => 30 ],
	'setup'   => function ( $id ) {
		if ( ! examplepress_feature_enabled( $id ) ) {
			return;
		}
		add_filter( 'wp_check_post_lock_window', function () use ($id) {
			return (int) examplepress_feature_option( $id, 'duration', 30 );
		} );
	},
] );

examplepress_register_feature( 'remove-dashboard-widgets', [
	'label'   => 'Remove Default Dashboard Widgets',
	'default' => true,
	'setup'   => function ( $id ) {
		if ( ! examplepress_feature_enabled( $id ) ) {
			return;
		}
		add_action( 'wp_dashboard_setup', function () {
			remove_meta_box( 'dashboard_right_now', 'dashboard', 'normal' );
			remove_meta_box( 'dashboard_activity', 'dashboard', 'normal' );
			remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
			remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
			remove_meta_box( 'dashboard_site_health', 'dashboard', 'normal' );
			remove_action( 'welcome_panel', 'wp_welcome_panel' );
			remove_meta_box( 'wc_admin_dashboard_setup', 'dashboard', 'normal' );
		} );
	},
] );

examplepress_register_feature( 'login-branding', [
	'label'   => 'Custom Login Branding',
	'default' => true,
	'setup'   => function ( $id ) {
		if ( ! examplepress_feature_enabled( $id ) ) {
			return;
		}
		// login_enqueue_scripts — inject theme.json global styles + custom stylesheet.
		add_action( 'login_enqueue_scripts', function () {
			if ( function_exists( 'wp_get_global_stylesheet' ) ) {
				$global_styles = wp_get_global_stylesheet();
				wp_register_style( 'ep-global', false );
				wp_enqueue_style( 'ep-global' );
				wp_add_inline_style( 'ep-global', $global_styles );
			}
			wp_enqueue_style(
				'examplepress-login',
				get_theme_file_uri( 'assets/css/login.css' ),
				[ 'ep-global' ],
				EP_THEME_VERSION
			);
		} );
		// login_headerurl — point the logo link to the home page.
		add_filter( 'login_headerurl', fn() => home_url() );
		// login_headertext — show the site name instead of "Powered by WordPress".
		add_filter( 'login_headertext', fn() => get_bloginfo( 'name' ) );
	},
] );


examplepress_register_feature( 'permalink-structure', [
	'label'   => 'Enforce Permalink Structure',
	'default' => true,
	'options' => [ 'structure' => '/%postname%/' ],
	'setup'   => function ( $id ) {
		if ( ! examplepress_feature_enabled( $id ) ) {
			return;
		}
		$structure = (string) examplepress_feature_option( $id, 'structure', '/%postname%/' );
		add_filter( 'pre_option_permalink_structure', function () use ($structure) {
			return $structure;
		} );
	},
] );

examplepress_register_feature( 'managed-options', [
	'label'   => 'Managed Site Options',
	'default' => true,
	'options' => [ 'values' => [] ],
	'setup'   => function ( $id ) {
		if ( ! examplepress_feature_enabled( $id ) ) {
			return;
		}

		$values = (array) examplepress_feature_option( $id, 'values', [] );

		foreach ( $values as $option => $value ) {
			add_filter( "pre_option_{$option}", function () use ($value) {
				return $value;
			} );
			add_filter( "option_{$option}", function () use ($value) {
				return $value;
			} );
		}
	},
] );

// ── Theme JSON Overrides ───────────────────────────────────────────

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
