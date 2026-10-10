<?php
namespace WPCalibrate\CatalogImporter\Jobs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Entity representing an import job.
 */
class ImportJob {

	public const STATUS_DRAFT      = 'draft';
	public const STATUS_VALIDATING = 'validating';
	public const STATUS_READY      = 'ready';
	public const STATUS_QUEUED     = 'queued';
	public const STATUS_PROCESSING = 'processing';
	public const STATUS_COMPLETED  = 'completed';
	public const STATUS_FAILED     = 'failed';
	public const STATUS_CANCELLED  = 'cancelled';

	public const MODE_CREATE_ONLY   = 'create_only';
	public const MODE_UPDATE_ONLY   = 'update_only';
	public const MODE_CREATE_UPDATE = 'create_update';

	public const IDENTIFIER_SKU = 'sku';
	public const IDENTIFIER_ID  = 'id';

	public int $id = 0;
	public string $job_uuid = '';
	public int $user_id = 0;
	public string $status = self::STATUS_DRAFT;
	public string $source_type = 'csv';
	public string $source_filename = '';
	public string $file_path = '';
	public ?string $image_zip_path = null;
	public string $import_mode = self::MODE_CREATE_UPDATE;
	public string $identifier_type = self::IDENTIFIER_SKU;
	public array $mapping_config = [];
	public array $options_config = [];
	public int $total_rows = 0;
	public int $processed_rows = 0;
	public int $created_count = 0;
	public int $updated_count = 0;
	public int $skipped_count = 0;
	public int $warning_count = 0;
	public int $failed_count = 0;
	public int $current_batch = 0;
	public string $created_at = '';
	public ?string $started_at = null;
	public ?string $completed_at = null;
	public ?string $cancelled_at = null;
	public ?string $error_summary = null;
	public string $plugin_version = WPCALIBRATE_CATALOG_IMPORTER_VERSION;

	/**
	 * Populate from array or database object.
	 *
	 * @param array|object $data Raw data.
	 * @return self
	 */
	public static function from_data( array|object $data ): self {
		$job = new self();
		$arr = (array) $data;

		$job->id              = (int) ( $arr['id'] ?? 0 );
		$job->job_uuid        = (string) ( $arr['job_uuid'] ?? '' );
		$job->user_id         = (int) ( $arr['user_id'] ?? 0 );
		$job->status          = (string) ( $arr['status'] ?? self::STATUS_DRAFT );
		$job->source_type     = (string) ( $arr['source_type'] ?? 'csv' );
		$job->source_filename = (string) ( $arr['source_filename'] ?? '' );
		$job->file_path       = (string) ( $arr['file_path'] ?? '' );
		$job->image_zip_path  = ! empty( $arr['image_zip_path'] ) ? (string) $arr['image_zip_path'] : null;
		$job->import_mode     = (string) ( $arr['import_mode'] ?? self::MODE_CREATE_UPDATE );
		$job->identifier_type = (string) ( $arr['identifier_type'] ?? self::IDENTIFIER_SKU );

		$job->mapping_config = is_string( $arr['mapping_config'] ?? null )
			? ( json_decode( $arr['mapping_config'], true ) ?: [] )
			: ( is_array( $arr['mapping_config'] ?? null ) ? $arr['mapping_config'] : [] );

		$job->options_config = is_string( $arr['options_config'] ?? null )
			? ( json_decode( $arr['options_config'], true ) ?: [] )
			: ( is_array( $arr['options_config'] ?? null ) ? $arr['options_config'] : [] );

		$job->total_rows      = (int) ( $arr['total_rows'] ?? 0 );
		$job->processed_rows  = (int) ( $arr['processed_rows'] ?? 0 );
		$job->created_count   = (int) ( $arr['created_count'] ?? 0 );
		$job->updated_count   = (int) ( $arr['updated_count'] ?? 0 );
		$job->skipped_count   = (int) ( $arr['skipped_count'] ?? 0 );
		$job->warning_count   = (int) ( $arr['warning_count'] ?? 0 );
		$job->failed_count    = (int) ( $arr['failed_count'] ?? 0 );
		$job->current_batch   = (int) ( $arr['current_batch'] ?? 0 );
		$job->created_at      = (string) ( $arr['created_at'] ?? current_time( 'mysql' ) );
		$job->started_at      = ! empty( $arr['started_at'] ) ? (string) $arr['started_at'] : null;
		$job->completed_at    = ! empty( $arr['completed_at'] ) ? (string) $arr['completed_at'] : null;
		$job->cancelled_at    = ! empty( $arr['cancelled_at'] ) ? (string) $arr['cancelled_at'] : null;
		$job->error_summary   = ! empty( $arr['error_summary'] ) ? (string) $arr['error_summary'] : null;
		$job->plugin_version  = (string) ( $arr['plugin_version'] ?? WPCALIBRATE_CATALOG_IMPORTER_VERSION );

		return $job;
	}

	/**
	 * Convert to array for database write or JSON output.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return [
			'id'              => $this->id,
			'job_uuid'        => $this->job_uuid,
			'user_id'         => $this->user_id,
			'status'          => $this->status,
			'source_type'     => $this->source_type,
			'source_filename' => $this->source_filename,
			'file_path'       => $this->file_path,
			'image_zip_path'  => $this->image_zip_path,
			'import_mode'     => $this->import_mode,
			'identifier_type' => $this->identifier_type,
			'mapping_config'  => wp_json_encode( $this->mapping_config ),
			'options_config'  => wp_json_encode( $this->options_config ),
			'total_rows'      => $this->total_rows,
			'processed_rows'  => $this->processed_rows,
			'created_count'   => $this->created_count,
			'updated_count'   => $this->updated_count,
			'skipped_count'   => $this->skipped_count,
			'warning_count'   => $this->warning_count,
			'failed_count'    => $this->failed_count,
			'current_batch'   => $this->current_batch,
			'created_at'      => $this->created_at,
			'started_at'      => $this->started_at,
			'completed_at'    => $this->completed_at,
			'cancelled_at'    => $this->cancelled_at,
			'error_summary'   => $this->error_summary,
			'plugin_version'  => $this->plugin_version,
		];
	}

	/**
	 * Check if job is actively running or queued.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return in_array( $this->status, [ self::STATUS_QUEUED, self::STATUS_PROCESSING, self::STATUS_VALIDATING ], true );
	}

	/**
	 * Check if job is finished.
	 *
	 * @return bool
	 */
	public function is_finished(): bool {
		return in_array( $this->status, [ self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_CANCELLED ], true );
	}

	/**
	 * Calculate progress percentage.
	 *
	 * @return int 0 to 100.
	 */
	public function get_progress_percent(): int {
		if ( $this->total_rows <= 0 ) {
			return $this->is_finished() ? 100 : 0;
		}
		$pct = (int) round( ( $this->processed_rows / $this->total_rows ) * 100 );
		return min( 100, max( 0, $pct ) );
	}
}
