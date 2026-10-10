<?php
namespace WPCalibrate\CatalogImporter\Admin;

use WPCalibrate\CatalogImporter\Security\SecurityHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller and renderer for the Plugin Settings page.
 */
class SettingsPage {

	public const OPTION_NAME = 'wpcalibrate_catalog_importer_settings';

	/**
	 * Default plugin options.
	 *
	 * @return array
	 */
	public static function get_defaults(): array {
		return [
			'default_import_mode'     => 'create_update',
			'default_identifier'      => 'sku',
			'default_product_status'  => 'publish',
			'enable_remote_images'    => true,
			'max_remote_image_size'   => 10, // MB
			'reuse_existing_images'   => true,
			'image_timeout'           => 15, // seconds
			'batch_size'              => 25,
			'history_retention_days'  => 30,
			'delete_data_on_uninstall'=> false,
		];
	}

	/**
	 * Get current merged settings.
	 *
	 * @return array
	 */
	public static function get_settings(): array {
		$saved = get_option( self::OPTION_NAME, [] );
		return wp_parse_args( is_array( $saved ) ? $saved : [], self::get_defaults() );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register(): void {
		add_action( 'admin_post_wpcalibrate_save_settings', [ $this, 'handle_save_settings' ] );
	}

	/**
	 * Handle settings form submission.
	 *
	 * @return void
	 */
	public function handle_save_settings(): void {
		$nonce = sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ?? '' ) );
		SecurityHelper::verify_nonce( $nonce, SecurityHelper::NONCE_ACTION_SETTINGS );

		if ( ! SecurityHelper::can_manage_settings() ) {
			wp_die( esc_html__( 'Unauthorized', 'wpcalibrate-catalog-importer' ), 403 );
		}

		$settings = [
			'default_import_mode'     => sanitize_key( $_POST['default_import_mode'] ?? 'create_update' ),
			'default_identifier'      => sanitize_key( $_POST['default_identifier'] ?? 'sku' ),
			'default_product_status'  => sanitize_key( $_POST['default_product_status'] ?? 'publish' ),
			'enable_remote_images'    => ! empty( $_POST['enable_remote_images'] ),
			'max_remote_image_size'   => max( 1, min( 50, (int) ( $_POST['max_remote_image_size'] ?? 10 ) ) ),
			'reuse_existing_images'   => ! empty( $_POST['reuse_existing_images'] ),
			'image_timeout'           => max( 5, min( 60, (int) ( $_POST['image_timeout'] ?? 15 ) ) ),
			'batch_size'              => max( 10, min( 250, (int) ( $_POST['batch_size'] ?? 25 ) ) ),
			'history_retention_days'  => max( 1, min( 365, (int) ( $_POST['history_retention_days'] ?? 30 ) ) ),
			'delete_data_on_uninstall'=> ! empty( $_POST['delete_data_on_uninstall'] ),
		];

		update_option( self::OPTION_NAME, $settings );

