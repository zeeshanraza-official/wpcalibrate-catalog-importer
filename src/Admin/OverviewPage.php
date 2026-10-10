<?php
namespace WPCalibrate\CatalogImporter\Admin;

use WPCalibrate\CatalogImporter\Security\SecurityHelper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Controller and renderer for the Plugin Overview page.
 * Details the plugin's purpose, methodology, architecture, and step-by-step usage guide.
 */
class OverviewPage {

	/**
	 * Render Overview Page.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! SecurityHelper::can_import() ) {
			wp_die( esc_html__( 'Unauthorized access.', 'wpcalibrate-catalog-importer' ) );
		}
		?>
		<div class="wrap wpcalibrate-wrap">
			<!-- Header -->
			<header class="wpcalibrate-header">
				<div class="wpcalibrate-header-brand">
					<img src="<?php echo esc_url( WPCALIBRATE_CATALOG_IMPORTER_URL . 'branding/icon-dark.png' ); ?>" alt="WPCalibrate" class="wpcalibrate-logo" />
					<div>
						<h1><?php esc_html_e( 'Catalog Importer — Overview', 'wpcalibrate-catalog-importer' ); ?></h1>
						<p class="wpcalibrate-tagline"><?php esc_html_e( 'High-performance, secure bulk product catalog importer for WooCommerce', 'wpcalibrate-catalog-importer' ); ?></p>
					</div>
				</div>
				<div class="wpcalibrate-header-actions">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=1' ) ); ?>" class="button button-primary button-hero">
						<?php esc_html_e( 'Launch Import Wizard &rarr;', 'wpcalibrate-catalog-importer' ); ?>
					</a>
				</div>
			</header>

			<!-- Primary Navigation Tabs -->
			<nav class="nav-tab-wrapper wpcalibrate-nav-tabs" aria-label="<?php esc_attr_e( 'Plugin Navigation', 'wpcalibrate-catalog-importer' ); ?>">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_OVERVIEW ) ); ?>" class="nav-tab nav-tab-active"><?php esc_html_e( 'Overview', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=1' ) ); ?>" class="nav-tab"><?php esc_html_e( 'Import Wizard', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_HISTORY ) ); ?>" class="nav-tab"><?php esc_html_e( 'Import History', 'wpcalibrate-catalog-importer' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_TEMPLATES ) ); ?>" class="nav-tab"><?php esc_html_e( 'Mapping Templates', 'wpcalibrate-catalog-importer' ); ?></a>
				<?php if ( SecurityHelper::can_manage_settings() ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_SETTINGS ) ); ?>" class="nav-tab"><?php esc_html_e( 'Settings', 'wpcalibrate-catalog-importer' ); ?></a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_LICENSE ) ); ?>" class="nav-tab"><?php esc_html_e( 'License', 'wpcalibrate-catalog-importer' ); ?></a>
				<?php endif; ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_SUPPORT ) ); ?>" class="nav-tab"><?php esc_html_e( 'Support', 'wpcalibrate-catalog-importer' ); ?></a>
			</nav>

			<!-- Section 1: Purpose -->
			<main class="wpcalibrate-card" style="margin-top: 20px;">
				<div class="overview-hero-section">
					<h2><?php esc_html_e( 'Plugin Purpose', 'wpcalibrate-catalog-importer' ); ?></h2>
					<p class="overview-lead-text">
						<?php esc_html_e( 'WPCalibrate Catalog Importer enables store owners, agencies, and enterprise merchants to safely import and update thousands of products from arbitrary CSV and XLSX spreadsheets without forcing you to reformat or rename your supplier columns.', 'wpcalibrate-catalog-importer' ); ?>
					</p>

					<div class="overview-pillars-grid">
						<div class="pillar-card">
							<div class="pillar-icon">🔄</div>
							<h3><?php esc_html_e( 'Zero Column Lock-In', 'wpcalibrate-catalog-importer' ); ?></h3>
							<p><?php esc_html_e( 'Upload any supplier feed as-is. Our interactive field mapper suggests matches automatically and lets you map any column to any WooCommerce field.', 'wpcalibrate-catalog-importer' ); ?></p>
						</div>
						<div class="pillar-card">
							<div class="pillar-icon">⚡</div>
							<h3><?php esc_html_e( 'O(1) Streaming Engine', 'wpcalibrate-catalog-importer' ); ?></h3>
							<p><?php esc_html_e( 'Native streaming parsers for both CSV and XLSX consume constant memory regardless of whether your catalog has 50 rows or 50,000 rows.', 'wpcalibrate-catalog-importer' ); ?></p>
						</div>
						<div class="pillar-card">
							<div class="pillar-icon">🛡️</div>
							<h3><?php esc_html_e( 'Enterprise Safety', 'wpcalibrate-catalog-importer' ); ?></h3>
							<p><?php esc_html_e( 'Pre-flight dry runs catch errors before touching your database. Action Scheduler background batching lets you close your browser while imports continue safely.', 'wpcalibrate-catalog-importer' ); ?></p>
						</div>
					</div>
				</div>

				<hr class="wpcalibrate-separator" />

				<!-- Section 2: Method & Architecture -->
				<section class="overview-method-section">
					<h2><?php esc_html_e( 'Method & 7-Step Import Lifecycle', 'wpcalibrate-catalog-importer' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Every import job executes through a deterministic, fault-tolerant 7-step pipeline designed to prevent data corruption:', 'wpcalibrate-catalog-importer' ); ?></p>

					<div class="method-steps-container">
						<div class="method-step-box">
							<span class="method-step-badge">1</span>
							<h4><?php esc_html_e( 'Upload', 'wpcalibrate-catalog-importer' ); ?></h4>
							<p><?php esc_html_e( 'Upload CSV or XLSX files plus an optional image ZIP archive. Files are quarantined in a secure WordPress upload folder with script execution protections.', 'wpcalibrate-catalog-importer' ); ?></p>
						</div>

						<div class="method-step-box">
							<span class="method-step-badge">2</span>
							<h4><?php esc_html_e( 'Import Mode', 'wpcalibrate-catalog-importer' ); ?></h4>
							<p><?php esc_html_e( 'Select Create & Update, Create-Only, or Update-Only mode, and choose SKU (recommended) or internal Product ID as your matching identifier.', 'wpcalibrate-catalog-importer' ); ?></p>
						</div>

						<div class="method-step-box">
							<span class="method-step-badge">3</span>
							<h4><?php esc_html_e( 'Field Mapping', 'wpcalibrate-catalog-importer' ); ?></h4>
							<p><?php esc_html_e( 'Match source columns with 30+ WooCommerce fields, product attributes, or custom meta keys. Save mappings as named templates for future imports.', 'wpcalibrate-catalog-importer' ); ?></p>
						</div>

						<div class="method-step-box">
							<span class="method-step-badge">4</span>
							<h4><?php esc_html_e( 'Options', 'wpcalibrate-catalog-importer' ); ?></h4>
							<p><?php esc_html_e( 'Configure default statuses, category creation, image deduplication, and blank-cell overwrite policies to protect existing product fields.', 'wpcalibrate-catalog-importer' ); ?></p>
						</div>

						<div class="method-step-box">
							<span class="method-step-badge">5</span>
							<h4><?php esc_html_e( 'Validation', 'wpcalibrate-catalog-importer' ); ?></h4>
							<p><?php esc_html_e( 'Pre-flight simulation inspects every row for duplicate SKUs, invalid prices, missing variation parents, and syntax errors without modifying products.', 'wpcalibrate-catalog-importer' ); ?></p>
						</div>

						<div class="method-step-box">
							<span class="method-step-badge">6</span>
							<h4><?php esc_html_e( 'Background Batch', 'wpcalibrate-catalog-importer' ); ?></h4>
							<p><?php esc_html_e( 'Action Scheduler processes products in bounded batches (default 25) with atomic concurrency locking. Progress updates live and persists across reloads.', 'wpcalibrate-catalog-importer' ); ?></p>
						</div>

						<div class="method-step-box">
							<span class="method-step-badge">7</span>
							<h4><?php esc_html_e( 'Results & Audit', 'wpcalibrate-catalog-importer' ); ?></h4>
							<p><?php esc_html_e( 'Review comprehensive success and failure counts, and download a formula-injection-safe CSV report of any skipped or failed records.', 'wpcalibrate-catalog-importer' ); ?></p>
						</div>
					</div>
				</section>

				<hr class="wpcalibrate-separator" />

				<!-- Section 3: How to Use -->
				<section class="overview-howto-section">
					<h2><?php esc_html_e( 'How to Use — Step-by-Step Guide', 'wpcalibrate-catalog-importer' ); ?></h2>

					<div class="howto-grid">
						<div class="howto-card">
							<h3><?php esc_html_e( '1. Simple & Grouped Products', 'wpcalibrate-catalog-importer' ); ?></h3>
							<p><?php esc_html_e( 'Each row in your file represents a distinct product. Map columns for SKU, Title, Regular Price, Stock Qty, and Categories. For grouped products, specify child product SKUs in the grouped children column.', 'wpcalibrate-catalog-importer' ); ?></p>
						</div>

						<div class="howto-card">
							<h3><?php esc_html_e( '2. Variable Products & Variations', 'wpcalibrate-catalog-importer' ); ?></h3>
							<p><?php esc_html_e( 'Define the parent product on one row with product type set to "variable" and attributes listed (e.g. Size:S,M,L). Follow it with variation rows referencing the parent SKU and their specific option (e.g. Size=M).', 'wpcalibrate-catalog-importer' ); ?></p>
						</div>

						<div class="howto-card">
							<h3><?php esc_html_e( '3. Importing Images', 'wpcalibrate-catalog-importer' ); ?></h3>
							<p><?php esc_html_e( 'Images can be imported from remote HTTPS URLs (with SSRF protection), existing WordPress Media Library IDs, or by uploading an optional ZIP file containing images named by SKU (e.g. SKU123.jpg, SKU123_1.jpg).', 'wpcalibrate-catalog-importer' ); ?></p>
						</div>

						<div class="howto-card">
							<h3><?php esc_html_e( '4. Custom Metadata Safely', 'wpcalibrate-catalog-importer' ); ?></h3>
							<p><?php esc_html_e( 'Map any spreadsheet column to custom product meta. Protected WooCommerce internal keys (_price, _stock) are automatically blocked, and PHP serialized object payloads are stripped for safety.', 'wpcalibrate-catalog-importer' ); ?></p>
						</div>
					</div>
				</section>

				<hr class="wpcalibrate-separator" />

				<!-- Section 4: Supported Product Types -->
				<section class="overview-supported-section">
					<h2><?php esc_html_e( 'Supported WooCommerce Product Types', 'wpcalibrate-catalog-importer' ); ?></h2>
					<div class="types-badges-list">
						<span class="type-badge">📦 <?php esc_html_e( 'Simple Products', 'wpcalibrate-catalog-importer' ); ?></span>
						<span class="type-badge">🎨 <?php esc_html_e( 'Variable Products', 'wpcalibrate-catalog-importer' ); ?></span>
						<span class="type-badge">🔀 <?php esc_html_e( 'Product Variations', 'wpcalibrate-catalog-importer' ); ?></span>
						<span class="type-badge">📁 <?php esc_html_e( 'Grouped Products', 'wpcalibrate-catalog-importer' ); ?></span>
						<span class="type-badge">🔗 <?php esc_html_e( 'External / Affiliate Products', 'wpcalibrate-catalog-importer' ); ?></span>
						<span class="type-badge">☁️ <?php esc_html_e( 'Virtual Products', 'wpcalibrate-catalog-importer' ); ?></span>
						<span class="type-badge">📥 <?php esc_html_e( 'Downloadable Products', 'wpcalibrate-catalog-importer' ); ?></span>
					</div>
				</section>

				<hr class="wpcalibrate-separator" />

				<!-- Section 5: Demo Products Importer -->
				<section class="overview-demo-section" style="background: #f0f7ff; border: 1px solid #c8e1ff; border-radius: 8px; padding: 24px; margin-top: 24px;">
					<div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
						<div style="max-width: 650px;">
							<h3 style="margin: 0 0 8px 0; color: #005177; font-size: 18px;">
								✨ <?php esc_html_e( 'Test with Bundled Demo Products', 'wpcalibrate-catalog-importer' ); ?>
							</h3>
							<p style="margin: 0; color: #334155; font-size: 14px; line-height: 1.5;">
								<?php esc_html_e( 'Experience the full power of WPCalibrate without preparing a spreadsheet. Import 10+ realistic demo products (Simple, Variable, Variations, Grouped, and External) directly into WooCommerce with 1-click.', 'wpcalibrate-catalog-importer' ); ?>
							</p>
						</div>
						<div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
							<button type="button" id="wpcalibrate-btn-demo-import" class="button button-primary button-hero" style="background: #0073aa;">
								🚀 <?php esc_html_e( '1-Click Demo Import', 'wpcalibrate-catalog-importer' ); ?>
							</button>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wpcalibrate_load_demo_wizard' ), SecurityHelper::NONCE_ACTION_IMPORT ) ); ?>" class="button button-secondary" style="height: 46px; line-height: 44px; padding: 0 16px;">
								🧙 <?php esc_html_e( 'Test in 7-Step Wizard &rarr;', 'wpcalibrate-catalog-importer' ); ?>
							</a>
						</div>
					</div>
					<div id="wpcalibrate-demo-status-notice" style="display: none; margin-top: 16px;"></div>
				</section>

				<hr class="wpcalibrate-separator" />

				<!-- Call to Action -->
				<div class="overview-cta-box" style="text-align: center; padding: 25px 0;">
					<h3><?php esc_html_e( 'Ready to Start Importing?', 'wpcalibrate-catalog-importer' ); ?></h3>
					<p class="description" style="margin-bottom: 20px;">
						<?php esc_html_e( 'You can upload your own file or download our comprehensive reference CSV template below:', 'wpcalibrate-catalog-importer' ); ?>
					</p>
					<a href="<?php echo esc_url( WPCALIBRATE_CATALOG_IMPORTER_URL . 'sample-products-import-template.csv' ); ?>" download class="button button-secondary" style="margin-right: 12px;">
						📥 <?php esc_html_e( 'Download Sample Template CSV', 'wpcalibrate-catalog-importer' ); ?>
					</a>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AdminMenu::SLUG_WIZARD . '&step=1' ) ); ?>" class="button button-primary button-hero">
						🚀 <?php esc_html_e( 'Launch Import Wizard &rarr;', 'wpcalibrate-catalog-importer' ); ?>
					</a>
				</div>
			</main>
		</div>
		<?php
	}
}
