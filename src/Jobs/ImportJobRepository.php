<?php
namespace WPCalibrate\CatalogImporter\Jobs;

use WPCalibrate\CatalogImporter\Database\SchemaManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repository for Import Jobs and Row Logs.
 */
class ImportJobRepository {

	/**
	 * Create a new import job record.
	 *
	 * @param array $data Job attributes.
	 * @return ImportJob
	 */
	public function create( array $data ): ImportJob {
		global $wpdb;

		if ( empty( $data['job_uuid'] ) ) {
			$data['job_uuid'] = wp_generate_uuid4();
		}
		if ( empty( $data['user_id'] ) ) {
			$data['user_id'] = get_current_user_id();
		}
		if ( empty( $data['created_at'] ) ) {
			$data['created_at'] = current_time( 'mysql' );
		}

		$job   = ImportJob::from_data( $data );
		$table = SchemaManager::get_table_name( SchemaManager::TABLE_JOBS );

		$row = $job->to_array();
		unset( $row['id'] );

		$wpdb->insert( $table, $row );
		$job->id = (int) $wpdb->insert_id;

		return $job;
	}

	/**
	 * Find job by primary ID.
	 *
	 * @param int $id Job ID.
	 * @return ImportJob|null
	 */
	public function get_by_id( int $id ): ?ImportJob {
		global $wpdb;
		$table = SchemaManager::get_table_name( SchemaManager::TABLE_JOBS );

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return $row ? ImportJob::from_data( $row ) : null;
	}

	/**
	 * Find job by UUID.
	 *
	 * @param string $uuid Job UUID.
	 * @return ImportJob|null
	 */
	public function get_by_uuid( string $uuid ): ?ImportJob {
		global $wpdb;
		$table = SchemaManager::get_table_name( SchemaManager::TABLE_JOBS );

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE job_uuid = %s", $uuid ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		return $row ? ImportJob::from_data( $row ) : null;
	}

