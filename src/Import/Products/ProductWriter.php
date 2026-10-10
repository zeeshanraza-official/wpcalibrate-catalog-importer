<?php
namespace WPCalibrate\CatalogImporter\Import\Products;

use WC_Product;
use WC_Product_Simple;
use WC_Product_Variable;
use WC_Product_Variation;
use WC_Product_Grouped;
use WC_Product_External;
use WC_Product_Download;
use WPCalibrate\CatalogImporter\Import\Images\ImageManager;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Executes WooCommerce product creation and updates strictly using WooCommerce CRUD APIs.
 */
class ProductWriter {

	protected ProductResolver $resolver;
	protected TaxonomyResolver $taxonomy_resolver;
	protected AttributeResolver $attribute_resolver;
	protected ImageManager $image_manager;

	/**
	 * Constructor.
	 */
	public function __construct(
		?ProductResolver $resolver = null,
		?TaxonomyResolver $taxonomy_resolver = null,
		?AttributeResolver $attribute_resolver = null,
		?ImageManager $image_manager = null
	) {
		$this->resolver           = $resolver ?: new ProductResolver();
		$this->taxonomy_resolver  = $taxonomy_resolver ?: new TaxonomyResolver();
		$this->attribute_resolver = $attribute_resolver ?: new AttributeResolver();
		$this->image_manager      = $image_manager ?: new ImageManager();
	}

	/**
	 * Set ImageManager instance.
	 *
	 * @param ImageManager $manager
	 * @return void
	 */
	public function set_image_manager( ImageManager $manager ): void {
		$this->image_manager = $manager;
	}

