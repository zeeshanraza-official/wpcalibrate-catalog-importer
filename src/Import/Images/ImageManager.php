<?php
namespace WPCalibrate\CatalogImporter\Import\Images;

use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates featured and gallery image resolution across URLs, Media Library, and ZIP archives.
 */
class ImageManager {

	protected RemoteImageDownloader $remote_downloader;

	/**
	 * Map of ZIP extracted files: SKU/basename => list of filepaths.
	 *
	 * @var array<string, list<string>>
	 */
	protected array $zip_image_index = [];

	/**
	 * Constructor.
	 *
	 * @param RemoteImageDownloader|null $downloader
	 * @param array<string, list<string>> $zip_index
	 */
	public function __construct( ?RemoteImageDownloader $downloader = null, array $zip_index = [] ) {
		$this->remote_downloader = $downloader ?: new RemoteImageDownloader();
		$this->zip_image_index   = $zip_index;
	}

	/**
	 * Set ZIP images index.
	 *
	 * @param array $index
	 * @return void
	 */
	public function set_zip_index( array $index ): void {
		$this->zip_image_index = $index;
	}

	/**
	 * Resolve a single image reference (URL, ID, or ZIP filename) to a WordPress attachment ID.
	 *
	 * @param string $ref Image reference.
	 * @param int $parent_post_id Product post ID.
	 * @param array $options Options.
	 * @return int Attachment ID or 0.
	 */
	public function resolve_image_to_attachment_id( string $ref, int $parent_post_id = 0, array $options = [] ): int {
		$ref = trim( $ref );
		if ( '' === $ref ) {
			return 0;
		}

		// 1. Numeric attachment ID.
		if ( is_numeric( $ref ) && (int) $ref > 0 ) {
			$post = get_post( (int) $ref );
			if ( $post && 'attachment' === $post->post_type ) {
				return (int) $ref;
			}
		}

		// 2. HTTP/HTTPS URL.
		if ( str_starts_with( $ref, 'http://' ) || str_starts_with( $ref, 'https://' ) ) {
			// Check if URL belongs to local site media library.
			$site_url = site_url();
			if ( str_starts_with( $ref, $site_url ) && function_exists( 'attachment_url_to_postid' ) ) {
				$local_id = attachment_url_to_postid( $ref );
				if ( $local_id > 0 ) {
					return $local_id;
				}
			}

			// Remote download.
			if ( ! empty( $options['enable_remote_images'] ) || ! isset( $options['enable_remote_images'] ) ) {
				$att_id = $this->remote_downloader->download_image( $ref, $parent_post_id, $options );
				if ( ! is_wp_error( $att_id ) && $att_id > 0 ) {
					return $att_id;
				}
			}
			return 0;
		}

		// 3. Match from ZIP image index.
		$normalized_ref = strtolower( wp_basename( $ref ) );
		$matched_file   = null;

		if ( isset( $this->zip_image_index[ $normalized_ref ] ) ) {
			$matched_file = $this->zip_image_index[ $normalized_ref ][0] ?? null;
		} else {
			$name_no_ext = pathinfo( $normalized_ref, PATHINFO_FILENAME );
			if ( isset( $this->zip_image_index[ $name_no_ext ] ) ) {
				$matched_file = $this->zip_image_index[ $name_no_ext ][0] ?? null;
			}
		}

		if ( $matched_file && file_exists( $matched_file ) ) {
			return $this->import_local_file_to_attachment( $matched_file, $parent_post_id );
		}

		// 4. Try matching existing media library attachment by filename.
		return $this->find_attachment_by_filename( $ref );
	}

