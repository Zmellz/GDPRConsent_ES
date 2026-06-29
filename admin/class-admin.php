<?php
/**
 * Admin bootstrap: menu, settings registration, asset enqueue,
 * AJAX handlers, scan trigger, log purging, export.
 *
 * @package GdprConsentAuditor
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class GdprCa_Admin
 */
class GdprCa_Admin {

        /**
         * Capability required to use this plugin.
         */
        const CAP = 'manage_options';

        /**
         * Admin page slug.
         */
        const SLUG = 'gdpr-ca';

        /**
         * Constructor.
         */
        public function __construct() {
                add_action( 'admin_menu', array( $this, 'register_menu' ) );
                add_action( 'admin_init', array( $this, 'register_settings' ) );
                add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

                // AJAX handlers.
                add_action( 'wp_ajax_gdpr_ca_run_scan', array( $this, 'ajax_run_scan' ) );
                add_action( 'wp_ajax_gdpr_ca_export_report', array( $this, 'ajax_export_report' ) );
                add_action( 'wp_ajax_gdpr_ca_export_report_csv', array( $this, 'ajax_export_report_csv' ) );
                add_action( 'wp_ajax_gdpr_ca_export_report_json', array( $this, 'ajax_export_report_json' ) );
                add_action( 'wp_ajax_gdpr_ca_purge_logs', array( $this, 'ajax_purge_logs' ) );

                // Admin notices for legal disclaimer.
                add_action( 'admin_notices', array( $this, 'maybe_show_disclaimer_notice' ) );
        }

        /**
         * Register the admin menu and sub-pages.
         *
         * @return void
         */
        public function register_menu() {
                $icon = 'dashicons-shield-alt';
                $pos  = 80;

                add_menu_page(
                        __( 'Auditor GDPR', 'gdpr-consent-auditor' ),
                        __( 'Auditor GDPR', 'gdpr-consent-auditor' ),
                        self::CAP,
                        self::SLUG,
                        array( $this, 'render_dashboard' ),
                        $icon,
                        $pos
                );

                $sections = array(
                        'dashboard'        => array( __( 'Panel', 'gdpr-consent-auditor' ), array( $this, 'render_dashboard' ) ),
                        'scan-results'     => array( __( 'Resultados del escaneo', 'gdpr-consent-auditor' ), array( $this, 'render_scan_results' ) ),
                        'banner-settings'  => array( __( 'Banner de consentimiento', 'gdpr-consent-auditor' ), array( $this, 'render_banner_settings' ) ),
                        'consent-logs'     => array( __( 'Registros de consentimiento', 'gdpr-consent-auditor' ), array( $this, 'render_consent_logs' ) ),
                        'legal-templates'  => array( __( 'Plantillas legales', 'gdpr-consent-auditor' ), array( $this, 'render_legal_templates' ) ),
                        'settings'         => array( __( 'Ajustes', 'gdpr-consent-auditor' ), array( $this, 'render_settings' ) ),
                );

                foreach ( $sections as $slug => $cfg ) {
                        add_submenu_page(
                                self::SLUG,
                                $cfg[0],
                                $cfg[0],
                                self::CAP,
                                self::SLUG . '-' . $slug,
                                $cfg[1]
                        );
                }
        }

        /**
         * Register the settings via Settings API.
         *
         * @return void
         */
        public function register_settings() {
                register_setting(
                        'gdpr_ca_settings_group',
                        GDPR_CA_OPTION_NAME,
                        array( $this, 'sanitize_settings' )
                );
        }

