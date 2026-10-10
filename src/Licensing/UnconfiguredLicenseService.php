<?php
namespace WPCalibrate\CatalogImporter\Licensing;

use WPCalibrate\CatalogImporter\Licensing\Contracts\LicenseServiceInterface;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unconfigured implementation for development release.
 * Strictly avoids fake activations or invented endpoints.
 */
class UnconfiguredLicenseService implements LicenseServiceInterface {

	public const OPTION_KEY = 'wpcalibrate_catalog_importer_license_key';

	/**
	 * {@inheritDoc}
	 */
	public function get_masked_key(): string {
		$raw = (string) get_option( self::OPTION_KEY, '' );
		if ( empty( $raw ) ) {
			return '';
		}

		$len = strlen( $raw );
		if ( $len <= 4 ) {
			return str_repeat( '•', $len );
		}

		$last4 = substr( $raw, -4 );
		return str_repeat( '•', min( 24, $len - 4 ) ) . '-' . $last4;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_status(): array {
		$key = (string) get_option( self::OPTION_KEY, '' );

		if ( empty( $key ) ) {
			return [
				'status'      => 'unconfigured',
				'is_active'   => false,
				'message'     => __( 'No license key entered.', 'wpcalibrate-catalog-importer' ),
				'expiry'      => null,
				'renewal_url' => 'https://marketplace.wpcalibrate.com/',
			];
		}

		// Explicitly declare that licensing API is unconfigured in development build.
		return [
			'status'      => 'api_unconfigured',
			'is_active'   => false,
			'message'     => __( 'WPCalibrate Marketplace Licensing API endpoint is not yet configured for this build.', 'wpcalibrate-catalog-importer' ),
			'expiry'      => null,
			'renewal_url' => 'https://marketplace.wpcalibrate.com/',
		];
	}

	/**
	 * {@inheritDoc}
	 */
	public function activate( string $license_key ): array|WP_Error {
		$clean_key = sanitize_text_field( trim( $license_key ) );
		if ( empty( $clean_key ) ) {
			return new WP_Error( 'empty_key', __( 'Please provide a valid license key.', 'wpcalibrate-catalog-importer' ) );
		}

		// Save the key safely in options.
		update_option( self::OPTION_KEY, $clean_key );

		// Return an honest error explaining the API endpoint is awaiting production configuration.
		return new WP_Error(
			'licensing_api_unconfigured',
			__( 'License key saved. Note: The remote WPCalibrate Licensing validation service is pending server configuration. The plugin will continue to function fully in local development mode.', 'wpcalibrate-catalog-importer' )
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function deactivate(): array|WP_Error {
		delete_option( self::OPTION_KEY );
		return [
			'status'    => 'deactivated',
			'is_active' => false,
			'message'   => __( 'License deactivated and local key removed.', 'wpcalibrate-catalog-importer' ),
		];
	}
}
