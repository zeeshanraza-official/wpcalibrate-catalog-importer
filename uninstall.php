<?php
/**
 * Uninstall routine for WPCalibrate Catalog Importer.
 *
 * @package WPCalibrate\CatalogImporter
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Check administrator configured data retention policy.
$settings = get_option( 'wpcalibrate_catalog_importer_settings', [] );
$delete_data = ! empty( $settings['delete_data_on_uninstall'] );

if ( ! $delete_data ) {
	// Preserve all plugin tables, settings, and job history for safe future reinstall.
	// NOTE: WooCommerce products and store data are ALWAYS preserved regardless of this setting.
	return;
}

global $wpdb;

// 1. Drop plugin custom database tables.
$tables = [
	$wpdb->prefix . 'wpcalibrate_catalog_import_jobs',
	$wpdb->prefix . 'wpcalibrate_catalog_import_rows',
	$wpdb->prefix . 'wpcalibrate_catalog_mapping_templates',
];

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// 2. Remove options.
delete_option( 'wpcalibrate_catalog_importer_settings' );
delete_option( 'wpcalibrate_catalog_importer_db_version' );
delete_option( 'wpcalibrate_catalog_importer_license_key' );

// 3. Clean temporary uploads directory.
$wp_upload = wp_upload_dir();
if ( empty( $wp_upload['error'] ) ) {
	$target_dir = trailingslashit( $wp_upload['basedir'] ) . 'wpcalibrate-catalog-importer';
	if ( is_dir( $target_dir ) ) {
		$files = scandir( $target_dir );
		if ( $files ) {
			foreach ( $files as $file ) {
				if ( '.' === $file || '..' === $file ) {
					continue;
				}
				$path = trailingslashit( $target_dir ) . $file;
				if ( is_dir( $path ) ) {
					$sub_files = scandir( $path );
					if ( $sub_files ) {
						foreach ( $sub_files as $sf ) {
							if ( '.' !== $sf && '..' !== $sf ) {
								@unlink( trailingslashit( $path ) . $sf );
							}
						}
					}
					@rmdir( $path );
				} else {
					@unlink( $path );
				}
			}
		}
		@rmdir( $target_dir );
	}
}

// 4. Unschedule Action Scheduler actions.
if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( 'wpcalibrate_catalog_importer_process_batch', [], 'wpcalibrate-catalog-importer' );
}
