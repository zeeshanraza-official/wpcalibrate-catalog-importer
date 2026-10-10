<?php
namespace WPCalibrate\CatalogImporter\Licensing\Contracts;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Service boundary interface for WPCalibrate licensing.
 */
interface LicenseServiceInterface {

	/**
	 * Get the stored masked license key.
	 *
	 * @return string Masked key (e.g. "••••••••-••••-WXYZ") or empty string.
	 */
	public function get_masked_key(): string;

	/**
	 * Check license status.
	 *
	 * @return array{
	 *     status: string,
	 *     is_active: bool,
	 *     message: string,
	 *     expiry: string|null,
	 *     renewal_url: string|null
	 * }
	 */
	public function get_status(): array;

	/**
	 * Activate a license key.
	 *
	 * @param string $license_key Plaintext key entered by admin.
	 * @return array|WP_Error
	 */
	public function activate( string $license_key ): array|WP_Error;

	/**
	 * Deactivate license.
	 *
	 * @return array|WP_Error
	 */
	public function deactivate(): array|WP_Error;
}