	/**
	 * Write (create or update) a product from normalized payload.
	 *
	 * @param array $normalized Normalized row data.
	 * @param string $action 'create' or 'update'.
	 * @param int $existing_id Existing product ID (if update).
	 * @param array $options Import options.
	 * @return array{product_id: int, action_taken: string}|WP_Error
	 */
	public function write_product( array $normalized, string $action, int $existing_id = 0, array $options = [] ): array|WP_Error {
		$fields = $normalized['fields'];
		$type   = $fields['type'] ?? 'simple';

		if ( 'variation' === $type ) {
			return $this->write_variation( $normalized, $action, $existing_id, $options );
		}

		// Instantiate or load product.
		if ( 'update' === $action && $existing_id > 0 ) {
			$product = wc_get_product( $existing_id );
			if ( ! $product ) {
				return new WP_Error( 'product_not_found', sprintf( __( 'Could not load existing product ID %d.', 'wpcalibrate-catalog-importer' ), $existing_id ) );
			}
		} else {
			$product = match ( $type ) {
				'variable' => new WC_Product_Variable(),
				'grouped'  => new WC_Product_Grouped(),
				'external' => new WC_Product_External(),
				default    => new WC_Product_Simple(),
			};
		}

		// Update core fields only if present (blank values do not erase existing data unless option enabled).
		$allow_clear = ! empty( $options['allow_clear_values'] );

		if ( isset( $fields['name'] ) && ( '' !== $fields['name'] || $allow_clear ) ) {
			$product->set_name( wp_strip_all_tags( $fields['name'] ) );
		}
		if ( isset( $fields['slug'] ) && '' !== $fields['slug'] ) {
			$product->set_slug( sanitize_title( $fields['slug'] ) );
		}
		if ( isset( $fields['status'] ) && '' !== $fields['status'] ) {
			$product->set_status( $fields['status'] );
		}
		if ( isset( $fields['featured'] ) ) {
			$product->set_featured( (bool) $fields['featured'] );
		}
		if ( isset( $fields['catalog_visibility'] ) && '' !== $fields['catalog_visibility'] ) {
			$product->set_catalog_visibility( $fields['catalog_visibility'] );
		}
		if ( isset( $fields['description'] ) && ( '' !== $fields['description'] || $allow_clear ) ) {
			$product->set_description( wp_kses_post( $fields['description'] ) );
		}
		if ( isset( $fields['short_description'] ) && ( '' !== $fields['short_description'] || $allow_clear ) ) {
			$product->set_short_description( wp_kses_post( $fields['short_description'] ) );
		}
		if ( isset( $fields['sku'] ) && ( '' !== $fields['sku'] || $allow_clear ) ) {
			$product->set_sku( $fields['sku'] );
		}

		// Pricing (simple & external).
		if ( $product instanceof WC_Product_Simple || $product instanceof WC_Product_External ) {
			if ( isset( $fields['regular_price'] ) && ( '' !== $fields['regular_price'] || $allow_clear ) ) {
				$product->set_regular_price( $fields['regular_price'] );
			}
			if ( isset( $fields['sale_price'] ) && ( '' !== $fields['sale_price'] || $allow_clear ) ) {
				$product->set_sale_price( $fields['sale_price'] );
			}
			if ( isset( $fields['date_on_sale_from'] ) ) {
				$product->set_date_on_sale_from( $fields['date_on_sale_from'] ?: null );
			}
			if ( isset( $fields['date_on_sale_to'] ) ) {
				$product->set_date_on_sale_to( $fields['date_on_sale_to'] ?: null );
			}
		}

		// Inventory.
		if ( isset( $fields['manage_stock'] ) ) {
			$product->set_manage_stock( $fields['manage_stock'] );
		}
		if ( isset( $fields['stock_quantity'] ) && '' !== $fields['stock_quantity'] ) {
			$product->set_stock_quantity( $fields['stock_quantity'] );
		}
		if ( isset( $fields['stock_status'] ) && '' !== $fields['stock_status'] ) {
			$product->set_stock_status( $fields['stock_status'] );
		}
		if ( isset( $fields['backorders'] ) && '' !== $fields['backorders'] ) {
			$product->set_backorders( $fields['backorders'] );
		}
		if ( isset( $fields['sold_individually'] ) ) {
			$product->set_sold_individually( (bool) $fields['sold_individually'] );
		}

		// Tax & Shipping.
		if ( isset( $fields['tax_status'] ) && '' !== $fields['tax_status'] ) {
			$product->set_tax_status( $fields['tax_status'] );
		}
		if ( isset( $fields['tax_class'] ) ) {
			$product->set_tax_class( sanitize_title( $fields['tax_class'] ) );
		}
		if ( isset( $fields['shipping_class'] ) && '' !== $fields['shipping_class'] ) {
			$term = get_term_by( 'slug', sanitize_title( $fields['shipping_class'] ), 'product_shipping_class' );
			if ( ! $term ) {
				$term = get_term_by( 'name', $fields['shipping_class'], 'product_shipping_class' );
			}
			if ( $term ) {
				$product->set_shipping_class_id( (int) $term->term_id );
			}
		}
		if ( isset( $fields['weight'] ) && ( '' !== $fields['weight'] || $allow_clear ) ) {
			$product->set_weight( $fields['weight'] );
		}
		if ( isset( $fields['length'] ) && ( '' !== $fields['length'] || $allow_clear ) ) {
			$product->set_length( $fields['length'] );
		}
		if ( isset( $fields['width'] ) && ( '' !== $fields['width'] || $allow_clear ) ) {
			$product->set_width( $fields['width'] );
		}
		if ( isset( $fields['height'] ) && ( '' !== $fields['height'] || $allow_clear ) ) {
			$product->set_height( $fields['height'] );
		}

		// Virtual & Downloadable.
		if ( isset( $fields['virtual'] ) ) {
			$product->set_virtual( (bool) $fields['virtual'] );
		}
		if ( isset( $fields['downloadable'] ) ) {
			$product->set_downloadable( (bool) $fields['downloadable'] );
		}
		if ( isset( $fields['download_limit'] ) ) {
			$product->set_download_limit( (int) $fields['download_limit'] );
		}
		if ( isset( $fields['download_expiry'] ) ) {
			$product->set_download_expiry( (int) $fields['download_expiry'] );
		}
		if ( ! empty( $fields['downloadable_files'] ) ) {
			$this->apply_downloads( $product, $fields['downloadable_files'] );
		}

		// External product.
		if ( $product instanceof WC_Product_External ) {
			if ( isset( $fields['external_url'] ) && '' !== $fields['external_url'] ) {
				$product->set_product_url( esc_url_raw( $fields['external_url'] ) );
			}
			if ( isset( $fields['button_text'] ) && '' !== $fields['button_text'] ) {
				$product->set_button_text( sanitize_text_field( $fields['button_text'] ) );
			}
		}

		// Advanced & GTIN.
		if ( isset( $fields['menu_order'] ) ) {
			$product->set_menu_order( (int) $fields['menu_order'] );
		}
		if ( isset( $fields['purchase_note'] ) ) {
			$product->set_purchase_note( wp_kses_post( $fields['purchase_note'] ) );
		}
		if ( isset( $fields['gtin'] ) && '' !== $fields['gtin'] ) {
			$gtin_clean = sanitize_text_field( $fields['gtin'] );
			if ( method_exists( $product, 'set_global_unique_id' ) ) {
				$product->set_global_unique_id( $gtin_clean );
			} else {
				$product->update_meta_data( '_global_unique_id', $gtin_clean );
			}
		}

		// Taxonomies.
		$allow_create_terms = $options['create_categories'] ?? true;
		if ( ! empty( $fields['category_names'] ) ) {
			$cat_ids = $this->taxonomy_resolver->resolve_categories( (array) $fields['category_names'], (bool) $allow_create_terms );
			if ( ! empty( $cat_ids ) ) {
				$product->set_category_ids( $cat_ids );
			}
		}
		if ( ! empty( $fields['tag_names'] ) ) {
			$tag_ids = $this->taxonomy_resolver->resolve_tags( (array) $fields['tag_names'], (bool) ( $options['create_tags'] ?? true ) );
			if ( ! empty( $tag_ids ) ) {
				$product->set_tag_ids( $tag_ids );
			}
		}

		// Attributes.
		$all_attributes = $normalized['attributes'];
		if ( ! empty( $fields['attributes'] ) ) {
			$parsed_combined = AttributeResolver::parse_combined_string( (string) $fields['attributes'] );
			$all_attributes  = array_merge( $all_attributes, $parsed_combined );
		}
		if ( ! empty( $all_attributes ) ) {
			$is_variable = ( $product instanceof WC_Product_Variable );
			$attr_objs   = $this->attribute_resolver->build_product_attributes(
				$all_attributes,
				$is_variable,
				(bool) ( $options['create_attributes'] ?? true )
			);
			if ( ! empty( $attr_objs ) ) {
				$product->set_attributes( $attr_objs );
			}
		}

		// Custom Meta.
		if ( ! empty( $normalized['meta'] ) ) {
			foreach ( $normalized['meta'] as $meta_key => $meta_val ) {
				$product->update_meta_data( $meta_key, $meta_val );
			}
		}

		// Relationships (upsells, cross-sells, grouped children).
		if ( ! empty( $fields['upsell_skus'] ) ) {
			$upsell_ids = $this->resolve_linked_ids( (array) $fields['upsell_skus'] );
			if ( ! empty( $upsell_ids ) ) {
				$product->set_upsell_ids( $upsell_ids );
			}
		}
		if ( ! empty( $fields['cross_sell_skus'] ) ) {
			$cross_ids = $this->resolve_linked_ids( (array) $fields['cross_sell_skus'] );
			if ( ! empty( $cross_ids ) ) {
				$product->set_cross_sell_ids( $cross_ids );
			}
		}
		if ( $product instanceof WC_Product_Grouped && ! empty( $fields['grouped_children'] ) ) {
			$child_ids = $this->resolve_linked_ids( (array) $fields['grouped_children'] );
			if ( ! empty( $child_ids ) ) {
				$product->set_children( $child_ids );
			}
		}

		// Save product first to obtain ID before image attachments.
		$product_id = $product->save();
		if ( ! $product_id ) {
			return new WP_Error( 'product_save_failed', __( 'Failed to save product via WooCommerce CRUD.', 'wpcalibrate-catalog-importer' ) );
		}

		// Images.
		$this->image_manager->apply_product_images( $product, $fields, $options );
		$product->save();

		// Invalidate cache.
		wc_delete_product_transients( $product_id );

		return [
			'product_id'   => (int) $product_id,
			'action_taken' => ( 'update' === $action ) ? 'updated' : 'created',
		];
	}

