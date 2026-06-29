<?php
/**
 * Deactivator: runs on plugin deactivation.
 *
 * @package GdprConsentAuditor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GdprCa_Deactivator
 *
 * Removes scheduled events and flushes caches. Does NOT delete
 * settings, consent logs, or the consent log table — that is
 * the job of the Uninstaller.
 */
class GdprCa_Deactivator {

	/**
	 * Deactivate the plugin.
	 *
	 * @return void
	 */
	public static function deactivate() {
		// Clear scheduled scan event.
		$timestamp = wp_next_scheduled( 'gdpr_ca_daily_scan' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'gdpr_ca_daily_scan' );
		}

		// Flush rewrite rules (revocation endpoint).
		flush_rewrite_rules();
	}
}
