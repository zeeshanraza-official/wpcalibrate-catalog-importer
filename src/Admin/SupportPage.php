<?php
namespace WPCalibrate\CatalogImporter\Admin;

use WPCalibrate\CatalogImporter\Support\SupportDetails;
use WPCalibrate\CatalogImporter\Security\SecurityHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller and renderer for the WPCalibrate Support page.
 */
class SupportPage {

	/**
	 * Render Support Page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! SecurityHelper::can_import() ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wpcalibrate-catalog-importer' ) );
		}

		$support = SupportDetails::get_info();
		?>
		<div class="wrap wpcalibrate-wrap">
			<header class="wpcalibrate-header">
				<div class="wpcalibrate-header-brand">
					<img src="<?php echo esc_url( WPCALIBRATE_CATALOG_IMPORTER_URL . 'branding/icon-dark.png' ); ?>" alt="WPCalibrate" class="wpcalibrate-logo" />
					<div>
						<h1><?php esc_html_e( 'Official Support & Help', 'wpcalibrate-catalog-importer' ); ?></h1>
						<p class="wpcalibrate-tagline"><?php esc_html_e( 'Direct support channels, documentation, and assistance for WPCalibrate plugins', 'wpcalibrate-catalog-importer' ); ?></p>
					</div>
				</div>
			</header>

			<!-- Primary Navigation Tabs -->
			<nav class="nav-tab-wrapper wpcalibrate-nav-tabs" aria-label="<?php esc_attr_e( 'Plugin Navigation', 'wpcalibrate-catalog-importer' ); ?>">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_OVERVIEW ) ); ?>" class="nav-tab"><?php esc_html_e( 'Overview', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=1' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Import Wizard', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_HISTORY ) ); ?>" class="nav-tab"><?php esc_html_e( 'Import History', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_TEMPLATES ) ); ?>" class="nav-tab"><?php esc_html_e( 'Mapping Templates', 'wpcalibrate-catalog-importer' ); ?></a>
				<?php if ( SecurityHelper::can_manage_settings() ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETTINGS ) ); ?>" class="nav-tab"><?php esc_html_e( 'Settings', 'wpcalibrate-catalog-importer' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_LICENSE ) ); ?>" class="nav-tab"><?php esc_html_e( 'License', 'wpcalibrate-catalog-importer' ); ?></a>
				<?php endif; ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_SUPPORT ) ); ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'Support', 'wpcalibrate-catalog-importer' ); ?></a>
			</nav>

			<main class="wpcalibrate-card">
				<style>
					.wpcalibrate-wrap .support-channels-grid {
						display: grid !important;
						grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)) !important;
						gap: 20px !important;
						align-items: stretch !important;
						margin-bottom: 30px !important;
					}
					.wpcalibrate-wrap .support-channel-item {
						display: flex !important;
						flex-direction: column !important;
						justify-content: space-between !important;
						background: #ffffff !important;
						border: 1px solid #e2e8f0 !important;
						border-radius: 8px !important;
						padding: 24px !important;
						box-sizing: border-box !important;
						min-width: 0 !important;
						box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04) !important;
						transition: transform 0.15s ease, box-shadow 0.15s ease !important;
					}
					.wpcalibrate-wrap .support-channel-item:hover {
						transform: translateY(-2px) !important;
						box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08) !important;
					}
					.wpcalibrate-wrap .support-channel-icon {
						font-size: 26px;
						margin-bottom: 12px;
						display: inline-block;
					}
					.wpcalibrate-wrap .support-channel-item h3 {
						margin: 0 0 8px 0 !important;
						font-size: 16px !important;
						font-weight: 600 !important;
						color: #1e293b !important;
					}
					.wpcalibrate-wrap .support-channel-item p {
						color: #64748b !important;
						font-size: 13px !important;
						line-height: 1.5 !important;
						margin: 0 0 16px 0 !important;
						flex-grow: 1 !important;
					}
					.wpcalibrate-wrap .support-channel-meta {
						font-size: 12px;
						color: #0284c7;
						background: #f0f9ff;
						padding: 4px 10px;
						border-radius: 4px;
						margin-bottom: 14px;
						display: inline-block;
						word-break: break-all;
					}
					.wpcalibrate-wrap .support-channel-item .button {
						width: 100% !important;
						text-align: center !important;
						box-sizing: border-box !important;
						white-space: normal !important;
						word-break: break-word !important;
						overflow-wrap: anywhere !important;
						height: auto !important;
						min-height: 38px !important;
						padding: 8px 14px !important;
						display: inline-flex !important;
						align-items: center !important;
						justify-content: center !important;
						margin-top: auto !important;
						font-weight: 600 !important;
						font-size: 13px !important;
						border-radius: 6px !important;
					}
				</style>

				<div class="support-channels-grid">
					<div class="support-channel-item">
						<div>
							<div class="support-channel-icon">✉️</div>
							<h3><?php esc_html_e( 'Email Support', 'wpcalibrate-catalog-importer' ); ?></h3>
							<p><?php esc_html_e( 'Send diagnostic information, questions, or bug reports directly to our engineering team.', 'wpcalibrate-catalog-importer' ); ?></p>
							<div class="support-channel-meta"><?php echo esc_html( $support['email'] ); ?></div>
						</div>
						<a href="mailto:<?php echo esc_attr( $support['email'] ); ?>" class="button button-secondary">
							<?php esc_html_e( 'Send Email Support &rarr;', 'wpcalibrate-catalog-importer' ); ?>
						</a>
					</div>

					<div class="support-channel-item">
						<div>
							<div class="support-channel-icon">💬</div>
							<h3><?php esc_html_e( 'WhatsApp / Direct Phone', 'wpcalibrate-catalog-importer' ); ?></h3>
							<p><?php esc_html_e( 'Quick messaging and urgent inquiry assistance via official WhatsApp support channel.', 'wpcalibrate-catalog-importer' ); ?></p>
							<div class="support-channel-meta"><?php echo esc_html( $support['phone'] ); ?></div>
						</div>
						<a href="https://wa.me/447474795976" target="_blank" rel="noopener noreferrer" class="button button-secondary">
							<?php esc_html_e( 'Chat on WhatsApp &rarr;', 'wpcalibrate-catalog-importer' ); ?>
						</a>
					</div>

					<div class="support-channel-item">
						<div>
							<div class="support-channel-icon">🌐</div>
							<h3><?php esc_html_e( 'Official Website & Docs', 'wpcalibrate-catalog-importer' ); ?></h3>
							<p><?php esc_html_e( 'Read thorough product guides, tutorials, and release notes at wpcalibrate.com.', 'wpcalibrate-catalog-importer' ); ?></p>
							<div class="support-channel-meta">wpcalibrate.com</div>
						</div>
						<a href="<?php echo esc_url( $support['website'] ); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary">
							<?php esc_html_e( 'Visit Website &rarr;', 'wpcalibrate-catalog-importer' ); ?>
						</a>
					</div>

					<div class="support-channel-item">
						<div>
							<div class="support-channel-icon">🛍️</div>
							<h3><?php esc_html_e( 'WPCalibrate Marketplace', 'wpcalibrate-catalog-importer' ); ?></h3>
							<p><?php esc_html_e( 'Manage subscriptions, renew licenses, and browse high-performance WooCommerce solutions.', 'wpcalibrate-catalog-importer' ); ?></p>
							<div class="support-channel-meta">marketplace.wpcalibrate.com</div>
						</div>
						<a href="<?php echo esc_url( $support['marketplace'] ); ?>" target="_blank" rel="noopener noreferrer" class="button button-primary">
							<?php esc_html_e( 'Visit Marketplace &rarr;', 'wpcalibrate-catalog-importer' ); ?>
						</a>
					</div>
				</div>

				<div class="support-system-info" style="margin-top: 20px; padding: 22px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px;">
					<h3 style="margin-top: 0; margin-bottom: 14px; font-size: 16px; color: #1e293b;"><?php esc_html_e( 'System Environment Information', 'wpcalibrate-catalog-importer' ); ?></h3>
					<ul style="margin: 0; line-height: 1.9; color: #475569;">
						<li><strong><?php esc_html_e( 'Plugin Version:', 'wpcalibrate-catalog-importer' ); ?></strong> <?php echo esc_html( WPCALIBRATE_CATALOG_IMPORTER_VERSION ); ?></li>
						<li><strong><?php esc_html_e( 'WordPress Version:', 'wpcalibrate-catalog-importer' ); ?></strong> <?php echo esc_html( get_bloginfo( 'version' ) ); ?></li>
						<li><strong><?php esc_html_e( 'WooCommerce Version:', 'wpcalibrate-catalog-importer' ); ?></strong> <?php echo esc_html( defined( 'WC_VERSION' ) ? WC_VERSION : 'N/A' ); ?></li>
						<li><strong><?php esc_html_e( 'PHP Version:', 'wpcalibrate-catalog-importer' ); ?></strong> <?php echo esc_html( PHP_VERSION ); ?></li>
						<li><strong><?php esc_html_e( 'Web Server:', 'wpcalibrate-catalog-importer' ); ?></strong> <?php echo esc_html( $_SERVER['SERVER_SOFTWARE'] ?? 'N/A' ); ?></li>
					</ul>
				</div>
			</main>
		</div>
		<?php
	}
}
