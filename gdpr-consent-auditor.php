<?php
/**
 * Plugin Name:       GDPR Consent Auditor
 * Plugin URI:        https://example.com/gdpr-consent-auditor
 * Description:       Technical audit tool for privacy, cookies and third-party scripts. Generates a privacy report and lets you build a configurable consent banner with granular per-category acceptance. Assistive tool only — does not guarantee legal compliance.
 * Version:           1.1.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Senior WP Dev
 * Author URI:        https://example.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       gdpr-consent-auditor
 * Domain Path:       /languages
 *
 * @package GdprConsentAuditor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Plugin constants.
 */
define( 'GDPR_CA_VERSION', '1.1.0' );
define( 'GDPR_CA_PLUGIN_FILE', __FILE__ );
define( 'GDPR_CA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'GDPR_CA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'GDPR_CA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'GDPR_CA_DB_VERSION', '1.0.0' );
define( 'GDPR_CA_OPTION_NAME', 'gdpr_ca_settings' );
define( 'GDPR_CA_CONSENT_COOKIE_NAME', 'gdpr_ca_consent' );
define( 'GDPR_CA_CONSENT_VERSION_OPTION', 'gdpr_ca_consent_version' );

/**
 * Simple PSR-4-ish autoloader for plugin classes.
 *
 * Class name pattern: GdprCa_Subsystem_Class -> includes/class-subsystem-class.php
 *
 * @param string $class Fully qualified class name.
 * @return void
 */
function gdpr_ca_autoload( $class ) {
        if ( 0 !== strpos( $class, 'GdprCa_' ) ) {
                return;
        }
        $relative  = substr( $class, strlen( 'GdprCa_' ) );
        $filename  = 'class-' . strtolower( str_replace( '_', '-', $relative ) ) . '.php';
        $filepath  = GDPR_CA_PLUGIN_DIR . 'includes/' . $filename;

        // Admin/Public classes live in their own subfolders.
        $admin_path     = GDPR_CA_PLUGIN_DIR . 'admin/' . $filename;
        $public_path    = GDPR_CA_PLUGIN_DIR . 'public/' . $filename;

        if ( file_exists( $admin_path ) ) {
                require_once $admin_path;
        } elseif ( file_exists( $public_path ) ) {
                require_once $public_path;
        } elseif ( file_exists( $filepath ) ) {
                require_once $filepath;
        }
}
spl_autoload_register( 'gdpr_ca_autoload' );

// Load helper functions early (not a class).
require_once GDPR_CA_PLUGIN_DIR . 'includes/helpers.php';

// Load the core bootstrap so cron and REST routes are actually registered.
require_once GDPR_CA_PLUGIN_DIR . 'includes/class-plugin.php';

/**
 * Activation hook — registers defaults and DB schema.
 */
register_activation_hook( __FILE__, array( 'GdprCa_Activator', 'activate' ) );

/**
 * Deactivation hook — clears caches and scheduled events.
 */
register_deactivation_hook( __FILE__, array( 'GdprCa_Deactivator', 'deactivate' ) );

/**
 * Boot the plugin once all plugins are loaded.
 *
 * @return void
 */
function gdpr_ca_boot() {
        // Load text domain.
        load_plugin_textdomain( 'gdpr-consent-auditor', false, dirname( GDPR_CA_PLUGIN_BASENAME ) . '/languages' );

        // Admin-side bootstrap.
        if ( is_admin() ) {
                new GdprCa_Admin();
        }

        // Public-side bootstrap (front-end banner, script blocking, consent endpoint).
        new GdprCa_Public();
}
add_action( 'plugins_loaded', 'gdpr_ca_boot' );
