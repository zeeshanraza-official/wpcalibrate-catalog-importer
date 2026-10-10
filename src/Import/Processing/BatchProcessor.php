<?php
namespace WPCalibrate\CatalogImporter\Import\Processing;

use WPCalibrate\CatalogImporter\Jobs\ImportJob;
use WPCalibrate\CatalogImporter\Jobs\ImportJobRepository;
use WPCalibrate\CatalogImporter\Import\Contracts\ParserInterface;
use WPCalibrate\CatalogImporter\Import\Parsers\CsvStreamParser;
use WPCalibrate\CatalogImporter\Import\Parsers\XlsxStreamParser;
use WPCalibrate\CatalogImporter\Import\Parsers\ZipPackageParser;
use WPCalibrate\CatalogImporter\Import\Mapping\MappingEngine;
use WPCalibrate\CatalogImporter\Import\Normalization\RowNormalizer;
use WPCalibrate\CatalogImporter\Import\Validation\RowValidator;
use WPCalibrate\CatalogImporter\Import\Products\ProductWriter;
use WPCalibrate\CatalogImporter\Import\Products\ProductResolver;
use WPCalibrate\CatalogImporter\Import\Products\TaxonomyResolver;
use WPCalibrate\CatalogImporter\Import\Products\AttributeResolver;
use WPCalibrate\CatalogImporter\Import\Images\ImageManager;
use WPCalibrate\CatalogImporter\Logging\PluginLogger;
use Exception;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Action Scheduler background batch execution with idempotency, locks, and recovery.
 */
class BatchProcessor {

	public const ACTION_PROCESS_BATCH = 'wpcalibrate_catalog_importer_process_batch';
	public const DEFAULT_BATCH_SIZE   = 25;

	protected ImportJobRepository $repository;
	protected IdempotencyManager $idempotency;

	/**
	 * Constructor.
	 */
	public function __construct(
		?ImportJobRepository $repository = null,
		?IdempotencyManager $idempotency = null
	) {
		$this->repository  = $repository ?: new ImportJobRepository();
		$this->idempotency = $idempotency ?: new IdempotencyManager();
	}

	/**
	 * Register Action Scheduler action hook.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( self::ACTION_PROCESS_BATCH, [ $this, 'process_batch' ], 10, 2 );
	}

	/**
	 * Queue an import job to begin execution via Action Scheduler.
	 *
	 * @param int $job_id Job ID.
	 * @return bool
	 */
	public function schedule_job( int $job_id ): bool {
		$job = $this->repository->get_by_id( $job_id );
		if ( ! $job ) {
			return false;
		}

		$this->repository->update_status( $job_id, ImportJob::STATUS_QUEUED );

		if ( function_exists( 'as_schedule_single_action' ) ) {
			as_schedule_single_action(
				time(),
				self::ACTION_PROCESS_BATCH,
				[
					'job_id'       => $job_id,
					'batch_number' => 0,
				],
				'wpcalibrate-catalog-importer'
			);
			return true;
		}

		// Fallback to WP-Cron if Action Scheduler is not loaded yet.
		if ( ! wp_next_scheduled( self::ACTION_PROCESS_BATCH, [ $job_id, 0 ] ) ) {
			wp_schedule_single_event( time(), self::ACTION_PROCESS_BATCH, [ $job_id, 0 ] );
		}

		return true;
	}

	/**
	 * Cancel a running or queued job safely.
	 *
	 * @param int $job_id Job ID.
	 * @return bool
	 */
	public function cancel_job( int $job_id ): bool {
		$job = $this->repository->get_by_id( $job_id );
		if ( ! $job ) {
			return false;
		}

		// Update status to cancelled.
		$this->repository->update_status( $job_id, ImportJob::STATUS_CANCELLED );

		// Clear pending Action Scheduler actions.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::ACTION_PROCESS_BATCH, [ 'job_id' => $job_id ], 'wpcalibrate-catalog-importer' );
		}

		$this->idempotency->clear_all_job_locks( $job_id );

