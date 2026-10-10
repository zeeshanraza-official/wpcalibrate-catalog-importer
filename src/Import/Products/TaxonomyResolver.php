<?php
namespace WPCalibrate\CatalogImporter\Import\Products;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves and caches product categories and tags with hierarchical term support.
 */
class TaxonomyResolver {

	/**
	 * Cache of category slugs/names to term IDs.
	 *
	 * @var array<string, int>
	 */
	protected array $cat_cache = [];

	/**
	 * Cache of tag slugs/names to term IDs.
	 *
	 * @var array<string, int>
	 */
	protected array $tag_cache = [];

	/**
	 * Resolve category hierarchy (e.g. "Clothing > Men's > Shirts") to term IDs.
	 *
	 * @param list<string> $category_strings List of category strings.
	 * @param bool $allow_create Whether to create new terms if not found.
	 * @return list<int> Term IDs.
	 */
	public function resolve_categories( array $category_strings, bool $allow_create = true ): array {
		$term_ids = [];

		foreach ( $category_strings as $cat_str ) {
			$path_parts = array_map( 'trim', explode( '>', $cat_str ) );
			$parent_id  = 0;

			foreach ( $path_parts as $part ) {
				if ( '' === $part ) {
					continue;
				}

				$cache_key = strtolower( $part ) . ':' . $parent_id;

				if ( isset( $this->cat_cache[ $cache_key ] ) ) {
					$term_id = $this->cat_cache[ $cache_key ];
				} else {
					$term = term_exists( $part, 'product_cat', $parent_id );

					if ( $term ) {
						$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
					} elseif ( $allow_create ) {
						$created = wp_insert_term( $part, 'product_cat', [ 'parent' => $parent_id ] );
						if ( ! is_wp_error( $created ) && is_array( $created ) ) {
							$term_id = (int) $created['term_id'];
						} else {
							$term_id = 0;
						}
					} else {
						$term_id = 0;
					}

					$this->cat_cache[ $cache_key ] = $term_id;
				}

				if ( $term_id > 0 ) {
					$parent_id  = $term_id;
					$term_ids[] = $term_id;
				}
			}
		}

		return array_values( array_unique( array_filter( $term_ids ) ) );
	}

	/**
	 * Resolve product tags to term IDs.
	 *
	 * @param list<string> $tag_strings List of tag names.
	 * @param bool $allow_create Whether to create new tags if missing.
	 * @return list<int> Term IDs.
	 */
	public function resolve_tags( array $tag_strings, bool $allow_create = true ): array {
		$term_ids = [];

		foreach ( $tag_strings as $tag_str ) {
			$name = trim( $tag_str );
			if ( '' === $name ) {
				continue;
			}

			$cache_key = strtolower( $name );

			if ( isset( $this->tag_cache[ $cache_key ] ) ) {
				$term_id = $this->tag_cache[ $cache_key ];
			} else {
				$term = term_exists( $name, 'product_tag' );

				if ( $term ) {
					$term_id = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
				} elseif ( $allow_create ) {
					$created = wp_insert_term( $name, 'product_tag' );
					if ( ! is_wp_error( $created ) && is_array( $created ) ) {
						$term_id = (int) $created['term_id'];
					} else {
						$term_id = 0;
					}
				} else {
					$term_id = 0;
				}

				$this->tag_cache[ $cache_key ] = $term_id;
			}

			if ( $term_id > 0 ) {
				$term_ids[] = $term_id;
			}
		}

		return array_values( array_unique( array_filter( $term_ids ) ) );
	}

	/**
	 * Clear in-memory taxonomy caches.
	 *
	 * @return void
	 */
	public function clear_cache(): void {
		$this->cat_cache = [];
		$this->tag_cache = [];
	}
}
