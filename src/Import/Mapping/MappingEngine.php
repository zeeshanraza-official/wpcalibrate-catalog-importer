<?php
namespace WPCalibrate\CatalogImporter\Import\Mapping;

use WPCalibrate\CatalogImporter\Import\Products\CustomMetaHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Transforms raw spreadsheet rows into structured entity data using mapping rules.
 */
class MappingEngine {

	/**
	 * Transform a raw associative row into a structured product data payload.
	 *
	 * @param array<string, string> $raw_row Raw header => value row.
	 * @param array<string, string> $mapping Source header => destination field key.
	 * @return array{
	 *     fields: array<string, mixed>,
	 *     attributes: array<string, string>,
	 *     meta: array<string, mixed>,
	 *     raw: array<string, string>
	 * }
	 */
	public function transform( array $raw_row, array $mapping ): array {
		$fields     = [];
		$attributes = [];
		$meta       = [];

		foreach ( $raw_row as $source_col => $value ) {
			$dest_key = $mapping[ $source_col ] ?? '';

			if ( empty( $dest_key ) || 'ignore' === $dest_key ) {
				continue;
			}

			$val_str = trim( (string) $value );

			// Check for attribute mapping: e.g. "attribute:color" or "attribute:size"
			if ( str_starts_with( $dest_key, 'attribute:' ) ) {
				$attr_name = substr( $dest_key, strlen( 'attribute:' ) );
				if ( '' !== $attr_name && '' !== $val_str ) {
					$attributes[ $attr_name ] = $val_str;
				}
				continue;
			}

			// Check for custom meta mapping: e.g. "meta:brand"
			if ( str_starts_with( $dest_key, 'meta:' ) ) {
				$meta_key = substr( $dest_key, strlen( 'meta:' ) );
				if ( CustomMetaHandler::is_key_allowed( $meta_key ) ) {
					$meta[ $meta_key ] = CustomMetaHandler::sanitize_value( $val_str );
				}
				continue;
			}

			// Standard known field.
			$fields[ $dest_key ] = $val_str;
		}

		return [
			'fields'     => $fields,
			'attributes' => $attributes,
			'meta'       => $meta,
			'raw'        => $raw_row,
		];
	}
}
