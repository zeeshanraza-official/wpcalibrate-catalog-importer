<?php
/**
 * GitHub In-Dashboard Updater.
 *
 * Handles automated plugin updates and details modal directly from GitHub releases in WordPress admin.
 */

declare(strict_types=1);

namespace WPCalibrate\CatalogImporter\Services;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GitHubUpdater
 */
final class GitHubUpdater {

	/**
	 * GitHub repository owner.
	 */
	private string $owner;

	/**
	 * GitHub repository name.
	 */
	private string $repo;

	/**
	 * Plugin main file path.
	 */
	private string $file;

	/**
	 * Plugin directory slug.
	 */
	private string $slug;

	/**
	 * Plugin basename.
	 */
	private string $basename;

	/**
	 * Current plugin version.
	 */
	private string $version;

	/**
	 * Constructor.
	 *
	 * @param string $owner   GitHub owner/organization.
	 * @param string $repo    GitHub repository name.
	 * @param string $file    Main plugin file path.
	 * @param string $version Current version string.
	 */
	public function __construct( string $owner, string $repo, string $file, string $version ) {
		$this->owner    = $owner;
		$this->repo     = $repo;
		$this->file     = $file;
		$this->slug     = dirname( plugin_basename( $file ) );
		$this->basename = plugin_basename( $file );
		$this->version  = $version;
	}

	/**
	 * Initialize update hooks.
	 *
	 * @return void
	 */
	public function init(): void {
		add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_update' ] );
		add_filter( 'plugins_api', [ $this, 'plugin_info' ], 20, 3 );
		add_filter( 'upgrader_post_install', [ $this, 'post_install' ], 10, 3 );
	}

	/**
	 * Check GitHub for new release tag and inject update package if available.
	 *
	 * @param mixed $transient
	 * @return mixed
	 */
	public function check_update( mixed $transient ): mixed {
		if ( ! is_object( $transient ) ) {
			$transient = new \stdClass();
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $transient;
		}

		$new_version = ltrim( (string) ( $release->tag_name ?? '' ), 'v' );
		if ( version_compare( $new_version, $this->version, '>' ) ) {
			$package = $this->find_download_package( $release );

			$obj               = new \stdClass();
			$obj->id           = "github.com/{$this->owner}/{$this->repo}";
			$obj->slug         = $this->slug;
			$obj->plugin       = $this->basename;
			$obj->new_version  = $new_version;
			$obj->url          = (string) ( $release->html_url ?? "https://github.com/{$this->owner}/{$this->repo}" );
			$obj->package      = $package;
			$obj->icons        = [
				'1x' => WPCALIBRATE_CATALOG_IMPORTER_URL . 'branding/icon-dark.png',
				'2x' => WPCALIBRATE_CATALOG_IMPORTER_URL . 'branding/icon-dark.png',
			];
			$obj->banners      = [];
			$obj->tested       = '7.1.3';
			$obj->requires_php = '8.2';

			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = [];
			}
			$transient->response[ $this->basename ] = $obj;
		}

		return $transient;
	}

	/**
	 * Provide details modal info when user clicks "View version details".
	 *
	 * @param mixed $res
	 * @param string $action
	 * @param mixed $args
	 * @return mixed
	 */
	public function plugin_info( mixed $res, string $action, mixed $args ): mixed {
		if ( 'plugin_information' !== $action || ! is_object( $args ) || ( $args->slug ?? '' ) !== $this->slug ) {
			return $res;
		}

		$release = $this->get_latest_release();
		if ( ! $release ) {
			return $res;
		}

		$new_version = ltrim( (string) ( $release->tag_name ?? '' ), 'v' );

		$info                = new \stdClass();
		$info->name          = 'WPCalibrate Catalog Importer';
		$info->slug          = $this->slug;
		$info->version       = $new_version;
		$info->author        = '<a href="https://wpcalibrate.com">WPCalibrate</a>';
		$info->homepage      = "https://github.com/{$this->owner}/{$this->repo}";
		$info->requires      = '6.5';
		$info->tested        = '7.1.3';
		$info->requires_php  = '8.2';
		$info->download_link = $this->find_download_package( $release );
		$info->last_updated  = (string) ( $release->published_at ?? gmdate( 'Y-m-d' ) );
		$info->sections      = [
			'description' => __( 'Enterprise-grade WooCommerce bulk product catalog importer supporting arbitrary CSV and XLSX spreadsheets, 7-step wizard, Action Scheduler background batching, and full HPOS compliance.', 'wpcalibrate-catalog-importer' ),
			'changelog'   => nl2br( esc_html( (string) ( $release->body ?? 'Bug fixes and performance improvements.' ) ) ),
		];

		return $info;
	}

	/**
	 * Ensure correct folder name after unzipping from GitHub.
	 *
	 * @param bool $true
	 * @param array<string, mixed> $hook_extra
	 * @param array<string, mixed> $result
	 * @return array<string, mixed>
	 */
	public function post_install( bool $true, array $hook_extra, array $result ): array {
		global $wp_filesystem;

		if ( ! $wp_filesystem || ! isset( $result['destination'] ) ) {
			return $result;
		}

		$plugin_dir = WP_PLUGIN_DIR . '/' . $this->slug;
		if ( $wp_filesystem->exists( $result['destination'] ) && $result['destination'] !== $plugin_dir ) {
			$wp_filesystem->move( $result['destination'], $plugin_dir );
			$result['destination'] = $plugin_dir;
		}

		return $result;
	}

	/**
	 * Fetch latest release from GitHub API with transient caching.
	 *
	 * @return object|null
	 */
	private function get_latest_release(): ?object {
		$cache_key = 'wpcalibrate_ci_gh_release';
		$cached    = get_site_transient( $cache_key );
		if ( false !== $cached && is_object( $cached ) ) {
			return $cached;
		}

		$url      = "https://api.github.com/repos/{$this->owner}/{$this->repo}/releases/latest";
		$response = wp_remote_get(
			$url,
			[
				'timeout' => 10,
				'headers' => [
					'Accept'     => 'application/vnd.github.v3+json',
					'User-Agent' => 'WPCalibrate-Catalog-Importer/' . $this->version,
				],
			]
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body );
		if ( ! is_object( $data ) ) {
			return null;
		}

		set_site_transient( $cache_key, $data, 12 * HOUR_IN_SECONDS );
		return $data;
	}

	/**
	 * Find zip package URL from release assets or zipball.
	 *
	 * @param object $release
	 * @return string
	 */
	private function find_download_package( object $release ): string {
		if ( ! empty( $release->assets ) && is_array( $release->assets ) ) {
			foreach ( $release->assets as $asset ) {
				if ( isset( $asset->name, $asset->browser_download_url ) && str_ends_with( (string) $asset->name, '.zip' ) ) {
					return (string) $asset->browser_download_url;
				}
			}
		}

		return (string) ( $release->zipball_url ?? '' );
	}
}