        /**
         * Sanitize the settings array on save.
         *
         * @param array $input Raw input.
         * @return array
         */
        public function sanitize_settings( $input ) {
                $current = get_option( GDPR_CA_OPTION_NAME, array() );
                if ( ! is_array( $current ) ) {
                        $current = array();
                }

                $out = $current;

                // Booleans.
                $bools = array(
                        'banner_enabled',
                        'log_consents',
                        'hash_ip',
                        'block_scripts',
                        'block_iframes',
                        'block_dynamic_scripts',
                        'gcm_v2_enabled',
                        'meta_pixel_consent_enabled',
                );
                foreach ( $bools as $b ) {
                        $out[ $b ] = empty( $input[ $b ] ) ? 0 : 1;
                }

                // Strings (plain text).
                $strings = array(
                        'banner_position',
                        'banner_layout',
                        'banner_title',
                        'banner_message',
                        'accept_all_label',
                        'reject_all_label',
                        'configure_label',
                        'save_label',
                        'policy_link_label',
                        'gcm_v2_default_ads',
                        'gcm_v2_default_analytics',
                        'gcm_v2_default_functional',
                        'gcm_v2_default_personalized_ads',
                        'meta_pixel_id',
                );
                foreach ( $strings as $s ) {
                        if ( isset( $input[ $s ] ) ) {
                                $out[ $s ] = sanitize_text_field( wp_unslash( $input[ $s ] ) );
                        }
                }

                $allowed_layouts        = array( 'bar', 'modal', 'widget' );
                $allowed_positions      = array( 'bottom', 'top' );
                $allowed_consent_states = array( 'denied', 'granted' );
                $allowed_categories     = array_keys( gdpr_ca_known_categories() );

                $out['banner_layout'] = in_array( isset( $out['banner_layout'] ) ? $out['banner_layout'] : 'bar', $allowed_layouts, true ) ? $out['banner_layout'] : 'bar';
                $out['banner_position'] = in_array( isset( $out['banner_position'] ) ? $out['banner_position'] : 'bottom', $allowed_positions, true ) ? $out['banner_position'] : 'bottom';

                foreach ( array( 'gcm_v2_default_ads', 'gcm_v2_default_analytics', 'gcm_v2_default_functional', 'gcm_v2_default_personalized_ads' ) as $state_key ) {
                        $out[ $state_key ] = in_array( isset( $out[ $state_key ] ) ? $out[ $state_key ] : 'denied', $allowed_consent_states, true ) ? $out[ $state_key ] : 'denied';
                }

                $out['primary_color'] = gdpr_ca_sanitize_hex_color(
                        isset( $input['primary_color'] ) ? sanitize_text_field( wp_unslash( $input['primary_color'] ) ) : ( isset( $out['primary_color'] ) ? $out['primary_color'] : '#1a73e8' ),
                        '#1a73e8'
                );
                $out['accent_color'] = gdpr_ca_sanitize_hex_color(
                        isset( $input['accent_color'] ) ? sanitize_text_field( wp_unslash( $input['accent_color'] ) ) : ( isset( $out['accent_color'] ) ? $out['accent_color'] : '#202124' ),
                        '#202124'
                );
                $out['background_color'] = gdpr_ca_sanitize_hex_color(
                        isset( $input['background_color'] ) ? sanitize_text_field( wp_unslash( $input['background_color'] ) ) : ( isset( $out['background_color'] ) ? $out['background_color'] : '#ffffff' ),
                        '#ffffff'
                );
                $out['text_color'] = gdpr_ca_sanitize_hex_color(
                        isset( $input['text_color'] ) ? sanitize_text_field( wp_unslash( $input['text_color'] ) ) : ( isset( $out['text_color'] ) ? $out['text_color'] : '#202124' ),
                        '#202124'
                );
                $out['muted_color'] = gdpr_ca_sanitize_hex_color(
                        isset( $input['muted_color'] ) ? sanitize_text_field( wp_unslash( $input['muted_color'] ) ) : ( isset( $out['muted_color'] ) ? $out['muted_color'] : '#5f6368' ),
                        '#5f6368'
                );
                $out['border_color'] = gdpr_ca_sanitize_hex_color(
                        isset( $input['border_color'] ) ? sanitize_text_field( wp_unslash( $input['border_color'] ) ) : ( isset( $out['border_color'] ) ? $out['border_color'] : '#d9dee7' ),
                        '#d9dee7'
                );
                $out['button_text_color'] = gdpr_ca_sanitize_hex_color(
                        isset( $input['button_text_color'] ) ? sanitize_text_field( wp_unslash( $input['button_text_color'] ) ) : ( isset( $out['button_text_color'] ) ? $out['button_text_color'] : '#ffffff' ),
                        '#ffffff'
                );
                $out['banner_radius'] = isset( $input['banner_radius'] ) ? max( 0, min( 32, absint( $input['banner_radius'] ) ) ) : ( isset( $out['banner_radius'] ) ? absint( $out['banner_radius'] ) : 18 );
                $out['banner_max_width'] = isset( $input['banner_max_width'] ) ? max( 320, min( 1600, absint( $input['banner_max_width'] ) ) ) : ( isset( $out['banner_max_width'] ) ? absint( $out['banner_max_width'] ) : 1040 );
                $out['banner_offset'] = isset( $input['banner_offset'] ) ? max( 0, min( 80, absint( $input['banner_offset'] ) ) ) : ( isset( $out['banner_offset'] ) ? absint( $out['banner_offset'] ) : 20 );

                // Color opacities (0-100).
                $opacity_keys = array(
                        'primary_color_opacity',
                        'accent_color_opacity',
                        'background_color_opacity',
                        'text_color_opacity',
                        'muted_color_opacity',
                        'border_color_opacity',
                        'button_text_color_opacity',
                );
                foreach ( $opacity_keys as $ok ) {
                        $out[ $ok ] = isset( $input[ $ok ] ) ? max( 0, min( 100, absint( $input[ $ok ] ) ) ) : ( isset( $out[ $ok ] ) ? absint( $out[ $ok ] ) : 100 );
                }

                // Padding (px).
                $padding_keys = array( 'banner_padding_top', 'banner_padding_right', 'banner_padding_bottom', 'banner_padding_left' );
                foreach ( $padding_keys as $pk ) {
                        $out[ $pk ] = isset( $input[ $pk ] ) ? max( 0, min( 80, absint( $input[ $pk ] ) ) ) : ( isset( $out[ $pk ] ) ? absint( $out[ $pk ] ) : 24 );
                }

                // Font sizes (px).
                $out['font_size_title'] = isset( $input['font_size_title'] ) ? max( 12, min( 48, absint( $input['font_size_title'] ) ) ) : ( isset( $out['font_size_title'] ) ? absint( $out['font_size_title'] ) : 20 );
                $out['font_size_message'] = isset( $input['font_size_message'] ) ? max( 10, min( 36, absint( $input['font_size_message'] ) ) ) : ( isset( $out['font_size_message'] ) ? absint( $out['font_size_message'] ) : 14 );
                $out['font_size_buttons'] = isset( $input['font_size_buttons'] ) ? max( 10, min( 28, absint( $input['font_size_buttons'] ) ) ) : ( isset( $out['font_size_buttons'] ) ? absint( $out['font_size_buttons'] ) : 14 );

                // Text alignment.
                $allowed_align = array( 'left', 'center', 'right' );
                $out['banner_text_align'] = in_array( isset( $input['banner_text_align'] ) ? $input['banner_text_align'] : 'center', $allowed_align, true ) ? $input['banner_text_align'] : 'center';

                // Meta Pixel ID must be digits only (15-16 digits). Empty is allowed.
                if ( isset( $out['meta_pixel_id'] ) ) {
                        $out['meta_pixel_id'] = preg_replace( '/[^0-9]/', '', $out['meta_pixel_id'] );
                }

                // Textareas (allow line breaks).
                $textareas = array(
                        'legal_cookie_policy_text',
                        'legal_privacy_notice_text',
                        'legal_category_descriptions_text',
                        'legal_banner_text_text',
                );
                foreach ( $textareas as $t ) {
                        if ( isset( $input[ $t ] ) ) {
                                $out[ $t ] = sanitize_textarea_field( wp_unslash( $input[ $t ] ) );
                        }
                }

                // Numeric.
                if ( isset( $input['log_retention_days'] ) ) {
                        $out['log_retention_days'] = max( 1, absint( $input['log_retention_days'] ) );
                }
                if ( isset( $input['policy_page_id'] ) ) {
                        $out['policy_page_id'] = absint( $input['policy_page_id'] );
                }

                // Categories.
                if ( isset( $input['categories'] ) && is_array( $input['categories'] ) ) {
                        $cleaned        = array();
                        foreach ( $allowed_categories as $cat_key ) {
                                $raw = isset( $input['categories'][ $cat_key ] ) ? (array) $input['categories'][ $cat_key ] : array();
                                $label = isset( $raw['label'] ) ? sanitize_text_field( $raw['label'] ) : '';
                                $desc  = isset( $raw['description'] ) ? sanitize_textarea_field( $raw['description'] ) : '';
                                $always = isset( $raw['always_on'] ) ? 1 : 0;
                                $cleaned[ $cat_key ] = array(
                                        'label'       => $label,
                                        'description' => $desc,
                                        'always_on'   => $always,
                                );
                        }
                        $out['categories'] = $cleaned;
                }

                // Manual block rules.
                if ( isset( $input['manual_blocks'] ) && is_array( $input['manual_blocks'] ) ) {
                        $clean = array();
                        foreach ( $input['manual_blocks'] as $rule ) {
                                if ( empty( $rule['pattern'] ) || empty( $rule['category'] ) ) {
                                        continue;
                                }
                                $category = sanitize_key( $rule['category'] );
                                if ( ! in_array( $category, $allowed_categories, true ) ) {
                                        continue;
                                }
                                $pattern = gdpr_ca_truncate_text( sanitize_text_field( wp_unslash( $rule['pattern'] ) ), 512 );
                                if ( '' === $pattern ) {
                                        continue;
                                }
                                $clean[] = array(
                                        'pattern'  => $pattern,
                                        'category' => $category,
                                );
                        }
                        $out['manual_blocks'] = $clean;
                }

                if ( $this->settings_require_consent_version_bump( $current, $out ) ) {
                        gdpr_ca_bump_consent_version();
                }

                return $out;
        }

