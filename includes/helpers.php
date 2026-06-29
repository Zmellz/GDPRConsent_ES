<?php
/**
 * Helper functions for the plugin.
 *
 * @package GdprConsentAuditor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get a plugin setting by key, with optional default.
 *
 * @param string $key     Setting key.
 * @param mixed  $default Default value if key not found.
 * @return mixed
 */
function gdpr_ca_get_setting( $key, $default = null ) {
	$settings = get_option( GDPR_CA_OPTION_NAME, array() );
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}
	if ( isset( $settings[ $key ] ) ) {
		return $settings[ $key ];
	}
	return $default;
}

/**
 * Update a single setting (merges into existing option array).
 *
 * @param string $key   Setting key.
 * @param mixed  $value Value to store.
 * @return bool
 */
function gdpr_ca_update_setting( $key, $value ) {
	$settings = get_option( GDPR_CA_OPTION_NAME, array() );
	if ( ! is_array( $settings ) ) {
		$settings = array();
	}
	$settings[ $key ] = $value;
	return update_option( GDPR_CA_OPTION_NAME, $settings );
}

/**
 * Update the entire settings array atomically.
 *
 * @param array $settings New settings array.
 * @return bool
 */
function gdpr_ca_update_settings( $settings ) {
	return update_option( GDPR_CA_OPTION_NAME, $settings );
}

/**
 * Truncate text safely even when the mbstring extension is unavailable.
 *
 * @param mixed $value  Value to truncate.
 * @param int   $length Maximum length.
 * @return string
 */
function gdpr_ca_truncate_text( $value, $length ) {
	$value  = (string) $value;
	$length = max( 0, (int) $length );

	if ( function_exists( 'mb_substr' ) ) {
		return mb_substr( $value, 0, $length );
	}

	return substr( $value, 0, $length );
}

/**
 * Sanitize a CSS hex color with a safe fallback.
 *
 * @param string $value    Raw color.
 * @param string $fallback Fallback hex color.
 * @return string
 */
function gdpr_ca_sanitize_hex_color( $value, $fallback ) {
	$value = is_string( $value ) ? trim( $value ) : '';

	if ( function_exists( 'sanitize_hex_color' ) ) {
		$clean = sanitize_hex_color( $value );
		return $clean ? $clean : $fallback;
	}

	return preg_match( '/^#(?:[0-9a-fA-F]{3}){1,2}$/', $value ) ? $value : $fallback;
}

/**
 * Bump the consent version. Should be called whenever the banner
 * configuration or category list changes — that invalidates all
 * previously captured consents.
 *
 * @return string New version number (string for safe storage).
 */
function gdpr_ca_bump_consent_version() {
	$current = (int) get_option( GDPR_CA_CONSENT_VERSION_OPTION, '1' );
	$new     = (string) ( $current + 1 );
	update_option( GDPR_CA_CONSENT_VERSION_OPTION, $new );
	return $new;
}

/**
 * Get the current consent version.
 *
 * @return string
 */
function gdpr_ca_get_consent_version() {
	return (string) get_option( GDPR_CA_CONSENT_VERSION_OPTION, '1' );
}

/**
 * Persist the latest scan results using dedicated storage plus legacy fallbacks.
 *
 * @param array $results Scan results.
 * @return bool
 */
function gdpr_ca_store_last_scan_results( $results ) {
	if ( ! is_array( $results ) ) {
		$results = array();
	}

	$stored = update_option( 'gdpr_ca_last_scan_results', $results, false );
	gdpr_ca_update_setting( 'last_scan_results', $results );
	set_transient( 'gdpr_ca_scan_results', $results, 12 * HOUR_IN_SECONDS );

	return $stored;
}

/**
 * Retrieve the latest scan results from dedicated storage with fallbacks.
 *
 * @return array
 */
function gdpr_ca_get_last_scan_results() {
	$results = get_option( 'gdpr_ca_last_scan_results', array() );
	if ( is_array( $results ) && ! empty( $results ) ) {
		return $results;
	}

	$results = gdpr_ca_get_setting( 'last_scan_results', array() );
	if ( is_array( $results ) && ! empty( $results ) ) {
		return $results;
	}

	$results = get_transient( 'gdpr_ca_scan_results' );
	return is_array( $results ) ? $results : array();
}

/**
 * Get the list of known third-party services and the patterns used
 * to detect them. Each entry has:
 *   'name'         — human-readable name
 *   'patterns'     — array of regex patterns (case-insensitive)
 *   'category'     — preferences|statistics|marketing
 *   'requires_consent' — bool
 *   'risk'         — low|medium|high
 *   'recommendation' — translated recommendation text (sprintf'd at scan time)
 *
 * @return array
 */
