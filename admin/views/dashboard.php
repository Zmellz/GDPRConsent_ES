<?php
/**
 * View: Dashboard.
 *
 * @package GdprConsentAuditor
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/** @var array $settings */
/** @var string $last_scan */
/** @var array $summary */
/** @var string $report_html */

$overall = isset( $summary['overall_risk'] ) ? $summary['overall_risk'] : 'low';
?>
<div class="wrap gdpr-ca-wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

        <div class="gdpr-ca-cards">
                <div class="gdpr-ca-card">
                        <span class="gdpr-ca-card-label"><?php esc_html_e( 'Plugins activos', 'gdpr-consent-auditor' ); ?></span>
                        <span class="gdpr-ca-card-value"><?php echo esc_html( isset( $summary['active_plugins'] ) ? (int) $summary['active_plugins'] : 0 ); ?></span>
                </div>
                <div class="gdpr-ca-card">
                        <span class="gdpr-ca-card-label"><?php esc_html_e( 'Scripts externos', 'gdpr-consent-auditor' ); ?></span>
                        <span class="gdpr-ca-card-value"><?php echo esc_html( isset( $summary['external_scripts'] ) ? (int) $summary['external_scripts'] : 0 ); ?></span>
                </div>
                <div class="gdpr-ca-card">
                        <span class="gdpr-ca-card-label"><?php esc_html_e( 'Servicios detectados', 'gdpr-consent-auditor' ); ?></span>
                        <span class="gdpr-ca-card-value"><?php echo esc_html( isset( $summary['known_services'] ) ? (int) $summary['known_services'] : 0 ); ?></span>
                </div>
                <div class="gdpr-ca-card">
                        <span class="gdpr-ca-card-label"><?php esc_html_e( 'Riesgo general', 'gdpr-consent-auditor' ); ?></span>
                        <span class="gdpr-ca-card-value">
                                <span class="gdpr-ca-risk gdpr-ca-risk-<?php echo esc_attr( $overall ); ?>"><?php echo esc_html( gdpr_ca_translate_risk_label( $overall ) ); ?></span>
                        </span>
                </div>
        </div>

        <div class="gdpr-ca-actions">
                <button type="button" class="button button-primary button-large" id="gdpr-ca-run-scan">
                        <span class="dashicons dashicons-search"></span>
                        <?php esc_html_e( 'Ejecutar escaneo ahora', 'gdpr-consent-auditor' ); ?>
                </button>
                <button type="button" class="button button-secondary" id="gdpr-ca-export-report">
                        <span class="dashicons dashicons-download"></span>
                        <?php esc_html_e( 'Exportar (.txt)', 'gdpr-consent-auditor' ); ?>
                </button>
                <button type="button" class="button button-secondary" id="gdpr-ca-export-report-csv">
                        <span class="dashicons dashicons-media-spreadsheet"></span>
                        <?php esc_html_e( 'Exportar (.csv)', 'gdpr-consent-auditor' ); ?>
                </button>
                <button type="button" class="button button-secondary" id="gdpr-ca-export-report-json">
                        <span class="dashicons dashicons-media-code"></span>
                        <?php esc_html_e( 'Exportar (.json)', 'gdpr-consent-auditor' ); ?>
                </button>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=gdpr-ca-scan-results' ) ); ?>" class="button button-secondary">
                        <span class="dashicons dashicons-list-view"></span>
                        <?php esc_html_e( 'Ver resultados completos', 'gdpr-consent-auditor' ); ?>
                </a>
                <?php if ( ! empty( $last_scan ) ) : ?>
                        <span class="gdpr-ca-last-scan"><?php echo esc_html( sprintf( /* translators: %s: timestamp */ __( 'Último escaneo: %s', 'gdpr-consent-auditor' ), $last_scan ) ); ?></span>
                <?php endif; ?>
        </div>

        <p id="gdpr-ca-scan-status" class="gdpr-ca-scan-status" aria-live="polite"></p>

        <script>
        (function () {
                if (window.__gdprCaScanBound) {
                        return;
                }
                window.__gdprCaScanBound = true;

                var button = document.getElementById('gdpr-ca-run-scan');
                var status = document.getElementById('gdpr-ca-scan-status');
                if (!button) {
                        return;
                }

                var config = {
                        ajaxUrl: <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>,
                        nonce: <?php echo wp_json_encode( wp_create_nonce( 'gdpr_ca_admin' ) ); ?>,
                        labels: {
                                idle: <?php echo wp_json_encode( __( 'Ejecutar escaneo ahora', 'gdpr-consent-auditor' ) ); ?>,
                                scanning: <?php echo wp_json_encode( __( 'Escaneando...', 'gdpr-consent-auditor' ) ); ?>,
                                done: <?php echo wp_json_encode( __( 'Escaneo completado.', 'gdpr-consent-auditor' ) ); ?>,
                                failed: <?php echo wp_json_encode( __( 'El escaneo ha fallado. Revisa el mensaje y vuelve a intentarlo.', 'gdpr-consent-auditor' ) ); ?>
                        }
                };

                function setStatus(message, isError) {
                        if (!status) {
                                return;
                        }
                        status.textContent = message || '';
                        status.className = 'gdpr-ca-scan-status' + (isError ? ' is-error' : '');
                }

                function setButton(label, disabled, spinning) {
                        button.disabled = !!disabled;
                        button.innerHTML = '<span class="dashicons ' + (spinning ? 'dashicons-update spin' : 'dashicons-search') + '"></span> ' + label;
                }

                function extractMessage(payload, fallback) {
                        if (!payload) {
                                return fallback;
                        }
                        if (payload.data && payload.data.message) {
                                return payload.data.message;
                        }
                        if (payload.message) {
                                return payload.message;
                        }
                        return fallback;
                }

                button.addEventListener('click', function (event) {
                        event.preventDefault();
                        setButton(config.labels.scanning, true, true);
                        setStatus(config.labels.scanning, false);

                        var body = new URLSearchParams();
                        body.append('action', 'gdpr_ca_run_scan');
                        body.append('nonce', config.nonce);

                        fetch(config.ajaxUrl, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                                },
                                body: body.toString()
                        }).then(function (response) {
                                return response.text().then(function (text) {
                                        var data = null;
                                        try {
                                                data = JSON.parse(text);
                                        } catch (error) {
                                                data = { success: false, message: text };
                                        }
                                        return { ok: response.ok, status: response.status, payload: data };
                                });
                        }).then(function (result) {
                                var ok = result.ok && result.payload && result.payload.success;
                                if (!ok) {
                                        var message = extractMessage(result.payload, config.labels.failed);
                                        setButton(config.labels.idle, false, false);
                                        setStatus(message, true);
                                        return;
                                }

                                var message = extractMessage(result.payload, config.labels.done);
                                setButton(config.labels.done, true, false);
                                setStatus(message, false);
                                window.setTimeout(function () {
                                        window.location.reload();
                                }, 900);
                        }).catch(function (error) {
                                setButton(config.labels.idle, false, false);
                                setStatus((error && error.message) || config.labels.failed, true);
                        });
                });
        })();
        </script>

        <div class="gdpr-ca-report-wrap">
                <?php echo $report_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML generated internally with escaping. ?>
        </div>
</div>
