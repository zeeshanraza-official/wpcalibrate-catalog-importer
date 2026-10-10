<?php
namespace WPCalibrate\CatalogImporter\Import\Mapping;

use WPCalibrate\CatalogImporter\Database\SchemaManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Repository for storing and retrieving named field mapping templates.
 */
class MappingTemplateRepository {

	/**
	 * Create a new template.
	 *
	 * @param string $name Template name.
	 * @param array $mapping_data Mapping structure.
	 * @param string $source_type Source type ('csv', 'xlsx').
	 * @param string|null $description Optional description.
	 * @return int Insert ID or 0 on error.
	 */
	public function create( string $name, array $mapping_data, string $source_type = 'csv', ?string $description = null ): int {
		global $wpdb;
		$table = SchemaManager::get_table_name( SchemaManager::TABLE_TEMPLATES );

		$now    = current_time( 'mysql' );
		$result = $wpdb->insert(
			$table,
			[
				'name'         => sanitize_text_field( $name ),
				'description'  => $description ? sanitize_textarea_field( $description ) : null,
				'mapping_data' => wp_json_encode( $mapping_data ),
				'source_type'  => sanitize_key( $source_type ),
				'created_at'   => $now,
				'updated_at'   => $now,
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s' ]
		);

		return $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update an existing template.
	 *
	 * @param int $id Template ID.
	 * @param string $name Template name.
	 * @param array $mapping_data Mapping structure.
	 * @param string|null $description Optional description.
	 * @return bool
	 */
	public function update( int $id, string $name, array $mapping_data, ?string $description = null ): bool {
		global $wpdb;
		$table = SchemaManager::get_table_name( SchemaManager::TABLE_TEMPLATES );

		$data = [
			'name'         => sanitize_text_field( $name ),
			'description'  => $description ? sanitize_textarea_field( $description ) : null,
			'mapping_data' => wp_json_encode( $mapping_data ),
			'updated_at'   => current_time( 'mysql' ),
		];

		$result = $wpdb->update(
			$table,
			$data,
			[ 'id' => $id ],
			[ '%s', '%s', '%s', '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}

	/**
	 * Delete a template.
	 *
	 * @param int $id Template ID.
	 * @return bool
	 */
	public function delete( int $id ): bool {
		global $wpdb;
		$table  = SchemaManager::get_table_name( SchemaManager::TABLE_TEMPLATES );
		$result = $wpdb->delete( $table, [ 'id' => $id ], [ '%d' ] );

		return false !== $result;
	}

	/**
	 * Get template by ID.
	 *
	 * @param int $id Template ID.
	 * @return array|null
	 */
	public function get_by_id( int $id ): ?array {
		global $wpdb;
		$table = SchemaManager::get_table_name( SchemaManager::TABLE_TEMPLATES );

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$row['mapping_data'] = json_decode( $row['mapping_data'], true ) ?: [];
		return $row;
	}

	/**
	 * Get template by name.
	 *
	 * @param string $name Template name.
	 * @return array|null
	 */
	public function get_by_name( string $name ): ?array {
		global $wpdb;
		$table = SchemaManager::get_table_name( SchemaManager::TABLE_TEMPLATES );

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE name = %s", $name ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$row['mapping_data'] = json_decode( $row['mapping_data'], true ) ?: [];
		return $row;
	}

	/**
	 * Get all saved templates.
	 *
	 * @return array
	 */
	public function get_all(): array {
		global $wpdb;
		$table = SchemaManager::get_table_name( SchemaManager::TABLE_TEMPLATES );

		$rows = $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY name ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return [];
		}

		foreach ( $rows as &$row ) {
			$row['mapping_data'] = json_decode( $row['mapping_data'], true ) ?: [];
		}

		return $rows;
	}
}
