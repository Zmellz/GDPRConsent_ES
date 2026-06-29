<?php
/**
 * Tests for GdprCa_Legal_Templates.
 *
 * @package GdprConsentAuditor\Tests\Unit
 */

namespace GdprConsentAuditor\Tests\Unit;

require_once __DIR__ . '/Unit_Test_Case.php';

/**
 * Class Legal_Templates_Test
 *
 * @covers \GdprCa_Legal_Templates
 */
class Legal_Templates_Test extends Unit_Test_Case {

	/**
	 * @test
	 */
	public function defaults_returns_four_templates() {
		$d = \GdprCa_Legal_Templates::defaults();
		$this->assertCount( 4, $d );
		$this->assertArrayHasKey( 'cookie_policy', $d );
		$this->assertArrayHasKey( 'privacy_notice', $d );
		$this->assertArrayHasKey( 'category_descriptions', $d );
		$this->assertArrayHasKey( 'banner_text', $d );
	}

	/**
	 * @test
	 */
	public function each_default_template_contains_legal_disclaimer() {
		$d = \GdprCa_Legal_Templates::defaults();
		foreach ( $d as $key => $content ) {
			$this->assertStringContainsString(
				'orientative',
				strtolower( $content ),
				"Template {$key} must contain the 'orientative' disclaimer keyword"
			);
		}
	}

	/**
	 * @test
	 */
	public function get_returns_default_when_setting_is_empty() {
		$content = \GdprCa_Legal_Templates::get( 'cookie_policy' );
		$this->assertStringContainsString( 'Cookie Policy', $content );
	}

	/**
	 * @test
	 */
	public function get_returns_stored_setting_when_present() {
		\gdpr_ca_update_setting( 'legal_cookie_policy_text', 'My custom cookie policy.' );
		$content = \GdprCa_Legal_Templates::get( 'cookie_policy' );
		$this->assertSame( 'My custom cookie policy.', $content );
	}

	/**
	 * @test
	 */
	public function interpolate_replaces_known_placeholders() {
		$out = \GdprCa_Legal_Templates::interpolate(
			'Site: {site_name} | URL: {site_url} | Email: {contact_email} | Date: {date}'
		);
		$this->assertStringContainsString( 'Site: Test Site', $out );
		$this->assertStringContainsString( 'URL: https://example.test', $out );
		$this->assertStringContainsString( 'Email: admin@example.test', $out );
		$this->assertStringNotContainsString( '{date}', $out );
	}

	/**
	 * @test
	 */
	public function disclaimer_html_contains_warning_phrase() {
		$html = \GdprCa_Legal_Templates::disclaimer_html();
		$this->assertStringContainsString( 'orientative', strtolower( $html ) );
		$this->assertStringContainsString( 'qualified legal professional', strtolower( $html ) );
	}

	/**
	 * @test
	 */
	public function template_list_returns_expected_labels() {
		$list = \GdprCa_Legal_Templates::template_list();
		$this->assertCount( 4, $list );
		$this->assertArrayHasKey( 'cookie_policy', $list );
		$this->assertArrayHasKey( 'privacy_notice', $list );
		$this->assertArrayHasKey( 'category_descriptions', $list );
		$this->assertArrayHasKey( 'banner_text', $list );
	}
}
