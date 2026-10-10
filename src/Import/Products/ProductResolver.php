<?php
namespace WPCalibrate\CatalogImporter\Import\Products;

use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves existing products and parents by SKU or ID with in-memory caching.
 */
class ProductResolver {

	/**
	 * In-memory cache by SKU.
	 *
	 * @var array<string, int>
	 */
	protected array $sku_cache = [];

	/**
	 * In-memory cache by Product ID.
	 *
	 * @var array<int, bool>
	 */
	protected array $id_cache = [];

	/**
	 * Resolve existing product ID by SKU.
	 *
	 * @param string $sku Product SKU.
	 * @return int 0 if not found, product ID otherwise.
	 */
	public function find_by_sku( string $sku ): int {
		$clean_sku = trim( $sku );
		if ( '' === $clean_sku ) {
			return 0;
		}

		if ( isset( $this->sku_cache[ $clean_sku ] ) ) {
			return $this->sku_cache[ $clean_sku ];
		}

		$product_id = wc_get_product_id_by_sku( $clean_sku );
		$this->sku_cache[ $clean_sku ] = (int) $product_id;

		return (int) $product_id;
	}

	/**
	 * Check if product ID exists and is a valid product.
	 *
	 * @param int $product_id Post ID.
	 * @return bool
	 */
	public function exists_by_id( int $product_id ): bool {
		if ( $product_id <= 0 ) {
			return false;
		}

		if ( isset( $this->id_cache[ $product_id ] ) ) {
			return $this->id_cache[ $product_id ];
		}

		$post = get_post( $product_id );
		$exists = ( $post && in_array( $post->post_type, [ 'product', 'product_variation' ], true ) );

		$this->id_cache[ $product_id ] = $exists;
		return $exists;
	}

	/**
	 * Resolve a product by configured identifier type.
	 *
	 * @param string $identifier_type 'sku' or 'id'.
	 * @param array $fields Normalized fields array.
	 * @return int Resolved product ID or 0.
	 */
	public function resolve_product_id( string $identifier_type, array $fields ): int {
		if ( 'id' === $identifier_type && ! empty( $fields['id'] ) ) {
			$id = (int) $fields['id'];
			return $this->exists_by_id( $id ) ? $id : 0;
		}

		if ( ! empty( $fields['sku'] ) ) {
			return $this->find_by_sku( (string) $fields['sku'] );
		}

		return 0;
	}

	/**
	 * Resolve parent product ID for a variation.
	 *
	 * @param array $fields Normalized fields array.
	 * @return int Parent product ID or 0.
	 */
	public function resolve_parent_id( array $fields ): int {
		if ( ! empty( $fields['parent_id'] ) ) {
			$parent_id = (int) $fields['parent_id'];
			if ( $this->exists_by_id( $parent_id ) ) {
				return $parent_id;
			}
		}

		if ( ! empty( $fields['parent_sku'] ) ) {
			return $this->find_by_sku( (string) $fields['parent_sku'] );
		}

		return 0;
	}

	/**
	 * Clear in-memory caches between batches.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->sku_cache = [];
		$this->id_cache  = [];
	}
}