        /**
         * Determine whether the new settings invalidate previously stored consent.
         *
         * @param array $before Previous settings.
         * @param array $after  Sanitized settings about to be saved.
         * @return bool
         */
        private function settings_require_consent_version_bump( $before, $after ) {
                $keys = array(
                        'banner_enabled',
                        'banner_position',
                        'banner_layout',
                        'banner_title',
                        'banner_message',
                        'accept_all_label',
                        'reject_all_label',
                        'configure_label',
                        'save_label',
                        'policy_page_id',
                        'policy_link_label',
                        'categories',
                        'block_scripts',
                        'block_iframes',
                        'block_dynamic_scripts',
                        'manual_blocks',
                        'gcm_v2_enabled',
                        'gcm_v2_default_ads',
                        'gcm_v2_default_analytics',
                        'gcm_v2_default_functional',
                        'gcm_v2_default_personalized_ads',
                        'meta_pixel_consent_enabled',
                        'meta_pixel_id',
                );

                foreach ( $keys as $key ) {
                        $old = array_key_exists( $key, $before ) ? $before[ $key ] : null;
                        $new = array_key_exists( $key, $after ) ? $after[ $key ] : null;
                        if ( $old !== $new ) {
                                return true;
                        }
                }

                return false;
        }

        /**
         * Enqueue admin assets.
         *
         * @param string $hook Current admin page hook.
         * @return void
         */
        public function enqueue_assets( $hook ) {
                if ( false === strpos( $hook, self::SLUG ) ) {
                        return;
                }

                wp_enqueue_style(
                        'gdpr-ca-admin-css',
                        GDPR_CA_PLUGIN_URL . 'admin/css/admin.css',
                        array(),
                        GDPR_CA_VERSION
                );

                wp_enqueue_script(
                        'gdpr-ca-admin-js',
                        GDPR_CA_PLUGIN_URL . 'admin/js/admin.js',
                        array( 'jquery' ),
                        GDPR_CA_VERSION,
                        true
                );

                wp_localize_script(
                        'gdpr-ca-admin-js',
                        'GdprCaAdmin',
                        array(
                                'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
                                'nonce'    => wp_create_nonce( 'gdpr_ca_admin' ),
                                'i18n'     => array(
                                        'scanning'      => __( 'Escaneando…', 'gdpr-consent-auditor' ),
                                        'scanDone'      => __( 'Escaneo completado.', 'gdpr-consent-auditor' ),
                                        'scanDoneWithWarnings' => __( 'Escaneo completado con advertencias.', 'gdpr-consent-auditor' ),
                                        'scanFailed'    => __( 'El escaneo ha fallado. Recarga la página e inténtalo de nuevo.', 'gdpr-consent-auditor' ),
                                        'confirmPurge'  => __( '¿Quieres borrar todos los registros de consentimiento anteriores al período de retención?', 'gdpr-consent-auditor' ),
                                        'exporting'     => __( 'Preparando exportación…', 'gdpr-consent-auditor' ),
                                ),
                        )
                );
        }

