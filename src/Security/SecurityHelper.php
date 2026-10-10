<?php
namespace WPCalibrate\CatalogImporter\Security;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enforces permissions, nonces, capabilities, and sanitization across importer operations.
 */
class SecurityHelper {

	public const NONCE_ACTION_IMPORT    = 'wpcalibrate_importer_nonce';
	public const NONCE_ACTION_SETTINGS  = 'wpcalibrate_settings_nonce';
	public const NONCE_ACTION_TEMPLATES = 'wpcalibrate_templates_nonce';
	public const NONCE_ACTION_LICENSE   = 'wpcalibrate_license_nonce';

	/**
	 * Check if current user can perform imports, dry runs, and template management.
	 *
	 * @return bool
	 */
	public static function can_import(): bool {
		$cap = apply_filters( 'wpcalibrate_catalog_importer_capability', 'manage_woocommerce' );
		return current_user_can( $cap );
	}

	/**
	 * Check if current user can modify sensitive plugin settings and licenses.
	 *
	 * @return bool
	 */
	public static function can_manage_settings(): bool {
		$cap = apply_filters( 'wpcalibrate_catalog_importer_settings_capability', 'manage_options' );
		return current_user_can( $cap );
	}

	/**
	 * Verify an AJAX or POST nonce, failing immediately with 403 or returning bool.
	 *
	 * @param string $nonce Nonce value.
	 * @param string $action Nonce action name.
	 * @param bool $die Whether to wp_die on failure.
	 * @return bool
	 */
	public static function verify_nonce( string $nonce, string $action = self::NONCE_ACTION_IMPORT, bool $die = true ): bool {
		$verified = (bool) wp_verify_nonce( $nonce, $action );

		if ( ! $verified && $die ) {
			wp_die(
				esc_html__( 'Security check failed. Please refresh the page and try again.', 'wpcalibrate-catalog-importer' ),
				esc_html__( 'Forbidden', 'wpcalibrate-catalog-importer' ),
				[ 'response' => 403 ]
			);
		}

		return $verified;
	}

	/**
	 * Create nonce for given action.
	 *
	 * @param string $action
	 * @return string
	 */
	public static function create_nonce( string $action = self::NONCE_ACTION_IMPORT ): string {
		return wp_create_nonce( $action );
	}
}