	/**
	 * Update entire job entity.
	 *
	 * @param ImportJob $job Job object.
	 * @return bool
	 */
	public function update( ImportJob $job ): bool {
		global $wpdb;
		$table = SchemaManager::get_table_name( SchemaManager::TABLE_JOBS );

		$data = $job->to_array();
		unset( $data['id'] );

		$result = $wpdb->update(
			$table,
			$data,
			[ 'id' => $job->id ],
			null,
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Update job status and timestamps.
	 *
	 * @param int $id Job ID.
	 * @param string $status New status.
	 * @param array $extra Optional extra fields to update.
	 * @return bool
	 */
	public function update_status( int $id, string $status, array $extra = [] ): bool {
		global $wpdb;
		$table = SchemaManager::get_table_name( SchemaManager::TABLE_JOBS );

		$data = array_merge( [ 'status' => $status ], $extra );

		if ( ImportJob::STATUS_PROCESSING === $status && empty( $data['started_at'] ) ) {
			$data['started_at'] = current_time( 'mysql' );
		}
		if ( in_array( $status, [ ImportJob::STATUS_COMPLETED, ImportJob::STATUS_FAILED ], true ) && empty( $data['completed_at'] ) ) {
			$data['completed_at'] = current_time( 'mysql' );
		}
		if ( ImportJob::STATUS_CANCELLED === $status && empty( $data['cancelled_at'] ) ) {
			$data['cancelled_at'] = current_time( 'mysql' );
		}

		$result = $wpdb->update( $table, $data, [ 'id' => $id ], null, [ '%d' ] );
		return false !== $result;
	}

	/**
	 * Increment row execution counters atomically.
	 *
	 * @param int $id Job ID.
	 * @param array $increments Key => amount (e.g. ['processed_rows' => 5, 'created_count' => 3]).
	 * @return bool
	 */
	public function increment_counters( int $id, array $increments ): bool {
		global $wpdb;
		$table = SchemaManager::get_table_name( SchemaManager::TABLE_JOBS );

		$allowed = [
			'processed_rows',
			'created_count',
			'updated_count',
			'skipped_count',
			'warning_count',
			'failed_count',
			'current_batch',
		];

		$clauses = [];
		foreach ( $increments as $field => $val ) {
			if ( in_array( $field, $allowed, true ) ) {
				$val = (int) $val;
				if ( 'current_batch' === $field ) {
					// Set current_batch directly rather than increment.
					$clauses[] = "{$field} = {$val}";
				} else {
					$clauses[] = "{$field} = {$field} + {$val}";
				}
			}
		}

		if ( empty( $clauses ) ) {
			return true;
		}

		$sql    = "UPDATE {$table} SET " . implode( ', ', $clauses ) . ' WHERE id = %d';
		$result = $wpdb->query( $wpdb->prepare( $sql, $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		return false !== $result;
	}

	/**
	 * Retrieve paginated jobs for history table.
	 *
	 * @param int $page Current page (1-indexed).
	 * @param int $per_page Records per page.
	 * @param array $filters Filters (e.g. status, search).
	 * @return array ['items' => ImportJob[], 'total' => int, 'pages' => int]
	 */
	public function get_paginated( int $page = 1, int $per_page = 20, array $filters = [] ): array {
		global $wpdb;
		$table = SchemaManager::get_table_name( SchemaManager::TABLE_JOBS );

		$where = [ '1=1' ];
		$args  = [];

		if ( ! empty( $filters['status'] ) ) {
			$where[] = 'status = %s';
			$args[]  = $filters['status'];
		}

		if ( ! empty( $filters['search'] ) ) {
			$where[] = '(source_filename LIKE %s OR job_uuid LIKE %s)';
			$like    = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$args[]  = $like;
			$args[]  = $like;
		}

		$where_clause = implode( ' AND ', $where );

		// Count query.
		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}";
		if ( ! empty( $args ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $args ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$total = (int) $wpdb->get_var( $count_sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$offset = max( 0, ( $page - 1 ) * $per_page );

		// Fetch query.
		$fetch_sql = "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY id DESC LIMIT %d, %d";
		$query_args = array_merge( $args, [ $offset, $per_page ] );
		$rows = $wpdb->get_results( $wpdb->prepare( $fetch_sql, $query_args ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		$items = [];
		if ( $rows ) {
			foreach ( $rows as $r ) {
				$items[] = ImportJob::from_data( $r );
			}
		}

		return [
			'items' => $items,
			'total' => $total,
			'pages' => (int) ceil( $total / max( 1, $per_page ) ),
		];
	}

	/**
	 * Delete a job and its associated row logs.
	 *
	 * @param int $id Job ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		$jobs_table = SchemaManager::get_table_name( SchemaManager::TABLE_JOBS );
		$rows_table = SchemaManager::get_table_name( SchemaManager::TABLE_ROWS );

		$this->clear_row_logs( $id );
		$result = $wpdb->delete( $jobs_table, [ 'id' => $id ], [ '%d' ] );

		return false !== $result;
	}

	/**
	 * Log a row result (success, warning, failure, skip).
	 *
	 * @param int $job_id Job ID.
	 * @param int $row_index Spreadsheet row number (1-indexed).
	 * @param string $status 'created', 'updated', 'skipped', 'warning', 'failed'
	 * @param string|null $identifier SKU or ID.
	 * @param int|null $product_id Resulting WC Product ID.
	 * @param string|null $action Action performed.
	 * @param string|null $message Human-readable message.
	 * @param string|null $error_code Machine error code.
	 * @param array|null $data_snapshot Sanitized raw data snapshot.
	 * @return int Insert ID.
	 */
	public function log_row(
		int $job_id,
		int $row_index,
		string $status,
		?string $identifier = null,
		?int $product_id = null,
		?string $action = null,
		?string $message = null,
		?string $error_code = null,
		?array $data_snapshot = null
	): int {
		global $wpdb;
		$table = SchemaManager::get_table_name( SchemaManager::TABLE_ROWS );

		$wpdb->insert(
			$table,
			[
				'job_id'        => $job_id,
				'row_index'     => $row_index,
				'identifier'    => $identifier ? substr( $identifier, 0, 191 ) : null,
				'product_id'    => $product_id,
				'status'        => $status,
				'action'        => $action,
				'message'       => $message,
				'error_code'    => $error_code,
				'data_snapshot' => $data_snapshot ? wp_json_encode( $data_snapshot ) : null,
				'created_at'    => current_time( 'mysql' ),
			],
			[
				'%d',
				'%d',
				'%s',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			]
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Fetch paginated row logs for a job.
	 *
	 * @param int $job_id Job ID.
	 * @param int $page Page number.
	 * @param int $per_page Items per page.
	 * @param string|null $status_filter Optional status filter.
	 * @return array
	 */
	public function get_row_logs( int $job_id, int $page = 1, int $per_page = 50, ?string $status_filter = null ): array {
		global $wpdb;
		$table = SchemaManager::get_table_name( SchemaManager::TABLE_ROWS );

		$where = [ 'job_id = %d' ];
		$args  = [ $job_id ];

		if ( ! empty( $status_filter ) ) {
			$where[] = 'status = %s';
			$args[]  = $status_filter;
		}

		$where_clause = implode( ' AND ', $where );

		$total = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}", $args ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		$offset = max( 0, ( $page - 1 ) * $per_page );
		$args[] = $offset;
		$args[] = $per_page;

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_clause} ORDER BY row_index ASC LIMIT %d, %d", $args ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);

		return [
			'items' => $rows ?: [],
			'total' => $total,
			'pages' => (int) ceil( $total / max( 1, $per_page ) ),
		];
	}

	/**
	 * Get all failed rows for a job (used for error CSV report generation).
	 *
	 * @param int $job_id Job ID.
	 * @return array
	 */
	public function get_failed_rows( int $job_id ): array {
		global $wpdb;
		$table = SchemaManager::get_table_name( SchemaManager::TABLE_ROWS );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT row_index, identifier, status, error_code, message FROM {$table} WHERE job_id = %d AND status IN ('failed', 'warning') ORDER BY row_index ASC",
				$job_id
			)
		);

		return $rows ?: [];
	}

	/**
	 * Clear row logs for a job.
	 *
	 * @param int $job_id Job ID.
	 * @return bool
	 */
	public function clear_row_logs( int $job_id ): bool {
		global $wpdb;
		$table = SchemaManager::get_table_name( SchemaManager::TABLE_ROWS );
		$result = $wpdb->delete( $table, [ 'job_id' => $job_id ], [ '%d' ] );

		return false !== $result;
	}
}
