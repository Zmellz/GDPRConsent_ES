<?php
/**
 * Report Generator: turns classified scan results into a
 * human-readable technical privacy report.
 *
 * The report is structured for an administrator and is intentionally
 * formatted as plain HTML that can be embedded in the admin dashboard
 * or exported.
 *
 * @package GdprConsentAuditor
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class GdprCa_Report_Generator
 */
class GdprCa_Report_Generator {

        /**
         * Build the report.
         *
         * @param array|null $scan_results Pre-computed scan results. If null,
         *                                 the most recent cached results are used.
         * @return array
         */
        public function build( $scan_results = null ) {
                if ( null === $scan_results ) {
                        $scan_results = gdpr_ca_get_last_scan_results();
                }

                $classifier = new GdprCa_Classifier();
                $classified = $classifier->classify( $scan_results );

                $summary = isset( $classified['summary'] ) ? $classified['summary'] : array();
                $overall = isset( $summary['overall_risk'] ) ? $summary['overall_risk'] : 'low';

                $items = array();
                foreach ( array( 'plugins', 'scripts', 'services', 'cookies' ) as $section ) {
                        if ( ! empty( $classified[ $section ] ) && is_array( $classified[ $section ] ) ) {
                                foreach ( $classified[ $section ] as $row ) {
                                        $items[] = array(
                                                'section'          => $section,
                                                'name'             => isset( $row['name'] ) ? $row['name'] : ( isset( $row['handle'] ) ? $row['handle'] : '' ),
                                                'source'           => isset( $row['source'] ) ? $row['source'] : '',
                                                'type'             => isset( $row['type'] ) ? $row['type'] : $section,
                                                'category'         => isset( $row['classification']['final_category'] ) ? $row['classification']['final_category'] : '',
                                                'requires_consent' => isset( $row['classification']['final_requires_consent'] ) ? $row['classification']['final_requires_consent'] : false,
                                                'risk'             => isset( $row['classification']['final_risk'] ) ? $row['classification']['final_risk'] : '',
                                                'recommendation'   => isset( $row['recommendation'] ) ? $row['recommendation'] : '',
                                                'legal_basis'      => isset( $row['classification']['legal_basis'] ) ? $row['classification']['legal_basis'] : array(),
                                                'summary_line'     => isset( $row['classification']['summary_line'] ) ? $row['classification']['summary_line'] : '',
                                        );
                                }
                        }
                }

                return array(
                        'title'        => __( 'Auditor GDPR - Informe técnico de privacidad', 'gdpr-consent-auditor' ),
                        'generated_at' => current_time( 'mysql' ),
                        'site_url'     => site_url(),
                        'overall_risk' => $overall,
                        'html'         => $this->render_html( $classified, $items, $summary, $overall ),
                        'summary'      => $summary,
                        'items'        => $items,
                );
        }

