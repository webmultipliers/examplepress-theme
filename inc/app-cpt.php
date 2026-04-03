<?php
/**
 * ExamplePress App CPT
 *
 * Shadow Custom Post Type for companion app tracking.
 * The CPT is a data store only — show_ui is false. All access is
 * gated by `manage_options` checks in the REST endpoints, so the
 * CPT uses standard `post` capabilities (no custom cap model).
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// ── CPT Registration ────────────────────────────────────────────

add_action( 'init', 'examplepress_register_app_cpt' );

function examplepress_register_app_cpt(): void {
	register_post_type( 'ep_app', [
		'labels' => [
			'name'          => __( 'Apps', 'examplepress-theme' ),
			'singular_name' => __( 'App', 'examplepress-theme' ),
		],
		'public'              => false,
		'show_ui'             => false,
		'show_in_rest'        => false,
		'exclude_from_search' => true,
		'supports'            => [ 'title' ],
		'capability_type'     => 'post',
	] );
}

// ── Helpers ─────────────────────────────────────────────────────

/**
 * Find the CPT post for an app by its plugin slug.
 *
 * @param string $slug Plugin slug.
 * @return WP_Post|null
 */
function examplepress_registry_get_post( string $slug ): ?\WP_Post {
	$posts = get_posts( [
		'post_type'      => 'ep_app',
		'posts_per_page' => 1,
		'post_status'    => 'any',
		'meta_key'       => '_ep_plugin_slug',
		'meta_value'     => $slug,
		'no_found_rows'  => true,
	] );

	return $posts[0] ?? null;
}

/**
 * Convert a CPT post + meta into the record array the rest of
 * the codebase expects.
 *
 * @param WP_Post $post App CPT post.
 * @return array
 */
function examplepress_cpt_to_record( \WP_Post $post ): array {
	$slug = get_post_meta( $post->ID, '_ep_plugin_slug', true ) ?: $post->post_name;

	return [
		'slug'        => $slug,
		'name'        => $post->post_title,
		'description' => get_post_meta( $post->ID, '_ep_description', true ) ?: '',
		'version'     => get_post_meta( $post->ID, '_ep_version', true ) ?: '',
		'source'      => get_post_meta( $post->ID, '_ep_source', true ) ?: 'scaffolded',
		'created_at'  => $post->post_date_gmt !== '0000-00-00 00:00:00' ? gmdate( 'c', strtotime( $post->post_date_gmt ) ) : '',
		'updated_at'  => $post->post_modified_gmt !== '0000-00-00 00:00:00' ? gmdate( 'c', strtotime( $post->post_modified_gmt ) ) : '',
		'github'      => [
			'owner_repo' => get_post_meta( $post->ID, '_ep_github_owner_repo', true ) ?: '',
			'repo_id'    => get_post_meta( $post->ID, '_ep_github_repo_id', true ) ?: '',
			'html_url'   => get_post_meta( $post->ID, '_ep_github_html_url', true ) ?: '',
		],
		'troy'        => [
			'server_url' => get_post_meta( $post->ID, '_ep_troy_server_url', true ) ?: '',
			'repo'       => get_post_meta( $post->ID, '_ep_troy_repo', true ) ?: '',
			'repo_id'    => get_post_meta( $post->ID, '_ep_troy_repo_id', true ) ?: '',
		],
	];
}

/**
 * Write record data to post meta. Only updates keys present in $data.
 *
 * @param int   $post_id WP_Post ID.
 * @param array $data    Record data to write.
 */
function examplepress_cpt_write_meta( int $post_id, array $data ): void {
	$flat_map = [
		'description' => '_ep_description',
		'version'     => '_ep_version',
		'source'      => '_ep_source',
	];

	foreach ( $flat_map as $key => $meta_key ) {
		if ( isset( $data[ $key ] ) ) {
			update_post_meta( $post_id, $meta_key, $data[ $key ] );
		}
	}

	// Nested: github.
	if ( isset( $data['github'] ) && is_array( $data['github'] ) ) {
		$github_map = [
			'owner_repo' => '_ep_github_owner_repo',
			'repo_id'    => '_ep_github_repo_id',
			'html_url'   => '_ep_github_html_url',
		];
		foreach ( $github_map as $key => $meta_key ) {
			if ( isset( $data['github'][ $key ] ) ) {
				update_post_meta( $post_id, $meta_key, $data['github'][ $key ] );
			}
		}
	}

	// Nested: troy.
	if ( isset( $data['troy'] ) && is_array( $data['troy'] ) ) {
		$troy_map = [
			'server_url' => '_ep_troy_server_url',
			'repo'       => '_ep_troy_repo',
			'repo_id'    => '_ep_troy_repo_id',
		];
		foreach ( $troy_map as $key => $meta_key ) {
			if ( isset( $data['troy'][ $key ] ) ) {
				update_post_meta( $post_id, $meta_key, $data['troy'][ $key ] );
			}
		}
	}
}