	/**
	 * Import local extracted file into Media Library.
	 *
	 * @param string $file_path Absolute path to file.
	 * @param int $parent_post_id Product post ID.
	 * @return int Attachment ID or 0.
	 */
	protected function import_local_file_to_attachment( string $file_path, int $parent_post_id = 0 ): int {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$filename = wp_basename( $file_path );
		$wp_upload = wp_upload_dir();

		if ( ! empty( $wp_upload['error'] ) ) {
			return 0;
		}

		$dest_filename = wp_unique_filename( $wp_upload['path'], $filename );
		$dest_path     = trailingslashit( $wp_upload['path'] ) . $dest_filename;

		if ( ! copy( $file_path, $dest_path ) ) {
			return 0;
		}

		$file_info = wp_check_filetype( $dest_path );
		$attachment = [
			'post_mime_type' => $file_info['type'],
			'post_title'     => preg_replace( '/\.[^.]+$/', '', $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		];

		$att_id = wp_insert_attachment( $attachment, $dest_path, $parent_post_id );
		if ( ! is_wp_error( $att_id ) && $att_id > 0 ) {
			$metadata = wp_generate_attachment_metadata( $att_id, $dest_path );
			wp_update_attachment_metadata( $att_id, $metadata );
			return (int) $att_id;
		}

		return 0;
	}

	/**
	 * Find attachment by filename in WordPress Media Library.
	 *
	 * @param string $filename
	 * @return int Attachment ID or 0.
	 */
	protected function find_attachment_by_filename( string $filename ): int {
		global $wpdb;

		$clean = wp_basename( trim( $filename ) );
		if ( '' === $clean ) {
			return 0;
		}

		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 1",
				'%' . $wpdb->esc_like( $clean )
			)
		);

		return (int) $id;
	}

	/**
	 * Apply images to WooCommerce product.
	 *
	 * @param WC_Product $product
	 * @param array $fields Normalized fields.
	 * @param array $options Options.
	 * @return void
	 */
	public function apply_product_images( WC_Product $product, array $fields, array $options = [] ): void {
		$featured_ref = $fields['image_featured'] ?? '';
		$gallery_refs = $fields['image_gallery'] ?? [];

		// If a generic "images" list was provided:
		if ( ! empty( $fields['images'] ) && empty( $featured_ref ) && empty( $gallery_refs ) ) {
			$all_images = is_array( $fields['images'] ) ? $fields['images'] : preg_split( '/[|,]/', (string) $fields['images'] );
			$all_images = array_values( array_filter( array_map( 'trim', (array) $all_images ) ) );

			if ( ! empty( $all_images ) ) {
				$featured_ref = array_shift( $all_images );
				$gallery_refs = $all_images;
			}
		}

		// Also check SKU matching in ZIP if no explicit image fields are provided.
		$sku = $product->get_sku();
		if ( empty( $featured_ref ) && '' !== $sku && ! empty( $this->zip_image_index ) ) {
			$sku_lower = strtolower( $sku );
			if ( isset( $this->zip_image_index[ $sku_lower ] ) ) {
				$zip_files    = $this->zip_image_index[ $sku_lower ];
				$featured_ref = array_shift( $zip_files );
				if ( empty( $gallery_refs ) && ! empty( $zip_files ) ) {
					$gallery_refs = $zip_files;
				}
			}
		}

		// Featured image.
		if ( '' !== $featured_ref ) {
			$featured_id = $this->resolve_image_to_attachment_id( (string) $featured_ref, $product->get_id(), $options );
			if ( $featured_id > 0 ) {
				$product->set_image_id( $featured_id );
			}
		}

		// Gallery images.
		if ( ! empty( $gallery_refs ) ) {
			$gallery_ids = [];
			foreach ( (array) $gallery_refs as $g_ref ) {
				$g_id = $this->resolve_image_to_attachment_id( (string) $g_ref, $product->get_id(), $options );
				if ( $g_id > 0 ) {
					$gallery_ids[] = $g_id;
				}
			}
			if ( ! empty( $gallery_ids ) ) {
				$product->set_gallery_image_ids( array_unique( $gallery_ids ) );
			}
		}
	}
}