	/**
	 * Write a product variation.
	 *
	 * @param array $normalized
	 * @param string $action
	 * @param int $existing_id
	 * @param array $options
	 * @return array{product_id: int, action_taken: string}|WP_Error
	 */
	protected function write_variation( array $normalized, string $action, int $existing_id = 0, array $options = [] ): array|WP_Error {
		$fields = $normalized['fields'];

		// Resolve parent.
		$parent_id = $this->resolver->resolve_parent_id( $fields );
		if ( $parent_id <= 0 ) {
			return new WP_Error( 'missing_parent', sprintf( __( 'Parent product for variation "%s" could not be resolved.', 'wpcalibrate-catalog-importer' ), $fields['sku'] ?? 'N/A' ) );
		}

		$parent = wc_get_product( $parent_id );
		if ( ! $parent || ! ( $parent instanceof WC_Product_Variable ) ) {
			return new WP_Error( 'invalid_parent_type', sprintf( __( 'Parent product ID %d is not a Variable Product.', 'wpcalibrate-catalog-importer' ), $parent_id ) );
		}

		if ( 'update' === $action && $existing_id > 0 ) {
			$variation = wc_get_product( $existing_id );
			if ( ! $variation || ! ( $variation instanceof WC_Product_Variation ) ) {
				return new WP_Error( 'variation_not_found', sprintf( __( 'Existing variation ID %d not found.', 'wpcalibrate-catalog-importer' ), $existing_id ) );
			}
		} else {
			$variation = new WC_Product_Variation();
			$variation->set_parent_id( $parent_id );
		}

		$allow_clear = ! empty( $options['allow_clear_values'] );

		if ( isset( $fields['sku'] ) && ( '' !== $fields['sku'] || $allow_clear ) ) {
			$variation->set_sku( $fields['sku'] );
		}
		if ( isset( $fields['status'] ) && '' !== $fields['status'] ) {
			$variation->set_status( $fields['status'] );
		}
		if ( isset( $fields['description'] ) && ( '' !== $fields['description'] || $allow_clear ) ) {
			$variation->set_description( wp_kses_post( $fields['description'] ) );
		}

		// Prices.
		if ( isset( $fields['regular_price'] ) && ( '' !== $fields['regular_price'] || $allow_clear ) ) {
			$variation->set_regular_price( $fields['regular_price'] );
		}
		if ( isset( $fields['sale_price'] ) && ( '' !== $fields['sale_price'] || $allow_clear ) ) {
			$variation->set_sale_price( $fields['sale_price'] );
		}
		if ( isset( $fields['date_on_sale_from'] ) ) {
			$variation->set_date_on_sale_from( $fields['date_on_sale_from'] ?: null );
		}
		if ( isset( $fields['date_on_sale_to'] ) ) {
			$variation->set_date_on_sale_to( $fields['date_on_sale_to'] ?: null );
		}

		// Inventory.
		if ( isset( $fields['manage_stock'] ) ) {
			$variation->set_manage_stock( $fields['manage_stock'] );
		}
		if ( isset( $fields['stock_quantity'] ) && '' !== $fields['stock_quantity'] ) {
			$variation->set_stock_quantity( $fields['stock_quantity'] );
		}
		if ( isset( $fields['stock_status'] ) && '' !== $fields['stock_status'] ) {
			$variation->set_stock_status( $fields['stock_status'] );
		}
		if ( isset( $fields['backorders'] ) && '' !== $fields['backorders'] ) {
			$variation->set_backorders( $fields['backorders'] );
		}

		// Shipping & Dimensions.
		if ( isset( $fields['weight'] ) && ( '' !== $fields['weight'] || $allow_clear ) ) {
			$variation->set_weight( $fields['weight'] );
		}
		if ( isset( $fields['length'] ) && ( '' !== $fields['length'] || $allow_clear ) ) {
			$variation->set_length( $fields['length'] );
		}
		if ( isset( $fields['width'] ) && ( '' !== $fields['width'] || $allow_clear ) ) {
			$variation->set_width( $fields['width'] );
		}
		if ( isset( $fields['height'] ) && ( '' !== $fields['height'] || $allow_clear ) ) {
			$variation->set_height( $fields['height'] );
		}
		if ( isset( $fields['tax_class'] ) ) {
			$variation->set_tax_class( sanitize_title( $fields['tax_class'] ) );
		}

		// Virtual & Downloadable.
		if ( isset( $fields['virtual'] ) ) {
			$variation->set_virtual( (bool) $fields['virtual'] );
		}
		if ( isset( $fields['downloadable'] ) ) {
			$variation->set_downloadable( (bool) $fields['downloadable'] );
		}

		// Variation Attributes mapping.
		$var_attrs = $normalized['attributes'];
		if ( ! empty( $fields['variation_attributes'] ) ) {
			$parsed    = AttributeResolver::parse_combined_string( (string) $fields['variation_attributes'] );
			$var_attrs = array_merge( $var_attrs, $parsed );
		}
		if ( ! empty( $var_attrs ) ) {
			$formatted_attrs = $this->attribute_resolver->build_variation_attributes( $var_attrs, $parent->get_attributes() );
			if ( ! empty( $formatted_attrs ) ) {
				$variation->set_attributes( $formatted_attrs );
			}
		}

		// Custom Meta.
		if ( ! empty( $normalized['meta'] ) ) {
			foreach ( $normalized['meta'] as $meta_key => $meta_val ) {
				$variation->update_meta_data( $meta_key, $meta_val );
			}
		}

		$var_id = $variation->save();
		if ( ! $var_id ) {
			return new WP_Error( 'variation_save_failed', __( 'Failed to save variation via WooCommerce CRUD.', 'wpcalibrate-catalog-importer' ) );
		}

		// Images.
		$this->image_manager->apply_product_images( $variation, $fields, $options );
		$variation->save();

		// Synchronize parent variable product.
		\WC_Product_Variable::sync( $parent_id );
		wc_delete_product_transients( $parent_id );
		wc_delete_product_transients( $var_id );

		return [
			'product_id'   => (int) $var_id,
			'action_taken' => ( 'update' === $action ) ? 'updated_variation' : 'created_variation',
		];
	}

