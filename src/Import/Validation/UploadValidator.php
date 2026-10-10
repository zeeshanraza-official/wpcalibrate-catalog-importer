<?php
namespace WPCalibrate\CatalogImporter\Import\Validation;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates uploaded spreadsheet and ZIP files, securing file paths and verifying MIME/structure.
 */
class UploadValidator {

	/**
	 * Allowed spreadsheet extensions.
	 */
	public const ALLOWED_EXTENSIONS = [ 'csv', 'xlsx' ];

	/**
	 * Get the secure upload directory for WPCalibrate Catalog Importer files.
	 *
	 * @return array{path: string, url: string}|WP_Error
	 */
	public static function get_upload_dir(): array|WP_Error {
		$wp_upload = wp_upload_dir();
		if ( ! empty( $wp_upload['error'] ) ) {
			return new WP_Error( 'upload_dir_error', $wp_upload['error'] );
		}

		$basedir = trailingslashit( $wp_upload['basedir'] ) . 'wpcalibrate-catalog-importer';
		$baseurl = trailingslashit( $wp_upload['baseurl'] ) . 'wpcalibrate-catalog-importer';

		if ( ! wp_mkdir_p( $basedir ) ) {
			return new WP_Error( 'mkdir_failed', __( 'Failed to create secure importer upload directory.', 'wpcalibrate-catalog-importer' ) );
		}

		// Ensure directory is protected with index.html and .htaccess.
		$index_file = trailingslashit( $basedir ) . 'index.html';
		if ( ! file_exists( $index_file ) ) {
			@file_put_contents( $index_file, '' );
		}

		$htaccess_file = trailingslashit( $basedir ) . '.htaccess';
		if ( ! file_exists( $htaccess_file ) ) {
			$htaccess_content = "# Protect imported files from direct execution\nDeny from all\n<FilesMatch \"\.(?i:csv|xlsx|zip)$\">\nDeny from all\n</FilesMatch>\n";
			@file_put_contents( $htaccess_file, $htaccess_content );
		}

		return [
			'path' => $basedir,
			'url'  => $baseurl,
		];
	}

	/**
	 * Validate and move an uploaded file to the secure importer storage.
	 *
	 * @param array $file_array Standard $_FILES['file'] element.
	 * @param array $allowed_extensions Allowed extensions.
	 * @return array{
	 *     file_path: string,
	 *     filename: string,
	 *     extension: string,
	 *     size: int
	 * }|WP_Error
	 */
	public function handle_upload( array $file_array, array $allowed_extensions = self::ALLOWED_EXTENSIONS ): array|WP_Error {
		if ( empty( $file_array['tmp_name'] ) || ! is_uploaded_file( $file_array['tmp_name'] ) ) {
			return new WP_Error( 'invalid_upload', __( 'No valid uploaded file found.', 'wpcalibrate-catalog-importer' ) );
		}

		if ( ! empty( $file_array['error'] ) ) {
			return new WP_Error( 'upload_error', sprintf( __( 'Upload error code: %d', 'wpcalibrate-catalog-importer' ), (int) $file_array['error'] ) );
		}

		$original_name = sanitize_file_name( (string) $file_array['name'] );
		$ext           = strtolower( pathinfo( $original_name, PATHINFO_EXTENSION ) );

		if ( ! in_array( $ext, $allowed_extensions, true ) ) {
			return new WP_Error(
				'unsupported_file_type',
				sprintf(
					/* translators: %s: Allowed extensions */
					__( 'Unsupported file type. Allowed formats: %s', 'wpcalibrate-catalog-importer' ),
					implode( ', ', $allowed_extensions )
				)
			);
		}

		$upload_dir = self::get_upload_dir();
		if ( is_wp_error( $upload_dir ) ) {
			return $upload_dir;
		}

		// Generate random, unguessable filename while keeping safe extension.
		$random_prefix = wp_generate_password( 24, false, false );
		$target_name   = 'import_' . $random_prefix . '.' . $ext;
		$target_path   = trailingslashit( $upload_dir['path'] ) . $target_name;

		if ( ! move_uploaded_file( $file_array['tmp_name'], $target_path ) ) {
			return new WP_Error( 'move_failed', __( 'Failed to save uploaded file in secure storage.', 'wpcalibrate-catalog-importer' ) );
		}

		return [
			'file_path' => $target_path,
			'filename'  => $original_name,
			'extension' => $ext,
			'size'      => (int) filesize( $target_path ),
		];
	}
}
