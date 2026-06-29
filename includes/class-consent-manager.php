<?php
/**
 * Consent Manager: records consent choices, decides what categories
 * are active for the current visitor, and exposes helpers for the
 * REST endpoint and the front-end banner.
 *
 * @package GdprConsentAuditor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GdprCa_Consent_Manager
 */
class GdprCa_Consent_Manager {

	/**
	 * Record a consent action.
	 *
	 * @param string $action     accept_all|reject_all|custom|revoke.
	 * @param array  $categories Categories accepted (only used when action=custom).
	 * @return true|WP_Error
	 */
	public function record_consent( $action, $categories = array() ) {
		$valid = array( 'accept_all', 'reject_all', 'custom', 'revoke' );
		if ( ! in_array( $action, $valid, true ) ) {
			return new WP_Error( 'gdpr_ca_invalid_action', __( 'Acción de consentimiento no válida.', 'gdpr-consent-auditor' ) );
		}

		// Normalize categories based on action.
		$active = $this->get_active_categories_for_action( $action, $categories );

		// Set the cookie first so it is available immediately.
		$this->set_consent_cookie( $active, $action );

		// Optionally log to DB.
		if ( gdpr_ca_get_setting( 'log_consents', 1 ) ) {
			$this->insert_log_row( $action, $active );
		}

		/**
		 * Fires after a consent is recorded. Third-party integrations
		 * (Google Consent Mode, server-side forwarding, etc.) hook here.
		 *
		 * @param string $action  Consent action.
		 * @param array  $active  Active categories.
		 */
		do_action( 'gdpr_ca_consent_recorded', $action, $active );

		return true;
	}

	/**
	 * Get the categories that are active for a given action.
	 *
	 * @param string $action     Consent action.
	 * @param array  $categories Explicit categories (for action=custom).
	 * @return array
	 */
	public function get_active_categories_for_action( $action, $categories = array() ) {
		$all = array_keys( gdpr_ca_known_categories() );

		switch ( $action ) {
			case 'accept_all':
				return $all;
			case 'reject_all':
				return array( 'necessary' );
			case 'revoke':
				return array( 'necessary' );
			case 'custom':
			default:
				// Always include 'necessary'.
				if ( ! in_array( 'necessary', $categories, true ) ) {
					$categories[] = 'necessary';
				}
				return array_values( array_intersect( $all, $categories ) );
		}
	}

