<?php
/**
 * Tests for GdprCa_Classifier.
 *
 * @package GdprConsentAuditor\Tests\Unit
 */

namespace GdprConsentAuditor\Tests\Unit;

require_once __DIR__ . '/Unit_Test_Case.php';

/**
 * Class Classifier_Test
 *
 * @covers \GdprCa_Classifier
 */
class Classifier_Test extends Unit_Test_Case {

	/**
	 * @test
	 */
	public function classify_returns_same_shape_with_classification_key() {
		$scan = array(
			'plugins'  => array(
				array( 'name' => 'Yoast SEO', 'category' => 'necessary', 'requires_consent' => false, 'risk' => 'low' ),
			),
			'scripts'  => array(),
			'services' => array(),
			'cookies'  => array(),
			'theme'    => array( 'name' => 'Twenty Twenty-Four' ),
			'summary'  => array(),
		);

		$c = new \GdprCa_Classifier();
		$out = $c->classify( $scan );

		$this->assertArrayHasKey( 'classified_at', $out );
		$this->assertArrayHasKey( 'classification', $out['plugins'][0] );
		$this->assertArrayHasKey( 'classification', $out['theme'] );
	}

	/**
	 * @test
	 */
	public function necessary_items_never_require_consent() {
		$scan = array(
			'plugins' => array(
				array( 'name' => 'Security', 'category' => 'necessary', 'requires_consent' => true, 'risk' => 'low' ),
			),
		);

		$out = ( new \GdprCa_Classifier() )->classify( $scan );
		$this->assertFalse( $out['plugins'][0]['classification']['final_requires_consent'] );
	}

	/**
	 * @test
	 */
	public function marketing_items_keep_consent_required() {
		$scan = array(
			'services' => array(
				array( 'name' => 'Meta Pixel', 'category' => 'marketing', 'requires_consent' => true, 'risk' => 'high' ),
			),
		);

		$out = ( new \GdprCa_Classifier() )->classify( $scan );
		$this->assertTrue( $out['services'][0]['classification']['final_requires_consent'] );
		$this->assertSame( 'high', $out['services'][0]['classification']['final_risk'] );
	}

	/**
	 * @test
	 */
	public function legal_basis_for_consent_required_is_consent() {
		$scan = array(
			'services' => array(
				array( 'name' => 'GA4', 'category' => 'statistics', 'requires_consent' => true, 'risk' => 'medium' ),
			),
		);

		$out = ( new \GdprCa_Classifier() )->classify( $scan );
		$basis = $out['services'][0]['classification']['legal_basis'];
		$this->assertSame( 'consent', $basis['basis'] );
		$this->assertStringContainsString( 'Art. 6(1)(a)', $basis['article'] );
	}

	/**
	 * @test
	 */
	public function legal_basis_for_necessary_is_legitimate_interest() {
		$scan = array(
			'plugins' => array(
				array( 'name' => 'Caching', 'category' => 'necessary', 'requires_consent' => false, 'risk' => 'low' ),
			),
		);

		$out = ( new \GdprCa_Classifier() )->classify( $scan );
		$basis = $out['plugins'][0]['classification']['legal_basis'];
		$this->assertSame( 'legitimate_interest', $basis['basis'] );
		$this->assertStringContainsString( 'Art. 6(1)(f)', $basis['article'] );
	}

	/**
	 * @test
	 */
	public function classify_with_empty_input_returns_empty() {
		$out = ( new \GdprCa_Classifier() )->classify( array() );
		$this->assertSame( array(), $out );
	}

	/**
	 * @test
	 */
	public function classify_with_null_returns_input_unchanged() {
		$out = ( new \GdprCa_Classifier() )->classify( null );
		$this->assertNull( $out );
	}

	/**
	 * @test
	 */
	public function summary_line_includes_name_and_risk() {
		$scan = array(
			'services' => array(
				array( 'name' => 'Hotjar', 'category' => 'statistics', 'requires_consent' => true, 'risk' => 'medium' ),
			),
		);

		$out = ( new \GdprCa_Classifier() )->classify( $scan );
		$line = $out['services'][0]['classification']['summary_line'];
		$this->assertStringContainsString( 'Hotjar', $line );
		$this->assertStringContainsString( 'medium', $line );
		$this->assertStringContainsString( 'consent', $line );
	}
}
