<?php
/**
 * Tests for GdprCa_Scanner (heuristic helpers).
 *
 * The full scan relies on global $wp_scripts / $wp_styles state that
 * is hard to reproduce without a full WP environment, so the unit
 * suite focuses on the heuristic helpers exposed via scan_results.
 *
 * @package GdprConsentAuditor\Tests\Unit
 */

namespace GdprConsentAuditor\Tests\Unit;

require_once __DIR__ . '/Unit_Test_Case.php';

/**
 * Class Scanner_Test
 *
 * @covers \GdprCa_Scanner
 */
class Scanner_Test extends Unit_Test_Case {

	/**
	 * @test
	 */
	public function scan_returns_expected_top_level_keys() {
		$s = new \GdprCa_Scanner();
		$r = $s->scan();

		$this->assertArrayHasKey( 'plugins', $r );
		$this->assertArrayHasKey( 'theme', $r );
		$this->assertArrayHasKey( 'scripts', $r );
		$this->assertArrayHasKey( 'services', $r );
		$this->assertArrayHasKey( 'cookies', $r );
		$this->assertArrayHasKey( 'summary', $r );
	}

	/**
	 * @test
	 */
	public function scan_summary_contains_required_keys() {
		$r = ( new \GdprCa_Scanner() )->scan();
		$s = $r['summary'];

		$this->assertArrayHasKey( 'plugin_count', $s );
		$this->assertArrayHasKey( 'active_plugins', $s );
		$this->assertArrayHasKey( 'script_count', $s );
		$this->assertArrayHasKey( 'external_scripts', $s );
		$this->assertArrayHasKey( 'known_services', $s );
		$this->assertArrayHasKey( 'cookie_count', $s );
		$this->assertArrayHasKey( 'overall_risk', $s );
	}

	/**
	 * @test
	 */
	public function scan_summary_overall_risk_is_one_of_low_medium_high() {
		$r = ( new \GdprCa_Scanner() )->scan();
		$this->assertContains( $r['summary']['overall_risk'], array( 'low', 'medium', 'high' ) );
	}

	/**
	 * @test
	 */
	public function scan_cookies_safely_handles_empty_cookie_superglobal() {
		$_COOKIE = array();
		$r = ( new \GdprCa_Scanner() )->scan();
		$this->assertSame( 0, $r['summary']['cookie_count'] );
	}

	/**
	 * @test
	 */
	public function scan_cookies_picks_up_necessary_cookie() {
		$_COOKIE = array(
			'wordpress_logged_in_xxx' => 'admin|1234',
			'_ga'                     => 'GA1.2.X',
		);
		$r = ( new \GdprCa_Scanner() )->scan();
		$this->assertSame( 2, $r['summary']['cookie_count'] );

		// Find each cookie by name.
		$by_name = array();
		foreach ( $r['cookies'] as $c ) {
			$by_name[ $c['name'] ] = $c;
		}
		$this->assertArrayHasKey( 'wordpress_logged_in_xxx', $by_name );
		$this->assertSame( 'necessary', $by_name['wordpress_logged_in_xxx']['category'] );
		$this->assertFalse( $by_name['wordpress_logged_in_xxx']['requires_consent'] );

		$this->assertArrayHasKey( '_ga', $by_name );
		$this->assertSame( 'statistics', $by_name['_ga']['category'] );
		$this->assertTrue( $by_name['_ga']['requires_consent'] );
	}

	/**
	 * @test
	 */
	public function scan_cookies_marketing_cookie_has_high_risk() {
		$_COOKIE = array( '_fbp' => 'fb.1.1234567890.1234567890' );
		$r = ( new \GdprCa_Scanner() )->scan();
		$this->assertSame( 'marketing', $r['cookies'][0]['category'] );
		$this->assertSame( 'high', $r['cookies'][0]['risk'] );
	}
}
