<?php
namespace WPCalibrate\CatalogImporter\Admin;

use WPCalibrate\CatalogImporter\Security\SecurityHelper;
use WPCalibrate\CatalogImporter\Import\Demo\DemoCatalogImporter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles WordPress admin menu registration, shared WPCalibrate parent menu reuse,
 * and page callbacks.
 */
class AdminMenu {

	public const PARENT_SLUG = 'wpcalibrate-menu';

	public const SLUG_OVERVIEW  = 'wpcalibrate-catalog-importer-overview';
	public const SLUG_WIZARD    = 'wpcalibrate-catalog-importer';
	public const SLUG_HISTORY   = 'wpcalibrate-catalog-importer-history';
	public const SLUG_TEMPLATES = 'wpcalibrate-catalog-importer-templates';
	public const SLUG_SETTINGS  = 'wpcalibrate-catalog-importer-settings';
	public const SLUG_LICENSE   = 'wpcalibrate-catalog-importer-license';
	public const SLUG_SUPPORT   = 'wpcalibrate-catalog-importer-support';

	protected OverviewPage $overview_page;
	protected WizardPage $wizard_page;
	protected HistoryPage $history_page;
	protected TemplatesPage $templates_page;
	protected SettingsPage $settings_page;
	protected LicensePage $license_page;
	protected SupportPage $support_page;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->overview_page  = new OverviewPage();
		$this->wizard_page    = new WizardPage();
		$this->history_page   = new HistoryPage();
		$this->templates_page = new TemplatesPage();
		$this->settings_page  = new SettingsPage();
		$this->license_page   = new LicensePage();
		$this->support_page   = new SupportPage();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_menu', [ $this, 'register_menus' ], 15 );
		add_action( 'admin_head', [ $this, 'output_menu_icon_styles' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Register page controllers AJAX/POST handlers.
		$this->wizard_page->register();
		$this->history_page->register();
		$this->templates_page->register();
		$this->settings_page->register();
		$this->license_page->register();

		// Demo catalog handlers.
		add_action( 'wp_ajax_wpcalibrate_import_demo_catalog', [ $this, 'ajax_import_demo_catalog' ] );
		add_action( 'admin_post_wpcalibrate_load_demo_wizard', [ $this, 'handle_load_demo_wizard' ] );
	}

	/**
	 * Output inline CSS in admin_head on all WordPress admin pages
	 * to ensure the WPCalibrate sidebar menu icon matches the 20x20px dimension
	 * and standard muted gray color (#a7aaad) of WordPress dashboard menu icons.
	 *
	 * @return void
	 */
	public function output_menu_icon_styles(): void {
		?>
		<style id="wpcalibrate-menu-icon-style">
			#adminmenu .toplevel_page_wpcalibrate-menu .wp-menu-image {
				display: flex !important;
				align-items: center !important;
				justify-content: center !important;
			}
			#adminmenu .toplevel_page_wpcalibrate-menu .wp-menu-image img {
				width: 20px !important;
				height: 20px !important;
				max-width: 20px !important;
				max-height: 20px !important;
				padding: 0 !important;
				margin: 0 !important;
				box-sizing: border-box !important;
				opacity: 0.7 !important;
				filter: grayscale(100%) brightness(70%) !important;
				transition: opacity 0.15s ease-in-out, filter 0.15s ease-in-out;
			}
			#adminmenu .toplevel_page_wpcalibrate-menu:hover .wp-menu-image img,
			#adminmenu .toplevel_page_wpcalibrate-menu.opensub .wp-menu-image img,
			#adminmenu .toplevel_page_wpcalibrate-menu.wp-has-current-submenu .wp-menu-image img,
			#adminmenu .toplevel_page_wpcalibrate-menu.current .wp-menu-image img {
				opacity: 1 !important;
				filter: grayscale(0%) brightness(100%) !important;
			}
		</style>
		<?php
	}

	/**
	 * Register WPCalibrate parent menu if missing, and register plugin submenus.
	 *
	 * @return void
	 */
	public function register_menus(): void {
		global $admin_page_hooks;

		$manage_wc_cap  = apply_filters( 'wpcalibrate_catalog_importer_capability', 'manage_woocommerce' );
		$manage_opt_cap = apply_filters( 'wpcalibrate_catalog_importer_settings_capability', 'manage_options' );

		// 1. Check if WPCalibrate parent menu has already been registered by another WPCalibrate plugin.
		if ( empty( $admin_page_hooks[ self::PARENT_SLUG ] ) ) {
			// Register shared parent menu using white icon.
			$icon_path = WPCALIBRATE_CATALOG_IMPORTER_URL . 'branding/icon-white.png';

			add_menu_page(
				'WPCalibrate',
				'WPCalibrate',
				$manage_wc_cap,
				self::PARENT_SLUG,
				[ $this->overview_page, 'render' ],
				$icon_path,
				56
			);

			// Rename/add the first default submenu item as Overview.
			add_submenu_page(
				self::PARENT_SLUG,
				__( 'Overview - Catalog Importer', 'wpcalibrate-catalog-importer' ),
				__( 'Overview', 'wpcalibrate-catalog-importer' ),
				$manage_wc_cap,
				self::SLUG_OVERVIEW,
				[ $this->overview_page, 'render' ]
			);

			// Remove duplicate first item if needed.
			remove_submenu_page( self::PARENT_SLUG, self::PARENT_SLUG );
		} else {
			// Parent menu exists; register Overview submenu for Catalog Importer.
			add_submenu_page(
				self::PARENT_SLUG,
				__( 'Overview - Catalog Importer', 'wpcalibrate-catalog-importer' ),
				__( 'Overview', 'wpcalibrate-catalog-importer' ),
				$manage_wc_cap,
				self::SLUG_OVERVIEW,
				[ $this->overview_page, 'render' ]
			);
		}

		// 2. Register Catalog Importer Wizard submenu.
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Catalog Importer', 'wpcalibrate-catalog-importer' ),
			__( 'Catalog Importer', 'wpcalibrate-catalog-importer' ),
			$manage_wc_cap,
			self::SLUG_WIZARD,
			[ $this->wizard_page, 'render' ]
		);

		// 3. Register internal submenus.
		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Import History - Catalog Importer', 'wpcalibrate-catalog-importer' ),
			__( 'Import History', 'wpcalibrate-catalog-importer' ),
			$manage_wc_cap,
			self::SLUG_HISTORY,
			[ $this->history_page, 'render' ]
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Mapping Templates - Catalog Importer', 'wpcalibrate-catalog-importer' ),
			__( 'Mapping Templates', 'wpcalibrate-catalog-importer' ),
			$manage_wc_cap,
			self::SLUG_TEMPLATES,
			[ $this->templates_page, 'render' ]
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Settings - Catalog Importer', 'wpcalibrate-catalog-importer' ),
			__( 'Settings', 'wpcalibrate-catalog-importer' ),
			$manage_opt_cap,
			self::SLUG_SETTINGS,
			[ $this->settings_page, 'render' ]
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'License - Catalog Importer', 'wpcalibrate-catalog-importer' ),
			__( 'License', 'wpcalibrate-catalog-importer' ),
			$manage_opt_cap,
			self::SLUG_LICENSE,
			[ $this->license_page, 'render' ]
		);

		add_submenu_page(
			self::PARENT_SLUG,
			__( 'Support - Catalog Importer', 'wpcalibrate-catalog-importer' ),
			__( 'Support', 'wpcalibrate-catalog-importer' ),
			$manage_wc_cap,
			self::SLUG_SUPPORT,
			[ $this->support_page, 'render' ]
		);
	}

	/**
	 * Enqueue admin CSS and JS strictly on this plugin's screens.
	 *
	 * @param string $hook_suffix
	 * @return void
	 */
	public function enqueue_assets( string $hook_suffix ): void {
		// Only enqueue on our plugin pages.
		$our_screens = [
			'toplevel_page_' . self::PARENT_SLUG,
			'wpcalibrate_page_' . self::SLUG_OVERVIEW,
			'wpcalibrate_page_' . self::SLUG_WIZARD,
			'wpcalibrate_page_' . self::SLUG_HISTORY,
			'wpcalibrate_page_' . self::SLUG_TEMPLATES,
			'wpcalibrate_page_' . self::SLUG_SETTINGS,
			'wpcalibrate_page_' . self::SLUG_LICENSE,
			'wpcalibrate_page_' . self::SLUG_SUPPORT,
		];

		// Check screen id or hook suffix.
		$is_plugin_page = false;
		foreach ( $our_screens as $screen ) {
			if ( str_contains( $hook_suffix, $screen ) || ( isset( $_GET['page'] ) && in_array( sanitize_text_field( wp_unslash( $_GET['page'] ) ), [ self::SLUG_OVERVIEW, self::SLUG_WIZARD, self::SLUG_HISTORY, self::SLUG_TEMPLATES, self::SLUG_SETTINGS, self::SLUG_LICENSE, self::SLUG_SUPPORT ], true ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$is_plugin_page = true;
				break;
			}
		}

		if ( ! $is_plugin_page ) {
			return;
		}

		$css_path = WPCALIBRATE_CATALOG_IMPORTER_PATH . 'assets/css/admin.css';
		$css_ver  = file_exists( $css_path ) ? (string) filemtime( $css_path ) : WPCALIBRATE_CATALOG_IMPORTER_VERSION;

		$js_path = WPCALIBRATE_CATALOG_IMPORTER_PATH . 'assets/js/admin.js';
		$js_ver  = file_exists( $js_path ) ? (string) filemtime( $js_path ) : WPCALIBRATE_CATALOG_IMPORTER_VERSION;

		wp_enqueue_style(
			'wpcalibrate-catalog-importer-admin',
			WPCALIBRATE_CATALOG_IMPORTER_URL . 'assets/css/admin.css',
			[],
			$css_ver
		);

		wp_enqueue_script(
			'wpcalibrate-catalog-importer-admin',
			WPCALIBRATE_CATALOG_IMPORTER_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			$js_ver,
			true
		);

		wp_localize_script(
			'wpcalibrate-catalog-importer-admin',
			'wpcalibrateImporter',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => SecurityHelper::create_nonce( SecurityHelper::NONCE_ACTION_IMPORT ),
				'strings' => [
					'validating'  => __( 'Validating rows, please wait...', 'wpcalibrate-catalog-importer' ),
					'confirmRun'  => __( 'Are you ready to begin the import? This will process in background batches.', 'wpcalibrate-catalog-importer' ),
					'confirmCancel' => __( 'Are you sure you want to cancel this import job?', 'wpcalibrate-catalog-importer' ),
					'processing'  => __( 'Processing import in background...', 'wpcalibrate-catalog-importer' ),
					'completed'   => __( 'Import completed!', 'wpcalibrate-catalog-importer' ),
					'failed'      => __( 'Import failed or encountered an error.', 'wpcalibrate-catalog-importer' ),
				],
			]
		);
	}

	/**
	 * AJAX endpoint for 1-click demo catalog import.
	 *
	 * @return void
	 */
	public function ajax_import_demo_catalog(): void {
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		SecurityHelper::verify_nonce( $nonce, SecurityHelper::NONCE_ACTION_IMPORT );

		if ( ! SecurityHelper::can_import() ) {
			wp_send_json_error( [ 'message' => __( 'Unauthorized access.', 'wpcalibrate-catalog-importer' ) ] );
		}

		$result = DemoCatalogImporter::run_instant_import();
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}

	/**
	 * Admin POST endpoint to initialize the 7-step wizard with pre-loaded demo catalog.
	 *
	 * @return void
	 */
	public function handle_load_demo_wizard(): void {
		$nonce = sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ?? '' ) );
		SecurityHelper::verify_nonce( $nonce, SecurityHelper::NONCE_ACTION_IMPORT );

		if ( ! SecurityHelper::can_import() ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wpcalibrate-catalog-importer' ) );
		}

		try {
			$job = DemoCatalogImporter::create_demo_job();
			wp_safe_redirect(
				admin_url( 'admin.php?page=' . self::SLUG_WIZARD . '&step=2&job_id=' . $job->id )
			);
			exit;
		} catch ( \Throwable $e ) {
			wp_die( esc_html( $e->getMessage() ) );
		}
	}
}
