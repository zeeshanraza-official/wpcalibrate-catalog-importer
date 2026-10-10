<?php
namespace WPCalibrate\CatalogImporter\Admin;

use WPCalibrate\CatalogImporter\Import\Mapping\MappingTemplateRepository;
use WPCalibrate\CatalogImporter\Security\SecurityHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller and renderer for the Mapping Templates page.
 */
class TemplatesPage {

	protected MappingTemplateRepository $repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new MappingTemplateRepository();
	}

	/**
	 * Register actions.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_wpcalibrate_delete_template', [ $this, 'handle_delete_template' ] );
	}

	/**
	 * Render Mapping Templates Page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! SecurityHelper::can_import() ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wpcalibrate-catalog-importer' ) );
		}

		$templates = $this->repository->get_all();
		?>
		<div class="wrap wpcalibrate-wrap">
			<header class="wpcalibrate-header">
				<div class="wpcalibrate-header-brand">
					<img src="<?php echo esc_url( WPCALIBRATE_CATALOG_IMPORTER_URL . 'branding/icon-dark.png' ); ?>" alt="WPCalibrate" class="wpcalibrate-logo" />
					<div>
						<h1><?php esc_html_e( 'Mapping Templates', 'wpcalibrate-catalog-importer' ); ?></h1>
						<p class="wpcalibrate-tagline"><?php esc_html_e( 'Reusable spreadsheet column configurations saved during imports', 'wpcalibrate-catalog-importer' ); ?></p>
					</div>
				</div>
			</header>

			<!-- Primary Navigation Tabs -->
			<nav class="nav-tab-wrapper wpcalibrate-nav-tabs" aria-label="<?php esc_attr_e( 'Plugin Navigation', 'wpcalibrate-catalog-importer' ); ?>">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_OVERVIEW ) ); ?>" class="nav-tab"><?php esc_html_e( 'Overview', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=1' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Import Wizard', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_HISTORY ) ); ?>" class="nav-tab"><?php esc_html_e( 'Import History', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_TEMPLATES ) ); ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'Mapping Templates', 'wpcalibrate-catalog-importer' ); ?></a>
				<?php if ( SecurityHelper::can_manage_settings() ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETTINGS ) ); ?>" class="nav-tab"><?php esc_html_e( 'Settings', 'wpcalibrate-catalog-importer' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_LICENSE ) ); ?>" class="nav-tab"><?php esc_html_e( 'License', 'wpcalibrate-catalog-importer' ); ?></a>
				<?php endif; ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_SUPPORT ) ); ?>" class="nav-tab"><?php esc_html_e( 'Support', 'wpcalibrate-catalog-importer' ); ?></a>
			</nav>

			<main class="wpcalibrate-card">
				<?php if ( empty( $templates ) ) : ?>
					<div class="empty-state" style="text-align: center; padding: 40px 20px;">
						<p class="description"><?php esc_html_e( 'No saved mapping templates found. You can name and save your column mappings during Step 3 of any import.', 'wpcalibrate-catalog-importer' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=1' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Start New Import', 'wpcalibrate-catalog-importer' ); ?>
						</a>
					</div>
				<?php else : ?>
					<table class="wp-list-table widefat striped fixed table-view-list">
						<thead>
							<tr>
								<th scope="col" style="width: 25%;"><?php esc_html_e( 'Template Name', 'wpcalibrate-catalog-importer' ); ?></th>
								<th scope="col" style="width: 15%;"><?php esc_html_e( 'Format', 'wpcalibrate-catalog-importer' ); ?></th>
								<th scope="col" style="width: 20%;"><?php esc_html_e( 'Mapped Columns', 'wpcalibrate-catalog-importer' ); ?></th>
								<th scope="col" style="width: 25%;"><?php esc_html_e( 'Created / Updated', 'wpcalibrate-catalog-importer' ); ?></th>
								<th scope="col" style="width: 15%;"><?php esc_html_e( 'Actions', 'wpcalibrate-catalog-importer' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $templates as $t ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $t['name'] ); ?></strong>
										<?php if ( ! empty( $t['description'] ) ) : ?>
											<br /><small class="text-muted"><?php echo esc_html( $t['description'] ); ?></small>
										<?php endif; ?>
									</td>
									<td>
										<span class="badge badge-secondary"><?php echo esc_html( strtoupper( $t['source_type'] ) ); ?></span>
									</td>
									<td>
										<?php echo esc_html( (string) count( $t['mapping_data'] ) ); ?> <?php esc_html_e( 'columns', 'wpcalibrate-catalog-importer' ); ?>
									</td>
									<td>
										<small><?php echo esc_html( $t['updated_at'] ?: $t['created_at'] ); ?></small>
									</td>
									<td>
										<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpcalibrate_delete_template&template_id=' . $t['id'] ), SecurityHelper::NONCE_ACTION_TEMPLATES ) ); ?>" class="button button-small text-danger" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to delete this template?', 'wpcalibrate-catalog-importer' ) ); ?>');">
											<?php esc_html_e( 'Delete', 'wpcalibrate-catalog-importer' ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</main>
		</div>
		<?php
	}

	/**
	 * Delete template action handler.
	 *
	 * @return void
	 */
	public function handle_delete_template(): void {
		$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) );
		SecurityHelper::verify_nonce( $nonce, SecurityHelper::NONCE_ACTION_TEMPLATES );

		if ( ! SecurityHelper::can_import() ) {
			wp_die( esc_html__( 'Unauthorized', 'wpcalibrate-catalog-importer' ), 403 );
		}

		$template_id = (int) ( $_GET['template_id'] ?? 0 );
		if ( $template_id > 0 ) {
			$this->repository->delete( $template_id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . AdminMenu::SLUG_TEMPLATES ) );
		exit;
	}
}
