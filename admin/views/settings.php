<?php
/**
 * View: Settings (storage, blocking, integrations).
 *
 * @package GdprConsentAuditor
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/** @var array $settings */

$value = function( $key, $default = '' ) use ( $settings ) {
        return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
};
?>
<div class="wrap gdpr-ca-wrap">
        <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

        <form method="post" action="options.php">
                <?php settings_fields( 'gdpr_ca_settings_group' ); ?>

                <h2><?php esc_html_e( 'Almacenamiento de registros de consentimiento', 'gdpr-consent-auditor' ); ?></h2>
                <table class="form-table">
                        <tr>
                                <th scope="row"><?php esc_html_e( 'Guardar registros de consentimiento en la base de datos', 'gdpr-consent-auditor' ); ?></th>
                                <td>
                                        <label>
                                                <input type="checkbox" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[log_consents]" value="1" <?php checked( $value( 'log_consents', 1 ), 1 ); ?> />
                                                <?php esc_html_e( 'Activar el registro en servidor de los eventos de consentimiento (recomendado para trazabilidad).', 'gdpr-consent-auditor' ); ?>
                                        </label>
                                </td>
                        </tr>
                        <tr>
                                <th scope="row"><?php esc_html_e( 'Hashear direcciones IP', 'gdpr-consent-auditor' ); ?></th>
                                <td>
                                        <label>
                                                <input type="checkbox" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[hash_ip]" value="1" <?php checked( $value( 'hash_ip', 1 ), 1 ); ?> />
                                                <?php esc_html_e( 'Guardar un hash SHA-256 de IP + User-Agent en lugar de la IP en bruto (seudonimización por defecto).', 'gdpr-consent-auditor' ); ?>
                                        </label>
                                </td>
                        </tr>
                        <tr>
                                <th scope="row"><?php esc_html_e( 'Retención (días)', 'gdpr-consent-auditor' ); ?></th>
                                <td>
                                        <input type="number" min="1" max="3650" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[log_retention_days]" value="<?php echo esc_attr( $value( 'log_retention_days', 365 ) ); ?>" />
                                        <p class="description"><?php esc_html_e( 'Los registros más antiguos que este límite se eliminarán con el cron diario o con el botón "Purgar" de la página de registros.', 'gdpr-consent-auditor' ); ?></p>
                                </td>
                        </tr>
                </table>

                <h2><?php esc_html_e( 'Bloqueo de scripts', 'gdpr-consent-auditor' ); ?></h2>
                <table class="form-table">
                        <tr>
                                <th scope="row"><?php esc_html_e( 'Bloquear scripts externos antes del consentimiento', 'gdpr-consent-auditor' ); ?></th>
                                <td>
                                        <label>
                                                <input type="checkbox" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[block_scripts]" value="1" <?php checked( $value( 'block_scripts', 1 ), 1 ); ?> />
                                                <?php esc_html_e( 'Envuelve los scripts de seguimiento conocidos con `type="text/plain"` hasta que se conceda el consentimiento.', 'gdpr-consent-auditor' ); ?>
                                        </label>
                                </td>
                        </tr>
                        <tr>
                                <th scope="row"><?php esc_html_e( 'Bloquear iframes antes del consentimiento', 'gdpr-consent-auditor' ); ?></th>
                                <td>
                                        <label>
                                                <input type="checkbox" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[block_iframes]" value="1" <?php checked( $value( 'block_iframes', 1 ), 1 ); ?> />
                                                <?php esc_html_e( 'Sustituye los iframes de terceros por un marcador que se carga al hacer clic.', 'gdpr-consent-auditor' ); ?>
                                        </label>
                                </td>
                        </tr>
                        <tr>
                                <th scope="row"><?php esc_html_e( 'Bloquear scripts cargados dinámicamente', 'gdpr-consent-auditor' ); ?></th>
                                <td>
                                        <label>
                                                <input type="checkbox" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[block_dynamic_scripts]" value="1" <?php checked( $value( 'block_dynamic_scripts', 0 ), 1 ); ?> />
                                                <?php esc_html_e( 'Intercepta `document.createElement(\'script\')` y ajusta `Element.prototype.setAttribute` para envolver scripts añadidos dinámicamente por JavaScript, por ejemplo mediante GTM o cargadores de píxeles.', 'gdpr-consent-auditor' ); ?>
                                        </label>
                                        <p class="description">
                                                <?php esc_html_e( 'Esto inyecta una pequeña capa JavaScript al principio de `<head>`, antes de que se ejecute cualquier otro script. Se recomienda en sitios que usan Google Tag Manager, fragmentos del cargador de Meta Pixel u otros cargadores dinámicos. Puede interferir con plugins que dependan de esta inyección dinámica, así que conviene probarlo antes de activarlo en producción.', 'gdpr-consent-auditor' ); ?>
                                        </p>
                                </td>
                        </tr>
                </table>

                <h2><?php esc_html_e( 'Google Consent Mode v2', 'gdpr-consent-auditor' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Emite el estado de consentimiento por defecto y lo actualiza cuando la persona visitante acepta o rechaza categorías. Úsalo junto con Google Analytics 4 y Google Ads.', 'gdpr-consent-auditor' ); ?></p>
                <table class="form-table">
                        <tr>
                                <th scope="row"><?php esc_html_e( 'Activar Consent Mode v2', 'gdpr-consent-auditor' ); ?></th>
                                <td>
                                        <label>
                                                <input type="checkbox" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[gcm_v2_enabled]" value="1" <?php checked( $value( 'gcm_v2_enabled', 0 ), 1 ); ?> />
                                                <?php esc_html_e( 'Inyecta el estado de consentimiento por defecto antes de que se renderice la página.', 'gdpr-consent-auditor' ); ?>
                                        </label>
                                </td>
                        </tr>
                        <tr>
                                <th scope="row"><?php esc_html_e( 'Valor por defecto de ads_storage', 'gdpr-consent-auditor' ); ?></th>
                                <td>
                                        <select name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[gcm_v2_default_ads]">
                                                <option value="denied" <?php selected( $value( 'gcm_v2_default_ads', 'denied' ), 'denied' ); ?>><?php esc_html_e( 'Denegado', 'gdpr-consent-auditor' ); ?></option>
                                                <option value="granted" <?php selected( $value( 'gcm_v2_default_ads', 'denied' ), 'granted' ); ?>><?php esc_html_e( 'Concedido', 'gdpr-consent-auditor' ); ?></option>
                                        </select>
                                </td>
                        </tr>
                        <tr>
                                <th scope="row"><?php esc_html_e( 'Valor por defecto de analytics_storage', 'gdpr-consent-auditor' ); ?></th>
                                <td>
                                        <select name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[gcm_v2_default_analytics]">
                                                <option value="denied" <?php selected( $value( 'gcm_v2_default_analytics', 'denied' ), 'denied' ); ?>><?php esc_html_e( 'Denegado', 'gdpr-consent-auditor' ); ?></option>
                                                <option value="granted" <?php selected( $value( 'gcm_v2_default_analytics', 'denied' ), 'granted' ); ?>><?php esc_html_e( 'Concedido', 'gdpr-consent-auditor' ); ?></option>
                                        </select>
                                </td>
                        </tr>
                        <tr>
                                <th scope="row"><?php esc_html_e( 'Valor por defecto de functionality_storage', 'gdpr-consent-auditor' ); ?></th>
                                <td>
                                        <select name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[gcm_v2_default_functional]">
                                                <option value="denied" <?php selected( $value( 'gcm_v2_default_functional', 'denied' ), 'denied' ); ?>><?php esc_html_e( 'Denegado', 'gdpr-consent-auditor' ); ?></option>
                                                <option value="granted" <?php selected( $value( 'gcm_v2_default_functional', 'denied' ), 'granted' ); ?>><?php esc_html_e( 'Concedido', 'gdpr-consent-auditor' ); ?></option>
                                        </select>
                                </td>
                        </tr>
                        <tr>
                                <th scope="row"><?php esc_html_e( 'Valor por defecto de ad_personalization', 'gdpr-consent-auditor' ); ?></th>
                                <td>
                                        <select name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[gcm_v2_default_personalized_ads]">
                                                <option value="denied" <?php selected( $value( 'gcm_v2_default_personalized_ads', 'denied' ), 'denied' ); ?>><?php esc_html_e( 'Denegado', 'gdpr-consent-auditor' ); ?></option>
                                                <option value="granted" <?php selected( $value( 'gcm_v2_default_personalized_ads', 'denied' ), 'granted' ); ?>><?php esc_html_e( 'Concedido', 'gdpr-consent-auditor' ); ?></option>
                                        </select>
                                </td>
                        </tr>
                </table>

                <h2><?php esc_html_e( 'Modo de consentimiento de Meta Pixel', 'gdpr-consent-auditor' ); ?></h2>
                <p class="description"><?php esc_html_e( 'Emite `fbq(\'consent\', \'revoke\')` por defecto y lo actualiza a `fbq(\'consent\', \'grant\')` solo cuando la persona visitante acepta la categoría de Marketing. El píxel lo cargas tú o un plugin colaborador; esta integración solo gestiona el estado del consentimiento.', 'gdpr-consent-auditor' ); ?></p>
                <table class="form-table">
                        <tr>
                                <th scope="row"><?php esc_html_e( 'Activar el modo de consentimiento de Meta Pixel', 'gdpr-consent-auditor' ); ?></th>
                                <td>
                                        <label>
                                                <input type="checkbox" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[meta_pixel_consent_enabled]" value="1" <?php checked( $value( 'meta_pixel_consent_enabled', 0 ), 1 ); ?> />
                                                <?php esc_html_e( 'Inyecta el estado por defecto `fbq(\'consent\', \'revoke\')` y lo actualiza cuando hay consentimiento.', 'gdpr-consent-auditor' ); ?>
                                        </label>
                                </td>
                        </tr>
                        <tr>
                                <th scope="row"><?php esc_html_e( 'ID de Meta Pixel', 'gdpr-consent-auditor' ); ?></th>
                                <td>
                                        <input type="text" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[meta_pixel_id]" value="<?php echo esc_attr( $value( 'meta_pixel_id', '' ) ); ?>" placeholder="<?php esc_attr_e( 'p. ej. 1234567890123456', 'gdpr-consent-auditor' ); ?>" />
                                        <p class="description"><?php esc_html_e( 'ID del píxel de 15 o 16 dígitos. Si se informa, el plugin también iniciará la cola base de `fbq` sin autoconfiguración, para que sigas controlando qué eventos se envían.', 'gdpr-consent-auditor' ); ?></p>
                                </td>
                        </tr>
                </table>

                <?php submit_button( __( 'Guardar ajustes', 'gdpr-consent-auditor' ) ); ?>
        </form>
</div>