function gdpr_ca_known_services() {
	return array(
		'google_analytics' => array(
			'name'              => 'Google Analytics',
			'patterns'          => array( '/google\-analytics\.com/', '/googletagmanager\.com\/gtag\/js/', '/www\.google\-analytics\.com\/analytics\.js/', '/gtag\(.*UA\-/', '/gtag\(.*G\-/' ),
			'category'          => 'statistics',
			'requires_consent'  => true,
			'risk'              => 'medium',
			'recommendation'    => __( 'Categoría de estadísticas. Usa Google Consent Mode v2 o anonimiza la IP y carga tras el consentimiento.', 'gdpr-consent-auditor' ),
		),
		'google_tag_manager' => array(
			'name'              => 'Google Tag Manager',
			'patterns'          => array( '/googletagmanager\.com\/gtm\.js/', '/googletagmanager\.com\/ns\.html/' ),
			'category'          => 'statistics',
			'requires_consent'  => true,
			'risk'              => 'medium',
			'recommendation'    => __( 'Estadísticas / Marketing según las etiquetas. Bloquea el contenedor hasta recibir el consentimiento.', 'gdpr-consent-auditor' ),
		),
		'meta_pixel' => array(
			'name'              => 'Meta Pixel (Facebook)',
			'patterns'          => array( '/connect\.facebook\.net\/.*fbevents\.js/', '/fbq\(/', '/www\.facebook\.com\/tr/' ),
			'category'          => 'marketing',
			'requires_consent'  => true,
			'risk'              => 'high',
			'recommendation'    => __( 'Categoría Marketing. Debe bloquearse hasta contar con consentimiento explícito.', 'gdpr-consent-auditor' ),
		),
		'youtube' => array(
			'name'              => 'YouTube',
			'patterns'          => array( '/youtube\.com\/embed\//', '/youtu\.be\//', '/youtube\-nocookie\.com/' ),
			'category'          => 'marketing',
			'requires_consent'  => true,
			'risk'              => 'medium',
			'recommendation'    => __( 'Usa youtube-nocookie.com y carga tras el consentimiento, o sustitúyelo por un marcador de clic para cargar.', 'gdpr-consent-auditor' ),
		),
		'vimeo' => array(
			'name'              => 'Vimeo',
			'patterns'          => array( '/player\.vimeo\.com\/video\//' ),
			'category'          => 'marketing',
			'requires_consent'  => true,
			'risk'              => 'medium',
			'recommendation'    => __( 'Usa el modo privacidad de Vimeo y carga tras el consentimiento.', 'gdpr-consent-auditor' ),
		),
		'google_maps' => array(
			'name'              => 'Google Maps',
			'patterns'          => array( '/maps\.googleapis\.com/', '/maps\.google\.com\/maps/', '/www\.google\.com\/maps\/embed/' ),
			'category'          => 'preferences',
			'requires_consent'  => true,
			'risk'              => 'medium',
			'recommendation'    => __( 'Sustitúyelo por una vista previa estática del mapa y carga el mapa interactivo tras el consentimiento.', 'gdpr-consent-auditor' ),
		),
		'hotjar' => array(
			'name'              => 'Hotjar',
			'patterns'          => array( '/static\.hotjar\.com/', '/insights\.hotjar\.com/' ),
			'category'          => 'statistics',
			'requires_consent'  => true,
			'risk'              => 'medium',
			'recommendation'    => __( 'Categoría de estadísticas. Bloquéalo hasta el consentimiento y valora seudonimizar los identificadores de usuario.', 'gdpr-consent-auditor' ),
		),
		'tiktok_pixel' => array(
			'name'              => 'TikTok Pixel',
			'patterns'          => array( '/analytics\.tiktok\.com\/i18n\/pixel\/', '/ttq\(/' ),
			'category'          => 'marketing',
			'requires_consent'  => true,
			'risk'              => 'high',
			'recommendation'    => __( 'Categoría Marketing. Debe bloquearse hasta contar con consentimiento explícito.', 'gdpr-consent-auditor' ),
		),
		'linkedin_insight' => array(
			'name'              => 'LinkedIn Insight Tag',
			'patterns'          => array( '/snap\.licdn\.com/', '/px\.ads\.linkedin\.com/', '/_linkedin_data_partner/' ),
			'category'          => 'marketing',
			'requires_consent'  => true,
			'risk'              => 'high',
			'recommendation'    => __( 'Categoría Marketing. Debe bloquearse hasta contar con consentimiento explícito.', 'gdpr-consent-auditor' ),
		),
		'recaptcha' => array(
			'name'              => 'reCAPTCHA (Google)',
			'patterns'          => array( '/www\.google\.com\/recaptcha\/api\.js/', '/recaptcha\/enterprise\.js/' ),
			'category'          => 'necessary',
			'requires_consent'  => false,
			'risk'              => 'low',
			'recommendation'    => __( 'Necesario por seguridad. Puede cargarse antes del consentimiento; revisa qué datos procesa.', 'gdpr-consent-auditor' ),
		),
	);
}