	/**
	 * Parse and apply downloadable files to product.
	 *
	 * @param WC_Product $product
	 * @param mixed $download_data String or array.
	 * @return void
	 */
	protected function apply_downloads( WC_Product $product, mixed $download_data ): void {
		$downloads = [];

		if ( is_string( $download_data ) ) {
			$entries = preg_split( '/[|;]/', trim( $download_data ) );
			foreach ( (array) $entries as $entry ) {
				$entry = trim( (string) $entry );
				if ( '' === $entry ) {
					continue;
				}

				if ( str_contains( $entry, ':' ) && ! str_starts_with( $entry, 'http' ) ) {
					[ $name, $url ] = explode( ':', $entry, 2 );
				} else {
					$url  = $entry;
					$name = wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) ?: 'Download';
				}

				$download = new WC_Product_Download();
				$download->set_id( wp_generate_uuid4() );
				$download->set_name( sanitize_text_field( trim( $name ) ) );
				$download->set_file( esc_url_raw( trim( $url ) ) );
				$downloads[] = $download;
			}
		}

		if ( ! empty( $downloads ) ) {
			$product->set_downloads( $downloads );
		}
	}

	/**
	 * Resolve comma/pipe separated SKUs or IDs to array of valid product IDs.
	 *
	 * @param list<string> $identifiers
	 * @return list<int>
	 */
	protected function resolve_linked_ids( array $identifiers ): array {
		$ids = [];
		foreach ( $identifiers as $ident ) {
			$ident = trim( (string) $ident );
			if ( '' === $ident ) {
				continue;
			}

			if ( is_numeric( $ident ) && (int) $ident > 0 ) {
				$ids[] = (int) $ident;
			} else {
				$found_id = $this->resolver->find_by_sku( $ident );
				if ( $found_id > 0 ) {
					$ids[] = $found_id;
				}
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}
}
