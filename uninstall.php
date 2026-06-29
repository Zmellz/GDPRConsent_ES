<?php
/**
 * Uninstaller: runs when the user clicks "Delete" on the Plugins screen.
 *
 * During uninstall, WordPress includes this file directly WITHOUT loading
 * the plugin, so plugin constants (GDPR_CA_OPTION_NAME etc.) are NOT
 * available. Define them inline or use raw strings.
 *
 * @package GdprConsentAuditor
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Define option keys inline (plugin is not loaded during uninstall).
$option_name = 'gdpr_ca_settings';
$consent_version_option = 'gdpr_ca_consent_version';

// Remove options.
delete_option( $option_name );
delete_option( $consent_version_option );
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
