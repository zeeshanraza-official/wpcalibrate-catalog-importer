<?php
namespace WPCalibrate\CatalogImporter\Import\Mapping;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry of all WooCommerce destination fields, descriptions, data types,
 * required statuses, and header matching aliases.
 */
class FieldRegistry {

	/**
	 * Get definitions of all supported destination fields.
	 *
	 * @return array<string, array>
	 */
	public static function get_fields(): array {
		return [
			// Primary Identifiers
			'sku' => [
				'label'       => __( 'SKU (Stock Keeping Unit)', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Core', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false, // Can be matched by ID if selected
				'description' => __( 'Unique product or variation SKU.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'sku', 'product_sku', 'item_sku', 'code', 'product_code', 'item_code', 'item_number', 'part_number' ],
			],
			'id' => [
				'label'       => __( 'Product ID', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Core', 'wpcalibrate-catalog-importer' ),
				'type'        => 'integer',
				'required'    => false,
				'description' => __( 'Existing WooCommerce product ID (used when updating by ID).', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'id', 'product_id', 'post_id', 'item_id', 'wc_id' ],
			],
			'name' => [
				'label'       => __( 'Product Title / Name', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Core', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => true,
				'description' => __( 'Title of the product.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'name', 'title', 'product_name', 'product_title', 'item_name', 'heading' ],
			],
			'slug' => [
				'label'       => __( 'Product Slug', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Core', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'URL-friendly product slug.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'slug', 'post_name', 'url_slug' ],
			],
			'type' => [
				'label'       => __( 'Product Type', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Core', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'simple, variable, variation, grouped, external.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'type', 'product_type', 'item_type' ],
			],
			'parent_sku' => [
				'label'       => __( 'Parent Product SKU', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Core', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Parent SKU for variations or grouped child products.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'parent_sku', 'parent', 'parent_code', 'parent_item' ],
			],
			'parent_id' => [
				'label'       => __( 'Parent Product ID', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Core', 'wpcalibrate-catalog-importer' ),
				'type'        => 'integer',
				'required'    => false,
				'description' => __( 'Parent post ID for variations.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'parent_id', 'post_parent' ],
			],
			'status' => [
				'label'       => __( 'Product Status', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Core', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'publish, draft, pending, or private.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'status', 'product_status', 'post_status', 'state', 'visibility_status' ],
			],
			'featured' => [
				'label'       => __( 'Featured Product', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Core', 'wpcalibrate-catalog-importer' ),
				'type'        => 'boolean',
				'required'    => false,
				'description' => __( 'Is product featured (1, 0, yes, no).', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'featured', 'is_featured', 'featured_product' ],
			],
			'catalog_visibility' => [
				'label'       => __( 'Catalog Visibility', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Core', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'visible, catalog, search, or hidden.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'catalog_visibility', 'visibility' ],
			],
			'description' => [
				'label'       => __( 'Full Description', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Content', 'wpcalibrate-catalog-importer' ),
				'type'        => 'html',
				'required'    => false,
				'description' => __( 'Main product description.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'description', 'full_description', 'body', 'content', 'post_content', 'details' ],
			],
			'short_description' => [
				'label'       => __( 'Short Description', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Content', 'wpcalibrate-catalog-importer' ),
				'type'        => 'html',
				'required'    => false,
				'description' => __( 'Excerpt / short description.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'short_description', 'excerpt', 'summary', 'post_excerpt', 'teaser' ],
			],
			'regular_price' => [
				'label'       => __( 'Regular Price', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Pricing', 'wpcalibrate-catalog-importer' ),
				'type'        => 'price',
				'required'    => false,
				'description' => __( 'Standard selling price.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'regular_price', 'price', 'retail_price', 'msrp', 'list_price', 'standard_price' ],
			],
			'sale_price' => [
				'label'       => __( 'Sale Price', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Pricing', 'wpcalibrate-catalog-importer' ),
				'type'        => 'price',
				'required'    => false,
				'description' => __( 'Discounted sale price.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'sale_price', 'special_price', 'discount_price', 'offer_price' ],
			],
			'date_on_sale_from' => [
				'label'       => __( 'Sale Start Date', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Pricing', 'wpcalibrate-catalog-importer' ),
				'type'        => 'date',
				'required'    => false,
				'description' => __( 'Date sale begins (YYYY-MM-DD).', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'date_on_sale_from', 'sale_start', 'sale_from', 'discount_start' ],
			],
			'date_on_sale_to' => [
				'label'       => __( 'Sale End Date', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Pricing', 'wpcalibrate-catalog-importer' ),
				'type'        => 'date',
				'required'    => false,
				'description' => __( 'Date sale ends (YYYY-MM-DD).', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'date_on_sale_to', 'sale_end', 'sale_to', 'discount_end' ],
			],
			'manage_stock' => [
				'label'       => __( 'Manage Stock', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Inventory', 'wpcalibrate-catalog-importer' ),
				'type'        => 'boolean',
				'required'    => false,
				'description' => __( 'Enable stock management at product level (yes/no, 1/0).', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'manage_stock', 'track_stock', 'inventory_tracking' ],
			],
			'stock_quantity' => [
				'label'       => __( 'Stock Quantity', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Inventory', 'wpcalibrate-catalog-importer' ),
				'type'        => 'number',
				'required'    => false,
				'description' => __( 'Units available in stock.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'stock_quantity', 'stock', 'qty', 'quantity', 'inventory', 'inventory_qty', 'stock_qty' ],
			],
			'stock_status' => [
				'label'       => __( 'Stock Status', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Inventory', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'instock, outofstock, onbackorder.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'stock_status', 'in_stock', 'availability' ],
			],
			'backorders' => [
				'label'       => __( 'Allow Backorders', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Inventory', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'no, notify, yes.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'backorders', 'allow_backorders' ],
			],
			'sold_individually' => [
				'label'       => __( 'Sold Individually', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Inventory', 'wpcalibrate-catalog-importer' ),
				'type'        => 'boolean',
				'required'    => false,
				'description' => __( 'Limit purchases to 1 item per order (1, 0, yes, no).', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'sold_individually', 'individual_sale' ],
			],
			'tax_status' => [
				'label'       => __( 'Tax Status', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Tax & Shipping', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'taxable, shipping, none.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'tax_status', 'is_taxable' ],
			],
			'tax_class' => [
				'label'       => __( 'Tax Class', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Tax & Shipping', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Standard, reduced-rate, zero-rate, or custom tax class.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'tax_class' ],
			],
			'shipping_class' => [
				'label'       => __( 'Shipping Class', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Tax & Shipping', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Shipping class slug or name.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'shipping_class' ],
			],
			'weight' => [
				'label'       => __( 'Weight', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Tax & Shipping', 'wpcalibrate-catalog-importer' ),
				'type'        => 'number',
				'required'    => false,
				'description' => __( 'Product weight.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'weight', 'product_weight', 'item_weight' ],
			],
			'length' => [
				'label'       => __( 'Length', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Tax & Shipping', 'wpcalibrate-catalog-importer' ),
				'type'        => 'number',
				'required'    => false,
				'description' => __( 'Product length dimension.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'length' ],
			],
			'width' => [
				'label'       => __( 'Width', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Tax & Shipping', 'wpcalibrate-catalog-importer' ),
				'type'        => 'number',
				'required'    => false,
				'description' => __( 'Product width dimension.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'width' ],
			],
			'height' => [
				'label'       => __( 'Height', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Tax & Shipping', 'wpcalibrate-catalog-importer' ),
				'type'        => 'number',
				'required'    => false,
				'description' => __( 'Product height dimension.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'height' ],
			],
			'virtual' => [
				'label'       => __( 'Virtual Product', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Virtual & Downloads', 'wpcalibrate-catalog-importer' ),
				'type'        => 'boolean',
				'required'    => false,
				'description' => __( 'Virtual product with no shipping needed (1, 0, yes, no).', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'virtual', 'is_virtual' ],
			],
			'downloadable' => [
				'label'       => __( 'Downloadable Product', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Virtual & Downloads', 'wpcalibrate-catalog-importer' ),
				'type'        => 'boolean',
				'required'    => false,
				'description' => __( 'Product grants digital file access upon purchase (1, 0, yes, no).', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'downloadable', 'is_downloadable' ],
			],
			'downloadable_files' => [
				'label'       => __( 'Downloadable Files', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Virtual & Downloads', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Name and URL (e.g. "Guide:https://...|Ebook:https://..." or direct URL).', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'downloadable_files', 'downloads', 'download_files', 'files' ],
			],
			'download_limit' => [
				'label'       => __( 'Download Limit', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Virtual & Downloads', 'wpcalibrate-catalog-importer' ),
				'type'        => 'integer',
				'required'    => false,
				'description' => __( 'Number of times file can be downloaded (-1 for unlimited).', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'download_limit' ],
			],
			'download_expiry' => [
				'label'       => __( 'Download Expiry Days', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Virtual & Downloads', 'wpcalibrate-catalog-importer' ),
				'type'        => 'integer',
				'required'    => false,
				'description' => __( 'Number of days before download link expires (-1 for never).', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'download_expiry', 'download_expiry_days' ],
			],
			'external_url' => [
				'label'       => __( 'External Product URL', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'External Product', 'wpcalibrate-catalog-importer' ),
				'type'        => 'url',
				'required'    => false,
				'description' => __( 'Affiliate or external purchase URL.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'external_url', 'product_url', 'affiliate_url', 'buy_url' ],
			],
			'button_text' => [
				'label'       => __( 'External Button Text', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'External Product', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Text displayed on button (e.g. "Buy on Amazon").', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'button_text', 'buy_button_text', 'cta_text' ],
			],
			'category_names' => [
				'label'       => __( 'Categories', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Taxonomies', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Category names separated by comma or pipe (e.g. "Clothing > Shirts, Summer").', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'categories', 'category', 'category_names', 'product_cat', 'cats' ],
			],
			'tag_names' => [
				'label'       => __( 'Tags', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Taxonomies', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Tag names separated by comma or pipe (e.g. "cotton, casual, eco").', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'tags', 'tag', 'tag_names', 'product_tag' ],
			],
			'images' => [
				'label'       => __( 'Images (Featured & Gallery)', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Images', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'URLs, attachment IDs, or ZIP filenames (first image is featured; others gallery).', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'images', 'image', 'photos', 'picture', 'product_images', 'image_urls' ],
			],
			'image_featured' => [
				'label'       => __( 'Featured Image', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Images', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Single primary featured image URL, ID, or ZIP filename.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'image_featured', 'featured_image', 'main_image', 'thumbnail', 'cover_image' ],
			],
			'image_gallery' => [
				'label'       => __( 'Gallery Images', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Images', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Additional gallery image URLs, IDs, or filenames separated by comma or pipe.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'image_gallery', 'gallery', 'additional_images', 'product_gallery' ],
			],
			'upsell_skus' => [
				'label'       => __( 'Upsell Products (SKUs or IDs)', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Linked Products', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'SKUs or product IDs separated by comma or pipe.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'upsells', 'upsell_skus', 'upsell_ids' ],
			],
			'cross_sell_skus' => [
				'label'       => __( 'Cross-sell Products (SKUs or IDs)', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Linked Products', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'SKUs or product IDs separated by comma or pipe.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'cross_sells', 'cross_sell_skus', 'cross_sell_ids', 'crosssells' ],
			],
			'grouped_children' => [
				'label'       => __( 'Grouped Product Children (SKUs or IDs)', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Linked Products', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Child SKUs or IDs belonging to this grouped product.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'grouped_children', 'children', 'group_members', 'grouped_products' ],
			],
			'purchase_note' => [
				'label'       => __( 'Purchase Note', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Advanced', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Note sent to customer after purchase.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'purchase_note', 'order_note' ],
			],
			'menu_order' => [
				'label'       => __( 'Menu Order', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Advanced', 'wpcalibrate-catalog-importer' ),
				'type'        => 'integer',
				'required'    => false,
				'description' => __( 'Custom sorting position order.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'menu_order', 'position', 'sort_order' ],
			],
			'gtin' => [
				'label'       => __( 'GTIN / Barcode / Global Unique ID', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Advanced', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'GTIN, UPC, EAN, or ISBN.', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'gtin', 'barcode', 'upc', 'ean', 'isbn', 'global_unique_id' ],
			],
			'attributes' => [
				'label'       => __( 'Product Attributes (Combined)', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Attributes', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Attributes formatted as Name:Val1,Val2|Name2:Val3', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'attributes', 'product_attributes' ],
			],
			'variation_attributes' => [
				'label'       => __( 'Variation Attributes (Variation Row)', 'wpcalibrate-catalog-importer' ),
				'group'       => __( 'Attributes', 'wpcalibrate-catalog-importer' ),
				'type'        => 'string',
				'required'    => false,
				'description' => __( 'Specific attribute choice for this variation (e.g. Color=Blue|Size=M).', 'wpcalibrate-catalog-importer' ),
				'aliases'     => [ 'variation_attributes', 'variation_attribute', 'attribute_values', 'variant_options' ],
			],
		];
	}

	/**
	 * Suggest a destination field key for a given source column header.
	 *
	 * @param string $source_header
	 * @return string|null Matching field key or null.
	 */
	public static function suggest_mapping( string $source_header ): ?string {
		$normalized = strtolower( trim( $source_header ) );
		$clean      = preg_replace( '/[^a-z0-9_]/', '_', $normalized );
		$clean      = preg_replace( '/_+/', '_', (string) $clean );
		$clean      = trim( (string) $clean, '_' );

		// Check if it's explicitly an attribute column: e.g. "attribute:color", "attribute_color", "attr:size"
		if ( preg_match( '/^(?:attribute|attr)[:_-](.+)$/i', $normalized, $matches ) ) {
			return 'attribute:' . sanitize_title( $matches[1] );
		}

		// Check if it's explicitly a custom meta column: e.g. "meta:brand", "custom_meta:cost"
		if ( preg_match( '/^(?:meta|custom_meta)[:_-](.+)$/i', $normalized, $matches ) ) {
			return 'meta:' . sanitize_key( $matches[1] );
		}

		$fields = self::get_fields();

		// Exact key match.
		if ( isset( $fields[ $clean ] ) ) {
			return $clean;
		}

		// Check aliases.
		foreach ( $fields as $field_key => $field_data ) {
			if ( in_array( $clean, $field_data['aliases'], true ) || in_array( $normalized, $field_data['aliases'], true ) ) {
				return $field_key;
			}
		}

		return null;
	}
}