/**
 * Hash an IP+UA for pseudonymous storage. Uses site-unique salt.
 *
 * @param string $ip  Client IP.
 * @param string $ua  User-Agent.
 * @return string 64-char hex SHA-256.
 */
function gdpr_ca_hash_identifier( $ip, $ua ) {
	$salt = wp_salt( 'nonce' ) . AUTH_SALT;
	return hash( 'sha256', $ip . '|' . $ua . '|' . $salt );
}

/**
 * Get the visitor IP address. Tries common reverse-proxy headers
 * but falls back to REMOTE_ADDR. Only the first IP is kept.
 *
 * @return string
 */
function gdpr_ca_get_client_ip() {
	$ip = '';

	if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
		$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
	}

	// Honor X-Forwarded-For only if the request comes through a trusted proxy.
	// We are intentionally conservative: do not trust XFF by default.
	if ( empty( $ip ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
		$forwarded = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		$parts     = explode( ',', $forwarded );
		if ( ! empty( $parts[0] ) ) {
			$ip = trim( $parts[0] );
		}
	}

	return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
}

/**
 * Sanitize the categories array coming from the consent form.
 *
 * @param mixed $input Raw input.
 * @return array
 */
function gdpr_ca_sanitize_categories( $input ) {
	if ( ! is_array( $input ) ) {
		return array();
	}
	$allowed = array_keys( gdpr_ca_known_categories() );
	$out     = array();
	foreach ( $input as $cat ) {
		$cat = sanitize_key( $cat );
		if ( in_array( $cat, $allowed, true ) ) {
			$out[] = $cat;
		}
	}
	return $out;
}

/**
 * Known consent categories and their metadata.
 *
 * @return array
 */
function gdpr_ca_known_categories() {
	return array(
		'necessary'   => array(
			'label'    => __( 'Necesarias', 'gdpr-consent-auditor' ),
			'always_on' => true,
		),
		'preferences' => array(
			'label'    => __( 'Preferencias', 'gdpr-consent-auditor' ),
			'always_on' => false,
		),
		'statistics'  => array(
			'label'    => __( 'Estadísticas', 'gdpr-consent-auditor' ),
			'always_on' => false,
		),
		'marketing'   => array(
			'label'    => __( 'Marketing', 'gdpr-consent-auditor' ),
			'always_on' => false,
		),
	);
}

/**
 * Convert a hex color to an RGBA string, applying an opacity percentage.
 *
 * @param string $hex      Hex color (e.g. #ff0000 or #f00).
 * @param int    $opacity  Opacity percentage 0-100 (100 = fully opaque).
 * @return string RGBA string or fallback hex if parsing fails.
 */
function gdpr_ca_hex_to_rgba( $hex, $opacity = 100 ) {
        $hex = ltrim( $hex, '#' );
        $opacity = max( 0, min( 100, (int) $opacity ) );

        if ( 3 === strlen( $hex ) ) {
                $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }

        if ( 6 !== strlen( $hex ) ) {
                return '#' . $hex;
        }

        $r = hexdec( substr( $hex, 0, 2 ) );
        $g = hexdec( substr( $hex, 2, 2 ) );
        $b = hexdec( substr( $hex, 4, 2 ) );

        if ( $opacity >= 100 ) {
                return "rgb({$r},{$g},{$b})";
        }

        $a = round( $opacity / 100, 2 );
        return "rgba({$r},{$g},{$b},{$a})";
}

/**
 * Map an internal category to a Google Consent Mode v2 purpose.
 *
 * @param string $category necessary|preferences|statistics|marketing.
 * @return string ads_storage|analytics_storage|functionality_storage|personalization_storage|security_storage
 */
function gdpr_ca_category_to_gcm( $category ) {
	$map = array(
		'necessary'   => 'security_storage',
		'preferences' => 'functionality_storage',
		'statistics'  => 'analytics_storage',
		'marketing'   => 'ad_storage',
	);
	return isset( $map[ $category ] ) ? $map[ $category ] : 'ad_storage';
}

/**
 * Translate an internal risk slug into a user-facing Spanish label.
 *
 * @param string $risk Risk slug.
 * @return string
 */
function gdpr_ca_translate_risk_label( $risk ) {
	$risk = strtolower( (string) $risk );
	if ( 'high' === $risk ) {
		return __( 'Alto', 'gdpr-consent-auditor' );
	}
	if ( 'medium' === $risk ) {
		return __( 'Medio', 'gdpr-consent-auditor' );
	}
	return __( 'Bajo', 'gdpr-consent-auditor' );
}
