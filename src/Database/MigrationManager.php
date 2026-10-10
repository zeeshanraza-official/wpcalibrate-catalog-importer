<?php
namespace WPCalibrate\CatalogImporter\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migration Manager for versioned, repeatable database migrations.
 */
class MigrationManager {

	public const OPTION_DB_VERSION = 'wpcalibrate_catalog_importer_db_version';

	/**
	 * Run migrations if needed.
	 *
	 * @return void
	 */
	public static function run(): void {
		$installed_version = get_option( self::OPTION_DB_VERSION, '0.0.0' );

		if ( version_compare( $installed_version, WPCALIBRATE_CATALOG_IMPORTER_DB_VERSION, '<' ) ) {
			self::migrate( $installed_version, WPCALIBRATE_CATALOG_IMPORTER_DB_VERSION );
		}
	}

	/**
	 * Perform migrations from current to target version.
	 *
	 * @param string $from Current version.
	 * @param string $to Target version.
	 * @return void
	 */
	protected static function migrate( string $from, string $to ): void {
		// Install or update schema tables.
		SchemaManager::install_tables();

		// Record successful migration version.
		update_option( self::OPTION_DB_VERSION, $to, true );
	}
}
