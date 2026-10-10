<?php
namespace WPCalibrate\CatalogImporter\Import\Reports;

use WPCalibrate\CatalogImporter\Jobs\ImportJobRepository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates downloadable error and warning reports with CSV formula injection protection.
 */
class ErrorReportGenerator {

	protected ImportJobRepository $repository;

	/**
	 * Constructor.
	 *
	 * @param ImportJobRepository|null $repository
	 */
	public function __construct( ?ImportJobRepository $repository = null ) {
		$this->repository = $repository ?: new ImportJobRepository();
	}

	/**
	 * Sanitize a CSV cell value to prevent CSV / Spreadsheet Formula Injection (CWE-1236).
	 * If cell begins with =, +, -, @, \t, or \r, prefix with a single quote.
	 *
	 * @param mixed $value
	 * @return string Safe cell value.
	 */
	public static function escape_csv_cell( mixed $value ): string {
		if ( null === $value || '' === $value ) {
			return '';
		}

		$str = (string) $value;

		// Check for dangerous formula injection trigger characters at the beginning.
		$first_char = substr( $str, 0, 1 );
		if ( in_array( $first_char, [ '=', '+', '-', '@', "\t", "\r" ], true ) ) {
			return "'" . $str;
		}

		return $str;
	}

	/**
	 * Generate and stream CSV error report directly to browser output.
	 *
	 * @param int $job_id
	 * @param string|null $filename
	 * @return void
	 */
	public function stream_error_report( int $job_id, ?string $filename = null ): void {
		$rows = $this->repository->get_failed_rows( $job_id );

		$safe_filename = $filename ? sanitize_file_name( $filename ) : 'import_errors_job_' . $job_id . '.csv';

		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $safe_filename . '"' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		$output = fopen( 'php://output', 'wb' );

		// Write UTF-8 BOM so Excel opens with proper character encoding.
		fwrite( $output, "\xEF\xBB\xBF" );

		// Header columns.
		fputcsv(
			$output,
			[
				__( 'Row Number', 'wpcalibrate-catalog-importer' ),
				__( 'Identifier (SKU / ID)', 'wpcalibrate-catalog-importer' ),
				__( 'Result Status', 'wpcalibrate-catalog-importer' ),
				__( 'Error Code', 'wpcalibrate-catalog-importer' ),
				__( 'Message', 'wpcalibrate-catalog-importer' ),
			]
		);

		foreach ( $rows as $r ) {
			fputcsv(
				$output,
				[
					self::escape_csv_cell( (string) $r->row_index ),
					self::escape_csv_cell( (string) $r->identifier ),
					self::escape_csv_cell( (string) $r->status ),
					self::escape_csv_cell( (string) $r->error_code ),
					self::escape_csv_cell( (string) $r->message ),
				]
			);
		}

		fclose( $output );
		exit;
	}
}
