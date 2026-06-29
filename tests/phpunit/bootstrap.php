<?php
/**
 * PHPUnit bootstrap.
 *
 * Loads a minimal set of WordPress stubs so that the plugin's
 * classes can be instantiated and tested without a full WP test
 * suite. For tests that need real WP behavior (DB, hooks), use
 * the integration suite with the official wordpress-develop test
 * framework.
 *
 * @package GdprConsentAuditor
 */

if ( ! defined( 'GDPR_CA_TEST_MODE' ) ) {
	define( 'GDPR_CA_TEST_MODE', 1 );
}

$_tests_dir = getenv( 'WP_TESTS_DIR' );

// If the official WP test suite is available, load it; otherwise fall back to stubs.
if ( ! empty( $_tests_dir ) && file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	require_once $_tests_dir . '/includes/functions.php';

	tests_add_filter( 'muplugins_loaded', function () {
		require dirname( __DIR__, 2 ) . '/gdpr-consent-auditor.php';
	} );

	require $_tests_dir . '/includes/bootstrap.php';
} else {
	// Stub mode — load our minimal WP stubs.
	require_once __DIR__ . '/stubs/wp-stubs.php';

	// Plugin constants (mirror gdpr-consent-auditor.php).
	define( 'GDPR_CA_VERSION', '1.0.0' );
	define( 'GDPR_CA_PLUGIN_FILE', dirname( __DIR__, 2 ) . '/gdpr-consent-auditor.php' );
	define( 'GDPR_CA_PLUGIN_DIR', dirname( GDPR_CA_PLUGIN_FILE ) . '/' );
	define( 'GDPR_CA_PLUGIN_URL', 'https://example.test/wp-content/plugins/gdpr-consent-auditor/' );
	define( 'GDPR_CA_PLUGIN_BASENAME', 'gdpr-consent-auditor/gdpr-consent-auditor.php' );
	define( 'GDPR_CA_DB_VERSION', '1.0.0' );
	define( 'GDPR_CA_OPTION_NAME', 'gdpr_ca_settings' );
	define( 'GDPR_CA_CONSENT_COOKIE_NAME', 'gdpr_ca_consent' );
	define( 'GDPR_CA_CONSENT_VERSION_OPTION', 'gdpr_ca_consent_version' );

	// Load helpers + class files manually (skip the main file's WP-specific boot).
	require_once GDPR_CA_PLUGIN_DIR . 'includes/helpers.php';
	require_once GDPR_CA_PLUGIN_DIR . 'includes/class-activator.php';
	require_once GDPR_CA_PLUGIN_DIR . 'includes/class-deactivator.php';
	require_once GDPR_CA_PLUGIN_DIR . 'includes/class-scanner.php';
	require_once GDPR_CA_PLUGIN_DIR . 'includes/class-classifier.php';
	require_once GDPR_CA_PLUGIN_DIR . 'includes/class-consent-manager.php';
	require_once GDPR_CA_PLUGIN_DIR . 'includes/class-script-blocker.php';
	require_once GDPR_CA_PLUGIN_DIR . 'includes/class-report-generator.php';
	require_once GDPR_CA_PLUGIN_DIR . 'includes/class-legal-templates.php';
}
