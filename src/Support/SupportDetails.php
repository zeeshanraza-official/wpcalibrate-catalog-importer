<?php
namespace WPCalibrate\CatalogImporter\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Encapsulates official WPCalibrate support and contact metadata.
 */
class SupportDetails {

	public const AUTHOR_NAME    = 'WPCalibrate';
	public const AUTHOR_EMAIL   = 'support@wpcalibrate.com';
	public const AUTHOR_WEBSITE = 'https://wpcalibrate.com';
	public const MARKETPLACE    = 'https://marketplace.wpcalibrate.com/';
	public const PHONE_WHATSAPP = '+447474795976';

	/**
	 * Get formatted support array.
	 *
	 * @return array<string, string>
	 */
	public static function get_info(): array {
		return [
			'author'      => self::AUTHOR_NAME,
			'email'       => self::AUTHOR_EMAIL,
			'website'     => self::AUTHOR_WEBSITE,
			'marketplace' => self::MARKETPLACE,
			'phone'       => self::PHONE_WHATSAPP,
		];
	}
}
