<?php
/**
 * Tests for GdprCa_Report_Generator (HTML / TXT / CSV / JSON output).
 *
 * @package GdprConsentAuditor\Tests\Unit
 */

namespace GdprConsentAuditor\Tests\Unit;

require_once __DIR__ . '/Unit_Test_Case.php';

/**
 * Class Report_Generator_Test
 *
 * @covers \GdprCa_Report_Generator
 */
class Report_Generator_Test extends Unit_Test_Case {

	/**
	 * Sample scan results reused by every test.
	 */
	private function sample_scan() {
		return array(
			'plugins'  => array(
				array(
					'name'             => 'Google Analytics for WP',
					'version'          => '8.0.0',
					'source'           => 'google-analytics-for-wordpress/google-analytics-for-wordpress.php',
					'type'             => 'plugin',
					'active'           => true,
					'category'         => 'statistics',
					'requires_consent' => true,
					'risk'             => 'medium',
					'recommendation'   => 'Statistics plugin.',
				),
			),
			'theme'    => array(
				'name'             => 'Twenty Twenty-Four',
				'version'          => '1.0',
				'source'           => 'twentytwentyfour',
				'type'             => 'theme',
				'requires_consent' => false,
				'risk'             => 'low',
				'recommendation'   => 'Local assets only.',
			),
			'scripts'  => array(
				array(
					'handle'           => 'gtag',
					'source'           => 'https://www.googletagmanager.com/gtag/js?id=G-XXX',
					'type'             => 'script',
					'external'         => true,
					'category'         => 'statistics',
					'requires_consent' => true,
					'risk'             => 'medium',
					'recommendation'   => 'Block until Statistics consent.',
					'matched_service'  => 'Google Tag Manager',
				),
			),
			'services' => array(
				array(
					'key'              => 'google_analytics',
					'name'             => 'Google Analytics',
					'category'         => 'statistics',
					'requires_consent' => true,
					'risk'             => 'medium',
					'recommendation'   => 'Use Consent Mode v2.',
					'type'             => 'service',
					'source'           => '/google\-analytics/',
				),
			),
			'cookies'  => array(
				array(
					'name'             => '_ga',
					'value_preview'    => 'GA1.2.X',
					'category'         => 'statistics',
					'requires_consent' => true,
					'risk'             => 'medium',
					'recommendation'   => 'Block before consent.',
				),
			),
			'summary'  => array(
				'plugin_count'     => 1,
				'active_plugins'   => 1,
				'script_count'     => 1,
				'external_scripts' => 1,
				'known_services'   => 1,
				'cookie_count'     => 1,
				'overall_risk'     => 'medium',
			),
		);
	}

	/**
	 * @test
	 */
	public function build_returns_expected_shape() {
		$report = ( new \GdprCa_Report_Generator() )->build( $this->sample_scan() );

		$this->assertArrayHasKey( 'title', $report );
		$this->assertArrayHasKey( 'generated_at', $report );
		$this->assertArrayHasKey( 'site_url', $report );
		$this->assertArrayHasKey( 'overall_risk', $report );
		$this->assertArrayHasKey( 'html', $report );
		$this->assertArrayHasKey( 'summary', $report );
		$this->assertArrayHasKey( 'items', $report );
	}

	/**
	 * @test
	 */
	public function build_html_contains_disclaimer_and_findings() {
		$html = ( new \GdprCa_Report_Generator() )->build( $this->sample_scan() )['html'];

		$this->assertStringContainsString( 'technical assistive report', $html );
		$this->assertStringContainsString( 'Google Analytics', $html );
		$this->assertStringContainsString( 'Summary', $html );
		$this->assertStringContainsString( 'Detailed findings', $html );
		$this->assertStringContainsString( 'gdpr-ca-risk-medium', $html );
	}

