<?php
namespace WPCalibrate\CatalogImporter\Import\Processing;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages batch locking, concurrency control, and idempotent batch execution.
 */
class IdempotencyManager {

	/**
	 * Lock expiry in seconds (10 minutes).
	 */
	public const LOCK_EXPIRY = 600;

	/**
	 * Acquire an exclusive lock for a specific batch.
	 *
	 * @param int $job_id
	 * @param int $batch_number
	 * @return bool True if lock acquired, false if already locked.
	 */
	public function acquire_lock( int $job_id, int $batch_number ): bool {
		$transient_key = "wpcalibrate_batch_lock_{$job_id}_{$batch_number}";
		$existing      = get_transient( $transient_key );

		if ( $existing ) {
			return false;
		}

		return set_transient( $transient_key, time(), self::LOCK_EXPIRY );
	}

	/**
	 * Release batch lock.
	 *
	 * @param int $job_id
	 * @param int $batch_number
	 * @return void
	 */
	public function release_lock( int $job_id, int $batch_number ): void {
		$transient_key = "wpcalibrate_batch_lock_{$job_id}_{$batch_number}";
		delete_transient( $transient_key );
	}

	/**
	 * Clear all locks for a job (e.g. on completion or cancellation).
	 *
	 * @param int $job_id
	 * @param int $max_batches
	 * @return void
	 */
	public function clear_all_job_locks( int $job_id, int $max_batches = 1000 ): void {
		// Clean transient locks.
		for ( $b = 0; $b <= $max_batches; $b++ ) {
			$transient_key = "wpcalibrate_batch_lock_{$job_id}_{$b}";
			if ( get_transient( $transient_key ) ) {
				delete_transient( $transient_key );
			}
		}
	}
}