        /**
         * Render a view, passing data to it.
         *
         * @param string $view View slug (file name without .php).
         * @param array  $data Optional data extract()ed into the view.
         * @return void
         */
        private function view( $view, $data = array() ) {
                if ( ! current_user_can( self::CAP ) ) {
                        wp_die( esc_html__( 'No tienes permisos para acceder a esta página.', 'gdpr-consent-auditor' ) );
                }
                $path = GDPR_CA_PLUGIN_DIR . 'admin/views/' . $view . '.php';
                if ( ! file_exists( $path ) ) {
                        echo '<div class="wrap"><p>' . esc_html__( 'Vista no encontrada.', 'gdpr-consent-auditor' ) . '</p></div>';
                        return;
                }
                // Make settings available in all views.
                $settings = get_option( GDPR_CA_OPTION_NAME, array() );
                $data['settings'] = $settings;
                extract( $data, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- admin views only.
                include $path;
        }

        /* -----------------------------------------------------------------
         * Render callbacks for each sub-page.
         * --------------------------------------------------------------- */

        /**
         * Dashboard.
         *
         * @return void
         */
        public function render_dashboard() {
                $last_scan = gdpr_ca_get_setting( 'last_scan_at', 0 );
                $results   = gdpr_ca_get_last_scan_results();
                $summary   = isset( $results['summary'] ) ? $results['summary'] : array();

                $report   = new GdprCa_Report_Generator();
                $report_html = $report->build( $results );
                $this->view( 'dashboard', array(
                        'last_scan'    => $last_scan,
                        'summary'      => $summary,
                        'report_html'  => $report_html['html'],
                ) );
        }

        /**
         * Scan results page.
         *
         * @return void
         */
        public function render_scan_results() {
                $results = gdpr_ca_get_last_scan_results();
                $this->view( 'scan-results', array( 'results' => $results ) );
        }

        /**
         * Banner settings page.
         *
         * @return void
         */
        public function render_banner_settings() {
                $this->view( 'banner-settings' );
        }

        /**
         * Consent logs page.
         *
         * @return void
         */
        public function render_consent_logs() {
                $page    = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $manager = new GdprCa_Consent_Manager();
                $logs    = $manager->get_logs( $page, 50 );
                $this->view( 'consent-logs', array(
                        'logs'   => $logs['items'],
                        'total'  => $logs['total'],
                        'page'   => $page,
                        'pages'  => max( 1, (int) ceil( $logs['total'] / 50 ) ),
                ) );
        }

        /**
         * Legal templates page.
         *
         * @return void
         */
        public function render_legal_templates() {
                $this->view( 'legal-templates' );
        }

        /**
         * Settings page.
         *
         * @return void
         */
        public function render_settings() {
                $this->view( 'settings' );
        }

        /* -----------------------------------------------------------------
         * AJAX handlers.
         * --------------------------------------------------------------- */

        /**
         * Run a scan on demand.
         *
         * @return void
         */
        public function ajax_run_scan() {
                check_ajax_referer( 'gdpr_ca_admin', 'nonce' );

                if ( ! current_user_can( self::CAP ) ) {
                        wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'gdpr-consent-auditor' ) ), 403 );
                        return;
                }

                try {
                        $scanner = new GdprCa_Scanner();
                        $results = $scanner->scan();
                } catch ( Throwable $e ) {
                        wp_send_json_error(
                                array(
                                        'message' => sprintf(
                                                /* translators: %s: error message. */
                                                __( 'El escaneo ha fallado: %s', 'gdpr-consent-auditor' ),
                                                $e->getMessage()
                                        ),
                                ),
                                500
                        );
                        return;
                }

                gdpr_ca_update_setting( 'last_scan_at', current_time( 'mysql' ) );
                gdpr_ca_store_last_scan_results( $results );

                $stored_results = gdpr_ca_get_last_scan_results();
                $warnings = isset( $results['warnings'] ) && is_array( $results['warnings'] ) ? $results['warnings'] : array();
                if ( empty( $stored_results ) && ! empty( $results ) ) {
                        $warnings[] = __( 'El escaneo se ejecutó, pero no fue posible recuperar los resultados guardados después de almacenarlos.', 'gdpr-consent-auditor' );
                }
                $message  = __( 'Escaneo completado.', 'gdpr-consent-auditor' );
                if ( ! empty( $warnings ) ) {
                        $message = sprintf(
                                /* translators: %d: warning count. */
                                _n( 'Escaneo completado con %d advertencia.', 'Escaneo completado con %d advertencias.', count( $warnings ), 'gdpr-consent-auditor' ),
                                count( $warnings )
                        );
                }

                wp_send_json_success( array(
                        'message'  => $message,
                        'summary'  => isset( $stored_results['summary'] ) ? $stored_results['summary'] : ( isset( $results['summary'] ) ? $results['summary'] : array() ),
                        'warnings' => $warnings,
                ) );
        }

        /**
         * Export the most recent report as .txt download.
         *
         * @return void
         */
        public function ajax_export_report() {
                check_ajax_referer( 'gdpr_ca_admin', 'nonce' );

                if ( ! current_user_can( self::CAP ) ) {
                        wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'gdpr-consent-auditor' ) ), 403 );
                }

                $report  = new GdprCa_Report_Generator();
                $content = $report->build_text();
                $name    = sprintf( 'gdpr-ca-report-%s.txt', gmdate( 'Y-m-d-His' ) );

                // Send file download.
                nocache_headers();
                header( 'Content-Type: text/plain; charset=utf-8' );
                header( 'Content-Disposition: attachment; filename="' . $name . '"' );
                header( 'Content-Length: ' . strlen( $content ) );
                echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- plain text report.
                exit;
        }

        /**
         * Export the most recent report as .csv download.
         *
         * @return void
         */
        public function ajax_export_report_csv() {
                check_ajax_referer( 'gdpr_ca_admin', 'nonce' );

                if ( ! current_user_can( self::CAP ) ) {
                        wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'gdpr-consent-auditor' ) ), 403 );
                }

                $report  = new GdprCa_Report_Generator();
                $content = $report->build_csv();
                $name    = sprintf( 'gdpr-ca-report-%s.csv', gmdate( 'Y-m-d-His' ) );

                nocache_headers();
                header( 'Content-Type: text/csv; charset=utf-8' );
                header( 'Content-Disposition: attachment; filename="' . $name . '"' );
                header( 'Content-Length: ' . strlen( $content ) );
                echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV with internal quoting.
                exit;
        }

        /**
         * Export the most recent report as .json download.
         *
         * @return void
         */
        public function ajax_export_report_json() {
                check_ajax_referer( 'gdpr_ca_admin', 'nonce' );

                if ( ! current_user_can( self::CAP ) ) {
                        wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'gdpr-consent-auditor' ) ), 403 );
                }

                $report  = new GdprCa_Report_Generator();
                $content = $report->build_json();
                $name    = sprintf( 'gdpr-ca-report-%s.json', gmdate( 'Y-m-d-His' ) );

                nocache_headers();
                header( 'Content-Type: application/json; charset=utf-8' );
                header( 'Content-Disposition: attachment; filename="' . $name . '"' );
                header( 'Content-Length: ' . strlen( $content ) );
                echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON.
                exit;
        }

        /**
         * Purge expired consent logs on demand.
         *
         * @return void
         */
        public function ajax_purge_logs() {
                check_ajax_referer( 'gdpr_ca_admin', 'nonce' );

                if ( ! current_user_can( self::CAP ) ) {
                        wp_send_json_error( array( 'message' => __( 'Permisos insuficientes.', 'gdpr-consent-auditor' ) ), 403 );
                }

                $manager = new GdprCa_Consent_Manager();
                $deleted = $manager->purge_expired_logs();

                wp_send_json_success( array(
                        'message' => sprintf(
                                /* translators: %d: number of deleted rows. */
                                _n( '%d consent log deleted.', '%d consent logs deleted.', $deleted, 'gdpr-consent-auditor' ),
                                $deleted
                        ),
                        'deleted' => $deleted,
                ) );
        }

        /**
         * Show a persistent admin notice reminding the user that
         * the legal templates need professional review.
         *
         * @return void
         */
        public function maybe_show_disclaimer_notice() {
                if ( ! current_user_can( self::CAP ) ) {
                        return;
                }
                $screen = get_current_screen();
                if ( ! $screen || false === strpos( $screen->id, self::SLUG ) ) {
                        return;
                }
                echo '<div class="notice notice-info"><p>';
                echo '<strong>' . esc_html__( 'Auditor GDPR:', 'gdpr-consent-auditor' ) . '</strong> ';
                echo esc_html__( 'Este plugin es una herramienta técnica de apoyo. No garantiza el cumplimiento legal. Haz que un profesional cualificado revise tus textos legales y tu flujo de consentimiento.', 'gdpr-consent-auditor' );
                echo '</p></div>';
        }
}