        /**
         * Render the report as HTML.
         *
         * @param array  $scan    Full scan results.
         * @param array  $items   Flat item list.
         * @param array  $summary Summary.
         * @param string $overall Overall risk.
         * @return string
         */
        private function render_html( $scan, $items, $summary, $overall ) {
                ob_start();

                echo '<div class="gdpr-ca-report">';
                echo '<div class="gdpr-ca-report-disclaimer">';
                echo '<strong>' . esc_html__( 'Aviso:', 'gdpr-consent-auditor' ) . '</strong> ';
                echo esc_html__( 'Este es un informe técnico de apoyo. No constituye asesoramiento legal ni garantiza el cumplimiento normativo. Haz que lo revise un profesional cualificado en privacidad.', 'gdpr-consent-auditor' );
                echo '</div>';

                echo '<h2>' . esc_html__( 'Resumen', 'gdpr-consent-auditor' ) . '</h2>';
                echo '<table class="widefat striped">';
                echo '<tbody>';
                echo '<tr><th>' . esc_html__( 'Plugins detectados', 'gdpr-consent-auditor' ) . '</th><td>' . esc_html( isset( $summary['plugin_count'] ) ? (int) $summary['plugin_count'] : 0 ) . '</td></tr>';
                echo '<tr><th>' . esc_html__( 'Plugins activos', 'gdpr-consent-auditor' ) . '</th><td>' . esc_html( isset( $summary['active_plugins'] ) ? (int) $summary['active_plugins'] : 0 ) . '</td></tr>';
                echo '<tr><th>' . esc_html__( 'Scripts registrados', 'gdpr-consent-auditor' ) . '</th><td>' . esc_html( isset( $summary['script_count'] ) ? (int) $summary['script_count'] : 0 ) . '</td></tr>';
                echo '<tr><th>' . esc_html__( 'Scripts externos', 'gdpr-consent-auditor' ) . '</th><td>' . esc_html( isset( $summary['external_scripts'] ) ? (int) $summary['external_scripts'] : 0 ) . '</td></tr>';
                echo '<tr><th>' . esc_html__( 'Servicios de terceros detectados', 'gdpr-consent-auditor' ) . '</th><td>' . esc_html( isset( $summary['known_services'] ) ? (int) $summary['known_services'] : 0 ) . '</td></tr>';
                echo '<tr><th>' . esc_html__( 'Cookies del lado servidor', 'gdpr-consent-auditor' ) . '</th><td>' . esc_html( isset( $summary['cookie_count'] ) ? (int) $summary['cookie_count'] : 0 ) . '</td></tr>';
                echo '<tr><th>' . esc_html__( 'Riesgo general', 'gdpr-consent-auditor' ) . '</th><td><span class="gdpr-ca-risk gdpr-ca-risk-' . esc_attr( $overall ) . '">' . esc_html( gdpr_ca_translate_risk_label( $overall ) ) . '</span></td></tr>';
                echo '</tbody>';
                echo '</table>';

                echo '<h2>' . esc_html__( 'Hallazgos detallados', 'gdpr-consent-auditor' ) . '</h2>';
                if ( empty( $items ) ) {
                        echo '<p>' . esc_html__( 'No se han detectado elementos. Ejecuta primero un escaneo.', 'gdpr-consent-auditor' ) . '</p>';
                } else {
                        echo '<table class="widefat striped gdpr-ca-report-table">';
                        echo '<thead><tr>';
                        echo '<th>' . esc_html__( 'Sección', 'gdpr-consent-auditor' ) . '</th>';
                        echo '<th>' . esc_html__( 'Nombre', 'gdpr-consent-auditor' ) . '</th>';
                        echo '<th>' . esc_html__( 'Origen', 'gdpr-consent-auditor' ) . '</th>';
                        echo '<th>' . esc_html__( 'Categoría', 'gdpr-consent-auditor' ) . '</th>';
                        echo '<th>' . esc_html__( 'Requiere consentimiento', 'gdpr-consent-auditor' ) . '</th>';
                        echo '<th>' . esc_html__( 'Riesgo', 'gdpr-consent-auditor' ) . '</th>';
                        echo '<th>' . esc_html__( 'Recomendación', 'gdpr-consent-auditor' ) . '</th>';
                        echo '<th>' . esc_html__( 'Base sugerida', 'gdpr-consent-auditor' ) . '</th>';
                        echo '</tr></thead>';
                        echo '<tbody>';
                        foreach ( $items as $item ) {
                                echo '<tr>';
                                echo '<td>' . esc_html( $item['section'] ) . '</td>';
                                echo '<td>' . esc_html( $item['name'] ) . '</td>';
                                echo '<td><code>' . esc_html( gdpr_ca_truncate_text( $item['source'], 80 ) ) . '</code></td>';
                                echo '<td>' . esc_html( ucfirst( $item['category'] ) ) . '</td>';
                                echo '<td>' . ( $item['requires_consent'] ? esc_html__( 'Sí', 'gdpr-consent-auditor' ) : esc_html__( 'No', 'gdpr-consent-auditor' ) ) . '</td>';
                                echo '<td><span class="gdpr-ca-risk gdpr-ca-risk-' . esc_attr( $item['risk'] ) . '">' . esc_html( gdpr_ca_translate_risk_label( $item['risk'] ) ) . '</span></td>';
                                echo '<td>' . esc_html( $item['recommendation'] ) . '</td>';
                                echo '<td>' . esc_html( isset( $item['legal_basis']['article'] ) ? $item['legal_basis']['article'] : '-' ) . '</td>';
                                echo '</tr>';
                        }
                        echo '</tbody>';
                        echo '</table>';
                }

                echo '</div>';
                return ob_get_clean();
        }

