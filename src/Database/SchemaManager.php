<?php
namespace WPCalibrate\CatalogImporter\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles database table installation and updates using dbDelta.
 */
class SchemaManager {

	/**
	 * Jobs table name without prefix.
	 */
	public const TABLE_JOBS = 'wpcalibrate_catalog_import_jobs';

	/**
	 * Row details and errors table name without prefix.
	 */
	public const TABLE_ROWS = 'wpcalibrate_catalog_import_rows';

	/**
	 * Mapping templates table name without prefix.
	 */
	public const TABLE_TEMPLATES = 'wpcalibrate_catalog_mapping_templates';

	/**
	 * Get table name with WordPress prefix.
	 *
	 * @param string $short_name Short table name.
	 * @return string
	 */
	public static function get_table_name( string $short_name ): string {
		global $wpdb;
		return $wpdb->prefix . $short_name;
	}

	/**
	 * Install or upgrade custom tables.
	 *
	 * @return bool
	 */
	public static function install_tables(): bool {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$table_jobs      = self::get_table_name( self::TABLE_JOBS );
		$table_rows      = self::get_table_name( self::TABLE_ROWS );
		$table_templates = self::get_table_name( self::TABLE_TEMPLATES );

		$sql_jobs = "CREATE TABLE {$table_jobs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_uuid varchar(64) NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			status varchar(32) NOT NULL DEFAULT 'draft',
			source_type varchar(16) NOT NULL DEFAULT 'csv',
			source_filename varchar(255) NOT NULL DEFAULT '',
			file_path text NOT NULL,
			image_zip_path text NULL,
			import_mode varchar(32) NOT NULL DEFAULT 'create_update',
			identifier_type varchar(32) NOT NULL DEFAULT 'sku',
			mapping_config longtext NOT NULL,
			options_config longtext NOT NULL,
			total_rows bigint(20) unsigned NOT NULL DEFAULT 0,
			processed_rows bigint(20) unsigned NOT NULL DEFAULT 0,
			created_count bigint(20) unsigned NOT NULL DEFAULT 0,
			updated_count bigint(20) unsigned NOT NULL DEFAULT 0,
			skipped_count bigint(20) unsigned NOT NULL DEFAULT 0,
			warning_count bigint(20) unsigned NOT NULL DEFAULT 0,
			failed_count bigint(20) unsigned NOT NULL DEFAULT 0,
			current_batch int(10) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			started_at datetime NULL,
			completed_at datetime NULL,
			cancelled_at datetime NULL,
			error_summary text NULL,
			plugin_version varchar(32) NOT NULL DEFAULT '1.0.0',
			PRIMARY KEY  (id),
			UNIQUE KEY job_uuid (job_uuid),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql_rows = "CREATE TABLE {$table_rows} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_id bigint(20) unsigned NOT NULL,
			row_index bigint(20) unsigned NOT NULL,
			identifier varchar(191) NULL,
			product_id bigint(20) unsigned NULL,
			status varchar(32) NOT NULL DEFAULT 'pending',
			action varchar(32) NULL,
			message text NULL,
			error_code varchar(64) NULL,
			data_snapshot text NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY job_id_row (job_id, row_index),
			KEY job_id_status (job_id, status)
		) {$charset_collate};";

		$sql_templates = "CREATE TABLE {$table_templates} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL,
			description text NULL,
			mapping_data longtext NOT NULL,
			source_type varchar(16) NOT NULL DEFAULT 'csv',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY template_name (name)
		) {$charset_collate};";

		dbDelta( $sql_jobs );
		dbDelta( $sql_rows );
		dbDelta( $sql_templates );

		return true;
	}

	/**
	 * Drop all custom tables (used during complete uninstall when configured).
	 *
	 * @return void
	 */
	public static function drop_tables(): void {
		global $wpdb;

		$tables = [
			self::get_table_name( self::TABLE_JOBS ),
			self::get_table_name( self::TABLE_ROWS ),
			self::get_table_name( self::TABLE_TEMPLATES ),
		];

		foreach ( $tables as $table ) {
			// Table names are hardcoded with $wpdb->prefix so safe from SQL injection.
			$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}
}
