<?php
namespace WPCalibrate\CatalogImporter\Import\Contracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Interface for spreadsheet streaming parsers.
 */
interface ParserInterface {

	/**
	 * Get source column headers.
	 *
	 * @param string $file_path Absolute path to spreadsheet.
	 * @param array $options Parser specific options (e.g. delimiter).
	 * @return array List of string column header names.
	 */
	public function get_headers( string $file_path, array $options = [] ): array;

	/**
	 * Get sample rows for preview.
	 *
	 * @param string $file_path Absolute path to file.
	 * @param int $limit Number of sample rows to retrieve.
	 * @param array $options Parser specific options.
	 * @return array Array of associative arrays [ header => value ] or indexed rows.
	 */
	public function get_sample_rows( string $file_path, int $limit = 5, array $options = [] ): array;

	/**
	 * Get total row count (excluding header).
	 *
	 * @param string $file_path Absolute path to file.
	 * @param array $options Parser specific options.
	 * @return int Total data rows.
	 */
	public function count_rows( string $file_path, array $options = [] ): int;

	/**
	 * Stream rows as a Generator.
	 * Yields: [ 'row_index' => int, 'data' => array ]
	 *
	 * @param string $file_path Absolute path to file.
	 * @param int $offset Row offset to start from (0-indexed data row).
	 * @param int|null $limit Maximum rows to yield in this batch.
	 * @param array $options Parser specific options.
	 * @return \Generator
	 */
	public function stream_rows( string $file_path, int $offset = 0, ?int $limit = null, array $options = [] ): \Generator;
}