        /**
         * Render the report as plain text for export.
         *
         * @param array|null $scan_results Scan results.
         * @return string
         */
        public function build_text( $scan_results = null ) {
                $report = $this->build( $scan_results );
                $out    = array();
                $out[]  = $report['title'];
                $out[]  = str_repeat( '=', 70 );
                $out[]  = sprintf( 'Sitio: %s', $report['site_url'] );
                $out[]  = sprintf( 'Generado: %s', $report['generated_at'] );
                $out[]  = sprintf( 'Riesgo general: %s', $report['overall_risk'] );
                $out[]  = '';
                $out[]  = '--- AVISO LEGAL ---';
                $out[]  = 'Este es un informe técnico de apoyo. No constituye asesoramiento legal.';
                $out[]  = '';
                $out[]  = '--- HALLAZGOS ---';

                foreach ( $report['items'] as $item ) {
                        $out[] = sprintf(
                                "[%s] %s\n  origen: %s\n  categoría: %s | riesgo: %s | consentimiento: %s\n  base: %s\n  recomendación: %s",
                                strtoupper( $item['section'] ),
                                $item['name'],
                                $item['source'],
                                $item['category'],
                                $item['risk'],
                                $item['requires_consent'] ? 'SI' : 'NO',
                                isset( $item['legal_basis']['article'] ) ? $item['legal_basis']['article'] : '-',
                                $item['recommendation']
                        );
                }

                return implode( "\n", $out );
        }

        /**
         * Build the report as CSV.
         *
         * @param array|null $scan_results Scan results.
         * @return string
         */
        public function build_csv( $scan_results = null ) {
                $report = $this->build( $scan_results );

                $header = array(
                        'section',
                        'name',
                        'source',
                        'type',
                        'category',
                        'requires_consent',
                        'risk',
                        'recommendation',
                        'legal_basis',
                        'legal_basis_article',
                        'summary_line',
                );

                $rows = array( $this->csv_row( $header ) );
                foreach ( $report['items'] as $item ) {
                        $rows[] = $this->csv_row(
                                array(
                                        $item['section'],
                                        $item['name'],
                                        $item['source'],
                                        $item['type'],
                                        $item['category'],
                                        $item['requires_consent'] ? 'si' : 'no',
                                        $item['risk'],
                                        $item['recommendation'],
                                        isset( $item['legal_basis']['basis'] ) ? $item['legal_basis']['basis'] : '',
                                        isset( $item['legal_basis']['article'] ) ? $item['legal_basis']['article'] : '',
                                        $item['summary_line'],
                                )
                        );
                }

                $meta = array(
                        '# ' . $report['title'],
                        '# sitio: ' . $report['site_url'],
                        '# generado: ' . $report['generated_at'],
                        '# riesgo_general: ' . $report['overall_risk'],
                        '# aviso: Este es un informe técnico de apoyo. No constituye asesoramiento legal.',
                );

                return implode( "\n", $meta ) . "\n" . implode( "\n", $rows ) . "\n";
        }

        /**
         * Build the report as JSON.
         *
         * @param array|null $scan_results Scan results.
         * @return string
         */
        public function build_json( $scan_results = null ) {
                $report = $this->build( $scan_results );

                $payload = array(
                        'title'        => $report['title'],
                        'generated_at' => $report['generated_at'],
                        'site_url'     => $report['site_url'],
                        'overall_risk' => $report['overall_risk'],
                        'summary'      => $report['summary'],
                        'disclaimer'   => __( 'Este es un informe técnico de apoyo. No constituye asesoramiento legal.', 'gdpr-consent-auditor' ),
                        'items'        => $report['items'],
                );

                return wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
        }

        /**
         * Format a single CSV row with RFC-4180 quoting.
         *
         * @param array $cells Row cells.
         * @return string
         */
        private function csv_row( array $cells ) {
                $out = array();
                foreach ( $cells as $cell ) {
                        $cell = (string) $cell;
                        if ( preg_match( '/[,"\r\n]|^\s|\s$/', $cell ) ) {
                                $cell = '"' . str_replace( '"', '""', $cell ) . '"';
                        }
                        $out[] = $cell;
                }
                return implode( ',', $out );
        }
}