	/**
	 * @test
	 */
	public function build_text_is_plain_text_with_sections() {
		$txt = ( new \GdprCa_Report_Generator() )->build_text( $this->sample_scan() );

		$this->assertStringContainsString( 'GDPR Consent Auditor', $txt );
		$this->assertStringContainsString( 'DISCLAIMER', $txt );
		$this->assertStringContainsString( 'FINDINGS', $txt );
		$this->assertStringContainsString( 'Google Analytics', $txt );
		$this->assertStringContainsString( 'Statistics', $txt );
	}

	/**
	 * @test
	 */
	public function build_csv_is_valid_csv_with_header_row() {
		$csv = ( new \GdprCa_Report_Generator() )->build_csv( $this->sample_scan() );

		$lines = explode( "\n", trim( $csv ) );
		$this->assertGreaterThanOrEqual( 2, count( $lines ), 'CSV must have header + at least 1 data row' );

		// Header row.
		$header = str_getcsv( $lines[0] );
		$this->assertContains( 'section', $header );
		$this->assertContains( 'name', $header );
		$this->assertContains( 'category', $header );
		$this->assertContains( 'requires_consent', $header );
		$this->assertContains( 'risk', $header );

		// First data row must reference GA plugin.
		$row = str_getcsv( $lines[1] );
		$this->assertContains( 'plugins', $row );
		$this->assertContains( 'Google Analytics for WP', $row );

		// Must reference Meta Pixel? No — sample has none. Just check at least one service row exists.
		$found_service_row = false;
		foreach ( $lines as $line ) {
			$r = str_getcsv( $line );
			if ( in_array( 'services', $r, true ) ) {
				$found_service_row = true;
				break;
			}
		}
		$this->assertTrue( $found_service_row, 'CSV must include services rows' );
	}

	/**
	 * @test
	 */
	public function build_csv_escapes_commas_and_quotes() {
		// Inject a name containing a comma.
		$scan = $this->sample_scan();
		$scan['plugins'][0]['name'] = 'Plugin, With Comma';

		$csv = ( new \GdprCa_Report_Generator() )->build_csv( $scan );
		$this->assertStringContainsString( '"Plugin, With Comma"', $csv );
	}

	/**
	 * @test
	 */
	public function build_json_is_valid_json_with_items_array() {
		$json = ( new \GdprCa_Report_Generator() )->build_json( $this->sample_scan() );

		$decoded = json_decode( $json, true );
		$this->assertIsArray( $decoded );
		$this->assertArrayHasKey( 'title', $decoded );
		$this->assertArrayHasKey( 'items', $decoded );
		$this->assertArrayHasKey( 'summary', $decoded );
		$this->assertGreaterThan( 0, count( $decoded['items'] ) );

		// Each item must have the expected fields.
		foreach ( $decoded['items'] as $item ) {
			$this->assertArrayHasKey( 'section', $item );
			$this->assertArrayHasKey( 'name', $item );
			$this->assertArrayHasKey( 'category', $item );
			$this->assertArrayHasKey( 'risk', $item );
			$this->assertArrayHasKey( 'requires_consent', $item );
		}
	}

	/**
	 * @test
	 */
	public function build_with_empty_scan_returns_no_findings_message() {
		$html = ( new \GdprCa_Report_Generator() )->build( array() )['html'];
		$this->assertStringContainsString( 'No items detected', $html );
	}

	/**
	 * @test
	 */
	public function build_csv_with_empty_scan_returns_only_header() {
		$csv = ( new \GdprCa_Report_Generator() )->build_csv( array() );
		$lines = explode( "\n", trim( $csv ) );
		$this->assertCount( 1, $lines, 'Empty scan must produce CSV with only the header row' );
	}

	/**
	 * @test
	 */
	public function build_json_with_empty_scan_returns_empty_items() {
		$json = ( new \GdprCa_Report_Generator() )->build_json( array() );
		$decoded = json_decode( $json, true );
		$this->assertSame( array(), $decoded['items'] );
	}
}
