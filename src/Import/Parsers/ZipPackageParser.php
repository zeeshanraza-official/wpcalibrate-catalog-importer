<?php
namespace WPCalibrate\CatalogImporter\Import\Parsers;

use ZipArchive;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Secure ZIP parser for bulk product images with Zip-Slip protection,
 * decompression bomb prevention, and SKU/filename image indexation.
 */
class ZipPackageParser {

	/**
	 * Default maximum allowed extracted files.
	 */
	public const DEFAULT_MAX_FILES = 2000;

	/**
	 * Default maximum total uncompressed size (250MB).
	 */
	public const DEFAULT_MAX_TOTAL_BYTES = 262144000;

	/**
	 * Maximum compression ratio (uncompressed size / compressed size) to prevent zip bombs.
	 */
	public const MAX_COMPRESSION_RATIO = 100;

	/**
	 * Allowed image extensions.
	 */
	public const ALLOWED_EXTENSIONS = [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif' ];

	/**
	 * Validate ZIP package before extraction.
	 *
	 * @param string $zip_path Path to ZIP archive.
	 * @param int $max_files Maximum allowed files.
	 * @param int $max_bytes Maximum total uncompressed bytes.
	 * @return true|WP_Error
	 */
	public function validate_zip( string $zip_path, int $max_files = self::DEFAULT_MAX_FILES, int $max_bytes = self::DEFAULT_MAX_TOTAL_BYTES ): true|WP_Error {
		if ( ! file_exists( $zip_path ) || ! is_readable( $zip_path ) ) {
			return new WP_Error( 'zip_unreadable', __( 'The uploaded ZIP file does not exist or is not readable.', 'wpcalibrate-catalog-importer' ) );
		}

		$zip = new ZipArchive();
		$res = $zip->open( $zip_path, ZipArchive::RDONLY );
		if ( true !== $res ) {
			return new WP_Error( 'zip_invalid', __( 'The uploaded file is not a valid ZIP archive.', 'wpcalibrate-catalog-importer' ) );
		}

		$num_files   = $zip->numFiles;
		$total_bytes = 0;

		if ( $num_files > $max_files ) {
			$zip->close();
			return new WP_Error(
				'zip_too_many_files',
				sprintf(
					/* translators: 1: File count, 2: Maximum allowed files */
					__( 'ZIP archive contains %1$d files, which exceeds the limit of %2$d files.', 'wpcalibrate-catalog-importer' ),
					$num_files,
					$max_files
				)
			);
		}

		for ( $i = 0; $i < $num_files; $i++ ) {
			$stat = $zip->statIndex( $i );
			if ( ! $stat ) {
				continue;
			}

			$entry_name = (string) $stat['name'];

			// Path traversal check (Zip Slip defense).
			if ( str_contains( $entry_name, '..' ) || str_starts_with( $entry_name, '/' ) || str_starts_with( $entry_name, '\\' ) || preg_match( '/^[a-zA-Z]:/', $entry_name ) ) {
				$zip->close();
				return new WP_Error( 'zip_traversal_detected', __( 'ZIP archive contains unsafe path traversal references.', 'wpcalibrate-catalog-importer' ) );
			}

			// Skip directory entries.
			if ( str_ends_with( $entry_name, '/' ) || str_ends_with( $entry_name, '\\' ) ) {
				continue;
			}

			// Check file extension.
			$ext = strtolower( pathinfo( $entry_name, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, self::ALLOWED_EXTENSIONS, true ) ) {
				// We can ignore non-image files or reject if dangerous.
				if ( in_array( $ext, [ 'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'phar', 'cgi', 'pl', 'py', 'exe', 'sh', 'bat', 'cmd', 'js', 'html', 'htm' ], true ) ) {
					$zip->close();
					return new WP_Error( 'zip_executable_detected', __( 'ZIP archive contains dangerous executable or script files.', 'wpcalibrate-catalog-importer' ) );
				}
			}

			$uncompressed = (int) $stat['size'];
			$compressed   = (int) $stat['comp_size'];

			// Decompression bomb check:
			if ( $compressed > 0 && ( $uncompressed / $compressed ) > self::MAX_COMPRESSION_RATIO && $uncompressed > 10485760 ) {
				$zip->close();
				return new WP_Error( 'zip_bomb_detected', __( 'ZIP archive exceeds safe compression ratio limits.', 'wpcalibrate-catalog-importer' ) );
			}

			$total_bytes += $uncompressed;
			if ( $total_bytes > $max_bytes ) {
				$zip->close();
				return new WP_Error(
					'zip_size_exceeded',
					sprintf(
						/* translators: %s: Maximum allowed size */
						__( 'Total uncompressed size of the ZIP archive exceeds the maximum limit of %s.', 'wpcalibrate-catalog-importer' ),
						size_format( $max_bytes )
					)
				);
			}
		}

		$zip->close();
		return true;
	}

	/**
	 * Extract safe image files from ZIP to target directory and return index of images.
	 *
	 * @param string $zip_path Absolute path to ZIP file.
	 * @param string $target_dir Target extraction directory.
	 * @return array<string, list<string>>|WP_Error Map of SKU/base-name => list of absolute extracted filepaths.
	 */
	public function extract_and_index( string $zip_path, string $target_dir ): array|WP_Error {
		$validation = $this->validate_zip( $zip_path );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		if ( ! wp_mkdir_p( $target_dir ) ) {
			return new WP_Error( 'mkdir_failed', __( 'Could not create temporary directory for image extraction.', 'wpcalibrate-catalog-importer' ) );
		}

		// Ensure directory is protected with index.html and .htaccess.
		file_put_contents( trailingslashit( $target_dir ) . 'index.html', '' );
		if ( ! file_exists( trailingslashit( $target_dir ) . '.htaccess' ) ) {
			file_put_contents( trailingslashit( $target_dir ) . '.htaccess', "Deny from all\n<FilesMatch \"\.(?i:jpe?g|png|gif|webp|avif)$\">\nAllow from all\n</FilesMatch>\n" );
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			return new WP_Error( 'zip_open_failed', __( 'Could not open ZIP archive for extraction.', 'wpcalibrate-catalog-importer' ) );
		}

		$image_index = []; // [ 'sku_or_basename' => [ 'file_path_1', 'file_path_2' ] ]

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$stat = $zip->statIndex( $i );
			if ( ! $stat ) {
				continue;
			}

			$entry_name = (string) $stat['name'];

			// Skip directories or traversal.
			if ( str_ends_with( $entry_name, '/' ) || str_ends_with( $entry_name, '\\' ) || str_contains( $entry_name, '..' ) ) {
				continue;
			}

			$ext = strtolower( pathinfo( $entry_name, PATHINFO_EXTENSION ) );
			if ( ! in_array( $ext, self::ALLOWED_EXTENSIONS, true ) ) {
				continue;
			}

			// Clean filename to prevent filesystem path issues.
			$base_filename = wp_basename( $entry_name );
			$clean_name    = sanitize_file_name( $base_filename );

			if ( empty( $clean_name ) ) {
				continue;
			}

			$dest_file = trailingslashit( $target_dir ) . $clean_name;

			// Extract stream safely.
			$fp_in = $zip->getStream( $entry_name );
			if ( ! $fp_in ) {
				continue;
			}

			$fp_out = fopen( $dest_file, 'wb' );
			if ( $fp_out ) {
				while ( ! feof( $fp_in ) ) {
					fwrite( $fp_out, fread( $fp_in, 8192 ) );
				}
				fclose( $fp_out );
			}
			fclose( $fp_in );

			if ( file_exists( $dest_file ) ) {
				// Parse SKU or identifier from filename.
				// Conventions supported:
				// - "SKU123.jpg" -> key "sku123"
				// - "SKU123_1.jpg", "SKU123-2.jpg" -> key "sku123" (gallery item)
				// - Also index full clean filename: "sku123.jpg"
				$name_without_ext = pathinfo( $clean_name, PATHINFO_FILENAME );
				$normalized_name  = strtolower( trim( $name_without_ext ) );

				// Index exact filename.
				$image_index[ strtolower( $clean_name ) ][] = $dest_file;
				$image_index[ $normalized_name ][]          = $dest_file;

				// Index by SKU prefix if has suffix like _1, _2, -1, -2.
				if ( preg_match( '/^(.*?)[-_]([0-9]+)$/', $normalized_name, $matches ) ) {
					$base_sku = $matches[1];
					$image_index[ $base_sku ][] = $dest_file;
				}
			}
		}

		$zip->close();

		// Sort all matched file arrays deterministically.
		foreach ( $image_index as $k => &$file_list ) {
			sort( $file_list, SORT_NATURAL | SORT_FLAG_CASE );
			$file_list = array_values( array_unique( $file_list ) );
		}

		return $image_index;
	}

	/**
	 * Clean temporary directory and all files inside.
	 *
	 * @param string $dir Path to directory.
	 * @return void
	 */
	public static function clean_directory( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = scandir( $dir );
		if ( ! $files ) {
			return;
		}

		foreach ( $files as $file ) {
			if ( '.' === $file || '..' === $file ) {
				continue;
			}
			$path = trailingslashit( $dir ) . $file;
			if ( is_dir( $path ) ) {
				self::clean_directory( $path );
			} else {
				@unlink( $path );
			}
		}

		@rmdir( $dir );
	}
}
