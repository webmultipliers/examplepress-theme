<?php
/**
 * ExamplePress Theme Updater
 *
 * Self-contained GitHub Releases updater for the ExamplePress theme.
 * Hooks into the WordPress update system — no plugin required.
 *
 * Usage: require_once get_template_directory() . '/inc/updater.php';
 *        new ExamplePress_Updater();
 *
 * Configuration is driven by the "updater" key in examplepress.json:
 *
 *   "updater": {
 *     "github_repo":    "webmultipliers/examplepress-theme",
 *     "asset_filename": "examplepress-theme.zip",
 *     "theme_slug":     "examplepress-theme",
 *     "default_channel":"prerelease",
 *     "requires_wp":    "6.9",
 *     "requires_php":   "8.4"
 *   }
 *
 * Channels
 * --------
 *   stable      → only non-prerelease GitHub releases
 *   prerelease  → also includes prerelease releases (default for development tags)
 *
 * Tag format produced by the release workflow:
 *   v{semver}-{channel}-{short-sha}   e.g. v1.0.0-development-b476169
 *   v{semver}                          e.g. v1.0.0  (stable)
 *
 * Runtime channel override (takes priority over JSON default):
 *   define( 'EXAMPLEPRESS_UPDATE_CHANNEL', 'prerelease' );
 *
 * @package ExamplePress
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ExamplePress_Updater {

	/** Transient TTL in seconds (1 hour). */
	private const CACHE_TTL = HOUR_IN_SECONDS;

	/** Transient key prefix. */
	private const TRANSIENT_PREFIX = 'examplepress_updater_';

	// -------------------------------------------------------------------------
	// Config-driven state
	// -------------------------------------------------------------------------

	private string $github_repo;
	private string $asset_filename;
	private string $theme_slug;
	private string $requires_wp;
	private string $requires_php;
	private string $current_version;
	private string $channel;

	// -------------------------------------------------------------------------
	// Bootstrap
	// -------------------------------------------------------------------------

	public function __construct() {
		$config = examplepress_get_config()['updater'] ?? [];

		$this->github_repo    = $config['github_repo']    ?? 'webmultipliers/examplepress-theme';
		$this->asset_filename = $config['asset_filename']  ?? 'examplepress-theme.zip';
		$this->theme_slug     = $config['theme_slug']      ?? 'examplepress-theme';
		$this->requires_wp    = $config['requires_wp']     ?? '6.9';
		$this->requires_php   = $config['requires_php']    ?? '8.4';
		$this->current_version = $this->get_current_version();
		$this->channel         = $this->resolve_channel( $config['default_channel'] ?? 'prerelease' );

		add_filter( 'pre_set_site_transient_update_themes', [ $this, 'inject_update' ] );
		add_filter( 'themes_api', [ $this, 'theme_info' ], 10, 3 );
		add_filter( 'upgrader_source_selection', [ $this, 'fix_source_dir' ], 10, 4 );

		// Clear cached release data after a successful theme update.
		add_action( 'upgrader_process_complete', [ $this, 'flush_transients' ], 10, 2 );
	}

	// -------------------------------------------------------------------------
	// Core hooks
	// -------------------------------------------------------------------------

	/**
	 * Inject update info into the WordPress update_themes transient.
	 *
	 * @param  object $transient  The existing transient value.
	 * @return object
	 */
	public function inject_update( object $transient ): object {
		if ( empty( $transient->checked ) ) {
			return $transient;
		}

		$release = $this->get_latest_release();

		if ( ! $release ) {
			return $transient;
		}

		$new_version = $this->parse_version( $release['tag_name'] );

		if ( ! $new_version ) {
			return $transient;
		}

		if ( ! $this->is_newer( $new_version, $this->current_version ) ) {
			return $transient;
		}

		$download_url = $this->get_asset_url( $release );

		if ( ! $download_url ) {
			return $transient;
		}

		$transient->response[ $this->theme_slug ] = [
			'theme'       => $this->theme_slug,
			'new_version' => $new_version,
			'url'         => $release['html_url'],
			'package'     => $download_url,
			'requires'    => $this->requires_wp,
			'requires_php'=> $this->requires_php,
		];

		return $transient;
	}

	/**
	 * Serve theme info for the "View version details" modal.
	 *
	 * @param  false|object $result  Default false.
	 * @param  string       $action  API action.
	 * @param  object       $args    Request arguments.
	 * @return false|object
	 */
	public function theme_info( $result, string $action, object $args ) {
		if ( 'theme_information' !== $action ) {
			return $result;
		}

		if ( ! isset( $args->slug ) || $args->slug !== $this->theme_slug ) {
			return $result;
		}

		$release = $this->get_latest_release();

		if ( ! $release ) {
			return $result;
		}

		$new_version  = $this->parse_version( $release['tag_name'] );
		$download_url = $this->get_asset_url( $release );

		return (object) [
			'name'          => 'ExamplePress',
			'slug'          => $this->theme_slug,
			'version'       => $new_version,
			'author'        => '<a href="https://github.com/webmultipliers">webmultipliers</a>',
			'homepage'      => 'https://github.com/' . $this->github_repo,
			'download_link' => $download_url,
			'requires'      => $this->requires_wp,
			'requires_php'  => $this->requires_php,
			'sections'      => [
				'description' => 'A code-first WordPress FSE theme built on Blockstudio.',
				'changelog'   => $this->format_changelog( $release ),
			],
		];
	}

	/**
	 * Rename the extracted theme directory to the correct slug.
	 *
	 * GitHub zips extract to a directory named after the repo + tag, e.g.
	 * "examplepress-theme-1.0.0-development-b476169/". This renames it back
	 * to "examplepress-theme/" so WordPress finds the theme.
	 *
	 * @param  string      $source        Path to extracted source.
	 * @param  string      $remote_source Temp directory.
	 * @param  WP_Upgrader $upgrader      Upgrader instance.
	 * @param  array       $hook_extra    Extra hook data.
	 * @return string
	 */
	public function fix_source_dir( string $source, string $remote_source, $upgrader, array $hook_extra ): string {
		global $wp_filesystem;

		if ( ! isset( $hook_extra['theme'] ) || $hook_extra['theme'] !== $this->theme_slug ) {
			return $source;
		}

		$expected = trailingslashit( $remote_source ) . $this->theme_slug . '/';

		if ( $source !== $expected ) {
			$wp_filesystem->move( $source, $expected );
			return $expected;
		}

		return $source;
	}

	/**
	 * Flush update transients after a theme upgrade.
	 *
	 * @param  WP_Upgrader $upgrader   Upgrader instance.
	 * @param  array       $hook_extra Extra hook data.
	 */
	public function flush_transients( $upgrader, array $hook_extra ): void {
		if (
			isset( $hook_extra['type'], $hook_extra['themes'] ) &&
			'theme' === $hook_extra['type'] &&
			in_array( $this->theme_slug, (array) $hook_extra['themes'], true )
		) {
			$this->delete_transients();
		}
	}

	// -------------------------------------------------------------------------
	// GitHub API
	// -------------------------------------------------------------------------

	/**
	 * Fetch and cache the latest qualifying release from GitHub.
	 *
	 * @return array|null  Release data array, or null on failure.
	 */
	private function get_latest_release(): ?array {
		$transient_key = self::TRANSIENT_PREFIX . 'release_' . $this->channel;
		$cached        = get_transient( $transient_key );

		if ( false !== $cached ) {
			return $cached ?: null; // empty string = cached negative result
		}

		$releases = $this->fetch_releases();
		$release  = null;

		if ( is_array( $releases ) ) {
			foreach ( $releases as $candidate ) {
				if ( $this->qualifies( $candidate ) ) {
					$release = $candidate;
					break;
				}
			}
		}

		// Cache a falsy value to avoid hammering the API on failures.
		set_transient( $transient_key, $release ?? '', self::CACHE_TTL );

		return $release;
	}

	/**
	 * Call the GitHub releases API.
	 *
	 * Uses an authenticated request when a GitHub token is available
	 * (PAT or Troy read token) to avoid the 60 req/hr unauthenticated
	 * rate limit — critical for shared hosting with many WP sites on
	 * the same IP.
	 *
	 * @return array|null  Decoded JSON, or null on HTTP/decode failure.
	 */
	private function fetch_releases(): ?array {
		$url     = sprintf( 'https://api.github.com/repos/%s/releases', $this->github_repo );
		$headers = [
			'Accept'     => 'application/vnd.github+json',
			'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
		];

		// Authenticate if a token is available (5,000 req/hr vs 60).
		$token = $this->get_api_token();
		if ( $token ) {
			$headers['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_remote_get( $url, [
			'timeout' => 10,
			'headers' => $headers,
		] );

		if ( is_wp_error( $response ) ) {
			$this->log( 'GitHub API error: ' . $response->get_error_message() );
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( 200 !== (int) $code ) {
			$this->log( 'GitHub API returned HTTP ' . $code );
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! is_array( $data ) ) {
			$this->log( 'GitHub API returned invalid JSON.' );
			return null;
		}

		return $data;
	}

	// -------------------------------------------------------------------------
	// Release qualification
	// -------------------------------------------------------------------------

	/**
	 * Determine whether a release qualifies for the current channel.
	 *
	 * Channel logic:
	 *   stable     → accept only non-draft, non-prerelease
	 *   prerelease → accept any non-draft (prerelease or stable)
	 *
	 * @param  array $release  GitHub release object.
	 * @return bool
	 */
	private function qualifies( array $release ): bool {
		if ( ! empty( $release['draft'] ) ) {
			return false;
		}

		if ( 'stable' === $this->channel && ! empty( $release['prerelease'] ) ) {
			return false;
		}

		// Must have a parseable version from the tag.
		if ( ! $this->parse_version( $release['tag_name'] ?? '' ) ) {
			return false;
		}

		// Must have the expected zip asset attached.
		if ( ! $this->get_asset_url( $release ) ) {
			return false;
		}

		return true;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Extract a clean semver string from a tag name.
	 *
	 * Handles:
	 *   v1.0.0                           → 1.0.0
	 *   v1.0.0-development-b476169       → 1.0.0-development-b476169
	 *   1.0.0                            → 1.0.0
	 *
	 * Returns null if the tag doesn't start with a semver core.
	 *
	 * @param  string $tag  Git tag name.
	 * @return string|null
	 */
	private function parse_version( string $tag ): ?string {
		$tag = ltrim( $tag, 'v' );

		// Must begin with MAJOR.MINOR.PATCH.
		if ( ! preg_match( '/^\d+\.\d+\.\d+/', $tag ) ) {
			return null;
		}

		return $tag;
	}

	/**
	 * Return the browser-download URL of the theme zip asset.
	 *
	 * @param  array $release  GitHub release object.
	 * @return string|null
	 */
	private function get_asset_url( array $release ): ?string {
		if ( empty( $release['assets'] ) || ! is_array( $release['assets'] ) ) {
			return null;
		}

		foreach ( $release['assets'] as $asset ) {
			if ( isset( $asset['name'] ) && $asset['name'] === $this->asset_filename ) {
				return $asset['browser_download_url'] ?? null;
			}
		}

		return null;
	}

	/**
	 * Compare version strings, respecting prerelease suffixes.
	 *
	 * Uses PHP's version_compare after normalising prerelease labels so that
	 * 1.0.0-development-b476169 correctly compares against 1.0.0.
	 *
	 * @param  string $new      Candidate version.
	 * @param  string $current  Installed version.
	 * @return bool
	 */
	private function is_newer( string $new, string $current ): bool {
		return version_compare( $new, $current, '>' );
	}

	/**
	 * Get the current installed theme version from style.css.
	 *
	 * @return string
	 */
	private function get_current_version(): string {
		$theme = wp_get_theme( $this->theme_slug );
		return $theme->get( 'Version' ) ?: '0.0.0';
	}

	/**
	 * Determine update channel.
	 *
	 * Priority:
	 *   1. EXAMPLEPRESS_UPDATE_CHANNEL constant (defined in wp-config.php / functions.php)
	 *   2. examplepress_update_channel filter
	 *   3. examplepress.json → updater.default_channel
	 *   4. Falls back to 'prerelease'
	 *
	 * @param  string $default  Default channel from examplepress.json.
	 * @return string  'stable' | 'prerelease'
	 */
	private function resolve_channel( string $default = 'prerelease' ): string {
		$channel = $default;

		if ( defined( 'EXAMPLEPRESS_UPDATE_CHANNEL' ) ) {
			$channel = EXAMPLEPRESS_UPDATE_CHANNEL;
		}

		/** @param string $channel 'stable' | 'prerelease' */
		$channel = apply_filters( 'examplepress_update_channel', $channel );

		return in_array( $channel, [ 'stable', 'prerelease' ], true ) ? $channel : 'prerelease';
	}

	/**
	 * Format the GitHub release body as a minimal changelog string.
	 *
	 * @param  array $release  GitHub release object.
	 * @return string
	 */
	private function format_changelog( array $release ): string {
		$body = $release['body'] ?? '';
		$body = wp_kses_post( $body );
		return $body ?: '<p>See the <a href="' . esc_url( $release['html_url'] ) . '">release page</a> for details.</p>';
	}

	/**
	 * Retrieve a GitHub API token for authenticated requests.
	 *
	 * Checks (in order): Troy read token, GitHub PAT, GitHub App
	 * installation token. Returns null if none are available —
	 * the request will fall back to unauthenticated (60 req/hr).
	 *
	 * @return string|null
	 */
	private function get_api_token(): ?string {
		// Troy read token (read-only, ideal for public release checks).
		$token = get_option( 'ep_troy_github_pat', '' );
		if ( $token ) {
			return $token;
		}

		// GitHub PAT (write-capable, but works).
		$token = get_option( 'ep_github_pat', '' );
		if ( $token ) {
			return $token;
		}

		// GitHub App installation token.
		if ( function_exists( 'examplepress_github_app_get_installation_token' ) ) {
			$token = examplepress_github_app_get_installation_token();
			if ( $token && ! is_wp_error( $token ) ) {
				return $token;
			}
		}

		return null;
	}

	/**
	 * Delete all updater-related transients.
	 */
	private function delete_transients(): void {
		delete_transient( self::TRANSIENT_PREFIX . 'release_stable' );
		delete_transient( self::TRANSIENT_PREFIX . 'release_prerelease' );
	}

	/**
	 * Log a debug message when WP_DEBUG_LOG is active.
	 *
	 * @param  string $message
	 */
	private function log( string $message ): void {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions
			error_log( '[ExamplePress Updater] ' . $message );
		}
	}
}
