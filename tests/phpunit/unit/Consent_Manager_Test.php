<?php
/**
 * Tests for GdprCa_Consent_Manager.
 *
 * @package GdprConsentAuditor\Tests\Unit
 */

namespace GdprConsentAuditor\Tests\Unit;

require_once __DIR__ . '/Unit_Test_Case.php';

/**
 * Class Consent_Manager_Test
 *
 * @covers \GdprCa_Consent_Manager
 */
class Consent_Manager_Test extends Unit_Test_Case {

	/**
	 * @test
	 */
	public function accept_all_returns_all_categories() {
		$m = new \GdprCa_Consent_Manager();
		$active = $m->get_active_categories_for_action( 'accept_all' );
		$this->assertSame( array( 'necessary', 'preferences', 'statistics', 'marketing' ), $active );
	}

	/**
	 * @test
	 */
	public function reject_all_returns_only_necessary() {
		$m = new \GdprCa_Consent_Manager();
		$active = $m->get_active_categories_for_action( 'reject_all' );
		$this->assertSame( array( 'necessary' ), $active );
	}

	/**
	 * @test
	 */
	public function revoke_returns_only_necessary() {
		$m = new \GdprCa_Consent_Manager();
		$active = $m->get_active_categories_for_action( 'revoke' );
		$this->assertSame( array( 'necessary' ), $active );
	}

	/**
	 * @test
	 */
	public function custom_always_includes_necessary() {
		$m = new \GdprCa_Consent_Manager();
		$active = $m->get_active_categories_for_action( 'custom', array( 'statistics' ) );
		$this->assertContains( 'necessary', $active );
		$this->assertContains( 'statistics', $active );
		$this->assertNotContains( 'marketing', $active );
	}

	/**
	 * @test
	 */
	public function custom_filters_unknown_categories() {
		$m = new \GdprCa_Consent_Manager();
		$active = $m->get_active_categories_for_action( 'custom', array( 'bogus', 'necessary', 'preferences' ) );
		$this->assertSame( array( 'necessary', 'preferences' ), $active );
	}

	/**
	 * @test
	 */
	public function record_consent_returns_error_for_invalid_action() {
		$m = new \GdprCa_Consent_Manager();
		$result = $m->record_consent( 'totally_invalid_action' );
		$this->assertWPError( $result );
	}

	/**
	 * @test
	 */
	public function record_consent_sets_cookie() {
		$m = new \GdprCa_Consent_Manager();
		$m->record_consent( 'accept_all' );

		$cookie = $this->last_cookie( GDPR_CA_CONSENT_COOKIE_NAME );
		$this->assertNotNull( $cookie, 'Cookie gdpr_ca_consent must be set' );

		// Decode the payload.
		$decoded = base64_decode( $cookie['value'], true );
		$this->assertNotFalse( $decoded );
		$payload = json_decode( $decoded, true );
		$this->assertIsArray( $payload );
		$this->assertSame( '1', $payload['v'] );
		$this->assertSame( 'accept_all', $payload['a'] );
		$this->assertContains( 'necessary', $payload['c'] );
		$this->assertContains( 'marketing', $payload['c'] );
	}

	/**
	 * @test
	 */
	public function record_consent_fires_action() {
		$m = new \GdprCa_Consent_Manager();
		$m->record_consent( 'reject_all' );

		$fired = $this->fired_actions( 'gdpr_ca_consent_recorded' );
		$this->assertCount( 1, $fired, 'gdpr_ca_consent_recorded action must fire once' );
	}

	/**
	 * @test
	 */
	public function get_current_consent_returns_default_when_no_cookie() {
		unset( $_COOKIE[ GDPR_CA_CONSENT_COOKIE_NAME ] );
		$m = new \GdprCa_Consent_Manager();
		$c = $m->get_current_consent();
		$this->assertFalse( $c['has_consent'] );
		$this->assertSame( array( 'necessary' ), $c['categories'] );
	}

	/**
	 * @test
	 */
	public function get_current_consent_reads_cookie_and_validates_version() {
		$m = new \GdprCa_Consent_Manager();
		$m->record_consent( 'accept_all' );

		// Re-read — cookie is in $_COOKIE.
		$cookie_value = $GLOBALS['__gdpr_ca_cookies_set'][ GDPR_CA_CONSENT_COOKIE_NAME ]['value'];
		$_COOKIE[ GDPR_CA_CONSENT_COOKIE_NAME ] = $cookie_value;

		$c = $m->get_current_consent();
		$this->assertTrue( $c['has_consent'] );
		$this->assertSame( 'accept_all', $c['action'] );
		$this->assertContains( 'marketing', $c['categories'] );
	}

	/**
	 * @test
	 */
	public function get_current_consent_invalidates_stale_version() {
		$m = new \GdprCa_Consent_Manager();
		$m->record_consent( 'accept_all' );

		// Bump version — consent should now be considered stale.
		\gdpr_ca_bump_consent_version();

		$cookie_value = $GLOBALS['__gdpr_ca_cookies_set'][ GDPR_CA_CONSENT_COOKIE_NAME ]['value'];
		$_COOKIE[ GDPR_CA_CONSENT_COOKIE_NAME ] = $cookie_value;

		$c = $m->get_current_consent();
		$this->assertFalse( $c['has_consent'], 'A version mismatch must invalidate prior consent' );
	}

	/**
	 * @test
	 */
	public function is_category_allowed_uses_current_consent_state() {
		$m = new \GdprCa_Consent_Manager();
		$m->record_consent( 'reject_all' );

		$_COOKIE[ GDPR_CA_CONSENT_COOKIE_NAME ] = $GLOBALS['__gdpr_ca_cookies_set'][ GDPR_CA_CONSENT_COOKIE_NAME ]['value'];

		$this->assertTrue( $m->is_category_allowed( 'necessary' ) );
		$this->assertFalse( $m->is_category_allowed( 'marketing' ) );
		$this->assertFalse( $m->is_category_allowed( 'statistics' ) );
	}

	/**
	 * Compatibility shim: assertWPError was introduced in older PHPUnit WP
	 * bindings. Here we do a manual check.
	 *
	 * @param mixed $v
	 */
	public static function assertWPError( $v, string $message = '' ): void {
		self::assertInstanceOf( \WP_Error::class, $v, $message );
	}
}

/**
 * Minimal WP_Error stub for tests.
 */
class WP_Error {
	private $code;
	private $message;
	public function __construct( $code = '', $message = '' ) {
		$this->code    = $code;
		$this->message = $message;
	}
	public function get_error_message() { return $this->message; }
	public function get_error_code()    { return $this->code; }
}

/**
 * is_wp_error polyfill — needed by Consent_Manager::record_consent return type checks.
 */
function is_wp_error( $v ) {
	return $v instanceof WP_Error;
}
