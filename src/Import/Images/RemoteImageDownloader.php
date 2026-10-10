<?php
namespace WPCalibrate\CatalogImporter\Import\Images;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Downloads remote images safely with SSRF mitigation, file size checks, and deduplication.
 */
class RemoteImageDownloader {

	/**
	 * Default timeout in seconds.
	 */
	public const DEFAULT_TIMEOUT = 15;

	/**
	 * Default maximum allowed image size (10MB).
	 */
	public const DEFAULT_MAX_SIZE = 10485760;

	/**
	 * In-memory map of URL => attachment ID to avoid redundant downloads in same batch.
	 *
	 * @var array<string, int>
	 */
	protected array $download_cache = [];

	/**
	 * Validate remote URL against SSRF vulnerabilities.
	 *
	 * @param string $url URL to check.
	 * @return bool
	 */
	public static function is_safe_url( string $url ): bool {
		$clean_url = trim( $url );
		if ( '' === $clean_url ) {
			return false;
		}

		// Only allow http and https schemes.
		$parsed = wp_parse_url( $clean_url );
		if ( ! $parsed || empty( $parsed['scheme'] ) || empty( $parsed['host'] ) ) {
			return false;
		}

		$scheme = strtolower( $parsed['scheme'] );
		if ( ! in_array( $scheme, [ 'http', 'https' ], true ) ) {
			return false;
		}

		// Validate using WordPress URL validation.
		if ( ! wp_http_validate_url( $clean_url ) ) {
			return false;
		}

		$host = $parsed['host'];

		// Resolve host IP to block private/loopback/reserved subnets.
		$ip = gethostbyname( $host );
		if ( empty( $ip ) || $ip === $host ) {
			// DNS lookup failed.
			return false;
		}

		// Check if IP is in private/reserved ranges.
		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
			return false;
		}

		return true;
	}

	/**
	 * Download a remote image and import into Media Library as attachment.
	 *
	 * @param string $url Remote image URL.
	 * @param int $parent_post_id Product post ID to attach to.
	 * @param array $options Options (timeout, max_size, reuse).
	 * @return int|WP_Error Attachment ID on success, or WP_Error.
	 */
	public function download_image( string $url, int $parent_post_id = 0, array $options = [] ): int|WP_Error {
		$url = trim( $url );
		if ( '' === $url ) {
			return new WP_Error( 'empty_url', __( 'Remote image URL is empty.', 'wpcalibrate-catalog-importer' ) );
		}

		// Check in-memory cache.
		if ( isset( $this->download_cache[ $url ] ) ) {
			return $this->download_cache[ $url ];
		}

		// Deduplication check: check if an attachment already exists for this source URL.
		$reuse_existing = $options['reuse_existing'] ?? true;
		if ( $reuse_existing ) {
			$existing_id = $this->find_existing_attachment_by_url( $url );
			if ( $existing_id > 0 ) {
				$this->download_cache[ $url ] = $existing_id;
				return $existing_id;
			}
		}

		// SSRF verification.
		if ( ! self::is_safe_url( $url ) ) {
			return new WP_Error( 'ssrf_blocked', sprintf( __( 'Remote URL "%s" was rejected for security reasons (unsafe scheme or internal host).', 'wpcalibrate-catalog-importer' ), esc_url_raw( $url ) ) );
		}

		$timeout  = (int) ( $options['timeout'] ?? self::DEFAULT_TIMEOUT );
		$max_size = (int) ( $options['max_size'] ?? self::DEFAULT_MAX_SIZE );

		// Head request or GET with stream.
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Download using download_url().
		$temp_file = download_url( $url, $timeout );
		if ( is_wp_error( $temp_file ) ) {
			return $temp_file;
		}

		$file_size = (int) filesize( $temp_file );
		if ( $file_size > $max_size ) {
			@unlink( $temp_file );
			return new WP_Error( 'file_too_large', sprintf( __( 'Image size (%s) exceeds maximum allowed size.', 'wpcalibrate-catalog-importer' ), size_format( $file_size ) ) );
		}

		// Verify file type and extension.
		$file_info = wp_check_filetype_and_ext( $temp_file, wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
		$ext       = $file_info['ext'];
		$type      = $file_info['type'];

		if ( ! $type || ! str_starts_with( $type, 'image/' ) ) {
			@unlink( $temp_file );
			return new WP_Error( 'invalid_image_type', __( 'Downloaded file is not a valid image.', 'wpcalibrate-catalog-importer' ) );
		}

		$clean_filename = sanitize_file_name( wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
		if ( empty( $clean_filename ) || ! str_contains( $clean_filename, '.' ) ) {
			$clean_filename = 'image_' . wp_generate_password( 8, false ) . '.' . ( $ext ?: 'jpg' );
		}

		$file_array = [
			'name'     => $clean_filename,
			'tmp_name' => $temp_file,
		];

		$attachment_id = media_handle_sideload( $file_array, $parent_post_id );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $temp_file );
			return $attachment_id;
		}

		// Save source URL meta for future deduplication.
		// Strip query parameters to avoid logging tokens or sensitive params.
		$normalized_source = strtok( $url, '?' );
		update_post_meta( $attachment_id, '_wpcalibrate_source_url', esc_url_raw( (string) $normalized_source ) );

		$this->download_cache[ $url ] = $attachment_id;
		return (int) $attachment_id;
	}

	/**
	 * Find existing attachment ID by stored source URL.
	 *
	 * @param string $url Source URL.
	 * @return int Attachment ID or 0.
	 */
	protected function find_existing_attachment_by_url( string $url ): int {
		global $wpdb;

		$normalized = strtok( $url, '?' );
		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wpcalibrate_source_url' AND meta_value = %s LIMIT 1",
				$normalized
			)
		);

		return (int) $attachment_id;
	}

	/**
	 * Clear cache.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->download_cache = [];
	}
}
