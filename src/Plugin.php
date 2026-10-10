<?php
namespace WPCalibrate\CatalogImporter;

use WPCalibrate\CatalogImporter\Admin\AdminMenu;
use WPCalibrate\CatalogImporter\Database\MigrationManager;
use WPCalibrate\CatalogImporter\Import\Processing\BatchProcessor;
use WPCalibrate\CatalogImporter\Import\Processing\IdempotencyManager;
use WPCalibrate\CatalogImporter\Import\Validation\UploadValidator;
use WPCalibrate\CatalogImporter\Admin\SettingsPage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Plugin singleton coordinator.
 */
class Plugin {

	protected static ?self $instance = null;

	protected AdminMenu $admin_menu;
	protected BatchProcessor $batch_processor;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	protected function __construct() {
		$this->admin_menu       = new AdminMenu();
		$this->batch_processor = new BatchProcessor();
	}

	/**
	 * Initialize plugin components after WooCommerce is loaded.
	 *
	 * @return void
	 */
	public function init(): void {
		// Load translations.
		load_plugin_textdomain(
			'wpcalibrate-catalog-importer',
			false,
			dirname( plugin_basename( WPCALIBRATE_CATALOG_IMPORTER_FILE ) ) . '/languages'
		);

		// Run pending database migrations if version bumped.
		MigrationManager::run();

		// Register Admin menus and asset handlers.
		if ( is_admin() ) {
			$this->admin_menu->register();

			$updater = new \WPCalibrate\CatalogImporter\Services\GitHubUpdater(
				'zeeshanraza-official',
				'wpcalibrate-catalog-importer',
				WPCALIBRATE_CATALOG_IMPORTER_FILE,
				WPCALIBRATE_CATALOG_IMPORTER_VERSION
			);
			$updater->init();
		}

		// Register background batch Action Scheduler worker hook.
		$this->batch_processor->register_hooks();
	}

	/**
	 * Plugin activation lifecycle callback.
	 *
	 * @return void
	 */
	public static function activate(): void {
		// Run table installations.
		MigrationManager::run();

		// Set default settings if missing.
		if ( false === get_option( SettingsPage::OPTION_NAME ) ) {
			update_option( SettingsPage::OPTION_NAME, SettingsPage::get_defaults() );
		}

		// Create and protect upload directory.
		UploadValidator::get_upload_dir();
	}

	/**
	 * Plugin deactivation lifecycle callback.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Unschedule pending Action Scheduler actions.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( BatchProcessor::ACTION_PROCESS_BATCH, [], 'wpcalibrate-catalog-importer' );
		}
	}
}
