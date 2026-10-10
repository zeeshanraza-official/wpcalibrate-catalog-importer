<?php
namespace WPCalibrate\CatalogImporter\Import\Validation;

use WPCalibrate\CatalogImporter\Jobs\ImportJob;
use WPCalibrate\CatalogImporter\Import\Products\ProductResolver;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Validates individual normalized rows during dry runs and live imports.
 */
class RowValidator {

	protected ProductResolver $resolver;

	/**
	 * Set of SKUs already seen in the current file to catch duplicates.
	 *
	 * @var array<string, int> SKU => first row_index seen.
	 */
	protected array $seen_skus = [];

	/**
	 * Constructor.
	 *
	 * @param ProductResolver|null $resolver
	 */
	public function __construct( ?ProductResolver $resolver = null ) {
		$this->resolver = $resolver ?: new ProductResolver();
	}

	/**
	 * Reset seen SKUs index (for new dry run or batch).
	 *
	 * @return void
	 */
	public function reset_seen_skus(): void {
		$this->seen_skus = [];
	}

	/**
	 * Validate a normalized row.
	 *
	 * @param array $normalized Result of RowNormalizer::normalize().
	 * @param int $row_index Spreadsheet row number (e.g. 2, 3...).
	 * @param string $import_mode 'create_only', 'update_only', 'create_update'.
	 * @param string $identifier_type 'sku' or 'id'.
	 * @param array $options Additional options.
	 * @return array{
	 *     status: string,
	 *     action: string,
	 *     errors: list<string>,
	 *     warnings: list<string>,
	 *     error_code: string|null,
	 *     product_id: int
	 * }
	 */
	public function validate_row(
		array $normalized,
		int $row_index,
		string $import_mode = ImportJob::MODE_CREATE_UPDATE,
		string $identifier_type = ImportJob::IDENTIFIER_SKU,
		array $options = []
	): array {
		$fields   = $normalized['fields'];
		$errors   = [];
		$warnings = [];
		$action   = 'create';

		$sku       = $fields['sku'] ?? '';
		$id        = $fields['id'] ?? 0;
		$type      = $fields['type'] ?? 'simple';
		$is_variant = ( 'variation' === $type );

		// 1. Check duplicate SKU in file.
		if ( '' !== $sku ) {
			if ( isset( $this->seen_skus[ $sku ] ) ) {
				$first_row = $this->seen_skus[ $sku ];
				$errors[]  = sprintf(
					/* translators: 1: Duplicate SKU, 2: Previous row number */
					__( 'Duplicate SKU "%1$s" found in source file (previously seen on row %2$d).', 'wpcalibrate-catalog-importer' ),
					$sku,
					$first_row
				);
			} else {
				$this->seen_skus[ $sku ] = $row_index;
			}
		}

		// 2. Resolve existing product.
		$existing_id = $this->resolver->resolve_product_id( $identifier_type, $fields );

		// 3. Import mode enforcement.
		if ( ImportJob::MODE_CREATE_ONLY === $import_mode ) {
			if ( $existing_id > 0 ) {
				return [
					'status'     => 'skipped',
					'action'     => 'skip',
					'errors'     => [],
					'warnings'   => [ sprintf( __( 'Product with %1$s "%2$s" already exists (ID: %3$d); skipped per create-only mode.', 'wpcalibrate-catalog-importer' ), strtoupper( $identifier_type ), ( 'id' === $identifier_type ? $id : $sku ), $existing_id ) ],
					'error_code' => 'skipped_exists',
					'product_id' => $existing_id,
				];
			}
			$action = 'create';
		} elseif ( ImportJob::MODE_UPDATE_ONLY === $import_mode ) {
			if ( 0 === $existing_id ) {
				return [
					'status'     => 'skipped',
					'action'     => 'skip',
					'errors'     => [],
					'warnings'   => [ sprintf( __( 'No existing product found matching %1$s "%2$s"; skipped per update-only mode.', 'wpcalibrate-catalog-importer' ), strtoupper( $identifier_type ), ( 'id' === $identifier_type ? $id : $sku ) ) ],
					'error_code' => 'skipped_not_found',
					'product_id' => 0,
				];
			}
			$action = 'update';
		} else {
			// CREATE_UPDATE
			$action = ( $existing_id > 0 ) ? 'update' : 'create';
		}

		// 4. Identifier requirement.
		if ( 'create' === $action ) {
			if ( ImportJob::IDENTIFIER_SKU === $identifier_type && '' === $sku && ! $is_variant ) {
				$warnings[] = __( 'Row has no SKU; WooCommerce will generate product without SKU.', 'wpcalibrate-catalog-importer' );
			}
			if ( ImportJob::IDENTIFIER_ID === $identifier_type && $id <= 0 ) {
				$errors[] = __( 'Valid numeric Product ID required for ID matching mode.', 'wpcalibrate-catalog-importer' );
			}
			if ( ! $is_variant && empty( $fields['name'] ) ) {
				$errors[] = __( 'Product title/name is required to create a new product.', 'wpcalibrate-catalog-importer' );
			}
		}

		// 5. Variation specifics.
		if ( $is_variant ) {
			$parent_id = $this->resolver->resolve_parent_id( $fields );
			if ( 0 === $parent_id ) {
				// Parent might be declared in previous row of same file or doesn't exist yet.
				$parent_sku = $fields['parent_sku'] ?? '';
				if ( '' === $parent_sku && empty( $fields['parent_id'] ) ) {
					$errors[] = __( 'Variation row must have a parent SKU or parent ID.', 'wpcalibrate-catalog-importer' );
				} elseif ( '' !== $parent_sku && ! isset( $this->seen_skus[ $parent_sku ] ) ) {
					$warnings[] = sprintf( __( 'Parent SKU "%s" not found in store or prior file rows. Parent must exist to attach variation.', 'wpcalibrate-catalog-importer' ), $parent_sku );
				}
			}
		}

		// 6. External product specifics.
		if ( 'external' === $type && 'create' === $action && empty( $fields['external_url'] ) ) {
			$errors[] = __( 'External products require an external product URL.', 'wpcalibrate-catalog-importer' );
		}

		// 7. Pricing validation.
		$has_reg  = isset( $fields['regular_price'] ) && '' !== $fields['regular_price'];
		$has_sale = isset( $fields['sale_price'] ) && '' !== $fields['sale_price'];

		if ( $has_reg && (float) $fields['regular_price'] < 0 ) {
			$errors[] = __( 'Regular price cannot be negative.', 'wpcalibrate-catalog-importer' );
		}

		if ( $has_sale ) {
			if ( (float) $fields['sale_price'] < 0 ) {
				$errors[] = __( 'Sale price cannot be negative.', 'wpcalibrate-catalog-importer' );
			}
			if ( $has_reg && (float) $fields['sale_price'] >= (float) $fields['regular_price'] ) {
				$warnings[] = sprintf(
					__( 'Sale price (%1$s) is equal to or greater than regular price (%2$s).', 'wpcalibrate-catalog-importer' ),
					$fields['sale_price'],
					$fields['regular_price']
				);
			}
		}

		// 8. Sale dates validation.
		if ( ! empty( $fields['date_on_sale_from'] ) && ! empty( $fields['date_on_sale_to'] ) ) {
			if ( strtotime( $fields['date_on_sale_from'] ) > strtotime( $fields['date_on_sale_to'] ) ) {
				$warnings[] = __( 'Sale start date is after sale end date.', 'wpcalibrate-catalog-importer' );
			}
		}

		// 9. Stock validation.
		if ( isset( $fields['stock_quantity'] ) && ! is_numeric( $fields['stock_quantity'] ) ) {
			$warnings[] = __( 'Stock quantity is not a valid number.', 'wpcalibrate-catalog-importer' );
		}

		$status = 'valid';
		$code   = null;

		if ( ! empty( $errors ) ) {
			$status = 'invalid';
			$code   = 'validation_error';
		} elseif ( ! empty( $warnings ) ) {
			$status = 'warning';
			$code   = 'validation_warning';
		}

		return [
			'status'     => $status,
			'action'     => $action,
			'errors'     => $errors,
			'warnings'   => $warnings,
			'error_code' => $code,
			'product_id' => $existing_id,
		];
	}
}
