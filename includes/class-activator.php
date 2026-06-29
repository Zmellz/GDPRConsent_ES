<?php
/**
 * Activator: runs on plugin activation.
 *
 * @package GdprConsentAuditor
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class GdprCa_Activator
 *
 * Sets default options, creates the consent log table, schedules
 * the periodic re-scan event, and stores the DB schema version.
 */
class GdprCa_Activator {

        /**
         * Activate the plugin.
         *
         * @return void
         */
        public static function activate() {
                // Defaults only if the option does not exist yet.
                if ( false === get_option( GDPR_CA_OPTION_NAME ) ) {
                        $defaults = self::default_settings();
                        add_option( GDPR_CA_OPTION_NAME, $defaults );
                }

                // Initial consent version (bumped every time categories/banner config change).
                if ( false === get_option( GDPR_CA_CONSENT_VERSION_OPTION ) ) {
                        add_option( GDPR_CA_CONSENT_VERSION_OPTION, '1' );
                }

                // Create / upgrade DB schema.
                self::create_consent_table();

                // Schedule daily re-scan if not scheduled.
                if ( ! wp_next_scheduled( 'gdpr_ca_daily_scan' ) ) {
                        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'gdpr_ca_daily_scan' );
                }

                // Store DB version for future upgrades.
                update_option( 'gdpr_ca_db_version', GDPR_CA_DB_VERSION );

                // Flush rewrite rules if a revocation endpoint is enabled later.
                flush_rewrite_rules();
        }

        /**
         * Default plugin settings.
         *
         * @return array
         */
        public static function default_settings() {
                return array(
                        // Banner.
                        'banner_enabled'             => 1,
                        'banner_position'            => 'bottom',
                        'banner_layout'              => 'bar',
                        'banner_title'               => 'Valoramos tu privacidad',
                        'banner_message'             => 'Usamos cookies y tecnologías similares para mejorar tu experiencia de navegación, ofrecer contenido o anuncios personalizados y analizar nuestro tráfico. Al hacer clic en "Aceptar todo", consientes el uso de cookies.',
                        'accept_all_label'           => 'Aceptar todo',
                        'reject_all_label'           => 'Rechazar todo',
                        'configure_label'            => 'Configurar',
                        'save_label'                 => 'Guardar selección',
                        'policy_link_label'          => 'Política de cookies',
                        'policy_page_id'             => 0,
                        'primary_color'              => '#1a73e8',
                        'accent_color'               => '#202124',
                        'background_color'           => '#ffffff',
                        'text_color'                 => '#202124',
                        'muted_color'                => '#5f6368',
                        'border_color'               => '#d9dee7',
                        'button_text_color'          => '#ffffff',
                        'banner_radius'              => 18,
                        'banner_max_width'           => 1040,
                        'banner_offset'              => 20,
                        // Categories (always-on vs opt-in).
                        'categories'                 => array(
                                'necessary'     => array(
                                        'label'       => 'Necesarias',
                                        'description' => 'Necesarias para que el sitio web funcione. No se pueden desactivar.',
                                        'always_on'   => 1,
                                ),
                                'preferences'   => array(
                                        'label'       => 'Preferencias',
                                        'description' => 'Recuerdan tus elecciones (idioma, región) para ofrecer una experiencia más fluida.',
                                        'always_on'   => 0,
                                ),
                                'statistics'    => array(
                                        'label'       => 'Estadísticas',
                                        'description' => 'Miden de forma anónima cómo se usa la web para poder mejorarla.',
                                        'always_on'   => 0,
                                ),
                                'marketing'     => array(
                                        'label'       => 'Marketing',
                                        'description' => 'Se usan para mostrar anuncios relevantes y medir el rendimiento de las campañas publicitarias.',
                                        'always_on'   => 0,
                                ),
                        ),
                        // Consent log.
                        'log_consents'               => 1,
                        'log_retention_days'         => 365,
                        'hash_ip'                    => 1,
                        // Blocking.
                        'block_scripts'              => 1,
                        'block_iframes'              => 1,
                        'block_dynamic_scripts'      => 0,
                        'manual_blocks'              => array(),
                        // Integrations.
                        'gcm_v2_enabled'             => 0,
                        'gcm_v2_default_ads'         => 'denied',
                        'gcm_v2_default_analytics'   => 'denied',
                        'gcm_v2_default_functional'  => 'denied',
                        'gcm_v2_default_personalized_ads' => 'denied',
                        'meta_pixel_consent_enabled' => 0,
                        'meta_pixel_id'              => '',
                        // Legal.
                        'legal_disclaimer_shown'     => 1,
                        'cookie_policy_text'         => '',
                        'privacy_notice_text'        => '',
                        'category_descriptions_text' => '',
                        // Misc.
                        'last_scan_at'               => 0,
                        'last_scan_results'          => array(),
                );
        }

        /**
         * Create the consent log table.
         *
         * Schema:
         *   id            BIGINT UNSIGNED AUTO_INCREMENT
         *   consent_date  DATETIME
         *   consent_hash  CHAR(64)   -- SHA-256(ip + ua + salt) for pseudonymization
         *   user_id       BIGINT UNSIGNED (0 for anonymous)
         *   user_agent    VARCHAR(255)
         *   action        VARCHAR(32)  accept_all|reject_all|custom|revoke
         *   categories    TEXT (JSON)
         *   consent_version VARCHAR(16)
         *   PRIMARY KEY (id), KEY (consent_hash), KEY (consent_date)
         *
         * @return void
         */
        public static function create_consent_table() {
                global $wpdb;
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';

                $table_name      = $wpdb->prefix . 'gdpr_ca_consents';
                $charset_collate = $wpdb->get_charset_collate();

                $sql = "CREATE TABLE {$table_name} (
                        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                        consent_date DATETIME NOT NULL,
                        consent_hash CHAR(64) NOT NULL DEFAULT '',
                        user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
                        user_agent VARCHAR(255) NOT NULL DEFAULT '',
                        action VARCHAR(32) NOT NULL DEFAULT 'custom',
                        categories LONGTEXT NOT NULL,
                        consent_version VARCHAR(16) NOT NULL DEFAULT '1',
                        PRIMARY KEY  (id),
                        KEY consent_hash (consent_hash),
                        KEY consent_date (consent_date),
                        KEY user_id (user_id)
                ) {$charset_collate};";

                dbDelta( $sql );
        }
}
