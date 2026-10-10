# WPCalibrate Catalog Importer for WooCommerce

[![WordPress Tested Up To](https://img.shields.io/badge/WordPress-6.5%20--%207.1.3-blue.svg)](https://wordpress.org)
[![WooCommerce Tested Up To](https://img.shields.io/badge/WooCommerce-8.5%20--%2011.2.1-96588a.svg)](https://woocommerce.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%20%7C%208.3-777bb4.svg)](https://php.net)
[![HPOS](https://img.shields.io/badge/HPOS-100%25%20Compatible-brightgreen.svg)](https://github.com/woocommerce/woocommerce)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-orange.svg)](LICENSE)
[![Latest Release](https://img.shields.io/badge/Release-v1.0.0-emerald.svg)](https://github.com/zeeshanraza-official/wpcalibrate-catalog-importer/releases)

**WPCalibrate Catalog Importer** (`wpcalibrate-catalog-importer`) is an enterprise-grade, memory-efficient WooCommerce bulk product catalog importer built for store owners, agencies, and large-scale merchants. Safely create and update thousands of products from arbitrary CSV and XLSX spreadsheets using an intuitive 7-step wizard, interactive dry-run validation, and WooCommerce Action Scheduler background batch processing.

---

## 📖 About

When managing large WooCommerce stores or migrating catalogs from external supplier feeds, native import tools frequently crash from server memory limits or require extensive spreadsheet reformatting. **WPCalibrate Catalog Importer** solves this by:

1. **Eliminating External Vendor Bloat**: Employs a zero-dependency streaming OpenXML reader written directly in native PHP (`ZipArchive` and `XMLReader`) and a streaming CSV reader. The importer runs with constant $O(1)$ memory usage whether processing 10 products or 50,000 products.
2. **Accepting Arbitrary Column Headers**: Store managers never have to rename supplier columns to match WooCommerce conventions. The interactive visual field mapping engine allows matching any source column to 30+ WooCommerce destination fields, with auto-detection for common aliases.
3. **Preventing Catalog Corruption**: Full simulation dry-run validation checks syntax, price logic, duplicate SKUs, missing variation parents, and warnings *before* any record touches the database.
4. **Providing Non-Blocking Execution**: Large imports run asynchronously via WooCommerce Action Scheduler in bounded batches, featuring real-time AJAX progress tracking and pause/cancellation capability.

---

## ✨ Features

### 🚀 High-Speed Streaming Parsers ($O(1)$ Memory)
- **Streaming CSV Parser:** Auto-detects delimiters (comma, semicolon, tab, pipe), handles UTF-8 Byte Order Marks (BOM), escaped quotes, multi-line values, and Mac/Windows line endings.
- **Zero-Bloat XLSX Streaming Parser:** Native OpenXML engine parses `.xlsx` workbooks row-by-row directly from raw XML streams, eliminating heavy Composer libraries like PhpSpreadsheet that cause version conflicts on customer sites.
- **ZIP Image Packages:** Extract and associate product images directly from an uploaded ZIP archive matching files by SKU or image filename.

### 🧙 7-Step Import Wizard
1. **Upload:** Upload CSV, XLSX, or optional ZIP image archives with MIME/size validation.
2. **Mode & Identifier:** Choose between *Create & Update*, *Create Only*, or *Update Only*, matching records by SKU or internal WooCommerce Product ID.
3. **Field Mapping:** Intuitive column mapper with alias suggestions and the ability to save and load reusable mapping templates.
4. **Import Options:** Configure default stock statuses, category hierarchy creation, tax classes, and image handling rules.
5. **Dry-Run Validation:** Run a non-destructive simulation to preview parsed items, check errors, and review warnings.
6. **Background Batch Processing:** Live visual progress bar updating via AJAX every 2 seconds with Action Scheduler execution.
7. **Results & Audit:** Comprehensive counts (created, updated, skipped, failed) and formula-injection-safe downloadable error CSV reports.

### 📦 All WooCommerce Product Types Supported
- **Simple Products:** Virtual, downloadable, regular/sale prices, dimensions, weight, stock quantity, and backorders.
- **Variable Products:** Auto-creates parent variable products with attributes (`pa_*` global taxonomies or custom local attributes).
- **Product Variations:** Automatically links child variation records to parent products by SKU, configuring specific options, prices, and stock.
- **Grouped Products:** Defines grouped parent containers and associates child SKUs.
- **External / Affiliate Products:** Supports external product URLs and customizable call-to-action button text.

### 🛡️ Enterprise Security Hardening
- **SSRF Protection:** Remote image fetching strictly validates HTTP/HTTPS protocols and uses host IP resolution to block loopback (`127.0.0.1`), link-local, and private subnets (`10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`).
- **Zip Slip Defense:** Archive extraction enforces canonical target directory boundary checks, blocking directory traversal (`../`).
- **CSV Formula Injection Prevention (CWE-1236):** Error CSV export automatically sanitizes cells starting with `=`, `+`, `-`, `@`, `\t`, or `\r`.
- **Protected Metadata Filtering:** Custom meta mapping allowlists clean metadata and strictly blocks internal protected keys (`_price`, `_stock`, `_sku`) and serialized PHP object payloads.

### 🔄 In-Dashboard Automatic Updates
- Native GitHub Release update integration (`GitHubUpdater`).
- When a new release tag (e.g. `v1.0.1`) is published on this repository, an update notification automatically appears under **Dashboard → Updates** and **Plugins**.
- Supports one-click updating directly from the WordPress dashboard without requiring manual FTP re-uploads.
- Fully compatible with the **Git Updater** / **GitHub Updater** standard headers.

---

## 📚 Documentation & How to Use

### Installation

#### Method 1: WordPress Dashboard Upload (Recommended)
1. Download the latest `wpcalibrate-catalog-importer-1.0.0.zip` from the [Releases](https://github.com/zeeshanraza-official/wpcalibrate-catalog-importer/releases) page.
2. In your WordPress admin dashboard, navigate to **Plugins → Add New Plugin → Upload Plugin**.
3. Choose the downloaded ZIP file and click **Install Now**.
4. Click **Activate Plugin**.

#### Method 2: Manual FTP / SFTP
1. Extract the `wpcalibrate-catalog-importer` folder from the ZIP archive.
2. Upload the folder to `/wp-content/plugins/wpcalibrate-catalog-importer/` on your server.
3. In WordPress admin, go to **Plugins → Installed Plugins** and activate **WPCalibrate Catalog Importer**.

---

### Step-by-Step Import Guide

#### 1. Preparing Your Spreadsheet
Ensure your spreadsheet includes a unique SKU or Product ID for matching. Column headers can be named whatever you prefer:

| SKU | Product Name | Type | Regular Price | Sale Price | Stock | Categories | Attributes |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| `WPC-SHIRT-01` | Classic Oxford Shirt | `simple` | `49.99` | `39.99` | `50` | `Apparel > Shirts` | |
| `WPC-HEADPHONE`| Wireless Headphones | `variable` | `199.99` | | | `Electronics` | Color:Black,Silver |
| `WPC-HEADPHONE-BLK` | Headphones - Black | `variation` | `199.99` | | `25` | | Color=Black |

> **Tip:** You can download a ready-to-use sample spreadsheet from **WPCalibrate → Overview** in your dashboard (`sample-products-import-template.csv`).

#### 2. Running the 7-Step Import Wizard
1. In your WordPress dashboard, navigate to **WPCalibrate → Import Wizard**.
2. **Upload:** Select your CSV or XLSX file (and optional ZIP image archive).
3. **Mode & Identifier:** Choose your import mode:
   - *Create new products and update existing products (Recommended)*
   - *Create new products only*
   - *Update existing products only*
4. **Field Mapping:** Match each source column to the target WooCommerce field. Unmapped columns are safely skipped. Save the mapping configuration as a reusable template if desired.
5. **Options:** Configure defaults for category separators, stock status, tax classes, and batch size.
6. **Dry-Run Validation:** Click **Run Validation Simulation** to inspect errors and warnings before any products are modified.
7. **Process Batch:** Click **Start Background Import**. Action Scheduler will process rows in bounded batches with real-time visual progress reporting.
8. **Results:** View completed metrics and download a CSV report of any skipped or failed rows.

---

### In-Dashboard Automatic Updates

This plugin includes native GitHub Release update integration:
- When a new version is released on GitHub, WordPress automatically flags an update notification under **Dashboard → Updates**.
- Store administrators can click **Update Now** to pull the latest verified release archive automatically.
- To inspect change details before upgrading, click **View version details** in the plugins table to view full release notes and changelog inside the native WordPress modal.

---

## 📋 Changelog

### [1.0.0] - 2026-10-10
- **Initial Production Release:**
  - High-performance streaming CSV parser with delimiter auto-detection and BOM handling.
  - Zero-bloat native streaming XLSX parser powered by PHP `ZipArchive` and `XMLReader`.
  - 7-step interactive import wizard with live progress tracking and Action Scheduler execution.
  - Full product type coverage: Simple, Variable, Product Variations, Grouped, External/Affiliate, Virtual, and Downloadable.
  - Three import modes: *Create & Update*, *Create Only*, and *Update Only*.
  - SSRF-hardened image downloader and ZIP image package extractor with Zip Slip defense.
  - Formula injection defense (CWE-1236) on CSV error reports.
  - Dedicated Overview tab with step-by-step documentation and 1-click bundled demo catalog import.
  - High-Performance Order Storage (HPOS) and Cart/Checkout Blocks compatibility declared.
  - Automated test runner with 13 comprehensive unit, integration, and performance benchmark suites.
  - Native in-dashboard GitHub updater (`GitHubUpdater`) supporting 1-click updates from GitHub releases.

---

## 💬 Support & Inquiries

- **Author:** [WPCalibrate](https://wpcalibrate.com)
- **Marketplace:** [https://marketplace.wpcalibrate.com](https://marketplace.wpcalibrate.com)
- **Direct Email:** [support@wpcalibrate.com](mailto:support@wpcalibrate.com)
- **WhatsApp Support:** [+447474795976](https://wa.me/447474795976)
- **Issues & Contributions:** [GitHub Issues](https://github.com/zeeshanraza-official/wpcalibrate-catalog-importer/issues)

---

## 📄 License

This plugin is free software, licensed under the terms of the [GNU General Public License v2.0 or later](LICENSE).  
Copyright © 2026 WPCalibrate.
