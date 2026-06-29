<?php
/**
 * Tests for helper functions in includes/helpers.php.
 *
 * @package GdprConsentAuditor\Tests\Unit
 */

namespace GdprConsentAuditor\Tests\Unit;

require_once __DIR__ . '/Unit_Test_Case.php';

/**
 * Class Helpers_Test
 *
 * @covers ::gdpr_ca_get_setting
 * @covers ::gdpr_ca_update_setting
 * @covers ::gdpr_ca_bump_consent_version
 * @covers ::gdpr_ca_get_consent_version
 * @covers ::gdpr_ca_hash_identifier
 * @covers ::gdpr_ca_sanitize_categories
 * @covers ::gdpr_ca_known_services
 * @covers ::gdpr_ca_known_categories
 * @covers ::gdpr_ca_category_to_gcm
 */
class Helpers_Test extends Unit_Test_Case {

	/**
	 * @test
	 */
	public function get_setting_returns_default_for_missing_key() {
		$this->assertNull( \gdpr_ca_get_setting( 'does_not_exist' ) );
		$this->assertSame( 'fallback', \gdpr_ca_get_setting( 'does_not_exist', 'fallback' ) );
	}

	/**
	 * @test
	 */
	public function update_setting_persists_value() {
		\gdpr_ca_update_setting( 'custom_key', 'custom_value' );
		$this->assertSame( 'custom_value', \gdpr_ca_get_setting( 'custom_key' ) );
	}

	/**
	 * @test
	 */
	public function bump_consent_version_increments() {
		$this->assertSame( '1', \gdpr_ca_get_consent_version() );
		\gdpr_ca_bump_consent_version();
		$this->assertSame( '2', \gdpr_ca_get_consent_version() );
		\gdpr_ca_bump_consent_version();
		$this->assertSame( '3', \gdpr_ca_get_consent_version() );
	}

	/**
	 * @test
	 */
	public function hash_identifier_is_sha256_and_deterministic() {
		$ip   = '203.0.113.10';
		$ua   = 'Mozilla/5.0 Test';
		$h1   = \gdpr_ca_hash_identifier( $ip, $ua );
		$h2   = \gdpr_ca_hash_identifier( $ip, $ua );

		$this->assertSame( 64, strlen( $h1 ), 'SHA-256 hex digest must be 64 chars' );
		$this->assertSame( $h1, $h2, 'Hash must be deterministic for same input + salt' );
	}

	/**
	 * @test
	 */
	public function hash_identifier_changes_with_different_input() {
		$a = \gdpr_ca_hash_identifier( '1.1.1.1', 'UA-A' );
		$b = \gdpr_ca_hash_identifier( '1.1.1.2', 'UA-A' );
		$c = \gdpr_ca_hash_identifier( '1.1.1.1', 'UA-B' );
		$this->assertNotEquals( $a, $b );
		$this->assertNotEquals( $a, $c );
	}

	/**
	 * @test
	 */
	public function sanitize_categories_filters_unknown() {
		$input  = array( 'necessary', 'bogus', 'statistics', '<script>', 'marketing' );
		$result = \gdpr_ca_sanitize_categories( $input );
		$this->assertSame( array( 'necessary', 'statistics', 'marketing' ), $result );
	}

	/**
	 * @test
	 */
	public function sanitize_categories_handles_non_array() {
		$this->assertSame( array(), \gdpr_ca_sanitize_categories( 'necessary' ) );
		$this->assertSame( array(), \gdpr_ca_sanitize_categories( null ) );
	}

	/**
	 * @test
	 */
	public function known_services_returns_expected_providers() {
		$services = \gdpr_ca_known_services();

		$this->assertArrayHasKey( 'google_analytics', $services );
		$this->assertArrayHasKey( 'google_tag_manager', $services );
		$this->assertArrayHasKey( 'meta_pixel', $services );
		$this->assertArrayHasKey( 'youtube', $services );
		$this->assertArrayHasKey( 'vimeo', $services );
		$this->assertArrayHasKey( 'google_maps', $services );
		$this->assertArrayHasKey( 'hotjar', $services );
		$this->assertArrayHasKey( 'tiktok_pixel', $services );
		$this->assertArrayHasKey( 'linkedin_insight', $services );
		$this->assertArrayHasKey( 'recaptcha', $services );

		// Each service must define all required keys.
		foreach ( $services as $key => $service ) {
			$this->assertArrayHasKey( 'name', $service, "Service {$key} missing 'name'" );
			$this->assertArrayHasKey( 'patterns', $service, "Service {$key} missing 'patterns'" );
			$this->assertArrayHasKey( 'category', $service, "Service {$key} missing 'category'" );
			$this->assertArrayHasKey( 'requires_consent', $service, "Service {$key} missing 'requires_consent'" );
			$this->assertArrayHasKey( 'risk', $service, "Service {$key} missing 'risk'" );
			$this->assertArrayHasKey( 'recommendation', $service, "Service {$key} missing 'recommendation'" );
		}
	}

	/**
	 * @test
	 */
	public function known_categories_returns_four_categories() {
		$cats = \gdpr_ca_known_categories();
		$this->assertCount( 4, $cats );
		$this->assertTrue( $cats['necessary']['always_on'] );
		$this->assertFalse( $cats['preferences']['always_on'] );
		$this->assertFalse( $cats['statistics']['always_on'] );
		$this->assertFalse( $cats['marketing']['always_on'] );
	}

	/**
	 * @test
	 */
	public function category_to_gcm_returns_expected_purpose() {
		$this->assertSame( 'security_storage',       \gdpr_ca_category_to_gcm( 'necessary' ) );
		$this->assertSame( 'functionality_storage',  \gdpr_ca_category_to_gcm( 'preferences' ) );
		$this->assertSame( 'analytics_storage',      \gdpr_ca_category_to_gcm( 'statistics' ) );
		$this->assertSame( 'ad_storage',             \gdpr_ca_category_to_gcm( 'marketing' ) );
		$this->assertSame( 'ad_storage',             \gdpr_ca_category_to_gcm( 'unknown' ) );
	}
}
