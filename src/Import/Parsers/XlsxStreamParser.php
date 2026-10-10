<?php
namespace WPCalibrate\CatalogImporter\Import\Parsers;

use WPCalibrate\CatalogImporter\Import\Contracts\ParserInterface;
use ZipArchive;
use XMLReader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * High-performance, streaming XLSX parser using native PHP ZipArchive and XMLReader.
 * Memory complexity: O(1) row processing. Zero external runtime dependencies.
 */
class XlsxStreamParser implements ParserInterface {

	/**
	 * Convert Excel column letter (e.g., 'A', 'B', 'AA', 'BC') to 0-based column index.
	 *
	 * @param string $cell_ref E.g. 'A1', 'BC12'.
	 * @return int 0-based index.
	 */
	public static function cell_ref_to_col_index( string $cell_ref ): int {
		$letters = preg_replace( '/[^A-Z]/', '', strtoupper( $cell_ref ) );
		$len     = strlen( $letters );
		$col     = 0;

		for ( $i = 0; $i < $len; $i++ ) {
			$col = $col * 26 + ( ord( $letters[ $i ] ) - 64 );
		}

		return max( 0, $col - 1 );
	}

	/**
	 * Extract shared strings from XLSX package.
	 *
	 * @param ZipArchive $zip
	 * @return array<int, string>
	 */
	protected function load_shared_strings( ZipArchive $zip ): array {
		$strings = [];

		// Locate sharedStrings.xml case-insensitively.
		$entry_name = null;
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = $zip->getNameIndex( $i );
			if ( strtolower( (string) $name ) === 'xl/sharedstrings.xml' ) {
				$entry_name = $name;
				break;
			}
		}

		if ( ! $entry_name ) {
			return $strings;
		}

		$fp = $zip->getStream( $entry_name );
		if ( ! $fp ) {
			return $strings;
		}

		// Read using XMLReader from stream.
		$xml = new XMLReader();
		// Create a temporary file to allow XMLReader to stream parse.
		$temp = wp_tempnam( 'xlsx_shared_' );
		if ( ! $temp ) {
			$temp = tempnam( sys_get_temp_dir(), 'xlsx_shared_' );
		}

		$dest = fopen( $temp, 'wb' );
		if ( $dest ) {
			while ( ! feof( $fp ) ) {
				fwrite( $dest, fread( $fp, 8192 ) );
			}
			fclose( $dest );
		}
		fclose( $fp );

		if ( $xml->open( $temp ) ) {
			$current_string = '';
			$in_text_node   = false;

			while ( $xml->read() ) {
				if ( XMLReader::ELEMENT === $xml->nodeType && 'si' === $xml->name ) {
					// String item start.
					$current_string = '';
				} elseif ( XMLReader::ELEMENT === $xml->nodeType && 't' === $xml->name ) {
					$in_text_node = true;
				} elseif ( XMLReader::TEXT === $xml->nodeType && $in_text_node ) {
					$current_string .= $xml->value;
				} elseif ( XMLReader::END_ELEMENT === $xml->nodeType && 't' === $xml->name ) {
					$in_text_node = false;
				} elseif ( XMLReader::END_ELEMENT === $xml->nodeType && 'si' === $xml->name ) {
					$strings[] = $current_string;
				}
			}
			$xml->close();
		}

		if ( file_exists( $temp ) ) {
			@unlink( $temp );
		}

