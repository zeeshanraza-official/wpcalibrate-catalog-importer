<?php
namespace WPCalibrate\CatalogImporter\Admin;

use WPCalibrate\CatalogImporter\Jobs\ImportJob;
use WPCalibrate\CatalogImporter\Jobs\ImportJobRepository;
use WPCalibrate\CatalogImporter\Security\SecurityHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller and renderer for the Import History page.
 */
class HistoryPage {

	protected ImportJobRepository $repository;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new ImportJobRepository();
	}

	/**
	 * Register actions.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_wpcalibrate_delete_job', [ $this, 'handle_delete_job' ] );
	}

	/**
	 * Render History Page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! SecurityHelper::can_import() ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wpcalibrate-catalog-importer' ) );
		}

		$current_page = max( 1, (int) ( $_GET['paged'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$data         = $this->repository->get_paginated( $current_page, 15 );
		$jobs         = $data['items'];
		$total_pages  = $data['pages'];
		?>
		<div class="wrap wpcalibrate-wrap">
			<header class="wpcalibrate-header">
				<div class="wpcalibrate-header-brand">
					<img src="<?php echo esc_url( WPCALIBRATE_CATALOG_IMPORTER_URL . 'branding/icon-dark.png' ); ?>" alt="WPCalibrate" class="wpcalibrate-logo" />
					<div>
						<h1><?php esc_html_e( 'Import History', 'wpcalibrate-catalog-importer' ); ?></h1>
						<p class="wpcalibrate-tagline"><?php esc_html_e( 'Audit trail of all catalog import jobs and row results', 'wpcalibrate-catalog-importer' ); ?></p>
					</div>
				</div>
				<div class="wpcalibrate-header-actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=1' ) ); ?>" class="button button-primary">
						<?php esc_html_e( '+ New Import', 'wpcalibrate-catalog-importer' ); ?>
					</a>
				</div>
			</header>

			<!-- Primary Navigation Tabs -->
			<nav class="nav-tab-wrapper wpcalibrate-nav-tabs" aria-label="<?php esc_attr_e( 'Plugin Navigation', 'wpcalibrate-catalog-importer' ); ?>">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_OVERVIEW ) ); ?>" class="nav-tab"><?php esc_html_e( 'Overview', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=1' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Import Wizard', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_HISTORY ) ); ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'Import History', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_TEMPLATES ) ); ?>" class="nav-tab"><?php esc_html_e( 'Mapping Templates', 'wpcalibrate-catalog-importer' ); ?></a>
				<?php if ( SecurityHelper::can_manage_settings() ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETTINGS ) ); ?>" class="nav-tab"><?php esc_html_e( 'Settings', 'wpcalibrate-catalog-importer' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_LICENSE ) ); ?>" class="nav-tab"><?php esc_html_e( 'License', 'wpcalibrate-catalog-importer' ); ?></a>
				<?php endif; ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_SUPPORT ) ); ?>" class="nav-tab"><?php esc_html_e( 'Support', 'wpcalibrate-catalog-importer' ); ?></a>
			</nav>

			<main class="wpcalibrate-card">
				<?php if ( empty( $jobs ) ) : ?>
					<div class="empty-state" style="text-align: center; padding: 40px 20px;">
						<p class="description"><?php esc_html_e( 'No import jobs recorded yet.', 'wpcalibrate-catalog-importer' ); ?></p>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=1' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Start Your First Import', 'wpcalibrate-catalog-importer' ); ?>
						</a>
					</div>
				<?php else : ?>
					<table class="wp-list-table widefat striped fixed table-view-list">
						<thead>
							<tr>
								<th scope="col" style="width: 15%;"><?php esc_html_e( 'File / Job UUID', 'wpcalibrate-catalog-importer' ); ?></th>
								<th scope="col" style="width: 12%;"><?php esc_html_e( 'Mode', 'wpcalibrate-catalog-importer' ); ?></th>
								<th scope="col" style="width: 12%;"><?php esc_html_e( 'Status', 'wpcalibrate-catalog-importer' ); ?></th>
								<th scope="col" style="width: 15%;"><?php esc_html_e( 'Progress', 'wpcalibrate-catalog-importer' ); ?></th>
								<th scope="col" style="width: 8%;"><?php esc_html_e( 'Created', 'wpcalibrate-catalog-importer' ); ?></th>
								<th scope="col" style="width: 8%;"><?php esc_html_e( 'Updated', 'wpcalibrate-catalog-importer' ); ?></th>
								<th scope="col" style="width: 8%;"><?php esc_html_e( 'Failed', 'wpcalibrate-catalog-importer' ); ?></th>
								<th scope="col" style="width: 12%;"><?php esc_html_e( 'Started / Completed', 'wpcalibrate-catalog-importer' ); ?></th>
								<th scope="col" style="width: 10%;"><?php esc_html_e( 'Actions', 'wpcalibrate-catalog-importer' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $jobs as $job ) : ?>
								<tr>
									<td>
										<strong><?php echo esc_html( $job->source_filename ); ?></strong><br />
										<small class="text-muted"><code><?php echo esc_html( substr( $job->job_uuid, 0, 13 ) ); ?>...</code></small>
									</td>
									<td>
										<span class="badge badge-secondary"><?php echo esc_html( str_replace( '_', ' ', ucfirst( $job->import_mode ) ) ); ?></span>
									</td>
									<td>
										<span class="badge badge-<?php echo ImportJob::STATUS_COMPLETED === $job->status ? 'success' : ( ImportJob::STATUS_PROCESSING === $job->status ? 'info' : ( ImportJob::STATUS_CANCELLED === $job->status ? 'warning' : 'secondary' ) ); ?>">
											<?php echo esc_html( strtoupper( $job->status ) ); ?>
										</span>
									</td>
									<td>
										<div class="mini-progress-bar-wrapper">
											<div class="mini-progress-bar" style="width: <?php echo esc_attr( (string) $job->get_progress_percent() ); ?>%;"></div>
										</div>
										<small><?php echo esc_html( (string) $job->processed_rows ); ?> / <?php echo esc_html( (string) $job->total_rows ); ?> (<?php echo esc_html( (string) $job->get_progress_percent() ); ?>%)</small>
									</td>
									<td><strong class="text-success"><?php echo esc_html( (string) $job->created_count ); ?></strong></td>
									<td><strong class="text-info"><?php echo esc_html( (string) $job->updated_count ); ?></strong></td>
									<td><strong class="<?php echo $job->failed_count > 0 ? 'text-danger' : ''; ?>"><?php echo esc_html( (string) $job->failed_count ); ?></strong></td>
									<td>
										<small><?php echo esc_html( $job->started_at ?: $job->created_at ); ?></small><br />
										<small class="text-muted"><?php echo esc_html( $job->completed_at ?: '-' ); ?></small>
									</td>
									<td>
										<div class="row-actions visible">
											<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=7&job_id=' . $job->id ) ); ?>" title="<?php esc_attr_e( 'View Details', 'wpcalibrate-catalog-importer' ); ?>">
												<?php esc_html_e( 'View', 'wpcalibrate-catalog-importer' ); ?>
											</a>
											<?php if ( $job->failed_count > 0 || $job->warning_count > 0 ) : ?>
												| <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpcalibrate_download_errors&job_id=' . $job->id ), SecurityHelper::NONCE_ACTION_IMPORT ) ); ?>" title="<?php esc_attr_e( 'Download Errors CSV', 'wpcalibrate-catalog-importer' ); ?>">
													<?php esc_html_e( 'CSV', 'wpcalibrate-catalog-importer' ); ?>
												</a>
											<?php endif; ?>
											| <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpcalibrate_delete_job&job_id=' . $job->id ), SecurityHelper::NONCE_ACTION_IMPORT ) ); ?>" class="text-danger" onclick="return confirm('<?php echo esc_js( __( 'Delete this history record? Note: Imported WooCommerce products will NOT be deleted.', 'wpcalibrate-catalog-importer' ) ); ?>');">
												<?php esc_html_e( 'Delete', 'wpcalibrate-catalog-importer' ); ?>
											</a>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>

					<!-- Pagination -->
					<?php if ( $total_pages > 1 ) : ?>
						<div class="tablenav bottom" style="margin-top: 20px;">
							<div class="tablenav-pages">
								<?php
								echo paginate_links( [ // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
									'base'      => add_query_arg( 'paged', '%#%' ),
									'format'    => '',
									'prev_text' => __( '&laquo; Prev', 'wpcalibrate-catalog-importer' ),
									'next_text' => __( 'Next &raquo;', 'wpcalibrate-catalog-importer' ),
									'total'     => $total_pages,
									'current'   => $current_page,
								] );
								?>
							</div>
						</div>
					<?php endif; ?>
				<?php endif; ?>
			</main>
		</div>
		<?php
	}

	/**
	 * Handle deletion of a job history record.
	 *
	 * @return void
	 */
	public function handle_delete_job(): void {
		$nonce = sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ?? '' ) );
		SecurityHelper::verify_nonce( $nonce );

		if ( ! SecurityHelper::can_import() ) {
			wp_die( esc_html__( 'Unauthorized', 'wpcalibrate-catalog-importer' ), 403 );
		}

		$job_id = (int) ( $_GET['job_id'] ?? 0 );
		if ( $job_id > 0 ) {
			$this->repository->delete( $job_id );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=' . AdminMenu::SLUG_HISTORY ) );
		exit;
	}
}
