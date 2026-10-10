<?php
/**
 * Plugin Name:       WPCalibrate Catalog Importer
 * Plugin URI:        https://wpcalibrate.com
 * Description:       High-performance, secure bulk product catalog importer for WooCommerce supporting CSV, XLSX, variable products, image packages, dry-run validation, and Action Scheduler background processing.
 * Version:           1.0.0
 * Author:            WPCalibrate
 * Author URI:        https://wpcalibrate.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wpcalibrate-catalog-importer
 * Domain Path:       /languages
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Requires Plugins:  woocommerce
 * Update URI:        https://github.com/zeeshanraza-official/wpcalibrate-catalog-importer
 * GitHub Plugin URI: zeeshanraza-official/wpcalibrate-catalog-importer
 * Primary Branch:    main
 * Release Asset:     true
 *
 * @package WPCalibrate\CatalogImporter
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'WPCALIBRATE_CATALOG_IMPORTER_VERSION', '1.0.0' );
define( 'WPCALIBRATE_CATALOG_IMPORTER_FILE', __FILE__ );
define( 'WPCALIBRATE_CATALOG_IMPORTER_PATH', plugin_dir_path( __FILE__ ) );
define( 'WPCALIBRATE_CATALOG_IMPORTER_URL', plugin_dir_url( __FILE__ ) );
define( 'WPCALIBRATE_CATALOG_IMPORTER_BASENAME', plugin_basename( __FILE__ ) );
define( 'WPCALIBRATE_CATALOG_IMPORTER_MIN_PHP', '8.2.0' );
define( 'WPCALIBRATE_CATALOG_IMPORTER_MIN_WP', '6.5.0' );
define( 'WPCALIBRATE_CATALOG_IMPORTER_MIN_WC', '8.5.0' );
define( 'WPCALIBRATE_CATALOG_IMPORTER_DB_VERSION', '1.0.0' );

/**
 * Autoloader for WPCalibrate\CatalogImporter classes.
 *
 * @param string $class Fully qualified class name.
 * @return void
 */
spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'WPCalibrate\\CatalogImporter\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );
		$file           = WPCALIBRATE_CATALOG_IMPORTER_PATH . 'src/' . str_replace( '\\', '/', $relative_class ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

/**
 * Check PHP compatibility before running anything else.
 */
if ( version_compare( PHP_VERSION, WPCALIBRATE_CATALOG_IMPORTER_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: Plugin name, 2: Required PHP version, 3: Current PHP version */
							__( '%1$s requires PHP %2$s or higher. Your server is running PHP %3$s. Please upgrade your PHP version.', 'wpcalibrate-catalog-importer' ),
							'WPCalibrate Catalog Importer',
							WPCALIBRATE_CATALOG_IMPORTER_MIN_PHP,
							PHP_VERSION
						)
					);
					?>
				</p>
			</div>
			<?php
		}
	);
	return;
}

/**
 * Declare High-Performance Order Storage (HPOS) and Features compatibility.
 */
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				WPCALIBRATE_CATALOG_IMPORTER_FILE,
				true
			);
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'cart_checkout_blocks',
				WPCALIBRATE_CATALOG_IMPORTER_FILE,
				true
			);
		}
	}
);

/**
 * Activation hook.
 */
register_activation_hook(
	__FILE__,
	static function (): void {
		// Run activation checks and migrations.
		\WPCalibrate\CatalogImporter\Plugin::activate();
	}
);

/**
 * Deactivation hook.
 */
register_deactivation_hook(
	__FILE__,
	static function (): void {
		\WPCalibrate\CatalogImporter\Plugin::deactivate();
	}
);

/**
 * Initialize plugin on plugins_loaded.
 */
add_action(
	'plugins_loaded',
	static function (): void {
		// Verify WooCommerce is active and meets requirements.
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					?>
					<div class="notice notice-warning is-dismissible">
						<p>
							<strong><?php esc_html_e( 'WPCalibrate Catalog Importer', 'wpcalibrate-catalog-importer' ); ?>:</strong>
							<?php esc_html_e( 'WooCommerce is required for this plugin to function. Please install and activate WooCommerce.', 'wpcalibrate-catalog-importer' ); ?>
						</p>
					</div>
					<?php
				}
			);
			return;
		}

		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, WPCALIBRATE_CATALOG_IMPORTER_MIN_WC, '<' ) ) {
			add_action(
				'admin_notices',
				static function (): void {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					?>
					<div class="notice notice-error is-dismissible">
						<p>
							<strong><?php esc_html_e( 'WPCalibrate Catalog Importer', 'wpcalibrate-catalog-importer' ); ?>:</strong>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: Required WooCommerce version, 2: Current WooCommerce version */
									__( 'Requires WooCommerce %1$s or higher. Your store is running WooCommerce %2$s.', 'wpcalibrate-catalog-importer' ),
									WPCALIBRATE_CATALOG_IMPORTER_MIN_WC,
									WC_VERSION
								)
							);
							?>
						</p>
					</div>
					<?php
				}
			);
			return;
		}

		// Initialize core plugin.
		\WPCalibrate\CatalogImporter\Plugin::instance()->init();
	},
	20
);
