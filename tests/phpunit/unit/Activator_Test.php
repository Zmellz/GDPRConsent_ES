<?php
/**
 * Tests for GdprCa_Activator.
 *
 * @package GdprConsentAuditor\Tests\Unit
 */

namespace GdprConsentAuditor\Tests\Unit;

require_once __DIR__ . '/Unit_Test_Case.php';

/**
 * Class Activator_Test
 *
 * @covers \GdprCa_Activator
 */
class Activator_Test extends Unit_Test_Case {

	/**
	 * @test
	 */
	public function default_settings_returns_array_with_required_keys() {
		$defaults = \GdprCa_Activator::default_settings();

		$this->assertIsArray( $defaults );
		$this->assertArrayHasKey( 'banner_enabled', $defaults );
		$this->assertArrayHasKey( 'categories', $defaults );
		$this->assertArrayHasKey( 'manual_blocks', $defaults );
		$this->assertArrayHasKey( 'log_consents', $defaults );
		$this->assertArrayHasKey( 'hash_ip', $defaults );
		$this->assertArrayHasKey( 'block_scripts', $defaults );
		$this->assertArrayHasKey( 'block_iframes', $defaults );
		$this->assertArrayHasKey( 'gcm_v2_enabled', $defaults );
	}

	/**
	 * @test
	 */
	public function default_settings_have_privacy_by_design_values() {
		$defaults = \GdprCa_Activator::default_settings();

		// Privacy by design defaults.
		$this->assertSame( 1, $defaults['hash_ip'], 'IP hashing must default to ON' );
		$this->assertSame( 1, $defaults['log_consents'], 'Consent logging must default to ON' );
		$this->assertSame( 1, $defaults['block_scripts'], 'Script blocking must default to ON' );
		$this->assertSame( 1, $defaults['block_iframes'], 'Iframe blocking must default to ON' );

		// GCM v2 default consent states must be 'denied' by default.
		$this->assertSame( 'denied', $defaults['gcm_v2_default_ads'] );
		$this->assertSame( 'denied', $defaults['gcm_v2_default_analytics'] );
		$this->assertSame( 'denied', $defaults['gcm_v2_default_functional'] );
		$this->assertSame( 'denied', $defaults['gcm_v2_default_personalized_ads'] );
	}

	/**
	 * @test
	 */
	public function default_settings_include_four_known_categories() {
		$defaults = \GdprCa_Activator::default_settings();
		$cats     = $defaults['categories'];

		$this->assertArrayHasKey( 'necessary', $cats );
		$this->assertArrayHasKey( 'preferences', $cats );
		$this->assertArrayHasKey( 'statistics', $cats );
		$this->assertArrayHasKey( 'marketing', $cats );

		// Necessary is always_on by default.
		$this->assertSame( 1, $cats['necessary']['always_on'] );
		// Other categories are opt-in.
		$this->assertSame( 0, $cats['preferences']['always_on'] );
		$this->assertSame( 0, $cats['statistics']['always_on'] );
		$this->assertSame( 0, $cats['marketing']['always_on'] );
	}

	/**
	 * @test
	 */
	public function activate_stores_settings_and_version() {
		\GdprCa_Activator::activate();

		$settings = \get_option( GDPR_CA_OPTION_NAME );
		$this->assertIsArray( $settings );
		$this->assertSame( 1, $settings['banner_enabled'] );

		$version = \get_option( GDPR_CA_CONSENT_VERSION_OPTION );
		$this->assertSame( '1', $version );

		$db_version = \get_option( 'gdpr_ca_db_version' );
		$this->assertSame( GDPR_CA_DB_VERSION, $db_version );
	}
}