	/**
	 * Set the consent cookie. Always include the consent version
	 * so a config change invalidates prior consent.
	 *
	 * @param array  $active  Active categories.
	 * @param string $action  Action that produced this state.
	 * @return void
	 */
	private function set_consent_cookie( $active, $action ) {
		$payload = array(
			'v'    => gdpr_ca_get_consent_version(),
			'a'    => $action,
			'c'    => array_values( $active ),
			'ts'   => time(),
		);

		$value    = wp_json_encode( $payload );
		$encoded  = base64_encode( $value ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

		$secure   = is_ssl();
		$http_only = false; // Banner JS needs to read it.

		/**
		 * Filter the cookie lifetime (seconds).
		 *
		 * @param int $lifetime Cookie lifetime.
		 */
		$lifetime = (int) apply_filters( 'gdpr_ca_cookie_lifetime', 6 * MONTH_IN_SECONDS );

		setcookie(
			GDPR_CA_CONSENT_COOKIE_NAME,
			$encoded,
			array(
				'expires'  => time() + $lifetime,
				'path'     => COOKIEPATH,
				'domain'   => COOKIE_DOMAIN,
				'secure'   => $secure,
				'httponly' => $http_only,
				'samesite' => 'Lax',
			)
		);

		// Mirror into $_COOKIE for same-request reads.
		$_COOKIE[ GDPR_CA_CONSENT_COOKIE_NAME ] = $encoded;
	}

	/**
	 * Insert a row into the consent log table.
	 *
	 * @param string $action Action.
	 * @param array  $active Active categories.
	 * @return void
	 */
	private function insert_log_row( $action, $active ) {
		global $wpdb;
		$table = $wpdb->prefix . 'gdpr_ca_consents';

		$ip = gdpr_ca_get_client_ip();
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		$hash_ip = (bool) gdpr_ca_get_setting( 'hash_ip', 1 );
		$identifier = $hash_ip ? gdpr_ca_hash_identifier( $ip, $ua ) : $ip;

		$wpdb->insert(
			$table,
			array(
				'consent_date'     => current_time( 'mysql' ),
				'consent_hash'     => $identifier,
				'user_id'          => get_current_user_id(),
				'user_agent'       => gdpr_ca_truncate_text( $ua, 255 ),
				'action'           => $action,
				'categories'       => wp_json_encode( $active ),
				'consent_version'  => gdpr_ca_get_consent_version(),
			),
			array( '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Read the current visitor's consent state from the cookie.
	 *
	 * @return array {
	 *     @type bool   $has_consent
	 *     @type string $action
	 *     @type array  $categories
	 *     @type int    $ts
	 *     @type string $version
	 * }
	 */
	public function get_current_consent() {
		$default = array(
			'has_consent' => false,
			'action'      => '',
			'categories'  => array( 'necessary' ),
			'ts'          => 0,
			'version'     => '',
		);

		if ( empty( $_COOKIE[ GDPR_CA_CONSENT_COOKIE_NAME ] ) ) {
			return $default;
		}

		$raw = sanitize_text_field( wp_unslash( $_COOKIE[ GDPR_CA_CONSENT_COOKIE_NAME ] ) );
		$decoded = base64_decode( $raw, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $decoded ) {
			return $default;
		}

		$data = json_decode( $decoded, true );
		if ( ! is_array( $data ) ) {
			return $default;
		}

		// If the stored version differs from current, the consent is stale.
		$current_version = gdpr_ca_get_consent_version();
		if ( ! isset( $data['v'] ) || (string) $data['v'] !== (string) $current_version ) {
			return $default;
		}

		$action = isset( $data['a'] ) ? sanitize_key( $data['a'] ) : '';
		if ( ! in_array( $action, array( 'accept_all', 'reject_all', 'custom', 'revoke' ), true ) ) {
			return $default;
		}

		$categories = isset( $data['c'] ) && is_array( $data['c'] ) ? gdpr_ca_sanitize_categories( $data['c'] ) : array();
		if ( ! in_array( 'necessary', $categories, true ) ) {
			$categories[] = 'necessary';
		}

		return array(
			'has_consent' => true,
			'action'      => $action,
			'categories'  => array_values( array_unique( $categories ) ),
			'ts'          => isset( $data['ts'] ) ? (int) $data['ts'] : 0,
			'version'     => isset( $data['v'] ) ? (string) $data['v'] : '',
		);
	}

	/**
	 * Is a given category currently allowed for this visitor?
	 *
	 * @param string $category Category slug.
	 * @return bool
	 */
	public function is_category_allowed( $category ) {
		$current = $this->get_current_consent();
		return in_array( $category, $current['categories'], true );
	}

	/**
	 * Retrieve paginated consent logs.
	 *
	 * @param int $page    Page number (1-based).
	 * @param int $per_page Items per page.
	 * @return array {
	 *     @type array $items
	 *     @type int   $total
	 * }
	 */
	public function get_logs( $page = 1, $per_page = 50 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'gdpr_ca_consents';

		$page     = max( 1, (int) $page );
		$per_page = max( 1, (int) $per_page );
		$offset   = ( $page - 1 ) * $per_page;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$table} ORDER BY consent_date DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			)
		);

		return array(
			'items' => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Delete consent logs older than the retention window.
	 *
	 * @return int Number of rows deleted.
	 */
	public function purge_expired_logs() {
		global $wpdb;
		$table     = $wpdb->prefix . 'gdpr_ca_consents';
		$days      = (int) gdpr_ca_get_setting( 'log_retention_days', 365 );
		$days      = max( 1, $days );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$deleted = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"DELETE FROM {$table} WHERE consent_date < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);

		return (int) $deleted;
	}
}
