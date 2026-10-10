<?php
namespace WPCalibrate\CatalogImporter\Import\Products;

use WC_Product_Attribute;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolves global taxonomies and product-level attributes for parent products and variations.
 */
class AttributeResolver {

	/**
	 * Parse combined attribute string (e.g. "Color:Red,Blue|Size:S,M,L").
	 *
	 * @param string $attr_string
	 * @return array<string, list<string>> Map of Attribute Name => list of values.
	 */
	public static function parse_combined_string( string $attr_string ): array {
		$result = [];
		$pairs  = preg_split( '/[|;]/', trim( $attr_string ) );

		if ( ! $pairs ) {
			return $result;
		}

		foreach ( $pairs as $pair ) {
			if ( ! str_contains( $pair, ':' ) && ! str_contains( $pair, '=' ) ) {
				continue;
			}

			$delimiter = str_contains( $pair, ':' ) ? ':' : '=';
			[ $name, $val_str ] = explode( $delimiter, $pair, 2 );

			$name = trim( $name );
			if ( '' === $name ) {
				continue;
			}

			$values = array_values( array_filter( array_map( 'trim', explode( ',', $val_str ) ) ) );
			if ( ! empty( $values ) ) {
				$result[ $name ] = $values;
			}
		}

		return $result;
	}

	/**
	 * Build WC_Product_Attribute array for a parent simple or variable product.
	 *
	 * @param array<string, mixed> $attributes_map Map of Name => string or array of values.
	 * @param bool $is_variable Whether product is variable.
	 * @param bool $allow_create_global Whether to register new global attributes.
	 * @return array<string, WC_Product_Attribute>
	 */
	public function build_product_attributes( array $attributes_map, bool $is_variable = false, bool $allow_create_global = true ): array {
		$product_attributes = [];
		$position           = 0;

		foreach ( $attributes_map as $name => $values ) {
			$name = trim( (string) $name );
			if ( '' === $name ) {
				continue;
			}

			if ( ! is_array( $values ) ) {
				$values = array_values( array_filter( array_map( 'trim', explode( ',', (string) $values ) ) ) );
			}

			if ( empty( $values ) ) {
				continue;
			}

			$taxonomy_slug = wc_attribute_taxonomy_name( $name );
			$is_global     = taxonomy_exists( $taxonomy_slug );

			if ( ! $is_global && $allow_create_global && function_exists( 'wc_create_attribute' ) ) {
				// Try creating global attribute if permitted.
				$attr_id = wc_create_attribute( [
					'name'         => $name,
					'slug'         => sanitize_title( $name ),
					'type'         => 'select',
					'order_by'     => 'menu_order',
					'has_archives' => false,
				] );

				if ( ! is_wp_error( $attr_id ) && $attr_id > 0 ) {
					// Register taxonomy dynamically in current execution.
					register_taxonomy( $taxonomy_slug, 'product', [ 'hierarchical' => false, 'show_ui' => false ] );
					$is_global = true;
				}
			}

			$attr = new WC_Product_Attribute();
			$attr->set_position( $position++ );
			$attr->set_visible( true );
			$attr->set_variation( $is_variable );

			if ( $is_global ) {
				$attr->set_id( wc_attribute_taxonomy_id_by_name( $name ) );
				$attr->set_name( $taxonomy_slug );

				$term_ids = [];
				foreach ( $values as $val ) {
					$term = term_exists( $val, $taxonomy_slug );
					if ( $term ) {
						$term_ids[] = is_array( $term ) ? (int) $term['term_id'] : (int) $term;
					} else {
						$new_term = wp_insert_term( $val, $taxonomy_slug );
						if ( ! is_wp_error( $new_term ) && is_array( $new_term ) ) {
							$term_ids[] = (int) $new_term['term_id'];
						}
					}
				}

				$attr->set_options( $term_ids );
				$product_attributes[ $taxonomy_slug ] = $attr;
			} else {
				// Local product attribute.
				$attr->set_id( 0 );
				$attr->set_name( $name );
				$attr->set_options( $values );
				$product_attributes[ sanitize_title( $name ) ] = $attr;
			}
		}

		return $product_attributes;
	}

	/**
	 * Build variation attribute values map for WC_Product_Variation.
	 *
	 * @param array $variation_attrs Raw or mapped variation attributes (e.g. ['Color' => 'Red', 'Size' => 'M']).
	 * @param array<string, WC_Product_Attribute> $parent_attributes
	 * @return array<string, string> Attribute key => slug value.
	 */
	public function build_variation_attributes( array $variation_attrs, array $parent_attributes = [] ): array {
		$result = [];

		foreach ( $variation_attrs as $name => $value ) {
			$name  = trim( (string) $name );
			$value = trim( (string) $value );

			if ( '' === $name || '' === $value ) {
				continue;
			}

			$taxonomy_slug = wc_attribute_taxonomy_name( $name );

			if ( taxonomy_exists( $taxonomy_slug ) ) {
				// Global attribute: WooCommerce expects sanitized term slug.
				$term = get_term_by( 'name', $value, $taxonomy_slug );
				$val_slug = $term ? $term->slug : sanitize_title( $value );
				$result[ $taxonomy_slug ] = $val_slug;
			} else {
				// Custom attribute: WooCommerce uses sanitize_title( name ).
				$result[ sanitize_title( $name ) ] = $value;
			}
		}

		return $result;
	}
}
