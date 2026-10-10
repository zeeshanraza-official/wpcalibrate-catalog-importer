<?php
namespace WPCalibrate\CatalogImporter\Admin;

use WPCalibrate\CatalogImporter\Licensing\Contracts\LicenseServiceInterface;
use WPCalibrate\CatalogImporter\Licensing\UnconfiguredLicenseService;
use WPCalibrate\CatalogImporter\Security\SecurityHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller and renderer for the Plugin License page.
 */
class LicensePage {

	protected LicenseServiceInterface $license_service;

	/**
	 * Constructor.
	 *
	 * @param LicenseServiceInterface|null $service
	 */
	public function __construct( ?LicenseServiceInterface $service = null ) {
		$this->license_service = $service ?: new UnconfiguredLicenseService();
	}

	/**
	 * Register actions.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_wpcalibrate_activate_license', [ $this, 'handle_activate' ] );
		add_action( 'admin_post_wpcalibrate_deactivate_license', [ $this, 'handle_deactivate' ] );
	}

	/**
	 * Handle activate form submission.
	 *
	 * @return void
	 */
	public function handle_activate(): void {
		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		SecurityHelper::verify_nonce( $nonce, SecurityHelper::NONCE_ACTION_LICENSE );

		if ( ! SecurityHelper::can_manage_settings() ) {
			wp_die( esc_html__( 'Unauthorized', 'wpcalibrate-catalog-importer' ), 403 );
		}

		$key    = sanitize_text_field( wp_unslash( $_POST['license_key'] ?? '' ) );
		$result = $this->license_service->activate( $key );

		$msg  = is_wp_error( $result ) ? $result->get_error_message() : __( 'License key saved.', 'wpcalibrate-catalog-importer' );
		$type = is_wp_error( $result ) ? 'warning' : 'success';

		wp_safe_redirect(
			add_query_arg(
				[
					'page'     => AdminMenu::SLUG_LICENSE,
					'msg_type' => $type,
					'msg'      => rawurlencode( $msg ),
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Handle deactivate.
	 *
	 * @return void
	 */
	public function handle_deactivate(): void {
		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		SecurityHelper::verify_nonce( $nonce, SecurityHelper::NONCE_ACTION_LICENSE );

		if ( ! SecurityHelper::can_manage_settings() ) {
			wp_die( esc_html__( 'Unauthorized', 'wpcalibrate-catalog-importer' ), 403 );
		}

		$this->license_service->deactivate();

		wp_safe_redirect(
			add_query_arg(
				[
					'page'     => AdminMenu::SLUG_LICENSE,
					'msg_type' => 'info',
					'msg'      => rawurlencode( __( 'License deactivated successfully.', 'wpcalibrate-catalog-importer' ) ),
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render License Page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! SecurityHelper::can_manage_settings() ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wpcalibrate-catalog-importer' ) );
		}

		$masked_key = $this->license_service->get_masked_key();
		$status     = $this->license_service->get_status();

		$msg_type = isset( $_GET['msg_type'] ) ? sanitize_key( $_GET['msg_type'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg      = isset( $_GET['msg'] ) ? sanitize_text_field( wp_unslash( $_GET['msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap wpcalibrate-wrap">
			<header class="wpcalibrate-header">
				<div class="wpcalibrate-header-brand">
					<img src="<?php echo esc_url( WPCALIBRATE_CATALOG_IMPORTER_URL . 'branding/icon-dark.png' ); ?>" alt="WPCalibrate" class="wpcalibrate-logo" />
					<div>
						<h1><?php esc_html_e( 'Plugin License', 'wpcalibrate-catalog-importer' ); ?></h1>
						<p class="wpcalibrate-tagline"><?php esc_html_e( 'Manage your WPCalibrate license for updates and official support', 'wpcalibrate-catalog-importer' ); ?></p>
					</div>
				</div>
			</header>

			<!-- Primary Navigation Tabs -->
			<nav class="nav-tab-wrapper wpcalibrate-nav-tabs" aria-label="<?php esc_attr_e( 'Plugin Navigation', 'wpcalibrate-catalog-importer' ); ?>">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_OVERVIEW ) ); ?>" class="nav-tab"><?php esc_html_e( 'Overview', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=1' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Import Wizard', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_HISTORY ) ); ?>" class="nav-tab"><?php esc_html_e( 'Import History', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_TEMPLATES ) ); ?>" class="nav-tab"><?php esc_html_e( 'Mapping Templates', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETTINGS ) ); ?>" class="nav-tab"><?php esc_html_e( 'Settings', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_LICENSE ) ); ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'License', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_SUPPORT ) ); ?>" class="nav-tab"><?php esc_html_e( 'Support', 'wpcalibrate-catalog-importer' ); ?></a>
			</nav>

			<?php if ( $msg ) : ?>
				<div class="notice notice-<?php echo esc_attr( $msg_type ?: 'info' ); ?> is-dismissible">
					<p><?php echo esc_html( $msg ); ?></p>
				</div>
			<?php endif; ?>

			<main class="wpcalibrate-card">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'License Status', 'wpcalibrate-catalog-importer' ); ?></th>
						<td>
							<span class="badge badge-<?php echo $status['is_active'] ? 'success' : 'warning'; ?>">
								<?php echo esc_html( strtoupper( $status['status'] ) ); ?>
							</span>
							<p class="description" style="margin-top: 6px;">
								<?php echo esc_html( $status['message'] ); ?>
							</p>
						</td>
					</tr>

					<?php if ( '' !== $masked_key ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Saved License Key', 'wpcalibrate-catalog-importer' ); ?></th>
							<td>
								<code><?php echo esc_html( $masked_key ); ?></code>
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: inline-block; margin-left: 15px;">
									<input type="hidden" name="action" value="wpcalibrate_deactivate_license" />
									<?php wp_nonce_field( SecurityHelper::NONCE_ACTION_LICENSE ); ?>
									<button type="submit" class="button button-small text-danger" onclick="return confirm('<?php echo esc_js( __( 'Deactivate and remove this license key?', 'wpcalibrate-catalog-importer' ) ); ?>');">
										<?php esc_html_e( 'Deactivate License', 'wpcalibrate-catalog-importer' ); ?>
									</button>
								</form>
							</td>
						</tr>
					<?php endif; ?>
				</table>

				<hr style="margin: 25px 0; border: 0; border-top: 1px solid #ddd;" />

				<h3><?php esc_html_e( 'Activate / Update License Key', 'wpcalibrate-catalog-importer' ); ?></h3>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpcalibrate-form">
					<input type="hidden" name="action" value="wpcalibrate_activate_license" />
					<?php wp_nonce_field( SecurityHelper::NONCE_ACTION_LICENSE ); ?>

					<div class="form-row">
						<label for="license_key" class="font-bold"><?php esc_html_e( 'License Key', 'wpcalibrate-catalog-importer' ); ?></label>
						<input type="password" id="license_key" name="license_key" class="regular-text" placeholder="<?php esc_attr_e( 'Paste your license key here...', 'wpcalibrate-catalog-importer' ); ?>" required autocomplete="off" />
						<p class="description">
							<?php
							echo esc_html(
								sprintf(
									/* translators: %s: Marketplace URL */
									__( 'Obtain or renew your key at %s', 'wpcalibrate-catalog-importer' ),
									'https://marketplace.wpcalibrate.com/'
								)
							);
							?>
						</p>
					</div>

					<p class="submit">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save & Activate License', 'wpcalibrate-catalog-importer' ); ?></button>
					</p>
				</form>
			</main>
		</div>
		<?php
	}
}