		return $strings;
	}

	/**
	 * Locate the primary worksheet entry name.
	 *
	 * @param ZipArchive $zip
	 * @return string|null
	 */
	protected function get_first_sheet_name( ZipArchive $zip ): ?string {
		// Look for sheet1.xml or first matching sheet in xl/worksheets/
		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = $zip->getNameIndex( $i );
			if ( preg_match( '#^xl/worksheets/sheet[0-9]+\.xml$#i', (string) $name ) ) {
				return $name;
			}
		}

		return null;
	}

	/**
	 * Extract worksheet to a temporary file for streaming XMLReader reading.
	 *
	 * @param ZipArchive $zip
	 * @param string $sheet_entry
	 * @return string|false Temporary file path or false.
	 */
	protected function extract_sheet_to_temp( ZipArchive $zip, string $sheet_entry ): string|false {
		$stream = $zip->getStream( $sheet_entry );
		if ( ! $stream ) {
			return false;
		}

		$temp = wp_tempnam( 'xlsx_sheet_' );
		if ( ! $temp ) {
			$temp = tempnam( sys_get_temp_dir(), 'xlsx_sheet_' );
		}

		$out = fopen( $temp, 'wb' );
		if ( ! $out ) {
			fclose( $stream );
			return false;
		}

		while ( ! feof( $stream ) ) {
			fwrite( $out, fread( $stream, 65536 ) );
		}

		fclose( $stream );
		fclose( $out );

		return $temp;
	}

	/**
	 * Parse a single <row> XML subtree into an indexed array of cell values.
	 *
	 * @param string $row_xml XML fragment representing `<row ...>...</row>`.
	 * @param array<int, string> $shared_strings
	 * @return array<int, string> 0-based column index => cell value.
	 */
	protected function parse_row_xml( string $row_xml, array &$shared_strings ): array {
		$reader = new XMLReader();
		$cells  = [];

		if ( ! $reader->xml( $row_xml ) ) {
			return $cells;
		}

		$current_cell_col = 0;
		$current_type     = '';
		$cell_value       = '';
		$in_value         = false;
		$in_inline_str    = false;

		while ( $reader->read() ) {
			if ( XMLReader::ELEMENT === $reader->nodeType && 'c' === $reader->name ) {
				$r = $reader->getAttribute( 'r' ) ?: 'A1';
				$current_cell_col = self::cell_ref_to_col_index( $r );
				$current_type     = $reader->getAttribute( 't' ) ?: '';
				$cell_value       = '';
			} elseif ( XMLReader::ELEMENT === $reader->nodeType && ( 'v' === $reader->name || 't' === $reader->name ) ) {
				$in_value = true;
			} elseif ( XMLReader::TEXT === $reader->nodeType && $in_value ) {
				$cell_value .= $reader->value;
			} elseif ( XMLReader::END_ELEMENT === $reader->nodeType && ( 'v' === $reader->name || 't' === $reader->name ) ) {
				$in_value = false;
			} elseif ( XMLReader::END_ELEMENT === $reader->nodeType && 'c' === $reader->name ) {
				// Format final cell value based on cell type.
				if ( 's' === $current_type ) {
					// Shared string lookup.
					$str_idx = (int) $cell_value;
					$cells[ $current_cell_col ] = $shared_strings[ $str_idx ] ?? '';
				} elseif ( 'b' === $current_type ) {
					// Boolean.
					$cells[ $current_cell_col ] = ( '1' === trim( $cell_value ) ) ? '1' : '0';
				} else {
					$cells[ $current_cell_col ] = trim( $cell_value );
				}
			}
		}

		$reader->close();

		// Fill any omitted columns up to the maximum column index found with empty string.
		if ( ! empty( $cells ) ) {
			$max_col = max( array_keys( $cells ) );
			$row_result = [];
			for ( $c = 0; $c <= $max_col; $c++ ) {
				$row_result[ $c ] = $cells[ $c ] ?? '';
			}
			return $row_result;
		}

		return [];
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_headers( string $file_path, array $options = [] ): array {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $file_path ) ) {
			return [];
		}

		$shared_strings = $this->load_shared_strings( $zip );
		$sheet_name     = $this->get_first_sheet_name( $zip );

		if ( ! $sheet_name ) {
			$zip->close();
			return [];
		}

		$temp_sheet = $this->extract_sheet_to_temp( $zip, $sheet_name );
		$zip->close();

		if ( ! $temp_sheet ) {
			return [];
		}

		$reader  = new XMLReader();
		$headers = [];

		if ( $reader->open( $temp_sheet ) ) {
			while ( $reader->read() ) {
				if ( XMLReader::ELEMENT === $reader->nodeType && 'row' === $reader->name ) {
					$row_xml = $reader->readOuterXml();
					$cells   = $this->parse_row_xml( $row_xml, $shared_strings );
					$headers = array_map( 'trim', $cells );
					break;
				}
			}
			$reader->close();
		}

		if ( file_exists( $temp_sheet ) ) {
			@unlink( $temp_sheet );
		}

		return $headers;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_sample_rows( string $file_path, int $limit = 5, array $options = [] ): array {
		$samples = [];
		$headers = $this->get_headers( $file_path, $options );
		if ( empty( $headers ) ) {
			return [];
		}

		foreach ( $this->stream_rows( $file_path, 0, $limit, $options ) as $row_item ) {
			$samples[] = $row_item['data'];
		}

		return $samples;
	}

	/**
	 * {@inheritDoc}
	 */
	public function count_rows( string $file_path, array $options = [] ): int {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $file_path ) ) {
			return 0;
		}

		$sheet_name = $this->get_first_sheet_name( $zip );
		if ( ! $sheet_name ) {
			$zip->close();
			return 0;
		}

		$temp_sheet = $this->extract_sheet_to_temp( $zip, $sheet_name );
		$zip->close();

		if ( ! $temp_sheet ) {
			return 0;
		}

		$reader = new XMLReader();
		$count  = 0;
		$is_first = true;

		if ( $reader->open( $temp_sheet ) ) {
			while ( $reader->read() ) {
				if ( XMLReader::ELEMENT === $reader->nodeType && 'row' === $reader->name ) {
					if ( $is_first ) {
						// Header row.
						$is_first = false;
						continue;
					}
					$count++;
				}
			}
			$reader->close();
		}

		if ( file_exists( $temp_sheet ) ) {
			@unlink( $temp_sheet );
		}

		return $count;
	}

	/**
	 * {@inheritDoc}
	 */
	public function stream_rows( string $file_path, int $offset = 0, ?int $limit = null, array $options = [] ): \Generator {
		$zip = new ZipArchive();
		if ( true !== $zip->open( $file_path ) ) {
			return;
		}

		$shared_strings = $this->load_shared_strings( $zip );
		$sheet_name     = $this->get_first_sheet_name( $zip );

		if ( ! $sheet_name ) {
			$zip->close();
			return;
		}

		$temp_sheet = $this->extract_sheet_to_temp( $zip, $sheet_name );
		$zip->close();

		if ( ! $temp_sheet ) {
			return;
		}

		$reader           = new XMLReader();
		$headers          = [];
		$is_first         = true;
		$current_data_row = 0; // 0-indexed data row.
		$yielded          = 0;
		$spreadsheet_row  = 0;

		if ( $reader->open( $temp_sheet ) ) {
			while ( $reader->read() ) {
				if ( XMLReader::ELEMENT === $reader->nodeType && 'row' === $reader->name ) {
					$spreadsheet_row++;
					$row_xml = $reader->readOuterXml();
					$cells   = $this->parse_row_xml( $row_xml, $shared_strings );

					if ( $is_first ) {
						$headers  = array_map( 'trim', $cells );
						$is_first = false;
						continue;
					}

					// Skip offset rows.
					if ( $current_data_row < $offset ) {
						$current_data_row++;
						continue;
					}

					// Build associative row according to headers.
					$data_row = [];
					foreach ( $headers as $col_idx => $header_name ) {
						if ( '' !== $header_name ) {
							$data_row[ $header_name ] = $cells[ $col_idx ] ?? '';
						}
					}

					yield [
						'row_index'  => $spreadsheet_row,
						'data_index' => $current_data_row,
						'data'       => $data_row,
					];

					$current_data_row++;
					$yielded++;

					if ( null !== $limit && $yielded >= $limit ) {
						break;
					}
				}
			}
			$reader->close();
		}

		if ( file_exists( $temp_sheet ) ) {
			@unlink( $temp_sheet );
		}
	}
}
