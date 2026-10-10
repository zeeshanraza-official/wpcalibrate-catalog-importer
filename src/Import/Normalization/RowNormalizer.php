<?php
namespace WPCalibrate\CatalogImporter\Import\Normalization;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Normalizes mapped values into standard WooCommerce data types and formats.
 */
class RowNormalizer {

	/**
	 * Normalize a boolean representation into 1/0 or 'yes'/'no'.
	 *
	 * @param mixed $value
	 * @param bool $return_yes_no If true returns 'yes'/'no' instead of bool/int.
	 * @return string|bool
	 */
	public static function normalize_bool( mixed $value, bool $return_yes_no = false ): string|bool {
		if ( is_bool( $value ) ) {
			$bool = $value;
		} else {
			$lower = strtolower( trim( (string) $value ) );
			$bool  = in_array( $lower, [ '1', 'yes', 'true', 'y', 'on' ], true );
		}

		if ( $return_yes_no ) {
			return $bool ? 'yes' : 'no';
		}

		return $bool;
	}

	/**
	 * Normalize prices (stripping currency symbols, handling comma decimals).
	 *
	 * @param mixed $value
	 * @return string Clean decimal string or '' if empty.
	 */
	public static function normalize_price( mixed $value ): string {
		if ( null === $value || '' === $value ) {
			return '';
		}

		$str = trim( (string) $value );
		// Strip currency symbols and letters (e.g. $, €, £, USD, etc.)
		$str = preg_replace( '/[^\d,.-]/', '', $str );

		if ( '' === $str ) {
			return '';
		}

		// Handle comma decimal vs comma thousand separator:
		// If format is like "1,234.56" -> remove comma.
		// If format is like "1.234,56" -> remove period, replace comma with period.
		// If format is like "12,99" -> replace comma with period.
		if ( preg_match( '/^\d{1,3}(?:\.\d{3})*,\d{1,4}$/', $str ) ) {
			$str = str_replace( '.', '', $str );
			$str = str_replace( ',', '.', $str );
		} elseif ( preg_match( '/^\d+,\d{1,4}$/', $str ) ) {
			$str = str_replace( ',', '.', $str );
		} else {
			$str = str_replace( ',', '', $str );
		}

		$val = (float) $str;
		return (string) max( 0, $val );
	}

	/**
	 * Normalize product status.
	 *
	 * @param string $status
	 * @param string $default
	 * @return string
	 */
	public static function normalize_status( string $status, string $default = 'publish' ): string {
		$status = strtolower( trim( $status ) );
		$valid  = [ 'publish', 'draft', 'pending', 'private' ];

		return in_array( $status, $valid, true ) ? $status : $default;
	}

	/**
	 * Normalize product type.
	 *
	 * @param string $type
	 * @param array $fields
	 * @return string
	 */
	public static function normalize_type( string $type, array $fields = [] ): string {
		$type  = strtolower( trim( $type ) );
		$valid = [ 'simple', 'variable', 'variation', 'grouped', 'external' ];

		if ( in_array( $type, $valid, true ) ) {
			return $type;
		}

		// If row has parent SKU/ID or variation attributes, deduce as variation.
		if ( ! empty( $fields['parent_sku'] ) || ! empty( $fields['parent_id'] ) || ! empty( $fields['variation_attributes'] ) ) {
			return 'variation';
		}

		return 'simple';
	}

	/**
	 * Normalize stock status.
	 *
	 * @param string $status
	 * @param float|int|string $qty
	 * @return string 'instock', 'outofstock', or 'onbackorder'
	 */
	public static function normalize_stock_status( string $status, mixed $qty = '' ): string {
		$status = strtolower( trim( $status ) );
		$map    = [
			'instock'     => 'instock',
			'in_stock'    => 'instock',
			'in stock'    => 'instock',
			'yes'         => 'instock',
			'1'           => 'instock',
			'outofstock'  => 'outofstock',
			'out_of_stock'=> 'outofstock',
			'out of stock'=> 'outofstock',
			'no'          => 'outofstock',
			'0'           => 'outofstock',
			'onbackorder' => 'onbackorder',
			'backorder'   => 'onbackorder',
		];

		if ( isset( $map[ $status ] ) ) {
			return $map[ $status ];
		}

		// Infer from qty if numeric.
		if ( is_numeric( $qty ) ) {
			return ( (float) $qty > 0 ) ? 'instock' : 'outofstock';
		}

		return 'instock';
	}

	/**
	 * Normalize backorders setting.
	 *
	 * @param string $value
	 * @return string 'no', 'notify', or 'yes'
	 */
	public static function normalize_backorders( string $value ): string {
		$val = strtolower( trim( $value ) );
		if ( in_array( $val, [ 'notify', 'notify_customer' ], true ) ) {
			return 'notify';
		}
		if ( in_array( $val, [ 'yes', '1', 'true', 'allow' ], true ) ) {
			return 'yes';
		}
		return 'no';
	}

	/**
	 * Normalize date to YYYY-MM-DD.
	 *
	 * @param string $date_str
	 * @return string|null
	 */
	public static function normalize_date( string $date_str ): ?string {
		$str = trim( $date_str );
		if ( '' === $str ) {
			return null;
		}

		$time = strtotime( $str );
		return $time ? gmdate( 'Y-m-d', $time ) : null;
	}

