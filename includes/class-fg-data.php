<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * FG_Data
 *
 * Fetches Fear & Greed Index data from alternative.me and provides
 * cached, sanitized, and structured results for use by the plugin.
 */
class FG_Data {

	const API_ENDPOINT = 'https://api.alternative.me/fng/?limit=7';
	const TRANSIENT_KEY = 'fg_gauge_data';
	const CACHE_TTL = DAY_IN_SECONDS; // 24 hours - fallback default

	/**
	 * Get cache TTL from admin settings, with fallback
	 *
	 * @return int Cache TTL in seconds
	 */
	private static function get_cache_ttl() {
		$opts = get_option( 'fgg_settings', array() );
		$hours = isset( $opts['cache_hours'] ) ? intval( $opts['cache_hours'] ) : 24;
		return $hours * HOUR_IN_SECONDS;
	}

	/**
	 * Public entry: get structured data
	 *
	 * @return array {
	 *   @type int    current_value Current Fear & Greed (0-100)
	 *   @type float  change_24h    Percentage change from yesterday (can be negative)
	 *   @type array  weekly_data   Array of historical points (oldest->newest)
	 *   @type int    timestamp     Unix timestamp when data was fetched
	 * }
	 */
	public static function get_data() {
		// Try cached
		$cached = get_transient( self::TRANSIENT_KEY );
		if ( $cached && is_array( $cached ) ) {
			return $cached;
		}

		// Fetch fresh
		$raw = self::fetch_from_api();

		// Determine cache TTL. If we used fallback data, only cache for 5 minutes
		// to allow the system to recover quickly when the API comes back online.
		$is_fallback = ( ! $raw || ! isset( $raw['data'] ) || ! is_array( $raw['data'] ) );
		if ( $is_fallback ) {
			// Use fallback static data
			$raw = self::fallback_data();
		}

		$weekly = self::normalize_weekly( $raw['data'] );

		// Current value is the last (newest) entry
		$current = null;
		if ( ! empty( $weekly ) ) {
			$last = end( $weekly );
			$current = isset( $last['value'] ) ? intval( $last['value'] ) : null;
		}

		// Yesterday value (previous day)
		$yesterday = null;
		if ( count( $weekly ) >= 2 ) {
			$yesterday_entry = $weekly[ count( $weekly ) - 2 ];
			$yesterday = isset( $yesterday_entry['value'] ) ? intval( $yesterday_entry['value'] ) : null;
		}

		// Calculate percentage change
		$change = null;
		if ( is_int( $current ) && is_int( $yesterday ) ) {
			if ( 0 === $yesterday ) {
				$change = ( $current === 0 ) ? 0.0 : 100.0;
			} else {
				$change = round( ( ( $current - $yesterday ) / $yesterday ) * 100, 2 );
			}
		}

		$structured = array(
			'current_value' => is_int( $current ) ? $current : 0,
			'change_24h'    => is_null( $change ) ? 0.0 : floatval( $change ),
			'weekly_data'   => $weekly,
			'timestamp'     => time(),
		);

		// Cache result. Use shorter TTL for fallback data.
		$ttl = $is_fallback ? 300 : self::get_cache_ttl();
		set_transient( self::TRANSIENT_KEY, $structured, $ttl );

		return $structured;
	}

	/**
	 * Fetch raw data from API using wp_remote_get with error handling
	 *
	 * @return array|false Decoded JSON array on success, false on failure
	 */
	/**
	 * Public wrapper to fetch raw data (used for admin test)
	 * @return array|false
	 */
	public static function fetch_raw() {
		return self::fetch_from_api();
	}

	protected static function fetch_from_api() {
		// Read potential overrides from options
		$opts = get_option( 'fgg_settings', array() );
		$endpoint = isset( $opts['api_endpoint'] ) && filter_var( $opts['api_endpoint'], FILTER_VALIDATE_URL ) ? $opts['api_endpoint'] : self::API_ENDPOINT;
		$api_key = isset( $opts['api_key'] ) ? trim( $opts['api_key'] ) : '';

		$args = array(
			'timeout' => 10,
			'headers' => array(
				'Accept' => 'application/json',
				'User-Agent' => 'FGG-Plugin/1.0 (+https://github.com/phveektor)'
			),
		);

		if ( ! empty( $api_key ) ) {
			$args['headers']['Authorization'] = 'Bearer ' . $api_key;
		}
		$response = wp_remote_get( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== intval( $code ) ) {
			return false;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) ) {
			return false;
		}

		$decoded = json_decode( $body, true );
		if ( null === $decoded || ! is_array( $decoded ) ) {
			return false;
		}

		return $decoded;
	}

	/**
	 * Normalize API "data" array into consistent structure and sanitize values.
	 * Returns an array ordered oldest -> newest.
	 *
	 * @param array $data Raw data from API
	 * @return array Normalized weekly data [{timestamp:int, value:int, value_classified:string}, ...]
	 */
	protected static function normalize_weekly( $data ) {
		$out = array();

		if ( ! is_array( $data ) ) {
			return $out;
		}

		// API often returns newest-first; sort by timestamp ascending
		usort( $data, function( $a, $b ) {
			$ta = isset( $a['timestamp'] ) ? intval( $a['timestamp'] ) : 0;
			$tb = isset( $b['timestamp'] ) ? intval( $b['timestamp'] ) : 0;
			return $ta - $tb;
		} );

		foreach ( $data as $point ) {
			$ts = isset( $point['timestamp'] ) ? intval( $point['timestamp'] ) : null;
			$val = null;
			if ( isset( $point['value'] ) ) {
				// value may be string; cast and clamp
				$val = intval( $point['value'] );
				if ( $val < 0 ) {
					$val = 0;
				} elseif ( $val > 100 ) {
					$val = 100;
				}
			}

			$classified = isset( $point['value_classification'] ) ? sanitize_text_field( $point['value_classification'] ) : '';

			if ( is_null( $ts ) || is_null( $val ) ) {
				continue;
			}

			$out[] = array(
				'timestamp' => $ts,
				'value'     => $val,
				'label'     => $classified,
			);
		}

		return $out;
	}

	/**
	 * Provide fallback static data when API fails. Returns same shape as API decode.
	 *
	 * @return array
	 */
	protected static function fallback_data() {
		$now = time();
		$days = array();
		// Sample conservative values across 7 days
		$sample = array( 45, 48, 50, 52, 49, 55, 60 );

		for ( $i = 6; $i >= 0; $i-- ) {
			$ts = $now - ( $i * DAY_IN_SECONDS );
			$val = $sample[ 6 - $i ] ?? 50;
			$days[] = array(
				'timestamp' => $ts,
				'value'     => strval( $val ),
				'value_classification' => self::classify_value( $val ),
			);
		}

		return array( 'data' => $days );
	}

	/**
	 * Simple helper to classify a numeric value similar to API's labels.
	 *
	 * @param int $v
	 * @return string
	 */
	protected static function classify_value( $v ) {
		$v = intval( $v );
		if ( $v <= 24 ) {
			return 'Extreme Fear';
		} elseif ( $v <= 49 ) {
			return 'Fear';
		} elseif ( $v <= 74 ) {
			return 'Greed';
		}
		return 'Extreme Greed';
	}
}