		return true;
	}

	/**
	 * Execute a single batch of product imports.
	 *
	 * @param int $job_id
	 * @param int $batch_number
	 * @return void
	 */
	public function process_batch( int $job_id, int $batch_number = 0 ): void {
		$job = $this->repository->get_by_id( $job_id );
		if ( ! $job ) {
			PluginLogger::error( "Job {$job_id} not found in process_batch." );
			return;
		}

		// If job was cancelled or finished, abort.
		if ( in_array( $job->status, [ ImportJob::STATUS_CANCELLED, ImportJob::STATUS_COMPLETED, ImportJob::STATUS_FAILED ], true ) ) {
			return;
		}

		// Acquire exclusive lock for this batch.
		if ( ! $this->idempotency->acquire_lock( $job_id, $batch_number ) ) {
			PluginLogger::warning( "Batch {$batch_number} for Job {$job_id} already locked by another process." );
			return;
		}

		// Transition to processing if queued.
		if ( ImportJob::STATUS_QUEUED === $job->status ) {
			$this->repository->update_status( $job_id, ImportJob::STATUS_PROCESSING );
		}

		$batch_size = (int) ( $job->options_config['batch_size'] ?? self::DEFAULT_BATCH_SIZE );
		$batch_size = max( 1, min( 500, $batch_size ) );

		$parser = ( 'xlsx' === $job->source_type ) ? new XlsxStreamParser() : new CsvStreamParser();
		$offset = $job->processed_rows;

		// Initialize ZIP image index if ZIP was provided.
		$zip_index = [];
		if ( ! empty( $job->image_zip_path ) && file_exists( $job->image_zip_path ) ) {
			$zip_parser = new ZipPackageParser();
			$upload_dir = wp_upload_dir();
			$extract_to = trailingslashit( $upload_dir['basedir'] ) . 'wpcalibrate-catalog-importer/tmp_zip_' . $job->id;
			$extracted  = $zip_parser->extract_and_index( $job->image_zip_path, $extract_to );
			if ( is_array( $extracted ) ) {
				$zip_index = $extracted;
			}
		}

		$resolver           = new ProductResolver();
		$taxonomy_resolver  = new TaxonomyResolver();
		$attribute_resolver = new AttributeResolver();
		$image_manager      = new ImageManager( null, $zip_index );
		$writer             = new ProductWriter( $resolver, $taxonomy_resolver, $attribute_resolver, $image_manager );
		$validator          = new RowValidator( $resolver );
		$mapping_engine     = new MappingEngine();
		$normalizer         = new RowNormalizer();

		$mapping_config = $job->mapping_config;
		$options_config = $job->options_config;
		$import_mode    = $job->import_mode;
		$id_type        = $job->identifier_type;

		$batch_processed = 0;
		$batch_created   = 0;
		$batch_updated   = 0;
		$batch_skipped   = 0;
		$batch_warnings  = 0;
		$batch_failed    = 0;

		try {
			foreach ( $parser->stream_rows( $job->file_path, $offset, $batch_size ) as $row_item ) {
				// Check cancellation flag between rows.
				if ( $this->is_job_cancelled( $job_id ) ) {
					$this->idempotency->release_lock( $job_id, $batch_number );
					return;
				}

				$row_index = (int) $row_item['row_index'];
				$raw_data  = (array) $row_item['data'];

				// 1. Transform.
				$transformed = $mapping_engine->transform( $raw_data, $mapping_config );

				// 2. Normalize.
				$normalized = $normalizer->normalize( $transformed, $options_config );

				// 3. Validate.
				$val_result = $validator->validate_row( $normalized, $row_index, $import_mode, $id_type, $options_config );

				$sku        = $normalized['fields']['sku'] ?? '';
				$identifier = ( 'id' === $id_type ) ? (string) ( $normalized['fields']['id'] ?? '' ) : $sku;

				if ( 'skipped' === $val_result['status'] ) {
					$batch_processed++;
					$batch_skipped++;
					$this->repository->log_row(
						$job_id,
						$row_index,
						'skipped',
						$identifier,
						$val_result['product_id'],
						'skip',
						implode( ' ', $val_result['warnings'] ),
						$val_result['error_code'],
						$raw_data
					);
					continue;
				}

				if ( 'invalid' === $val_result['status'] ) {
					$batch_processed++;
					$batch_failed++;
					$this->repository->log_row(
						$job_id,
						$row_index,
						'failed',
						$identifier,
						0,
						'fail',
						implode( ' ', $val_result['errors'] ),
						$val_result['error_code'],
						$raw_data
					);
					continue;
				}

				// If warnings exist, log warning count.
				if ( ! empty( $val_result['warnings'] ) ) {
					$batch_warnings++;
				}

				// 4. Write product using WooCommerce CRUD.
				$action   = $val_result['action'];
				$write_res = $writer->write_product( $normalized, $action, $val_result['product_id'], $options_config );

				if ( is_wp_error( $write_res ) ) {
					$batch_processed++;
					$batch_failed++;
					$this->repository->log_row(
						$job_id,
						$row_index,
						'failed',
						$identifier,
						0,
						'error',
						$write_res->get_error_message(),
						$write_res->get_error_code(),
						$raw_data
					);
				} else {
					$batch_processed++;
					$prod_id      = $write_res['product_id'];
					$action_taken = $write_res['action_taken'];

					if ( str_contains( $action_taken, 'created' ) ) {
						$batch_created++;
					} else {
						$batch_updated++;
					}

					$msg = sprintf(
						/* translators: 1: Action (created/updated), 2: Product ID */
						__( 'Successfully %1$s product #%2$d.', 'wpcalibrate-catalog-importer' ),
						$action_taken,
						$prod_id
					);

					if ( ! empty( $val_result['warnings'] ) ) {
						$msg .= ' ' . implode( ' ', $val_result['warnings'] );
					}

					$this->repository->log_row(
						$job_id,
						$row_index,
						! empty( $val_result['warnings'] ) ? 'warning' : 'created',
						$identifier,
						$prod_id,
						$action_taken,
						$msg,
						null,
						$raw_data
					);
				}
			}
		} catch ( Throwable $e ) {
			PluginLogger::error( "Exception in Batch {$batch_number} for Job {$job_id}: " . $e->getMessage() );
		}

		// Update counters atomically.
		$this->repository->increment_counters( $job_id, [
			'processed_rows' => $batch_processed,
			'created_count'  => $batch_created,
			'updated_count'  => $batch_updated,
			'skipped_count'  => $batch_skipped,
			'warning_count'  => $batch_warnings,
			'failed_count'   => $batch_failed,
			'current_batch'  => $batch_number + 1,
		] );

		// Release batch lock.
		$this->idempotency->release_lock( $job_id, $batch_number );

		// Refresh job data.
		$job = $this->repository->get_by_id( $job_id );
		if ( ! $job ) {
			return;
		}

		// Check if more rows exist.
		if ( $job->processed_rows < $job->total_rows && $batch_processed > 0 ) {
			// Schedule next batch immediately.
			if ( function_exists( 'as_schedule_single_action' ) ) {
				as_schedule_single_action(
					time(),
					self::ACTION_PROCESS_BATCH,
					[
						'job_id'       => $job_id,
						'batch_number' => $batch_number + 1,
					],
					'wpcalibrate-catalog-importer'
				);
			} else {
				wp_schedule_single_event( time(), self::ACTION_PROCESS_BATCH, [ $job_id, $batch_number + 1 ] );
			}
		} else {
			// Job completed.
			$this->repository->update_status( $job_id, ImportJob::STATUS_COMPLETED );
			$this->idempotency->clear_all_job_locks( $job_id );

			// Clean extracted zip files if any.
			if ( ! empty( $extract_to ) && is_dir( $extract_to ) ) {
				ZipPackageParser::clean_directory( $extract_to );
			}
		}
	}

	/**
	 * Quick check if job status was set to cancelled.
	 *
	 * @param int $job_id
	 * @return bool
	 */
	protected function is_job_cancelled( int $job_id ): bool {
		global $wpdb;
		$table  = \WPCalibrate\CatalogImporter\Database\SchemaManager::get_table_name( \WPCalibrate\CatalogImporter\Database\SchemaManager::TABLE_JOBS );
		$status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM {$table} WHERE id = %d", $job_id ) );

		return ( ImportJob::STATUS_CANCELLED === $status );
	}
}
