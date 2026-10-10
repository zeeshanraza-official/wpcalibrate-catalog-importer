<?php
namespace WPCalibrate\CatalogImporter\Import\Products;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates, filters, and applies custom meta keys with strict safeguards.
 */
class CustomMetaHandler {

	/**
	 * Internal WooCommerce / WordPress protected meta keys that MUST NOT be overwritten via custom meta.
	 */
	public const PROTECTED_INTERNAL_KEYS = [
		'_price',
		'_regular_price',
		'_sale_price',
		'_sale_price_dates_from',
		'_sale_price_dates_to',
		'_sku',
		'_stock',
		'_stock_status',
		'_manage_stock',
		'_backorders',
		'_tax_status',
		'_tax_class',
		'_weight',
		'_length',
		'_width',
		'_height',
		'_downloadable',
		'_virtual',
		'_download_limit',
		'_download_expiry',
		'_downloadable_files',
		'_product_image_gallery',
		'_thumbnail_id',
		'_upsell_ids',
		'_crosssell_ids',
		'_children',
		'_product_attributes',
		'_default_attributes',
		'_wp_attached_file',
		'_wp_attachment_metadata',
		'_edit_lock',
		'_edit_last',
	];

	/**
	 * Allowlisted underscore-prefixed keys (if any store-specific extensions require them safely).
	 */
	public const ALLOWLISTED_PROTECTED_KEYS = [
		'_global_unique_id',
		'_gtin',
		'_ean',
		'_isbn',
		'_mpn',
		'_brand',
		'_cost_of_goods',
	];

	/**
	 * Verify if a meta key is valid and allowed.
	 *
	 * @param string $meta_key Key name.
	 * @return bool
	 */
	public static function is_key_allowed( string $meta_key ): bool {
		$clean_key = trim( $meta_key );

		if ( '' === $clean_key || strlen( $clean_key ) > 255 ) {
			return false;
		}

		// Disallow keys starting with underscore unless explicitly allowlisted.
		if ( str_starts_with( $clean_key, '_' ) && ! in_array( $clean_key, self::ALLOWLISTED_PROTECTED_KEYS, true ) ) {
			return false;
		}

		// Specifically block critical internal keys.
		if ( in_array( $clean_key, self::PROTECTED_INTERNAL_KEYS, true ) ) {
			return false;
		}

		// Disallow dangerous script / SQL characters.
		if ( preg_match( '/[\x00-\x1f\x7f<>{};\'"]/', $clean_key ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Sanitize meta value safely without allowing PHP serialization attacks.
	 *
	 * @param mixed $value Raw value.
	 * @param string $type Expected type: 'string', 'number', 'json'.
	 * @return mixed
	 */
	public static function sanitize_value( mixed $value, string $type = 'string' ): mixed {
		if ( null === $value || '' === $value ) {
			return '';
		}

		if ( is_array( $value ) ) {
			return array_map( [ self::class, 'sanitize_value' ], $value );
		}

		$str = (string) $value;

		// Block any attempt to pass PHP serialized objects (e.g. O:8:"Exploit":...)
		if ( preg_match( '/^O:[0-9]+:"[a-zA-Z0-9_\\\\]+":/i', trim( $str ) ) ) {
			return '';
		}

		switch ( $type ) {
			case 'number':
				return is_numeric( $str ) ? ( $str + 0 ) : 0;

			case 'json':
				$decoded = json_decode( $str, true );
				return ( null !== $decoded ) ? $decoded : sanitize_text_field( $str );

			case 'string':
			default:
				return sanitize_text_field( $str );
		}
	}
}
