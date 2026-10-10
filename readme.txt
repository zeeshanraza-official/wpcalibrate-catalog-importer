=== WPCalibrate Catalog Importer ===
Contributors: wpcalibrate
Donate link: https://wpcalibrate.com
Tags: woocommerce, product importer, bulk import, csv import, xlsx import
Requires at least: 6.5
Tested up to: 7.1.3
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

High-performance, secure bulk product catalog importer for WooCommerce supporting CSV, XLSX, variable products, images, and Action Scheduler background batch processing.

== Description ==

**WPCalibrate Catalog Importer** is a robust, production-quality WooCommerce product catalog importer built for store owners, agencies, and enterprise merchants. It enables safe creation and updating of large product catalogs from arbitrary CSV and XLSX spreadsheets without forcing you to modify column headers.

### Key Features

* **Arbitrary Column Mapping:** Import supplier feeds and spreadsheets with any column header naming convention via an intuitive 7-step field mapping wizard.
* **CSV and Streaming XLSX Support:** High-speed streaming parser with O(1) memory usage that effortlessly handles large spreadsheets without server memory exhaustion.
* **All Product Types Supported:** Full support for Simple, Variable, Product Variations, Grouped, External/Affiliate, Virtual, and Downloadable products.
* **Flexible Import Modes:**
  * Create new products and update existing products in a single job.
  * Create-only mode (prevents accidental overwriting of existing catalog items).
  * Update-only mode (updates existing products without creating unmatched rows).
* **Deterministic Matching:** Match existing products by SKU (recommended) or internal WooCommerce Product ID.
* **Safe Dry-Run Validation:** Preview rows, identify warnings, and catch errors before committing changes to your store database.
* **WooCommerce Action Scheduler Background Batching:** Large catalogs are processed in background batches, allowing administrators to leave the screen while processing safely continues.
* **Taxonomies & Attributes:** Automatic hierarchy resolution for categories (e.g., `Clothing > Shirts`), tags, global attribute taxonomies, and custom product-level attributes.
* **Multi-Channel Image Handling:** Import images via remote HTTPS URLs with strict SSRF defenses, existing WordPress Media Library attachments, or bulk ZIP image archives.
* **Safe Custom Meta Mapping:** Map custom attributes and meta keys with automatic protection against reserved internal WooCommerce fields and serialized object attacks.
* **Formula Injection Defense:** Downloadable error CSV reports are automatically escaped against spreadsheet formula injection (CWE-1236).
* **High-Performance Order Storage (HPOS) Compatibility:** Fully compatible with WooCommerce HPOS and Custom Order Tables.

== Installation ==

1. Verify that WooCommerce 8.5+ is installed and activated.
2. Upload the `wpcalibrate-catalog-importer.zip` file via your WordPress admin dashboard (**Plugins > Add New > Upload Plugin**) or extract the folder to `/wp-content/plugins/`.
3. Activate the plugin through the **Plugins** menu.
4. Navigate to **WPCalibrate > Catalog Importer** in your WordPress admin menu to begin importing.

== Frequently Asked Questions ==

= Do I need to reformat my supplier's spreadsheet headers? =
No. The importer includes a field-mapping interface with automatic suggestions. You can map any column name to its intended WooCommerce destination field, ignore unwanted columns, or save your mappings as a reusable template.

= How are variable products formatted in the spreadsheet? =
The importer supports a parent product row followed by variation rows. The parent row defines the product as `variable` with attributes (e.g. `Size:S,M,L`). Variation rows specify `type=variation`, reference the `parent_sku`, and define their specific attribute choice (e.g. `variation_attributes=Size=M`).

= How do I import images from a ZIP file? =
You can upload an optional ZIP archive in Step 1 alongside your spreadsheet. Name the images according to product SKU or filename (e.g. `SKU123.jpg` for the featured image, `SKU123_1.jpg` for gallery images). The importer extracts images securely, preventing Zip Slip and directory traversal.

= Will cancelling an import delete products that were already imported? =
No. Cancelling a job stops upcoming queued batches. Any products already created or updated remain intact in your WooCommerce catalog.

= What happens on plugin uninstall? =
By default, all WooCommerce products, media, and store data are preserved. Plugin-specific job history and mapping templates are retained for future reinstalls unless "Delete plugin data on uninstall" is explicitly enabled in Settings.

== Screenshots ==

1. Step 1: Upload spreadsheet (CSV/XLSX) and optional images ZIP.
2. Step 3: Field mapping interface with automatic match suggestions.
3. Step 5: Dry-run validation report showing valid, warning, and invalid rows.
4. Step 6: Live background progress bar powered by Action Scheduler.
5. Import History: Audit log of past jobs with downloadable error reports.

== Changelog ==

= 1.0.0 =
* Initial production release.
* Added streaming CSV and native XLSX parsers.
* Added 7-step import wizard with dry-run validation.
* Added Action Scheduler background batch processor.
* Added variable product and variation importer.
* Added SSRF mitigation and CSV formula injection protection.
* Added saved mapping templates and import history audit log.
