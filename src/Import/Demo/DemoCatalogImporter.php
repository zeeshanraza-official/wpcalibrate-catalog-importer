<?php
namespace WPCalibrate\CatalogImporter\Import\Demo;

use WPCalibrate\CatalogImporter\Jobs\ImportJob;
use WPCalibrate\CatalogImporter\Jobs\ImportJobRepository;
use WPCalibrate\CatalogImporter\Import\Parsers\CsvStreamParser;
use WPCalibrate\CatalogImporter\Import\Mapping\FieldRegistry;
use WPCalibrate\CatalogImporter\Import\Processing\BatchProcessor;
use WPCalibrate\CatalogImporter\Security\SecurityHelper;
use WPCalibrate\CatalogImporter\Logging\PluginLogger;
use Exception;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service to provision, load, and import bundled demo catalog products
 * for instant testing and demonstration without needing external spreadsheets.
 */
class DemoCatalogImporter {

	/**
	 * Get the absolute filesystem path to the bundled sample CSV.
	 *
	 * @return string
	 */
	public static function get_demo_csv_path(): string {
		return trailingslashit( WPCALIBRATE_CATALOG_IMPORTER_PATH ) . 'sample-products-import-template.csv';
	}

	/**
	 * Create an ImportJob pre-configured with the bundled demo catalog.
	 *
	 * @return ImportJob
	 * @throws Exception
	 */
	public static function create_demo_job(): ImportJob {
		$csv_path = self::get_demo_csv_path();
		if ( ! file_exists( $csv_path ) ) {
			throw new Exception( __( 'Demo catalog template file not found on server.', 'wpcalibrate-catalog-importer' ) );
		}

		// Ensure sample file is copied to uploads directory for permanent audit trail
		$upload_dir = wp_upload_dir();
		$quarantine = trailingslashit( $upload_dir['basedir'] ) . 'wpcalibrate_imports';
		if ( ! is_dir( $quarantine ) ) {
			wp_mkdir_p( $quarantine );
		}

		$dest_filename = 'demo-products-catalog-' . gmdate( 'Ymd-His' ) . '.csv';
		$dest_path     = trailingslashit( $quarantine ) . $dest_filename;
		copy( $csv_path, $dest_path );

		$parser  = new CsvStreamParser();
		$headers = $parser->get_headers( $dest_path );

		// Build automatic mappings for demo template headers
		$mapping = [];
		foreach ( $headers as $header ) {
			$header_trim = trim( $header );
			$suggested   = FieldRegistry::suggest_mapping( $header_trim );

			if ( ! $suggested ) {
				// Handle custom meta or attributes pattern
				if ( str_starts_with( strtolower( $header_trim ), 'custom meta:' ) ) {
					$meta_key  = trim( substr( $header_trim, 12 ) );
					$suggested = 'custom_meta:' . sanitize_key( $meta_key );
				} elseif ( 'Attributes' === $header_trim ) {
					$suggested = 'attributes';
				} elseif ( 'Variation Attributes' === $header_trim ) {
					$suggested = 'variation_attributes';
				}
			}

			if ( $suggested ) {
				$mapping[ $header_trim ] = $suggested;
			}
		}

		// Count data rows
		$row_count = $parser->count_rows( $dest_path );

		$job_repo = new ImportJobRepository();
		$job      = $job_repo->create(
			[
				'source_filename' => $dest_filename,
				'file_path'       => $dest_path,
				'source_type'     => 'csv',
				'image_zip_path'  => null,
				'import_mode'     => ImportJob::MODE_CREATE_UPDATE,
				'identifier_type' => 'sku',
				'mapping_config'  => $mapping,
				'options_config'  => [
					'default_status'         => 'publish',
					'create_categories'      => true,
					'update_existing_images' => false,
					'delete_missing'         => false,
					'skip_duplicates'        => false,
					'batch_size'             => 50,
				],
				'total_rows'      => $row_count,
			]
		);

		return $job;
	}

	/**
	 * Run instant 1-click import of the demo catalog.
	 *
	 * @return array{success: bool, job_id: int, message: string, created: int, updated: int, failed: int}
	 */
	public static function run_instant_import(): array {
		if ( ! SecurityHelper::can_import() ) {
			return [
				'success' => false,
				'job_id'  => 0,
				'message' => __( 'Unauthorized access.', 'wpcalibrate-catalog-importer' ),
				'created' => 0,
				'updated' => 0,
				'failed'  => 0,
			];
		}

		try {
			$job      = self::create_demo_job();
			$job_repo = new ImportJobRepository();

			// Mark job as processing
			$job_repo->update_status( $job->id, ImportJob::STATUS_PROCESSING );

			// Process the entire batch synchronously
			$batch_processor = new BatchProcessor( $job_repo );
			$batch_processor->process_batch( $job->id, 0 );

			// Refresh job state
			$completed_job = $job_repo->get_by_id( $job->id );

			return [
				'success' => true,
				'job_id'  => $job->id,
				'message' => sprintf(
					__( 'Demo catalog imported successfully! %d products created, %d updated.', 'wpcalibrate-catalog-importer' ),
					$completed_job->created_count,
					$completed_job->updated_count
				),
				'created' => $completed_job->created_count,
				'updated' => $completed_job->updated_count,
				'failed'  => $completed_job->failed_count,
			];
		} catch ( Exception $e ) {
			PluginLogger::error( 'Demo catalog import failed: ' . $e->getMessage() );
			return [
				'success' => false,
				'job_id'  => 0,
				'message' => $e->getMessage(),
				'created' => 0,
				'updated' => 0,
				'failed'  => 0,
			];
		}
	}
}
