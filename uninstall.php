<?php
/**
 * Uninstaller: runs when the user clicks "Delete" on the Plugins screen.
 *
 * @package GdprConsentAuditor
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Class GdprCa_Uninstaller
 *
 * Removes all plugin data: options, transients, the consent log table,
 * and scheduled events. Cookie data in the user's browser cannot be
 * removed server-side; we rely on cookie expiration.
 */
class GdprCa_Uninstaller {

	/**
	 * Run uninstallation.
	 *
	 * @return void
	 */
	public static function uninstall() {
		global $wpdb;

		// Remove options.
		delete_option( GDPR_CA_OPTION_NAME );
		delete_option( GDPR_CA_CONSENT_VERSION_OPTION );
		delete_option( 'gdpr_ca_db_version' );
		delete_transient( 'gdpr_ca_scan_results' );

		// Remove consent log table.
		$table_name = $wpdb->prefix . 'gdpr_ca_consents';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is hardcoded internally.
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

		// Clear scheduled events.
		wp_clear_scheduled_hook( 'gdpr_ca_daily_scan' );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}

// Hook into WP's uninstall mechanism.
register_uninstall_hook( __FILE__, array( 'GdprCa_Uninstaller', 'uninstall' ) );
