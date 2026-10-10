<?php
namespace WPCalibrate\CatalogImporter\Logging;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dedicated logger abstraction with wc_get_logger integration and secret redaction.
 */
class PluginLogger {

	public const LOG_SOURCE = 'wpcalibrate-catalog-importer';

	/**
	 * Log info level.
	 *
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	public static function info( string $message, array $context = [] ): void {
		self::log( 'info', $message, $context );
	}

	/**
	 * Log warning level.
	 *
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	public static function warning( string $message, array $context = [] ): void {
		self::log( 'warning', $message, $context );
	}

	/**
	 * Log error level.
	 *
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	public static function error( string $message, array $context = [] ): void {
		self::log( 'error', $message, $context );
	}

	/**
	 * Internal log dispatcher.
	 *
	 * @param string $level
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	protected static function log( string $level, string $message, array $context = [] ): void {
		// Redact sensitive patterns if accidentally present in context.
		$sanitized_context = self::redact_secrets( $context );

		if ( function_exists( 'wc_get_logger' ) ) {
			$wc_logger = wc_get_logger();
			if ( method_exists( $wc_logger, $level ) ) {
				$wc_logger->$level( $message, array_merge( [ 'source' => self::LOG_SOURCE ], $sanitized_context ) );
				return;
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( sprintf( '[%s] [WPCalibrate Catalog Importer] %s %s', strtoupper( $level ), $message, wp_json_encode( $sanitized_context ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Redact any keys containing passwords, tokens, license keys, secrets.
	 *
	 * @param array $data
	 * @return array
	 */
	public static function redact_secrets( array $data ): array {
		$sensitive_keys = [ 'password', 'pass', 'secret', 'token', 'license', 'key', 'auth', 'authorization' ];

		foreach ( $data as $k => $v ) {
			if ( is_string( $k ) ) {
				$lower = strtolower( $k );
				foreach ( $sensitive_keys as $pattern ) {
					if ( str_contains( $lower, $pattern ) ) {
						$data[ $k ] = '***REDACTED***';
						continue 2;
					}
				}
			}

			if ( is_array( $v ) ) {
				$data[ $k ] = self::redact_secrets( $v );
			}
		}

		return $data;
	}
}