		wp_safe_redirect(
			add_query_arg(
				[
					'page'    => AdminMenu::SLUG_SETTINGS,
					'updated' => '1',
				],
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Render Settings Page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! SecurityHelper::can_manage_settings() ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wpcalibrate-catalog-importer' ) );
		}

		$settings = self::get_settings();
		$updated  = ! empty( $_GET['updated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		?>
		<div class="wrap wpcalibrate-wrap">
			<header class="wpcalibrate-header">
				<div class="wpcalibrate-header-brand">
					<img src="<?php echo esc_url( WPCALIBRATE_CATALOG_IMPORTER_URL . 'branding/icon-dark.png' ); ?>" alt="WPCalibrate" class="wpcalibrate-logo" />
					<div>
						<h1><?php esc_html_e( 'Importer Settings', 'wpcalibrate-catalog-importer' ); ?></h1>
						<p class="wpcalibrate-tagline"><?php esc_html_e( 'Configure global defaults, image performance limits, and retention policies', 'wpcalibrate-catalog-importer' ); ?></p>
					</div>
				</div>
			</header>

			<!-- Primary Navigation Tabs -->
			<nav class="nav-tab-wrapper wpcalibrate-nav-tabs" aria-label="<?php esc_attr_e( 'Plugin Navigation', 'wpcalibrate-catalog-importer' ); ?>">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_OVERVIEW ) ); ?>" class="nav-tab"><?php esc_html_e( 'Overview', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=1' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Import Wizard', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_HISTORY ) ); ?>" class="nav-tab"><?php esc_html_e( 'Import History', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_TEMPLATES ) ); ?>" class="nav-tab"><?php esc_html_e( 'Mapping Templates', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETTINGS ) ); ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'Settings', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_LICENSE ) ); ?>" class="nav-tab"><?php esc_html_e( 'License', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_SUPPORT ) ); ?>" class="nav-tab"><?php esc_html_e( 'Support', 'wpcalibrate-catalog-importer' ); ?></a>
			</nav>

			<?php if ( $updated ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Settings updated successfully.', 'wpcalibrate-catalog-importer' ); ?></p>
				</div>
			<?php endif; ?>

			<main class="wpcalibrate-card">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wpcalibrate-form">
					<input type="hidden" name="action" value="wpcalibrate_save_settings" />
					<?php wp_nonce_field( SecurityHelper::NONCE_ACTION_SETTINGS ); ?>

					<h2 class="title"><?php esc_html_e( 'General Defaults', 'wpcalibrate-catalog-importer' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="default_import_mode"><?php esc_html_e( 'Default Import Mode', 'wpcalibrate-catalog-importer' ); ?></label></th>
							<td>
								<select name="default_import_mode" id="default_import_mode" class="regular-text">
									<option value="create_update" <?php selected( $settings['default_import_mode'], 'create_update' ); ?>><?php esc_html_e( 'Create new and update existing products', 'wpcalibrate-catalog-importer' ); ?></option>
									<option value="create_only" <?php selected( $settings['default_import_mode'], 'create_only' ); ?>><?php esc_html_e( 'Create new products only', 'wpcalibrate-catalog-importer' ); ?></option>
									<option value="update_only" <?php selected( $settings['default_import_mode'], 'update_only' ); ?>><?php esc_html_e( 'Update existing products only', 'wpcalibrate-catalog-importer' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="default_identifier"><?php esc_html_e( 'Default Matching Identifier', 'wpcalibrate-catalog-importer' ); ?></label></th>
							<td>
								<select name="default_identifier" id="default_identifier" class="regular-text">
									<option value="sku" <?php selected( $settings['default_identifier'], 'sku' ); ?>><?php esc_html_e( 'SKU (Recommended)', 'wpcalibrate-catalog-importer' ); ?></option>
									<option value="id" <?php selected( $settings['default_identifier'], 'id' ); ?>><?php esc_html_e( 'WooCommerce Product ID', 'wpcalibrate-catalog-importer' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="default_product_status"><?php esc_html_e( 'Default Product Status', 'wpcalibrate-catalog-importer' ); ?></label></th>
							<td>
								<select name="default_product_status" id="default_product_status" class="regular-text">
									<option value="publish" <?php selected( $settings['default_product_status'], 'publish' ); ?>><?php esc_html_e( 'Publish', 'wpcalibrate-catalog-importer' ); ?></option>
									<option value="draft" <?php selected( $settings['default_product_status'], 'draft' ); ?>><?php esc_html_e( 'Draft', 'wpcalibrate-catalog-importer' ); ?></option>
									<option value="pending" <?php selected( $settings['default_product_status'], 'pending' ); ?>><?php esc_html_e( 'Pending Review', 'wpcalibrate-catalog-importer' ); ?></option>
									<option value="private" <?php selected( $settings['default_product_status'], 'private' ); ?>><?php esc_html_e( 'Private', 'wpcalibrate-catalog-importer' ); ?></option>
								</select>
							</td>
						</tr>
					</table>

					<h2 class="title" style="margin-top: 30px;"><?php esc_html_e( 'Image & Media Settings', 'wpcalibrate-catalog-importer' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Remote Image Import', 'wpcalibrate-catalog-importer' ); ?></th>
							<td>
								<label for="enable_remote_images">
									<input type="checkbox" name="enable_remote_images" id="enable_remote_images" value="1" <?php checked( $settings['enable_remote_images'] ); ?> />
									<?php esc_html_e( 'Enable downloading remote image URLs into WordPress Media Library', 'wpcalibrate-catalog-importer' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Deduplicate Media', 'wpcalibrate-catalog-importer' ); ?></th>
							<td>
								<label for="reuse_existing_images">
									<input type="checkbox" name="reuse_existing_images" id="reuse_existing_images" value="1" <?php checked( $settings['reuse_existing_images'] ); ?> />
									<?php esc_html_e( 'Reuse previously downloaded media if identical source URL exists', 'wpcalibrate-catalog-importer' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="max_remote_image_size"><?php esc_html_e( 'Max Image Size (MB)', 'wpcalibrate-catalog-importer' ); ?></label></th>
							<td>
								<input type="number" name="max_remote_image_size" id="max_remote_image_size" value="<?php echo esc_attr( (string) $settings['max_remote_image_size'] ); ?>" min="1" max="50" class="small-text" /> MB
								<p class="description"><?php esc_html_e( 'Reject remote images larger than this size limit.', 'wpcalibrate-catalog-importer' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="image_timeout"><?php esc_html_e( 'Image Download Timeout (Seconds)', 'wpcalibrate-catalog-importer' ); ?></label></th>
							<td>
								<input type="number" name="image_timeout" id="image_timeout" value="<?php echo esc_attr( (string) $settings['image_timeout'] ); ?>" min="5" max="60" class="small-text" />
							</td>
						</tr>
					</table>

					<h2 class="title" style="margin-top: 30px;"><?php esc_html_e( 'Performance & Batching', 'wpcalibrate-catalog-importer' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="batch_size"><?php esc_html_e( 'Default Batch Size', 'wpcalibrate-catalog-importer' ); ?></label></th>
							<td>
								<input type="number" name="batch_size" id="batch_size" value="<?php echo esc_attr( (string) $settings['batch_size'] ); ?>" min="10" max="250" class="small-text" />
								<p class="description"><?php esc_html_e( 'Number of rows processed per background Action Scheduler job (default: 25).', 'wpcalibrate-catalog-importer' ); ?></p>
							</td>
						</tr>
					</table>

					<h2 class="title" style="margin-top: 30px;"><?php esc_html_e( 'Data Retention & Uninstall', 'wpcalibrate-catalog-importer' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Uninstall Behavior', 'wpcalibrate-catalog-importer' ); ?></th>
							<td>
								<label for="delete_data_on_uninstall">
									<input type="checkbox" name="delete_data_on_uninstall" id="delete_data_on_uninstall" value="1" <?php checked( $settings['delete_data_on_uninstall'] ); ?> />
									<strong class="text-danger"><?php esc_html_e( 'Delete plugin tables, job history, and mapping templates when plugin is deleted', 'wpcalibrate-catalog-importer' ); ?></strong>
								</label>
								<p class="description"><?php esc_html_e( 'Note: Imported WooCommerce products and store data are NEVER deleted upon uninstall.', 'wpcalibrate-catalog-importer' ); ?></p>
							</td>
						</tr>
					</table>

					<p class="submit">
						<button type="submit" class="button button-primary"><?php esc_html_e( 'Save Settings', 'wpcalibrate-catalog-importer' ); ?></button>
					</p>
				</form>
			</main>
		</div>
		<?php
	}
}
