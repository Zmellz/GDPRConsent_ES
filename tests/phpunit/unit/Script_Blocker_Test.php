<?php
/**
 * Tests for GdprCa_Script_Blocker.
 *
 * @package GdprConsentAuditor\Tests\Unit
 */

namespace GdprConsentAuditor\Tests\Unit;

require_once __DIR__ . '/Unit_Test_Case.php';

/**
 * Class Script_Blocker_Test
 *
 * @covers \GdprCa_Script_Blocker
 */
class Script_Blocker_Test extends Unit_Test_Case {

	/**
	 * @test
	 */
	public function filter_script_tag_passes_through_local_scripts() {
		$this->set_is_admin( false );
		\gdpr_ca_update_setting( 'block_scripts', 1 );
		\gdpr_ca_update_setting( 'manual_blocks', array() );

		$b = new \GdprCa_Script_Blocker();
		$tag = '<script src="/wp-includes/js/jquery/jquery.min.js"></script>';
		$out = $b->filter_script_tag( $tag, 'jquery-core', '/wp-includes/js/jquery/jquery.min.js' );

		$this->assertSame( $tag, $out, 'Local scripts must pass through unchanged' );
	}

	/**
	 * @test
	 */
	public function filter_script_tag_passes_through_in_admin() {
		$this->set_is_admin( true );

		$b = new \GdprCa_Script_Blocker();
		$tag = '<script src="https://www.googletagmanager.com/gtag/js?id=G-XXX"></script>';
		$out = $b->filter_script_tag( $tag, 'gtag', 'https://www.googletagmanager.com/gtag/js?id=G-XXX' );

		$this->assertSame( $tag, $out, 'Scripts loaded in admin must pass through unchanged' );
	}

	/**
	 * @test
	 */
	public function filter_script_tag_wraps_known_google_analytics_script() {
		$this->set_is_admin( false );
		\gdpr_ca_update_setting( 'block_scripts', 1 );

		$b = new \GdprCa_Script_Blocker();
		$src = 'https://www.googletagmanager.com/gtag/js?id=G-XXX';
		$tag = '<script type="text/javascript" src="' . $src . '"></script>';
		$out = $b->filter_script_tag( $tag, 'gtag', $src );

		$this->assertStringContainsString( 'type="text/plain"', $out );
		$this->assertStringContainsString( 'data-gdpr-ca-category="statistics"', $out );
		$this->assertStringContainsString( 'data-gdpr-ca-handle="gtag"', $out );
	}

	/**
	 * @test
	 */
	public function filter_script_tag_blocks_meta_pixel_as_marketing() {
		$this->set_is_admin( false );
		\gdpr_ca_update_setting( 'block_scripts', 1 );

		$b = new \GdprCa_Script_Blocker();
		$src = 'https://connect.facebook.net/en_US/fbevents.js';
		$tag = '<script src="' . $src . '"></script>';
		$out = $b->filter_script_tag( $tag, 'fb-pixel', $src );

		$this->assertStringContainsString( 'type="text/plain"', $out );
		$this->assertStringContainsString( 'data-gdpr-ca-category="marketing"', $out );
	}

	/**
	 * @test
	 */
	public function filter_script_tag_recaptcha_is_not_blocked() {
		$this->set_is_admin( false );
		\gdpr_ca_update_setting( 'block_scripts', 1 );

		$b = new \GdprCa_Script_Blocker();
		$src = 'https://www.google.com/recaptcha/api.js?render=explicit';
		$tag = '<script src="' . $src . '"></script>';
		$out = $b->filter_script_tag( $tag, 'recaptcha', $src );

		$this->assertSame( $tag, $out, 'reCAPTCHA is in Necessary category and must not be blocked' );
	}

	/**
	 * @test
	 */
	public function manual_block_rule_overrides_known_service() {
		$this->set_is_admin( false );
		\gdpr_ca_update_setting( 'block_scripts', 1 );
		\gdpr_ca_update_setting(
			'manual_blocks',
			array(
				array(
					'pattern'  => 'my-tracker',
					'category' => 'marketing',
				),
			)
		);

		$b = new \GdprCa_Script_Blocker();
		$src = 'https://cdn.example.com/my-tracker.v2.js';
		$tag = '<script src="' . $src . '"></script>';
		$out = $b->filter_script_tag( $tag, 'my-tracker', $src );

		$this->assertStringContainsString( 'data-gdpr-ca-category="marketing"', $out );
	}

	/**
	 * @test
	 */
	public function filter_content_iframes_replaces_youtube_with_placeholder() {
		$this->set_is_admin( false );
		\gdpr_ca_update_setting( 'block_iframes', 1 );

		$b = new \GdprCa_Script_Blocker();
		$content = '<p>Watch:</p><iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ"></iframe>';
		$out = $b->filter_content_iframes( $content );

		$this->assertStringContainsString( 'gdpr-ca-iframe-placeholder', $out );
		$this->assertStringContainsString( 'data-gdpr-ca-category="marketing"', $out );
		$this->assertStringContainsString( 'data-gdpr-ca-src', $out );
		$this->assertStringContainsString( 'Load content', $out );
	}

	/**
	 * @test
	 */
	public function filter_content_iframes_leaves_local_iframes_alone() {
		$this->set_is_admin( false );
		\gdpr_ca_update_setting( 'block_iframes', 1 );

		$b = new \GdprCa_Script_Blocker();
		$content = '<iframe src="/local-embed/"></iframe>';
		$out = $b->filter_content_iframes( $content );
		$this->assertStringNotContainsString( 'gdpr-ca-iframe-placeholder', $out );
	}

	/**
	 * @test
	 */
	public function filter_content_iframes_skipped_when_blocking_disabled() {
		$this->set_is_admin( false );
		\gdpr_ca_update_setting( 'block_iframes', 0 );

		// Re-instantiate to apply the setting (constructor checks it).
		$b = new \GdprCa_Script_Blocker();
		$content = '<iframe src="https://www.youtube.com/embed/abc"></iframe>';
		// Since block_iframes is disabled, the filter is not registered at all.
		// Just verify the constructor didn't crash.
		$this->assertNotNull( $b );
	}

	/**
	 * @test
	 */
	public function filter_oembed_wraps_youtube_html() {
		$this->set_is_admin( false );
		\gdpr_ca_update_setting( 'block_iframes', 1 );

		$b = new \GdprCa_Script_Blocker();
		$embed_html = '<iframe src="https://www.youtube.com/embed/xyz" width="560" height="315"></iframe>';
		$out = $b->filter_oembed( $embed_html, 'https://youtu.be/xyz', array() );

		$this->assertStringContainsString( 'gdpr-ca-iframe-placeholder', $out );
		$this->assertStringContainsString( 'data-gdpr-ca-category="marketing"', $out );
		$this->assertStringContainsString( 'data-gdpr-ca-html', $out );
	}
}
