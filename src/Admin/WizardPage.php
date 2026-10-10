<?php
namespace WPCalibrate\CatalogImporter\Admin;

use WPCalibrate\CatalogImporter\Security\SecurityHelper;
use WPCalibrate\CatalogImporter\Jobs\ImportJob;
use WPCalibrate\CatalogImporter\Jobs\ImportJobRepository;
use WPCalibrate\CatalogImporter\Import\Validation\UploadValidator;
use WPCalibrate\CatalogImporter\Import\Parsers\CsvStreamParser;
use WPCalibrate\CatalogImporter\Import\Parsers\XlsxStreamParser;
use WPCalibrate\CatalogImporter\Import\Mapping\FieldRegistry;
use WPCalibrate\CatalogImporter\Import\Mapping\MappingTemplateRepository;
use WPCalibrate\CatalogImporter\Import\Mapping\MappingEngine;
use WPCalibrate\CatalogImporter\Import\Normalization\RowNormalizer;
use WPCalibrate\CatalogImporter\Import\Validation\RowValidator;
use WPCalibrate\CatalogImporter\Import\Processing\BatchProcessor;
use WPCalibrate\CatalogImporter\Import\Reports\ErrorReportGenerator;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller for the 7-step product import wizard.
 */
class WizardPage {

	protected ImportJobRepository $job_repo;
	protected MappingTemplateRepository $template_repo;
	protected BatchProcessor $batch_processor;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->job_repo       = new ImportJobRepository();
		$this->template_repo  = new MappingTemplateRepository();
		$this->batch_processor = new BatchProcessor( $this->job_repo );
	}

	/**
	 * Register AJAX hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'wp_ajax_wpcalibrate_importer_get_progress', [ $this, 'ajax_get_progress' ] );
		add_action( 'wp_ajax_wpcalibrate_importer_cancel', [ $this, 'ajax_cancel_job' ] );
		add_action( 'wp_ajax_wpcalibrate_importer_dry_run', [ $this, 'ajax_dry_run' ] );
		add_action( 'admin_post_wpcalibrate_download_errors', [ $this, 'handle_download_errors' ] );
	}

	/**
	 * Render the Wizard Page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! SecurityHelper::can_import() ) {
			wp_die( esc_html__( 'You do not have permission to access the product catalog importer.', 'wpcalibrate-catalog-importer' ) );
		}

		$step   = isset( $_GET['step'] ) ? (int) $_GET['step'] : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$job_id = isset( $_GET['job_id'] ) ? (int) $_GET['job_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$job    = $job_id > 0 ? $this->job_repo->get_by_id( $job_id ) : null;

		// Handle Form Submissions between steps.
		if ( 'POST' === ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			$this->handle_post( $step, $job );
			return;
		}

		?>
		<div class="wrap wpcalibrate-wrap">
			<header class="wpcalibrate-header">
				<div class="wpcalibrate-header-brand">
					<img src="<?php echo esc_url( WPCALIBRATE_CATALOG_IMPORTER_URL . 'branding/icon-dark.png' ); ?>" alt="WPCalibrate" class="wpcalibrate-logo" />
					<div>
						<h1><?php esc_html_e( 'Catalog Importer', 'wpcalibrate-catalog-importer' ); ?></h1>
						<p class="wpcalibrate-tagline"><?php esc_html_e( 'Safe, high-volume bulk product creation and updates for WooCommerce', 'wpcalibrate-catalog-importer' ); ?></p>
					</div>
				</div>
			</header>

			<!-- Primary Navigation Tabs -->
			<nav class="nav-tab-wrapper wpcalibrate-nav-tabs" aria-label="<?php esc_attr_e( 'Plugin Navigation', 'wpcalibrate-catalog-importer' ); ?>">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_OVERVIEW ) ); ?>" class="nav-tab"><?php esc_html_e( 'Overview', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=1' ) ); ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'Import Wizard', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_HISTORY ) ); ?>" class="nav-tab"><?php esc_html_e( 'Import History', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_TEMPLATES ) ); ?>" class="nav-tab"><?php esc_html_e( 'Mapping Templates', 'wpcalibrate-catalog-importer' ); ?></a>
				<?php if ( SecurityHelper::can_manage_settings() ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETTINGS ) ); ?>" class="nav-tab"><?php esc_html_e( 'Settings', 'wpcalibrate-catalog-importer' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_LICENSE ) ); ?>" class="nav-tab"><?php esc_html_e( 'License', 'wpcalibrate-catalog-importer' ); ?></a>
				<?php endif; ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_SUPPORT ) ); ?>" class="nav-tab"><?php esc_html_e( 'Support', 'wpcalibrate-catalog-importer' ); ?></a>
			</nav>

			<!-- Wizard Stepper Navigation -->
			<nav class="wpcalibrate-stepper" aria-label="<?php esc_attr_e( 'Import Wizard Steps', 'wpcalibrate-catalog-importer' ); ?>">
				<ol class="wpcalibrate-stepper-list">
					<li class="wpcalibrate-step-item <?php echo 1 === $step ? 'active' : ( $step > 1 ? 'completed' : '' ); ?>">
						<span class="step-num">1</span>
						<span class="step-name"><?php esc_html_e( 'Upload', 'wpcalibrate-catalog-importer' ); ?></span>
					</li>
					<li class="wpcalibrate-step-item <?php echo 2 === $step ? 'active' : ( $step > 2 ? 'completed' : '' ); ?>">
						<span class="step-num">2</span>
						<span class="step-name"><?php esc_html_e( 'Import Mode', 'wpcalibrate-catalog-importer' ); ?></span>
					</li>
					<li class="wpcalibrate-step-item <?php echo 3 === $step ? 'active' : ( $step > 3 ? 'completed' : '' ); ?>">
						<span class="step-num">3</span>
						<span class="step-name"><?php esc_html_e( 'Field Mapping', 'wpcalibrate-catalog-importer' ); ?></span>
					</li>
					<li class="wpcalibrate-step-item <?php echo 4 === $step ? 'active' : ( $step > 4 ? 'completed' : '' ); ?>">
						<span class="step-num">4</span>
						<span class="step-name"><?php esc_html_e( 'Options', 'wpcalibrate-catalog-importer' ); ?></span>
					</li>
					<li class="wpcalibrate-step-item <?php echo 5 === $step ? 'active' : ( $step > 5 ? 'completed' : '' ); ?>">
						<span class="step-num">5</span>
						<span class="step-name"><?php esc_html_e( 'Validation', 'wpcalibrate-catalog-importer' ); ?></span>
					</li>
					<li class="wpcalibrate-step-item <?php echo 6 === $step ? 'active' : ( $step > 6 ? 'completed' : '' ); ?>">
						<span class="step-num">6</span>
						<span class="step-name"><?php esc_html_e( 'Import', 'wpcalibrate-catalog-importer' ); ?></span>
					</li>
					<li class="wpcalibrate-step-item <?php echo 7 === $step ? 'active' : '' ; ?>">
						<span class="step-num">7</span>
						<span class="step-name"><?php esc_html_e( 'Results', 'wpcalibrate-catalog-importer' ); ?></span>
					</li>
				</ol>
			</nav>

			<main class="wpcalibrate-card">
				<?php
				match ( $step ) {
					1       => $this->render_step_1_upload(),
					2       => $this->render_step_2_mode( $job ),
					3       => $this->render_step_3_mapping( $job ),
					4       => $this->render_step_4_options( $job ),
					5       => $this->render_step_5_validation( $job ),
					6       => $this->render_step_6_progress( $job ),
					7       => $this->render_step_7_results( $job ),
					default => $this->render_step_1_upload(),
				};
				?>
			</main>
		</div>
		<?php
	}

	/**
	 * Handle POST transitions between steps.
	 *
	 * @param int $step
	 * @param ImportJob|null $job
	 * @return void
	 */
	protected function handle_post( int $step, ?ImportJob $job ): void {
		SecurityHelper::verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) ) );

		if ( 1 === $step ) {
			// Step 1: Upload File.
			$upload_validator = new UploadValidator();
			$file_res         = $upload_validator->handle_upload( $_FILES['import_file'] ?? [] );

			if ( is_wp_error( $file_res ) ) {
				$this->redirect_with_error( 1, $file_res->get_error_message() );
				return;
			}

			// Handle optional ZIP images package.
			$zip_path = null;
			if ( ! empty( $_FILES['images_zip']['name'] ) ) {
				$zip_res = $upload_validator->handle_upload( $_FILES['images_zip'], [ 'zip' ] );
				if ( ! is_wp_error( $zip_res ) ) {
					$zip_path = $zip_res['file_path'];
				}
			}

			$source_type = $file_res['extension'];
			$parser      = ( 'xlsx' === $source_type ) ? new XlsxStreamParser() : new CsvStreamParser();
			$total_rows  = $parser->count_rows( $file_res['file_path'] );

			if ( $total_rows <= 0 ) {
				$this->redirect_with_error( 1, __( 'The uploaded file contains no data rows.', 'wpcalibrate-catalog-importer' ) );
				return;
			}

			// Create initial job draft.
			$new_job = $this->job_repo->create( [
				'source_type'     => $source_type,
				'source_filename' => $file_res['filename'],
				'file_path'       => $file_res['file_path'],
				'image_zip_path'  => $zip_path,
				'total_rows'      => $total_rows,
				'status'          => ImportJob::STATUS_DRAFT,
			] );

			wp_safe_redirect( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=2&job_id=' . $new_job->id ) );
			exit;
		}

		if ( ! $job ) {
			$this->redirect_with_error( 1, __( 'Job session expired. Please start over.', 'wpcalibrate-catalog-importer' ) );
			return;
		}

		if ( 2 === $step ) {
			// Step 2: Mode & Identifier.
			$mode            = sanitize_key( $_POST['import_mode'] ?? ImportJob::MODE_CREATE_UPDATE );
			$identifier_type = sanitize_key( $_POST['identifier_type'] ?? ImportJob::IDENTIFIER_SKU );

			$job->import_mode     = $mode;
			$job->identifier_type = $identifier_type;
			$this->job_repo->update( $job );

			wp_safe_redirect( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=3&job_id=' . $job->id ) );
			exit;
		}

		if ( 3 === $step ) {
			// Step 3: Field Mapping.
			$raw_mapping = (array) ( $_POST['mapping'] ?? [] );
			$clean_map   = [];

			foreach ( $raw_mapping as $col => $dest ) {
				$clean_col = sanitize_text_field( wp_unslash( (string) $col ) );
				$clean_dest = sanitize_text_field( wp_unslash( (string) $dest ) );
				if ( '' !== $clean_col ) {
					$clean_map[ $clean_col ] = $clean_dest;
				}
			}

			$job->mapping_config = $clean_map;

			// Optional save as template.
			if ( ! empty( $_POST['save_template_name'] ) ) {
				$template_name = sanitize_text_field( wp_unslash( $_POST['save_template_name'] ) );
				$this->template_repo->create( $template_name, $clean_map, $job->source_type );
			}

			$this->job_repo->update( $job );

			wp_safe_redirect( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=4&job_id=' . $job->id ) );
			exit;
		}

		if ( 4 === $step ) {
			// Step 4: Import Options.
			$options = [
				'default_status'       => sanitize_key( $_POST['default_status'] ?? 'publish' ),
				'create_categories'    => ! empty( $_POST['create_categories'] ),
				'create_tags'          => ! empty( $_POST['create_tags'] ),
				'create_attributes'    => ! empty( $_POST['create_attributes'] ),
				'enable_remote_images' => ! empty( $_POST['enable_remote_images'] ),
				'reuse_existing_images'=> ! empty( $_POST['reuse_existing_images'] ),
				'allow_clear_values'   => ! empty( $_POST['allow_clear_values'] ),
				'batch_size'           => max( 10, min( 250, (int) ( $_POST['batch_size'] ?? 25 ) ) ),
			];

			$job->options_config = $options;
			$this->job_repo->update( $job );

			wp_safe_redirect( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=5&job_id=' . $job->id ) );
			exit;
		}

		if ( 5 === $step ) {
			// Step 5: Confirmed -> Start Import.
			$this->batch_processor->schedule_job( $job->id );
			wp_safe_redirect( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=6&job_id=' . $job->id ) );
			exit;
		}
	}

	/**
	 * Helper to redirect with query error.
	 *
	 * @param int $step
	 * @param string $message
	 * @return void
	 */
	protected function redirect_with_error( int $step, string $message ): void {
		wp_safe_redirect(
			add_query_arg(
				[
					'page'     => AdminMenu::SLUG_WIZARD,
					'step'     => $step,
					'err_msg'  => rawurlencode( $message ),
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Step 1: Upload.
	 */
	protected function render_step_1_upload(): void {
		$error_msg = isset( $_GET['err_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['err_msg'] ) ) : '';
		?>
		<h2><?php esc_html_e( 'Step 1: Upload Product Spreadsheet', 'wpcalibrate-catalog-importer' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Upload your product catalog in CSV or XLSX format. Arbitrary column names are fully supported through field mapping.', 'wpcalibrate-catalog-importer' ); ?></p>

		<?php if ( $error_msg ) : ?>
			<div class="notice notice-error inline"><p><?php echo esc_html( $error_msg ); ?></p></div>
		<?php endif; ?>

		<form method="post" enctype="multipart/form-data" class="wpcalibrate-form">
			<?php wp_nonce_field( SecurityHelper::NONCE_ACTION_IMPORT ); ?>

			<div class="form-row">
				<label for="import_file" class="form-label font-bold">
					<?php esc_html_e( 'Product Catalog File (CSV or XLSX) *', 'wpcalibrate-catalog-importer' ); ?>
				</label>
				<input type="file" id="import_file" name="import_file" accept=".csv, .xlsx, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, text/csv" required class="regular-text" />
				<p class="description"><?php esc_html_e( 'Maximum upload size: ', 'wpcalibrate-catalog-importer' ); ?><?php echo esc_html( size_format( wp_max_upload_size() ) ); ?></p>
			</div>

			<div class="form-row">
				<label for="images_zip" class="form-label font-bold">
					<?php esc_html_e( 'Optional Images ZIP Package', 'wpcalibrate-catalog-importer' ); ?>
				</label>
				<input type="file" id="images_zip" name="images_zip" accept=".zip, application/zip" class="regular-text" />
				<p class="description"><?php esc_html_e( 'Upload a ZIP archive containing images named by SKU or filename (e.g. SKU123.jpg, SKU123_1.jpg).', 'wpcalibrate-catalog-importer' ); ?></p>
			</div>

			<div class="form-actions">
				<button type="submit" class="button button-primary button-hero">
					<?php esc_html_e( 'Upload and Continue &rarr;', 'wpcalibrate-catalog-importer' ); ?>
				</button>
			</div>
		</form>

		<div class="demo-catalog-callout" style="margin-top: 30px; padding: 20px; background: #f0f7ff; border: 1px solid #c8e1ff; border-radius: 6px;">
			<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
				<div>
					<h3 style="margin: 0 0 6px 0; color: #005177;">✨ <?php esc_html_e( 'Want to test the wizard without your own file?', 'wpcalibrate-catalog-importer' ); ?></h3>
					<p style="margin: 0; color: #334155; font-size: 13px;">
						<?php esc_html_e( 'Load our pre-configured demo catalog with 10+ varied products (Simple, Variable, Grouped, External) to test all 7 steps.', 'wpcalibrate-catalog-importer' ); ?>
					</p>
				</div>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpcalibrate_load_demo_wizard' ), SecurityHelper::NONCE_ACTION_IMPORT ) ); ?>" class="button button-secondary" style="height: 38px; line-height: 36px; padding: 0 16px;">
					<?php esc_html_e( 'Load Demo Catalog into Wizard &rarr;', 'wpcalibrate-catalog-importer' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Step 2: Import Mode.
	 */
	protected function render_step_2_mode( ?ImportJob $job ): void {
		if ( ! $job ) {
			echo '<p>' . esc_html__( 'Job not found.', 'wpcalibrate-catalog-importer' ) . '</p>';
			return;
		}
		?>
		<h2><?php esc_html_e( 'Step 2: Select Import Mode & Matching Identifier', 'wpcalibrate-catalog-importer' ); ?></h2>
		<p class="description">
			<?php
			echo esc_html(
				sprintf(
					/* translators: 1: Source filename, 2: Detected rows */
					__( 'Source file: %1$s (%2$d data rows detected).', 'wpcalibrate-catalog-importer' ),
					$job->source_filename,
					$job->total_rows
				)
			);
			?>
		</p>

		<form method="post" class="wpcalibrate-form">
			<?php wp_nonce_field( SecurityHelper::NONCE_ACTION_IMPORT ); ?>

			<fieldset class="form-group">
				<legend class="form-legend font-bold"><?php esc_html_e( 'Import Mode', 'wpcalibrate-catalog-importer' ); ?></legend>
				<label class="radio-label">
					<input type="radio" name="import_mode" value="<?php echo esc_attr( ImportJob::MODE_CREATE_UPDATE ); ?>" <?php checked( $job->import_mode, ImportJob::MODE_CREATE_UPDATE ); ?> />
					<strong><?php esc_html_e( 'Create new products and update existing products (Recommended)', 'wpcalibrate-catalog-importer' ); ?></strong>
					<span class="sub-desc"><?php esc_html_e( 'Creates items that do not exist yet, and updates matched existing products.', 'wpcalibrate-catalog-importer' ); ?></span>
				</label>
				<label class="radio-label">
					<input type="radio" name="import_mode" value="<?php echo esc_attr( ImportJob::MODE_CREATE_ONLY ); ?>" <?php checked( $job->import_mode, ImportJob::MODE_CREATE_ONLY ); ?> />
					<strong><?php esc_html_e( 'Create new products only', 'wpcalibrate-catalog-importer' ); ?></strong>
					<span class="sub-desc"><?php esc_html_e( 'Skips any records that match an existing product in your catalog.', 'wpcalibrate-catalog-importer' ); ?></span>
				</label>
				<label class="radio-label">
					<input type="radio" name="import_mode" value="<?php echo esc_attr( ImportJob::MODE_UPDATE_ONLY ); ?>" <?php checked( $job->import_mode, ImportJob::MODE_UPDATE_ONLY ); ?> />
					<strong><?php esc_html_e( 'Update existing products only', 'wpcalibrate-catalog-importer' ); ?></strong>
					<span class="sub-desc"><?php esc_html_e( 'Updates matching existing products and skips any records not found in your catalog.', 'wpcalibrate-catalog-importer' ); ?></span>
				</label>
			</fieldset>

			<fieldset class="form-group">
				<legend class="form-legend font-bold"><?php esc_html_e( 'Existing Product Matching Identifier', 'wpcalibrate-catalog-importer' ); ?></legend>
				<label class="radio-label">
					<input type="radio" name="identifier_type" value="<?php echo esc_attr( ImportJob::IDENTIFIER_SKU ); ?>" <?php checked( $job->identifier_type, ImportJob::IDENTIFIER_SKU ); ?> />
					<strong><?php esc_html_e( 'Match by SKU (Recommended)', 'wpcalibrate-catalog-importer' ); ?></strong>
					<span class="sub-desc"><?php esc_html_e( 'Standard e-commerce unique Stock Keeping Unit identifier.', 'wpcalibrate-catalog-importer' ); ?></span>
				</label>
				<label class="radio-label">
					<input type="radio" name="identifier_type" value="<?php echo esc_attr( ImportJob::IDENTIFIER_ID ); ?>" <?php checked( $job->identifier_type, ImportJob::IDENTIFIER_ID ); ?> />
					<strong><?php esc_html_e( 'Match by WooCommerce Product ID', 'wpcalibrate-catalog-importer' ); ?></strong>
					<span class="sub-desc"><?php esc_html_e( 'Matches exact internal post IDs in WooCommerce.', 'wpcalibrate-catalog-importer' ); ?></span>
				</label>
			</fieldset>

			<div class="form-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=1' ) ); ?>" class="button"><?php esc_html_e( '&larr; Back', 'wpcalibrate-catalog-importer' ); ?></a>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Continue to Field Mapping &rarr;', 'wpcalibrate-catalog-importer' ); ?></button>
			</div>
		</form>
		<?php
	}

	/**
	 * Step 3: Field Mapping.
	 */
	protected function render_step_3_mapping( ?ImportJob $job ): void {
		if ( ! $job ) {
			echo '<p>' . esc_html__( 'Job not found.', 'wpcalibrate-catalog-importer' ) . '</p>';
			return;
		}

		$parser  = ( 'xlsx' === $job->source_type ) ? new XlsxStreamParser() : new CsvStreamParser();
		$headers = $parser->get_headers( $job->file_path );
		$samples = $parser->get_sample_rows( $job->file_path, 3 );
		$fields  = FieldRegistry::get_fields();

		// Load saved templates if any exist.
		$saved_templates = $this->template_repo->get_all();
		?>
		<h2><?php esc_html_e( 'Step 3: Map Source Columns to WooCommerce Fields', 'wpcalibrate-catalog-importer' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Match each column from your uploaded spreadsheet to the intended WooCommerce destination field. Unmapped columns will be ignored.', 'wpcalibrate-catalog-importer' ); ?></p>

		<?php if ( ! empty( $saved_templates ) ) : ?>
			<div class="template-loader-bar">
				<label for="apply_saved_template" class="font-bold"><?php esc_html_e( 'Apply Saved Mapping Template:', 'wpcalibrate-catalog-importer' ); ?></label>
				<select id="apply_saved_template" class="regular-text">
					<option value=""><?php esc_html_e( '-- Select Template --', 'wpcalibrate-catalog-importer' ); ?></option>
					<?php foreach ( $saved_templates as $tmpl ) : ?>
						<option value="<?php echo esc_attr( (string) $tmpl['id'] ); ?>" data-mappings="<?php echo esc_attr( wp_json_encode( $tmpl['mapping_data'] ) ); ?>">
							<?php echo esc_html( $tmpl['name'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div>
		<?php endif; ?>

		<form method="post" class="wpcalibrate-form" id="wpcalibrate-mapping-form">
			<?php wp_nonce_field( SecurityHelper::NONCE_ACTION_IMPORT ); ?>

			<table class="wp-list-table widefat striped fixed table-view-list wpcalibrate-mapping-table">
				<thead>
					<tr>
						<th scope="col" style="width: 25%;"><?php esc_html_e( 'Source Column Header', 'wpcalibrate-catalog-importer' ); ?></th>
						<th scope="col" style="width: 35%;"><?php esc_html_e( 'Sample Data Value', 'wpcalibrate-catalog-importer' ); ?></th>
						<th scope="col" style="width: 40%;"><?php esc_html_e( 'WooCommerce Destination Field', 'wpcalibrate-catalog-importer' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $headers as $idx => $header ) :
						$suggested = FieldRegistry::suggest_mapping( $header );
						$current   = $job->mapping_config[ $header ] ?? $suggested;

						// Gather sample values for this column.
						$sample_vals = [];
						foreach ( $samples as $s_row ) {
							if ( isset( $s_row[ $header ] ) && '' !== $s_row[ $header ] ) {
								$sample_vals[] = $s_row[ $header ];
							}
						}
						$sample_display = ! empty( $sample_vals ) ? implode( ' | ', array_slice( $sample_vals, 0, 2 ) ) : '<em>(' . __( 'empty', 'wpcalibrate-catalog-importer' ) . ')</em>';
						?>
						<tr>
							<td>
								<strong><?php echo esc_html( $header ); ?></strong>
							</td>
							<td class="sample-val">
								<code><?php echo esc_html( wp_trim_words( $sample_display, 10 ) ); ?></code>
							</td>
							<td>
								<select name="mapping[<?php echo esc_attr( $header ); ?>]" class="destination-field-select" style="width: 100%; max-width: 350px;">
									<option value="ignore"><?php esc_html_e( '-- Ignore / Do Not Import --', 'wpcalibrate-catalog-importer' ); ?></option>

									<?php
									// Group destination fields.
									$grouped = [];
									foreach ( $fields as $f_key => $f_info ) {
										$grouped[ $f_info['group'] ][ $f_key ] = $f_info;
									}

									foreach ( $grouped as $group_name => $group_fields ) :
										?>
										<optgroup label="<?php echo esc_attr( $group_name ); ?>">
											<?php foreach ( $group_fields as $f_key => $f_info ) : ?>
												<option value="<?php echo esc_attr( $f_key ); ?>" <?php selected( $current, $f_key ); ?>>
													<?php echo esc_html( $f_info['label'] ); ?>
												</option>
											<?php endforeach; ?>
										</optgroup>
									<?php endforeach; ?>

									<!-- Product Attribute option -->
									<optgroup label="<?php esc_attr_e( 'Custom Product Attributes', 'wpcalibrate-catalog-importer' ); ?>">
										<option value="attribute:<?php echo esc_attr( sanitize_title( $header ) ); ?>" <?php selected( $current, 'attribute:' . sanitize_title( $header ) ); ?>>
											<?php
											echo esc_html(
												sprintf(
													/* translators: %s: Column header */
													__( 'Attribute: %s', 'wpcalibrate-catalog-importer' ),
													$header
												)
											);
											?>
										</option>
									</optgroup>

									<!-- Custom Meta option -->
									<optgroup label="<?php esc_attr_e( 'Custom Product Meta', 'wpcalibrate-catalog-importer' ); ?>">
										<option value="meta:<?php echo esc_attr( sanitize_key( $header ) ); ?>" <?php selected( $current, 'meta:' . sanitize_key( $header ) ); ?>>
											<?php
											echo esc_html(
												sprintf(
													/* translators: %s: Column header */
													__( 'Custom Meta: %s', 'wpcalibrate-catalog-importer' ),
													sanitize_key( $header )
												)
											);
											?>
										</option>
									</optgroup>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div class="form-row save-template-row" style="margin-top: 20px;">
				<label for="save_template_name" class="font-bold">
					<?php esc_html_e( 'Save Mappings as Named Template (Optional):', 'wpcalibrate-catalog-importer' ); ?>
				</label>
				<input type="text" id="save_template_name" name="save_template_name" placeholder="<?php esc_attr_e( 'e.g. Supplier Catalog 2026', 'wpcalibrate-catalog-importer' ); ?>" class="regular-text" />
			</div>

			<div class="form-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=2&job_id=' . $job->id ) ); ?>" class="button"><?php esc_html_e( '&larr; Back', 'wpcalibrate-catalog-importer' ); ?></a>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Save & Configure Import Options &rarr;', 'wpcalibrate-catalog-importer' ); ?></button>
			</div>
		</form>
		<?php
	}

	/**
	 * Step 4: Import Options.
	 */
	protected function render_step_4_options( ?ImportJob $job ): void {
		if ( ! $job ) {
			echo '<p>' . esc_html__( 'Job not found.', 'wpcalibrate-catalog-importer' ) . '</p>';
			return;
		}

		$options = $job->options_config;
		?>
		<h2><?php esc_html_e( 'Step 4: Configure Import & Product Behavior Options', 'wpcalibrate-catalog-importer' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Configure how new taxonomies, images, and unmapped attributes are handled during the import.', 'wpcalibrate-catalog-importer' ); ?></p>

		<form method="post" class="wpcalibrate-form">
			<?php wp_nonce_field( SecurityHelper::NONCE_ACTION_IMPORT ); ?>

			<div class="form-row">
				<label for="default_status" class="form-label font-bold"><?php esc_html_e( 'Default Product Status for New Products', 'wpcalibrate-catalog-importer' ); ?></label>
				<select name="default_status" id="default_status" class="regular-text">
					<option value="publish" <?php selected( $options['default_status'] ?? 'publish', 'publish' ); ?>><?php esc_html_e( 'Published (Visible on site)', 'wpcalibrate-catalog-importer' ); ?></option>
					<option value="draft" <?php selected( $options['default_status'] ?? '', 'draft' ); ?>><?php esc_html_e( 'Draft (Hidden from store)', 'wpcalibrate-catalog-importer' ); ?></option>
					<option value="pending" <?php selected( $options['default_status'] ?? '', 'pending' ); ?>><?php esc_html_e( 'Pending Review', 'wpcalibrate-catalog-importer' ); ?></option>
					<option value="private" <?php selected( $options['default_status'] ?? '', 'private' ); ?>><?php esc_html_e( 'Private', 'wpcalibrate-catalog-importer' ); ?></option>
				</select>
			</div>

			<fieldset class="form-group">
				<legend class="form-legend font-bold"><?php esc_html_e( 'Taxonomies and Attributes Creation', 'wpcalibrate-catalog-importer' ); ?></legend>
				<label class="checkbox-label">
					<input type="checkbox" name="create_categories" value="1" <?php checked( $options['create_categories'] ?? true, true ); ?> />
					<?php esc_html_e( 'Automatically create categories that do not exist yet', 'wpcalibrate-catalog-importer' ); ?>
				</label>
				<label class="checkbox-label">
					<input type="checkbox" name="create_tags" value="1" <?php checked( $options['create_tags'] ?? true, true ); ?> />
					<?php esc_html_e( 'Automatically create product tags that do not exist yet', 'wpcalibrate-catalog-importer' ); ?>
				</label>
				<label class="checkbox-label">
					<input type="checkbox" name="create_attributes" value="1" <?php checked( $options['create_attributes'] ?? true, true ); ?> />
					<?php esc_html_e( 'Automatically register global product attributes if taxonomy does not exist', 'wpcalibrate-catalog-importer' ); ?>
				</label>
			</fieldset>

			<fieldset class="form-group">
				<legend class="form-legend font-bold"><?php esc_html_e( 'Image Download & Reuse Options', 'wpcalibrate-catalog-importer' ); ?></legend>
				<label class="checkbox-label">
					<input type="checkbox" name="enable_remote_images" value="1" <?php checked( $options['enable_remote_images'] ?? true, true ); ?> />
					<?php esc_html_e( 'Download remote HTTPS images into Media Library (protected with SSRF validation)', 'wpcalibrate-catalog-importer' ); ?>
				</label>
				<label class="checkbox-label">
					<input type="checkbox" name="reuse_existing_images" value="1" <?php checked( $options['reuse_existing_images'] ?? true, true ); ?> />
					<?php esc_html_e( 'Reuse already downloaded images if matching source URL exists (prevents duplicate media)', 'wpcalibrate-catalog-importer' ); ?>
				</label>
			</fieldset>

			<fieldset class="form-group">
				<legend class="form-legend font-bold"><?php esc_html_e( 'Update Safety & Blank Value Policy', 'wpcalibrate-catalog-importer' ); ?></legend>
				<label class="checkbox-label">
					<input type="checkbox" name="allow_clear_values" value="1" <?php checked( ! empty( $options['allow_clear_values'] ) ); ?> />
					<strong class="text-danger"><?php esc_html_e( 'Allow blank spreadsheet cells to erase existing WooCommerce product values', 'wpcalibrate-catalog-importer' ); ?></strong>
					<span class="sub-desc"><?php esc_html_e( 'When unchecked (recommended), blank values in the spreadsheet preserve existing product data.', 'wpcalibrate-catalog-importer' ); ?></span>
				</label>
			</fieldset>

			<div class="form-row">
				<label for="batch_size" class="form-label font-bold"><?php esc_html_e( 'Background Batch Size (Products per Batch)', 'wpcalibrate-catalog-importer' ); ?></label>
				<input type="number" id="batch_size" name="batch_size" value="<?php echo esc_attr( (string) ( $options['batch_size'] ?? 25 ) ); ?>" min="10" max="250" class="small-text" />
				<p class="description"><?php esc_html_e( 'Default is 25. Lower batch size on shared hosting to prevent server resource limits.', 'wpcalibrate-catalog-importer' ); ?></p>
			</div>

			<div class="form-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=3&job_id=' . $job->id ) ); ?>" class="button"><?php esc_html_e( '&larr; Back to Mapping', 'wpcalibrate-catalog-importer' ); ?></a>
				<button type="submit" class="button button-primary"><?php esc_html_e( 'Run Dry-Run Validation &rarr;', 'wpcalibrate-catalog-importer' ); ?></button>
			</div>
		</form>
		<?php
	}

	/**
	 * Step 5: Dry-Run Validation.
	 */
	protected function render_step_5_validation( ?ImportJob $job ): void {
		if ( ! $job ) {
			echo '<p>' . esc_html__( 'Job not found.', 'wpcalibrate-catalog-importer' ) . '</p>';
			return;
		}

		// Perform dry run across rows without saving any products.
		$parser     = ( 'xlsx' === $job->source_type ) ? new XlsxStreamParser() : new CsvStreamParser();
		$resolver   = new ProductResolver();
		$validator  = new RowValidator( $resolver );
		$normalizer = new RowNormalizer();
		$engine     = new MappingEngine();

		$valid_count     = 0;
		$warning_count   = 0;
		$invalid_count   = 0;
		$estimated_create = 0;
		$estimated_update = 0;
		$estimated_skip   = 0;

		$issue_samples = []; // Top issues to display

		foreach ( $parser->stream_rows( $job->file_path, 0, null ) as $row_item ) {
			$r_idx = (int) $row_item['row_index'];
			$raw   = (array) $row_item['data'];

			$transformed = $engine->transform( $raw, $job->mapping_config );
			$normalized  = $normalizer->normalize( $transformed, $job->options_config );
			$res         = $validator->validate_row( $normalized, $r_idx, $job->import_mode, $job->identifier_type, $job->options_config );

			if ( 'skipped' === $res['status'] ) {
				$estimated_skip++;
			} elseif ( 'invalid' === $res['status'] ) {
				$invalid_count++;
				if ( count( $issue_samples ) < 10 ) {
					$issue_samples[] = [
						'row'     => $r_idx,
						'type'    => 'error',
						'message' => implode( '; ', $res['errors'] ),
					];
				}
			} elseif ( 'warning' === $res['status'] ) {
				$warning_count++;
				if ( 'create' === $res['action'] ) {
					$estimated_create++;
				} else {
					$estimated_update++;
				}
				if ( count( $issue_samples ) < 10 ) {
					$issue_samples[] = [
						'row'     => $r_idx,
						'type'    => 'warning',
						'message' => implode( '; ', $res['warnings'] ),
					];
				}
			} else {
				$valid_count++;
				if ( 'create' === $res['action'] ) {
					$estimated_create++;
				} else {
					$estimated_update++;
				}
			}
		}
		?>
		<h2><?php esc_html_e( 'Step 5: Dry-Run Validation Report', 'wpcalibrate-catalog-importer' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Validation simulation complete. No changes have been made to your store products yet.', 'wpcalibrate-catalog-importer' ); ?></p>

		<div class="wpcalibrate-stats-grid">
			<div class="stat-card stat-success">
				<span class="stat-num"><?php echo esc_html( (string) $valid_count ); ?></span>
				<span class="stat-label"><?php esc_html_e( 'Valid Rows', 'wpcalibrate-catalog-importer' ); ?></span>
			</div>
			<div class="stat-card stat-warning">
				<span class="stat-num"><?php echo esc_html( (string) $warning_count ); ?></span>
				<span class="stat-label"><?php esc_html_e( 'Warning Rows', 'wpcalibrate-catalog-importer' ); ?></span>
			</div>
			<div class="stat-card stat-danger">
				<span class="stat-num"><?php echo esc_html( (string) $invalid_count ); ?></span>
				<span class="stat-label"><?php esc_html_e( 'Invalid Rows', 'wpcalibrate-catalog-importer' ); ?></span>
			</div>
			<div class="stat-card">
				<span class="stat-num"><?php echo esc_html( (string) $estimated_create ); ?></span>
				<span class="stat-label"><?php esc_html_e( 'Estimated to Create', 'wpcalibrate-catalog-importer' ); ?></span>
			</div>
			<div class="stat-card">
				<span class="stat-num"><?php echo esc_html( (string) $estimated_update ); ?></span>
				<span class="stat-label"><?php esc_html_e( 'Estimated to Update', 'wpcalibrate-catalog-importer' ); ?></span>
			</div>
			<div class="stat-card">
				<span class="stat-num"><?php echo esc_html( (string) $estimated_skip ); ?></span>
				<span class="stat-label"><?php esc_html_e( 'Estimated to Skip', 'wpcalibrate-catalog-importer' ); ?></span>
			</div>
		</div>

		<?php if ( ! empty( $issue_samples ) ) : ?>
			<h3><?php esc_html_e( 'Sample Issues Detected in Spreadsheet:', 'wpcalibrate-catalog-importer' ); ?></h3>
			<table class="wp-list-table widefat striped fixed">
				<thead>
					<tr>
						<th style="width: 15%;"><?php esc_html_e( 'Row #', 'wpcalibrate-catalog-importer' ); ?></th>
						<th style="width: 15%;"><?php esc_html_e( 'Severity', 'wpcalibrate-catalog-importer' ); ?></th>
						<th style="width: 70%;"><?php esc_html_e( 'Description', 'wpcalibrate-catalog-importer' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $issue_samples as $issue ) : ?>
						<tr>
							<td><strong><?php echo esc_html( (string) $issue['row'] ); ?></strong></td>
							<td>
								<span class="badge badge-<?php echo 'error' === $issue['type'] ? 'danger' : 'warning'; ?>">
									<?php echo esc_html( strtoupper( $issue['type'] ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( $issue['message'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>

		<form method="post" class="wpcalibrate-form" style="margin-top: 30px;">
			<?php wp_nonce_field( SecurityHelper::NONCE_ACTION_IMPORT ); ?>

			<div class="form-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=4&job_id=' . $job->id ) ); ?>" class="button"><?php esc_html_e( '&larr; Back to Options', 'wpcalibrate-catalog-importer' ); ?></a>
				<button type="submit" class="button button-primary button-hero" id="wpcalibrate-start-import-btn" <?php disabled( $valid_count === 0 && $warning_count === 0 ); ?>>
					<?php esc_html_e( 'Confirm & Start Background Import &rarr;', 'wpcalibrate-catalog-importer' ); ?>
				</button>
			</div>
		</form>
		<?php
	}

	/**
	 * Step 6: Background Progress.
	 */
	protected function render_step_6_progress( ?ImportJob $job ): void {
		if ( ! $job ) {
			echo '<p>' . esc_html__( 'Job not found.', 'wpcalibrate-catalog-importer' ) . '</p>';
			return;
		}

		$progress_pct = $job->get_progress_percent();
		?>
		<h2><?php esc_html_e( 'Step 6: Import in Progress', 'wpcalibrate-catalog-importer' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Products are being processed safely in background batches via Action Scheduler. You may leave this screen at any time; processing will continue automatically.', 'wpcalibrate-catalog-importer' ); ?></p>

		<div class="wpcalibrate-progress-container" data-job-id="<?php echo esc_attr( (string) $job->id ); ?>" style="margin: 30px 0;">
			<div class="wpcalibrate-progress-bar-wrapper">
				<div class="wpcalibrate-progress-bar" style="width: <?php echo esc_attr( (string) $progress_pct ); ?>%;"></div>
			</div>
			<div class="progress-meta" style="display: flex; justify-content: space-between; margin-top: 10px;">
				<span class="progress-status-text">
					<?php
					echo esc_html(
						sprintf(
							/* translators: 1: Processed rows, 2: Total rows, 3: Percent */
							__( 'Processed %1$d of %2$d rows (%3$d%%)...', 'wpcalibrate-catalog-importer' ),
							$job->processed_rows,
							$job->total_rows,
							$progress_pct
						)
					);
					?>
				</span>
				<span class="progress-status-badge badge badge-info"><?php echo esc_html( strtoupper( $job->status ) ); ?></span>
			</div>
		</div>

		<div class="wpcalibrate-stats-grid" id="wpcalibrate-live-counters">
			<div class="stat-card stat-success">
				<span class="stat-num" id="counter-created"><?php echo esc_html( (string) $job->created_count ); ?></span>
				<span class="stat-label"><?php esc_html_e( 'Created', 'wpcalibrate-catalog-importer' ); ?></span>
			</div>
			<div class="stat-card stat-info">
				<span class="stat-num" id="counter-updated"><?php echo esc_html( (string) $job->updated_count ); ?></span>
				<span class="stat-label"><?php esc_html_e( 'Updated', 'wpcalibrate-catalog-importer' ); ?></span>
			</div>
			<div class="stat-card stat-warning">
				<span class="stat-num" id="counter-skipped"><?php echo esc_html( (string) $job->skipped_count ); ?></span>
				<span class="stat-label"><?php esc_html_e( 'Skipped', 'wpcalibrate-catalog-importer' ); ?></span>
			</div>
			<div class="stat-card stat-danger">
				<span class="stat-num" id="counter-failed"><?php echo esc_html( (string) $job->failed_count ); ?></span>
				<span class="stat-label"><?php esc_html_e( 'Failed', 'wpcalibrate-catalog-importer' ); ?></span>
			</div>
		</div>

		<div class="form-actions" style="margin-top: 30px;">
			<button type="button" class="button button-secondary" id="wpcalibrate-cancel-job-btn" data-job-id="<?php echo esc_attr( (string) $job->id ); ?>">
				<?php esc_html_e( 'Cancel Import Job', 'wpcalibrate-catalog-importer' ); ?>
			</button>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=7&job_id=' . $job->id ) ); ?>" class="button button-primary" id="wpcalibrate-view-results-btn" style="<?php echo $job->is_finished() ? '' : 'display:none;'; ?>">
				<?php esc_html_e( 'View Final Results &rarr;', 'wpcalibrate-catalog-importer' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Step 7: Final Results.
	 */
	protected function render_step_7_results( ?ImportJob $job ): void {
		if ( ! $job ) {
			echo '<p>' . esc_html__( 'Job not found.', 'wpcalibrate-catalog-importer' ) . '</p>';
			return;
		}

		$user = get_userdata( $job->user_id );
		$username = $user ? $user->display_name : __( 'System / Admin', 'wpcalibrate-catalog-importer' );
		?>
		<h2><?php esc_html_e( 'Step 7: Import Summary & Results', 'wpcalibrate-catalog-importer' ); ?></h2>
		<p class="description"><?php esc_html_e( 'The import job has concluded. Review results and download failure logs below.', 'wpcalibrate-catalog-importer' ); ?></p>

		<div class="wpcalibrate-stats-grid">
			<div class="stat-card">
				<span class="stat-num"><?php echo esc_html( (string) $job->total_rows ); ?></span>
				<span class="stat-label"><?php esc_html_e( 'Total Rows', 'wpcalibrate-catalog-importer' ); ?></span>
			</div>
			<div class="stat-card stat-success">
				<span class="stat-num"><?php echo esc_html( (string) $job->created_count ); ?></span>
				<span class="stat-label"><?php esc_html_e( 'Created Products', 'wpcalibrate-catalog-importer' ); ?></span>
			</div>
			<div class="stat-card stat-info">
				<span class="stat-num"><?php echo esc_html( (string) $job->updated_count ); ?></span>
				<span class="stat-label"><?php esc_html_e( 'Updated Products', 'wpcalibrate-catalog-importer' ); ?></span>
			</div>
			<div class="stat-card stat-warning">
				<span class="stat-num"><?php echo esc_html( (string) $job->skipped_count ); ?></span>
				<span class="stat-label"><?php esc_html_e( 'Skipped', 'wpcalibrate-catalog-importer' ); ?></span>
			</div>
			<div class="stat-card stat-danger">
				<span class="stat-num"><?php echo esc_html( (string) $job->failed_count ); ?></span>
				<span class="stat-label"><?php esc_html_e( 'Failed Rows', 'wpcalibrate-catalog-importer' ); ?></span>
			</div>
		</div>

		<table class="wp-list-table widefat striped fixed" style="margin-top: 25px;">
			<tbody>
				<tr>
					<th style="width: 25%;"><?php esc_html_e( 'Job Status', 'wpcalibrate-catalog-importer' ); ?></th>
					<td><span class="badge badge-<?php echo ImportJob::STATUS_COMPLETED === $job->status ? 'success' : ( ImportJob::STATUS_CANCELLED === $job->status ? 'warning' : 'danger' ); ?>"><?php echo esc_html( strtoupper( $job->status ) ); ?></span></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Source File', 'wpcalibrate-catalog-importer' ); ?></th>
					<td><code><?php echo esc_html( $job->source_filename ); ?></code> (<?php echo esc_html( strtoupper( $job->source_type ) ); ?>)</td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Import Mode', 'wpcalibrate-catalog-importer' ); ?></th>
					<td><?php echo esc_html( str_replace( '_', ' ', ucfirst( $job->import_mode ) ) ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Initiated By', 'wpcalibrate-catalog-importer' ); ?></th>
					<td><?php echo esc_html( $username ); ?></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Created / Finished', 'wpcalibrate-catalog-importer' ); ?></th>
					<td><?php echo esc_html( $job->created_at ); ?> / <?php echo esc_html( $job->completed_at ?: 'N/A' ); ?></td>
				</tr>
			</tbody>
		</table>

		<div class="form-actions" style="margin-top: 30px;">
			<?php if ( $job->failed_count > 0 || $job->warning_count > 0 ) : ?>
				<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpcalibrate_download_errors&job_id=' . $job->id ), SecurityHelper::NONCE_ACTION_IMPORT ) ); ?>" class="button button-secondary">
					<?php esc_html_e( 'Download Failed Rows CSV Report', 'wpcalibrate-catalog-importer' ); ?>
				</a>
			<?php endif; ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_HISTORY ) ); ?>" class="button button-secondary">
				<?php esc_html_e( 'View All Import History', 'wpcalibrate-catalog-importer' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=1' ) ); ?>" class="button button-primary">
				<?php esc_html_e( 'Start New Import &rarr;', 'wpcalibrate-catalog-importer' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * AJAX endpoint: poll live job progress.
	 *
	 * @return void
	 */
	public function ajax_get_progress(): void {
		SecurityHelper::verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ) );

		if ( ! SecurityHelper::can_import() ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		$job_id = (int) ( $_POST['job_id'] ?? 0 );
		$job    = $this->job_repo->get_by_id( $job_id );

		if ( ! $job ) {
			wp_send_json_error( [ 'message' => 'Job not found' ], 404 );
		}

		wp_send_json_success( [
			'status'         => $job->status,
			'progress'       => $job->get_progress_percent(),
			'total_rows'     => $job->total_rows,
			'processed_rows' => $job->processed_rows,
			'created_count'  => $job->created_count,
			'updated_count'  => $job->updated_count,
			'skipped_count'  => $job->skipped_count,
			'failed_count'   => $job->failed_count,
			'is_finished'    => $job->is_finished(),
		] );
	}

	/**
	 * AJAX endpoint: cancel job.
	 *
	 * @return void
	 */
	public function ajax_cancel_job(): void {
		SecurityHelper::verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) ) );

		if ( ! SecurityHelper::can_import() ) {
			wp_send_json_error( [ 'message' => 'Unauthorized' ], 403 );
		}

		$job_id = (int) ( $_POST['job_id'] ?? 0 );
		$cancelled = $this->batch_processor->cancel_job( $job_id );

		if ( $cancelled ) {
			wp_send_json_success( [ 'message' => __( 'Job cancelled successfully.', 'wpcalibrate-catalog-importer' ) ] );
		} else {
			wp_send_json_error( [ 'message' => __( 'Could not cancel job.', 'wpcalibrate-catalog-importer' ) ] );
		}
	}

	/**
	 * Admin-post action: download formula-injection safe CSV report.
	 *
	 * @return void
	 */
	public function handle_download_errors(): void {
		$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) );
		SecurityHelper::verify_nonce( $nonce );

		if ( ! SecurityHelper::can_import() ) {
			wp_die( esc_html__( 'Unauthorized', 'wpcalibrate-catalog-importer' ), 403 );
		}

		$job_id    = (int) ( $_GET['job_id'] ?? 0 );
		$generator = new ErrorReportGenerator( $this->job_repo );
		$generator->stream_error_report( $job_id );
	}
}
