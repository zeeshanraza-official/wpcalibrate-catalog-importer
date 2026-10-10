<?php
namespace WPCalibrate\CatalogImporter\Import\Parsers;

use WPCalibrate\CatalogImporter\Import\Contracts\ParserInterface;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Memory-efficient streaming CSV parser.
 */
class CsvStreamParser implements ParserInterface {

	/**
	 * Detect CSV delimiter from file sample.
	 *
	 * @param string $file_path Absolute path.
	 * @return string Detected delimiter (default: ',').
	 */
	public function detect_delimiter( string $file_path ): string {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return ',';
		}

		$handle = fopen( $file_path, 'rb' );
		if ( ! $handle ) {
			return ',';
		}

		// Read first 4KB for detection.
		$sample = fread( $handle, 4096 );
		fclose( $handle );

		if ( empty( $sample ) ) {
			return ',';
		}

		// Strip UTF-8 BOM if present.
		if ( str_starts_with( $sample, "\xEF\xBB\xBF" ) ) {
			$sample = substr( $sample, 3 );
		}

		$delimiters = [ ',', ';', "\t", '|' ];
		$counts     = [];

		$lines = explode( "\n", str_replace( "\r\n", "\n", $sample ) );
		$first_line = $lines[0] ?? '';

		foreach ( $delimiters as $delim ) {
			$counts[ $delim ] = substr_count( $first_line, $delim );
		}

		arsort( $counts );
		$best = array_key_first( $counts );

		return ( $counts[ $best ] > 0 ) ? $best : ',';
	}

	/**
	 * Open file handle with BOM stripping.
	 *
	 * @param string $file_path
	 * @return resource|false
	 */
	protected function open_file( string $file_path ) {
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return false;
		}

		$handle = fopen( $file_path, 'rb' );
		if ( ! $handle ) {
			return false;
		}

		// Check and skip UTF-8 BOM.
		$bom = fread( $handle, 3 );
		if ( "\xEF\xBB\xBF" !== $bom ) {
			// Rewind to beginning if no BOM.
			rewind( $handle );
		}

		return $handle;
	}

	/**
	 * Clean header string.
	 *
	 * @param string $header
	 * @return string
	 */
	protected function clean_header( string $header ): string {
		// Strip any residual BOM or null bytes.
		$header = str_replace( [ "\xEF\xBB\xBF", "\0" ], '', $header );
		return trim( $header );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_headers( string $file_path, array $options = [] ): array {
		$handle = $this->open_file( $file_path );
		if ( ! $handle ) {
			return [];
		}

		$delimiter = $options['delimiter'] ?? $this->detect_delimiter( $file_path );
		$enclosure = $options['enclosure'] ?? '"';
		$escape    = $options['escape'] ?? '\\';

		$headers = fgetcsv( $handle, 0, $delimiter, $enclosure, $escape );
		fclose( $handle );

		if ( ! is_array( $headers ) ) {
			return [];
		}

		return array_map( [ $this, 'clean_header' ], $headers );
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_sample_rows( string $file_path, int $limit = 5, array $options = [] ): array {
		$handle = $this->open_file( $file_path );
		if ( ! $handle ) {
			return [];
		}

		$delimiter = $options['delimiter'] ?? $this->detect_delimiter( $file_path );
		$enclosure = $options['enclosure'] ?? '"';
		$escape    = $options['escape'] ?? '\\';

		$raw_headers = fgetcsv( $handle, 0, $delimiter, $enclosure, $escape );
		if ( ! is_array( $raw_headers ) ) {
			fclose( $handle );
			return [];
		}

		$headers = array_map( [ $this, 'clean_header' ], $raw_headers );
		$samples = [];
		$count   = 0;

		while ( ( $row = fgetcsv( $handle, 0, $delimiter, $enclosure, $escape ) ) !== false ) {
			// Skip completely empty lines.
			if ( [ null ] === $row || ( 1 === count( $row ) && '' === trim( (string) $row[0] ) ) ) {
				continue;
			}

			$sample_row = [];
			foreach ( $headers as $idx => $header_name ) {
				$sample_row[ $header_name ] = $row[ $idx ] ?? '';
			}

			$samples[] = $sample_row;
			$count++;

			if ( $count >= $limit ) {
				break;
			}
		}

		fclose( $handle );
		return $samples;
	}

	/**
	 * {@inheritDoc}
	 */
	public function count_rows( string $file_path, array $options = [] ): int {
		$handle = $this->open_file( $file_path );
		if ( ! $handle ) {
			return 0;
		}

		$delimiter = $options['delimiter'] ?? $this->detect_delimiter( $file_path );
		$enclosure = $options['enclosure'] ?? '"';
		$escape    = $options['escape'] ?? '\\';

		// Skip header line.
		fgetcsv( $handle, 0, $delimiter, $enclosure, $escape );

		$total = 0;
		while ( ( $row = fgetcsv( $handle, 0, $delimiter, $enclosure, $escape ) ) !== false ) {
			// Skip empty lines.
			if ( [ null ] === $row || ( 1 === count( $row ) && '' === trim( (string) $row[0] ) ) ) {
				continue;
			}
			$total++;
		}

		fclose( $handle );
		return $total;
	}

	/**
	 * {@inheritDoc}
	 */
	public function stream_rows( string $file_path, int $offset = 0, ?int $limit = null, array $options = [] ): \Generator {
		$handle = $this->open_file( $file_path );
		if ( ! $handle ) {
			return;
		}

		$delimiter = $options['delimiter'] ?? $this->detect_delimiter( $file_path );
		$enclosure = $options['enclosure'] ?? '"';
		$escape    = $options['escape'] ?? '\\';

		$raw_headers = fgetcsv( $handle, 0, $delimiter, $enclosure, $escape );
		if ( ! is_array( $raw_headers ) ) {
			fclose( $handle );
			return;
		}

		$headers = array_map( [ $this, 'clean_header' ], $raw_headers );

		$current_data_row = 0; // 0-indexed data row.
		$yielded          = 0;
		$spreadsheet_row  = 1; // Row 1 was header.

		while ( ( $row = fgetcsv( $handle, 0, $delimiter, $enclosure, $escape ) ) !== false ) {
			$spreadsheet_row++;

			// Skip completely empty lines.
			if ( [ null ] === $row || ( 1 === count( $row ) && '' === trim( (string) $row[0] ) ) ) {
				continue;
			}

			// If offset is greater than current data row, skip.
			if ( $current_data_row < $offset ) {
				$current_data_row++;
				continue;
			}

			$data_row = [];
			foreach ( $headers as $idx => $header_name ) {
				$data_row[ $header_name ] = $row[ $idx ] ?? '';
			}

			yield [
				'row_index'        => $spreadsheet_row,
				'data_index'       => $current_data_row,
				'data'             => $data_row,
			];

			$current_data_row++;
			$yielded++;

			if ( null !== $limit && $yielded >= $limit ) {
				break;
			}
		}

		fclose( $handle );
	}
}
