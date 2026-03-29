<?php
/**
 * Admin Customization Features
 *
 * Dashboard widgets, login branding, and post lock window.
 */

examplepress_register_feature( 'post-lock-window', [
	'label'   => 'Custom Post Lock Window',
	'default' => true,
	'options' => [ 'duration' => 30 ],
	'setup'   => function ( $id ) {
		if ( ! examplepress_feature_enabled( $id ) ) {
			return;
		}
		add_filter( 'wp_check_post_lock_window', function () use ( $id ) {
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
		add_filter( 'login_headerurl', fn() => home_url() );
		add_filter( 'login_headertext', fn() => get_bloginfo( 'name' ) );
	},
] );
