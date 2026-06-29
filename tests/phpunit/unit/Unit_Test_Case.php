<?php
/**
 * Base test case.
 *
 * Provides common setUp/TearDown that resets the stubbed option
 * store between tests, so tests are isolated.
 *
 * @package GdprConsentAuditor
 */

namespace GdprConsentAuditor\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Class Unit_Test_Case
 */
abstract class Unit_Test_Case extends TestCase {

	/**
	 * Reset options, transients, hooks, cookies between tests.
	 */
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['__gdpr_ca_options']       = array();
		$GLOBALS['__gdpr_ca_transients']    = array();
		$GLOBALS['__gdpr_ca_filters']       = array();
		$GLOBALS['__gdpr_ca_actions_fired'] = array();
		$GLOBALS['__gdpr_ca_cookies_set']   = array();
		$GLOBALS['__gdpr_ca_current_user_id'] = 0;
		$GLOBALS['__gdpr_ca_is_admin']      = false;

		// Re-seed default settings to mimic a fresh activation.
		\GdprCa_Activator::default_settings();
		// Re-run the part of activate() that stores defaults — directly.
		\update_option( GDPR_CA_OPTION_NAME, \GdprCa_Activator::default_settings() );
		\update_option( GDPR_CA_CONSENT_VERSION_OPTION, '1' );
	}

	/**
	 * Get the most recently set cookie by name.
	 *
	 * @param string $name Cookie name.
	 * @return array|null {value, options}
	 */
	protected function last_cookie( $name ) {
		return $GLOBALS['__gdpr_ca_cookies_set'][ $name ] ?? null;
	}

	/**
	 * Get the list of actions fired during the test.
	 *
	 * @param string $tag Optional tag filter.
	 * @return array
	 */
	protected function fired_actions( $tag = null ) {
		if ( null === $tag ) {
			return $GLOBALS['__gdpr_ca_actions_fired'];
		}
		return array_filter(
			$GLOBALS['__gdpr_ca_actions_fired'],
			function ( $e ) use ( $tag ) { return $e[0] === $tag; }
		);
	}

	/**
	 * Simulate a logged-in user.
	 *
	 * @param int $id User ID.
	 */
	protected function login_as( $id ) {
		$GLOBALS['__gdpr_ca_current_user_id'] = (int) $id;
	}

	/**
	 * Set the is_admin() return value.
	 *
	 * @param bool $is_admin
	 */
	protected function set_is_admin( $is_admin ) {
		$GLOBALS['__gdpr_ca_is_admin'] = (bool) $is_admin;
	}
}