	/**
	 * Normalize list strings (comma or pipe separated) into clean array of strings.
	 *
	 * @param mixed $value
	 * @return list<string>
	 */
	public static function normalize_list( mixed $value ): array {
		if ( is_array( $value ) ) {
			return array_values( array_filter( array_map( 'trim', $value ) ) );
		}

		$str = trim( (string) $value );
		if ( '' === $str ) {
			return [];
		}

		// Split on pipe or comma (support both).
		$parts = preg_split( '/[|,]/', $str );
		if ( ! $parts ) {
			return [ $str ];
		}

		$cleaned = [];
		foreach ( $parts as $p ) {
			$t = trim( $p );
			if ( '' !== $t ) {
				$cleaned[] = $t;
			}
		}

		return array_values( array_unique( $cleaned ) );
	}

	/**
	 * Normalize raw transformed payload.
	 *
	 * @param array $payload Result of MappingEngine::transform().
	 * @param array $options Import options.
	 * @return array Normalized payload.
	 */
	public function normalize( array $payload, array $options = [] ): array {
		$fields = $payload['fields'];

		// Core fields.
		$type   = self::normalize_type( $fields['type'] ?? '', $fields );
		$status = self::normalize_status( $fields['status'] ?? '', $options['default_status'] ?? 'publish' );

		$fields['type']   = $type;
		$fields['status'] = $status;

		if ( isset( $fields['sku'] ) ) {
			$fields['sku'] = trim( (string) $fields['sku'] );
		}
		if ( isset( $fields['parent_sku'] ) ) {
			$fields['parent_sku'] = trim( (string) $fields['parent_sku'] );
		}
		if ( isset( $fields['parent_id'] ) ) {
			$fields['parent_id'] = (int) $fields['parent_id'];
		}
		if ( isset( $fields['id'] ) ) {
			$fields['id'] = (int) $fields['id'];
		}

		// Prices.
		if ( isset( $fields['regular_price'] ) && '' !== $fields['regular_price'] ) {
			$fields['regular_price'] = self::normalize_price( $fields['regular_price'] );
		}
		if ( isset( $fields['sale_price'] ) && '' !== $fields['sale_price'] ) {
			$fields['sale_price'] = self::normalize_price( $fields['sale_price'] );
		}
		if ( isset( $fields['date_on_sale_from'] ) ) {
			$fields['date_on_sale_from'] = self::normalize_date( $fields['date_on_sale_from'] );
		}
		if ( isset( $fields['date_on_sale_to'] ) ) {
			$fields['date_on_sale_to'] = self::normalize_date( $fields['date_on_sale_to'] );
		}

		// Stock.
		if ( isset( $fields['manage_stock'] ) ) {
			$fields['manage_stock'] = self::normalize_bool( $fields['manage_stock'], true );
		}
		if ( isset( $fields['stock_quantity'] ) && '' !== $fields['stock_quantity'] ) {
			$fields['stock_quantity'] = (float) $fields['stock_quantity'];
		}
		if ( isset( $fields['stock_status'] ) || isset( $fields['stock_quantity'] ) ) {
			$fields['stock_status'] = self::normalize_stock_status(
				$fields['stock_status'] ?? '',
				$fields['stock_quantity'] ?? ''
			);
		}
		if ( isset( $fields['backorders'] ) ) {
			$fields['backorders'] = self::normalize_backorders( $fields['backorders'] );
		}
		if ( isset( $fields['sold_individually'] ) ) {
			$fields['sold_individually'] = self::normalize_bool( $fields['sold_individually'] );
		}

		// Virtual & Downloadable.
		if ( isset( $fields['virtual'] ) ) {
			$fields['virtual'] = self::normalize_bool( $fields['virtual'] );
		}
		if ( isset( $fields['downloadable'] ) ) {
			$fields['downloadable'] = self::normalize_bool( $fields['downloadable'] );
		}
		if ( isset( $fields['featured'] ) ) {
			$fields['featured'] = self::normalize_bool( $fields['featured'] );
		}

		// Tax & Dimensions.
		if ( isset( $fields['weight'] ) && '' !== $fields['weight'] ) {
			$fields['weight'] = (string) ( (float) $fields['weight'] );
		}
		if ( isset( $fields['length'] ) && '' !== $fields['length'] ) {
			$fields['length'] = (string) ( (float) $fields['length'] );
		}
		if ( isset( $fields['width'] ) && '' !== $fields['width'] ) {
			$fields['width'] = (string) ( (float) $fields['width'] );
		}
		if ( isset( $fields['height'] ) && '' !== $fields['height'] ) {
			$fields['height'] = (string) ( (float) $fields['height'] );
		}

		// Lists.
		if ( isset( $fields['category_names'] ) ) {
			$fields['category_names'] = self::normalize_list( $fields['category_names'] );
		}
		if ( isset( $fields['tag_names'] ) ) {
			$fields['tag_names'] = self::normalize_list( $fields['tag_names'] );
		}
		if ( isset( $fields['upsell_skus'] ) ) {
			$fields['upsell_skus'] = self::normalize_list( $fields['upsell_skus'] );
		}
		if ( isset( $fields['cross_sell_skus'] ) ) {
			$fields['cross_sell_skus'] = self::normalize_list( $fields['cross_sell_skus'] );
		}
		if ( isset( $fields['grouped_children'] ) ) {
			$fields['grouped_children'] = self::normalize_list( $fields['grouped_children'] );
		}
		if ( isset( $fields['image_gallery'] ) ) {
			$fields['image_gallery'] = self::normalize_list( $fields['image_gallery'] );
		}

		$payload['fields'] = $fields;
		return $payload;
	}
}
