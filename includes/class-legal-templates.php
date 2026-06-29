<?php
/**
 * Legal Templates: editable cookie policy, privacy notice and
 * banner text templates. Stored as settings; admin can edit them.
 *
 * All templates include a visible disclaimer that they are
 * orientative and must be reviewed by a legal professional.
 *
 * @package GdprConsentAuditor
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class GdprCa_Legal_Templates
 */
class GdprCa_Legal_Templates {

        /**
         * Default templates, keyed by slug.
         *
         * @return array
         */
        public static function defaults() {
                return array(
                        'cookie_policy' => __(
                                "# Política de cookies\n\nEsta Política de Cookies explica cómo {site_name} utiliza cookies y tecnologías similares en {site_url}. Su objetivo es ayudarte a entender qué datos se recogen, por qué se recogen y como puedes controlarlos.\n\n## 1. ¿Qué son las cookies?\nLas cookies son pequeños archivos de texto que se almacenan en tu dispositivo cuando visitas un sitio web. Se usan ampliamente para que los sitios funcionen de forma más eficiente y para proporcionar información a sus propietarios.\n\n## 2. Categorías de cookies utilizadas\n- Necesarias: imprescindibles para que el sitio funcione. No se pueden desactivar.\n- Preferencias: recuerdan tus ajustes (idioma, región).\n- Estadísticas: miden de forma anónima el uso del sitio para mejorarlo.\n- Marketing: se usan para mostrar publicidad relevante y medir el rendimiento de las campañas.\n\n## 3. Consentimiento\nPuedes aceptar o rechazar las cookies no necesarias en cualquier momento mediante el banner mostrado en tu primera visita. Después puedes retirar el consentimiento haciendo clic en el enlace \"Preferencias de cookies\" del pie de página.\n\n## 4. Servicios de terceros\nLa lista de servicios de terceros detectados en este sitio está disponible en el informe técnico de privacidad generado por el plugin Auditor GDPR.\n\n## 5. Actualizaciones\nPodemos actualizar esta politica. La fecha de \"última actualización\" que aparece abajo refleja la revisión más reciente.\n\nÚltima actualización: {date}\n\n---\nEsta plantilla es orientativa y debe ser revisada por un profesional legal cualificado antes de su publicación.",
                                'gdpr-consent-auditor'
                        ),
                        'privacy_notice' => __(
                                "# Aviso de privacidad\n\nEste Aviso de Privacidad explica cómo {site_name} recopila, usa y protege los datos personales de acuerdo con el Reglamento General de Protección de Datos de la UE (RGPD, Reglamento 2016/679), la Directiva ePrivacy (2002/58/CE) y la LOPDGDD española (Ley Orgánica 3/2018).\n\n## 1. Responsable del tratamiento\n{site_name}\nContacto: {contact_email}\n\n## 2. Datos personales que tratamos\n- Datos identificativos (nombre, email) cuando contactas con nosotros o te registras.\n- Datos de uso (dirección IP, navegador, páginas visitadas) recogidos mediante cookies y analítica.\n\n## 3. Bases jurídicas\n- Ejecución de contrato (art. 6.1.b RGPD).\n- Consentimiento (art. 6.1.a RGPD + art. 5.3 ePrivacy).\n- Interés legítimo (art. 6.1.f RGPD).\n- Obligación legal (art. 6.1.c RGPD).\n\n## 4. Transferencias internacionales\nAlgunos encargados del tratamiento de terceros pueden transferir datos fuera del EEE. Cuando esto ocurra, deben existir garantías adecuadas, como las Cláusulas Contractuales Tipo.\n\n## 5. Tus derechos\nAcceso, rectificación, supresión, limitación, portabilidad, oposición y derecho a no ser objeto de decisiones automatizadas.\n\n## 6. Conservación\nLos datos se conservan solo durante el tiempo necesario para las finalidades descritas. Los registros de consentimiento se guardan durante el período configurado por la persona administradora del sitio.\n\n## 7. Actualizaciones\nEste aviso puede actualizarse. La fecha de \"última actualización\" refleja la revisión más reciente.\n\nÚltima actualización: {date}\n\n---\nEsta plantilla es orientativa y debe ser revisada por un profesional legal cualificado antes de su publicación.",
                                'gdpr-consent-auditor'
                        ),
                        'category_descriptions' => __(
                                "# Descripciones de categorías\n\n## Necesarias\nCookies necesarias para que el sitio funcione (inicio de sesión, carrito, seguridad). No se pueden desactivar.\n\n## Preferencias\nCookies que recuerdan tus preferencias (idioma, región, tema).\n\n## Estadísticas\nCookies que miden como se usa el sitio web, de forma agregada o seudonimizada, para mejorarlo.\n\n## Marketing\nCookies usadas para crear un perfil de tus intereses y mostrarte publicidad relevante.",
                                'gdpr-consent-auditor'
                        ),
                        'banner_text' => __(
                                "Usamos cookies y tecnologías similares para mejorar tu experiencia de navegación, ofrecer contenido o anuncios personalizados y analizar nuestro tráfico. Puedes aceptar todo, rechazar todo o configurar tus preferencias por categoría. Puedes cambiar tu elección en cualquier momento desde el enlace \"Preferencias de cookies\" del pie de página.",
                                'gdpr-consent-auditor'
                        ),
                );
        }

        /**
         * Get a template, falling back to default if empty.
         *
         * @param string $key Template slug.
         * @return string
         */
        public static function get( $key ) {
                $settings_key = 'legal_' . $key . '_text';
                $value        = gdpr_ca_get_setting( $settings_key, '' );
                if ( empty( $value ) ) {
                        $defaults = self::defaults();
                        $value    = isset( $defaults[ $key ] ) ? $defaults[ $key ] : '';
                }
                return self::interpolate( $value );
        }

        /**
         * Replace placeholders.
         *
         * @param string $text Template text.
         * @return string
         */
        public static function interpolate( $text ) {
                $replacements = array(
                        '{site_name}'     => get_bloginfo( 'name' ),
                        '{site_url}'      => home_url(),
                        '{contact_email}' => get_option( 'admin_email' ),
                        '{date}'          => date_i18n( get_option( 'date_format' ) ),
                );
                return strtr( $text, $replacements );
        }

        /**
         * Render the disclaimer that appears under every editable template.
         *
         * @return string
         */
        public static function disclaimer_html() {
                return '<p class="gdpr-ca-legal-disclaimer"><strong>'
                        . esc_html__( 'Aviso:', 'gdpr-consent-auditor' ) . '</strong> '
                        . esc_html__( 'Este contenido es una plantilla orientativa y debe ser revisado por un profesional legal cualificado antes de su publicación.', 'gdpr-consent-auditor' )
                        . '</p>';
        }

        /**
         * Get the list of template keys and their admin labels.
         *
         * @return array
         */
        public static function template_list() {
                return array(
                        'cookie_policy'         => __( 'Política de cookies', 'gdpr-consent-auditor' ),
                        'privacy_notice'        => __( 'Aviso de privacidad', 'gdpr-consent-auditor' ),
                        'category_descriptions' => __( 'Descripciones de categorías', 'gdpr-consent-auditor' ),
                        'banner_text'           => __( 'Mensaje del banner', 'gdpr-consent-auditor' ),
                );
        }
}