<?php
/**
 * Scanner: detects plugins, theme, enqueued scripts, cookies
 * (declared), iframes and known third-party services.
 *
 * Note: server-side scan cannot read cookies that are HttpOnly
 * or set client-side after JS execution. Cookie detection is
 * limited to those the plugin/theme explicitly set via
 * setcookie()/Set-Cookie during the scan request.
 *
 * @package GdprConsentAuditor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GdprCa_Scanner
 */
class GdprCa_Scanner {

	/**
	 * Non-fatal warnings collected during the scan.
	 *
	 * @var array
	 */
	private $warnings = array();

	/**
	 * Run a full scan.
	 *
	 * @return array {
	 *     @type array $plugins
	 *     @type array $theme
	 *     @type array $scripts
	 *     @type array $services
	 *     @type array $cookies
	 *     @type array $summary
	 * }
	 */
	public function scan() {
		$this->warnings = array();
		$page_assets = $this->scan_homepage_assets();
		$scripts     = $this->dedupe_items( array_merge( $this->scan_scripts(), $page_assets ) );

		$results = array(
			'plugins'  => $this->scan_plugins(),
			'theme'    => $this->scan_theme(),
			'scripts'  => $scripts,
			'services' => $this->scan_known_services( $scripts ),
			'cookies'  => $this->scan_cookies(),
			'warnings' => $this->warnings,
			'summary'  => array(),
		);

		$results['summary'] = $this->build_summary( $results );
		return $results;
	}

	/**
	 * Scan installed and active plugins.
	 *
	 * @return array
	 */
	private function scan_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = (array) get_option( 'active_plugins', array() );
		$out            = array();

		foreach ( $all_plugins as $plugin_file => $data ) {
			$is_active = in_array( $plugin_file, $active_plugins, true );
			$out[]     = array(
				'name'              => $data['Name'],
				'version'           => isset( $data['Version'] ) ? $data['Version'] : '',
				'author'            => isset( $data['Author'] ) ? $data['Author'] : '',
				'plugin_uri'        => isset( $data['PluginURI'] ) ? $data['PluginURI'] : '',
				'source'            => $plugin_file,
				'type'              => 'plugin',
				'active'            => $is_active,
				'category'          => $this->guess_plugin_category( $data['Name'] ),
				'requires_consent'  => $this->plugin_requires_consent( $data['Name'] ),
				'risk'              => $this->plugin_risk_level( $data['Name'] ),
				'recommendation'    => $this->plugin_recommendation( $data['Name'] ),
			);
		}

		return $out;
	}

	/**
	 * Scan the active theme.
	 *
	 * @return array
	 */
	private function scan_theme() {
		$theme = wp_get_theme();
		return array(
			'name'           => $theme->get( 'Name' ),
			'version'        => $theme->get( 'Version' ),
			'author'         => $theme->get( 'Author' ),
			'theme_uri'      => $theme->get( 'ThemeURI' ),
			'template'       => $theme->get_template(),
			'source'         => $theme->get_stylesheet(),
			'type'           => 'theme',
			'requires_consent' => false,
			'risk'           => 'low',
			'recommendation' => __( 'Revisa las fuentes incluidas por el tema y cualquier petición HTTP externa. Prioriza recursos alojados localmente.', 'gdpr-consent-auditor' ),
		);
	}

	/**
	 * Inspect globally registered/enqueued scripts and styles.
	 *
	 * We hook into wp_print_scripts on a sandboxed request to capture
	 * the list of registered handles, then classify them.
	 *
	 * @return array
	 */
	private function scan_scripts() {
		global $wp_scripts, $wp_styles;

		$out = array();

		if ( ! $wp_scripts instanceof WP_Scripts ) {
			$wp_scripts = wp_scripts();
		}
		if ( ! $wp_styles instanceof WP_Styles ) {
			$wp_styles = wp_styles();
		}

		// Scripts.
		foreach ( (array) $wp_scripts->registered as $handle => $dep ) {
			$src = isset( $dep->src ) ? $dep->src : '';
			if ( empty( $src ) ) {
				continue;
			}
			$service = $this->match_service( $src );

			$out[] = array(
				'handle'            => $handle,
				'source'            => $src,
				'type'              => 'script',
				'external'          => $this->is_external( $src ),
				'category'          => $service ? $service['category'] : $this->guess_category_for_url( $src ),
				'requires_consent'  => $service ? $service['requires_consent'] : $this->is_external( $src ),
				'risk'              => $service ? $service['risk'] : ( $this->is_external( $src ) ? 'medium' : 'low' ),
				'recommendation'    => $service ? $service['recommendation'] : __( 'Script local. Audita su comportamiento para confirmar que no realiza seguimiento.', 'gdpr-consent-auditor' ),
				'matched_service'   => $service ? $service['name'] : '',
			);
		}

		// Styles.
		foreach ( (array) $wp_styles->registered as $handle => $dep ) {
			$src = isset( $dep->src ) ? $dep->src : '';
			if ( empty( $src ) ) {
				continue;
			}
			// Only flag external styles — local styles are normally safe.
			if ( ! $this->is_external( $src ) ) {
				continue;
			}
			$service = $this->match_service( $src );

			$out[] = array(
				'handle'            => $handle,
				'source'            => $src,
				'type'              => 'style',
				'external'          => true,
				'category'          => $service ? $service['category'] : 'preferences',
				'requires_consent'  => $service ? $service['requires_consent'] : false,
				'risk'              => $service ? $service['risk'] : 'low',
				'recommendation'    => $service ? $service['recommendation'] : __( 'Hoja de estilos externa. Verifica que no incruste píxeles de seguimiento.', 'gdpr-consent-auditor' ),
				'matched_service'   => $service ? $service['name'] : '',
			);
		}

		return $out;
	}

	/**
	 * Fetch the public home page and inspect actual rendered script/style/iframe
	 * URLs. Admin AJAX does not naturally run the front-end enqueue pipeline, so
	 * this keeps "Run scan" useful from wp-admin.
	 *
	 * @return array
	 */
	private function scan_homepage_assets() {
		if ( ! function_exists( 'wp_remote_get' ) || ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
			$this->add_warning( __( 'No se pudo inspeccionar la portada pública porque la API HTTP de WordPress no está disponible.', 'gdpr-consent-auditor' ) );
			return array();
		}

		$response = wp_remote_get(
			home_url( '/' ),
			array(
				'timeout'     => 10,
				'redirection' => 3,
				'user-agent'  => 'GDPR Consent Auditor/' . GDPR_CA_VERSION . '; ' . home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->add_warning(
				sprintf(
					/* translators: %s: error message. */
					__( 'No se pudo inspeccionar la portada pública: %s', 'gdpr-consent-auditor' ),
					$response->get_error_message()
				)
			);
			return array();
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			$this->add_warning(
				sprintf(
					/* translators: %d: HTTP status code. */
					__( 'La portada pública devolvió el código HTTP %d durante el escaneo.', 'gdpr-consent-auditor' ),
					(int) wp_remote_retrieve_response_code( $response )
				)
			);
			return array();
		}

		$html = wp_remote_retrieve_body( $response );
		if ( ! is_string( $html ) || '' === $html ) {
			$this->add_warning( __( 'La portada pública respondió sin HTML legible; el escaneo puede estar incompleto.', 'gdpr-consent-auditor' ) );
			return array();
		}

		$out = array();

		if ( preg_match_all( '/<script\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>/i', $html, $script_matches ) ) {
			foreach ( $script_matches[1] as $src ) {
				$src = $this->normalize_asset_url( html_entity_decode( $src, ENT_QUOTES, 'UTF-8' ) );
				if ( '' === $src ) {
					continue;
				}
				$service  = $this->match_service( $src );
				$external = $this->is_external( $src );
				$out[] = array(
					'handle'            => 'homepage-script',
					'source'            => $src,
					'type'              => 'script',
					'external'          => $external,
					'category'          => $service ? $service['category'] : $this->guess_category_for_url( $src ),
					'requires_consent'  => $service ? $service['requires_consent'] : $external,
					'risk'              => $service ? $service['risk'] : ( $external ? 'medium' : 'low' ),
					'recommendation'    => $service ? $service['recommendation'] : __( 'Detectado en la portada pública. Revisa si instala cookies o envía datos personales antes del consentimiento.', 'gdpr-consent-auditor' ),
					'matched_service'   => $service ? $service['name'] : '',
				);
			}
		}

		if ( preg_match_all( '/<iframe\b[^>]*\bsrc=["\']([^"\']+)["\'][^>]*>/i', $html, $iframe_matches ) ) {
			foreach ( $iframe_matches[1] as $src ) {
				$src = $this->normalize_asset_url( html_entity_decode( $src, ENT_QUOTES, 'UTF-8' ) );
				if ( '' === $src ) {
					continue;
				}
				$service  = $this->match_service( $src );
				$external = $this->is_external( $src );
				$out[] = array(
					'handle'            => 'homepage-iframe',
					'source'            => $src,
					'type'              => 'iframe',
					'external'          => $external,
					'category'          => $service ? $service['category'] : $this->guess_category_for_url( $src ),
					'requires_consent'  => $service ? $service['requires_consent'] : $external,
					'risk'              => $service ? $service['risk'] : ( $external ? 'medium' : 'low' ),
					'recommendation'    => $service ? $service['recommendation'] : __( 'Detectado en la portada pública. Revisa si instala cookies o envía datos personales antes del consentimiento.', 'gdpr-consent-auditor' ),
					'matched_service'   => $service ? $service['name'] : '',
				);
			}
		}

		if ( preg_match_all( '/<link\b([^>]*)>/i', $html, $link_matches ) ) {
			foreach ( $link_matches[1] as $attrs ) {
				if ( ! preg_match( '/\bhref=["\']([^"\']+)["\']/i', $attrs, $href_match ) ) {
					continue;
				}
				$rel = '';
				if ( preg_match( '/\brel=["\']([^"\']+)["\']/i', $attrs, $rel_match ) ) {
					$rel = strtolower( trim( $rel_match[1] ) );
				}
				if ( false === strpos( ' ' . $rel . ' ', ' stylesheet ' ) ) {
					continue;
				}
				$src = $this->normalize_asset_url( html_entity_decode( $href_match[1], ENT_QUOTES, 'UTF-8' ) );
				if ( '' === $src ) {
					continue;
				}
				$service  = $this->match_service( $src );
				if ( ! $this->is_relevant_style_source( $src, $service ) ) {
					continue;
				}
				$out[] = array(
					'handle'            => 'homepage-style',
					'source'            => $src,
					'type'              => 'style',
					'external'          => $this->is_external( $src ),
					'category'          => $service ? $service['category'] : 'preferences',
					'requires_consent'  => $service ? $service['requires_consent'] : false,
					'risk'              => $service ? $service['risk'] : 'low',
					'recommendation'    => $service ? $service['recommendation'] : __( 'Hoja de estilos o fuente remota relevante para privacidad. Revisa si conviene alojarla localmente.', 'gdpr-consent-auditor' ),
					'matched_service'   => $service ? $service['name'] : '',
				);
			}
		}

		return $out;
	}

	/**
	 * Detect known third-party services by scanning registered
	 * script URLs and any inline scripts captured during the scan.
	 *
	 * @return array
	 */
	private function scan_known_services( $items = array() ) {
		global $wp_scripts;
		$urls = array();

		if ( $wp_scripts instanceof WP_Scripts ) {
			foreach ( (array) $wp_scripts->registered as $dep ) {
				if ( ! empty( $dep->src ) ) {
					$urls[] = (string) $dep->src;
				}
				// Capture inline 'before'/'after' scripts (e.g., gtag init).
				if ( ! empty( $dep->extra['before'] ) ) {
					foreach ( (array) $dep->extra['before'] as $extra ) {
						$urls[] = (string) $extra;
					}
				}
				if ( ! empty( $dep->extra['after'] ) ) {
					foreach ( (array) $dep->extra['after'] as $extra ) {
						$urls[] = (string) $extra;
					}
				}
			}
		}

		foreach ( (array) $items as $item ) {
			if ( ! empty( $item['source'] ) ) {
				$urls[] = (string) $item['source'];
			}
		}

		$haystack = implode( "\n", $urls );
		$found    = array();
		$services = gdpr_ca_known_services();

		foreach ( $services as $key => $service ) {
			foreach ( $service['patterns'] as $pattern ) {
				if ( preg_match( $pattern . 'i', $haystack ) ) {
					$found[ $key ] = array(
						'key'               => $key,
						'name'              => $service['name'],
						'category'          => $service['category'],
						'requires_consent'  => $service['requires_consent'],
						'risk'              => $service['risk'],
						'recommendation'    => $service['recommendation'],
						'type'              => 'service',
						'source'            => $pattern,
					);
					break;
				}
			}
		}

		return array_values( $found );
	}

	/**
	 * Scan cookies set during the request (server-side).
	 *
	 * Limitation: cookies set client-side by third-party JS will
	 * not be visible here. The banner includes a client-side
	 * cookie scanner that complements this list.
	 *
	 * @return array
	 */
	private function scan_cookies() {
		$out = array();
		if ( ! isset( $_COOKIE ) || empty( $_COOKIE ) ) {
			return $out;
		}
		foreach ( $_COOKIE as $name => $value ) {
			$out[] = array(
				'name'              => sanitize_text_field( $name ),
				'value_preview'     => gdpr_ca_truncate_text( sanitize_text_field( $value ), 40 ),
				'category'          => $this->guess_cookie_category( $name ),
				'requires_consent'  => $this->cookie_requires_consent( $name ),
				'risk'              => $this->cookie_risk( $name ),
				'recommendation'    => $this->cookie_recommendation( $name ),
			);
		}
		return $out;
	}

	/**
	 * Build a summary of the scan results.
	 *
	 * @param array $results Full results.
	 * @return array
	 */
	private function build_summary( $results ) {
		$plugin_count = count( $results['plugins'] );
		$active_count = 0;
		foreach ( $results['plugins'] as $p ) {
			if ( ! empty( $p['active'] ) ) {
				$active_count++;
			}
		}

		$external_scripts = 0;
		foreach ( $results['scripts'] as $s ) {
			if ( ! empty( $s['external'] ) ) {
				$external_scripts++;
			}
		}

		$services = count( $results['services'] );

		$risk = 'low';
		foreach ( $results['services'] as $s ) {
			if ( 'high' === $s['risk'] ) {
				$risk = 'high';
				break;
			}
			if ( 'medium' === $s['risk'] ) {
				$risk = 'medium';
			}
		}

		return array(
			'plugin_count'      => $plugin_count,
			'active_plugins'    => $active_count,
			'script_count'      => count( $results['scripts'] ),
			'external_scripts'  => $external_scripts,
			'known_services'    => $services,
			'cookie_count'      => count( $results['cookies'] ),
			'warning_count'     => isset( $results['warnings'] ) && is_array( $results['warnings'] ) ? count( $results['warnings'] ) : 0,
			'overall_risk'      => $risk,
		);
	}

	/**
	 * Store a non-fatal warning without duplicates.
	 *
	 * @param string $message Warning message.
	 * @return void
	 */
	private function add_warning( $message ) {
		$message = trim( (string) $message );
		if ( '' === $message || in_array( $message, $this->warnings, true ) ) {
			return;
		}
		$this->warnings[] = $message;
	}

	/* -----------------------------------------------------------------
	 * Heuristic helpers — used to classify unknown items.
	 * --------------------------------------------------------------- */

	/**
	 * Normalize protocol-relative and root-relative asset URLs.
	 *
	 * @param string $src Raw source.
	 * @return string
	 */
	/**
	 * Decide whether an external stylesheet is privacy-relevant enough to show
	 * as a finding instead of generic front-end noise.
	 *
	 * @param string     $src     URL.
	 * @param array|bool $service Matched known service, if any.
	 * @return bool
	 */
	private function is_relevant_style_source( $src, $service = false ) {
		if ( $service ) {
			return true;
		}

		return (bool) preg_match( '/fonts\.googleapis\.com|fonts\.gstatic\.com|fonts\.bunny\.net|use\.typekit\.net|kit\.fontawesome\.com|use\.fontawesome\.com/i', $src );
	}

	private function normalize_asset_url( $src ) {
		$src = trim( (string) $src );
		if ( '' === $src || 0 === strpos( $src, 'data:' ) ) {
			return '';
		}
		if ( 0 === strpos( $src, '//' ) ) {
			return ( is_ssl() ? 'https:' : 'http:' ) . $src;
		}
		if ( 0 === strpos( $src, '/' ) ) {
			return home_url( $src );
		}
		return $src;
	}

	/**
	 * Remove duplicate findings by type/source.
	 *
	 * @param array $items Findings.
	 * @return array
	 */
	private function dedupe_items( $items ) {
		$out  = array();
		$seen = array();
		foreach ( $items as $item ) {
			$key = strtolower( ( isset( $item['type'] ) ? $item['type'] : '' ) . '|' . ( isset( $item['source'] ) ? $item['source'] : '' ) );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * Is the URL external (different host)?
	 *
	 * @param string $src URL.
	 * @return bool
	 */
	private function is_external( $src ) {
		if ( empty( $src ) ) {
			return false;
		}
		if ( 0 === strpos( $src, '/' ) ) {
			return false;
		}
		$host = wp_parse_url( $src, PHP_URL_HOST );
		if ( empty( $host ) ) {
			return false;
		}
		$site_host = wp_parse_url( site_url(), PHP_URL_HOST );
		return ( $host !== $site_host );
	}

	/**
	 * Try to match a URL against the known services list.
	 *
	 * @param string $haystack URL or inline script content.
	 * @return array|false
	 */
	private function match_service( $haystack ) {
		$services = gdpr_ca_known_services();
		foreach ( $services as $service ) {
			foreach ( $service['patterns'] as $pattern ) {
				if ( preg_match( $pattern . 'i', $haystack ) ) {
					return $service;
				}
			}
		}
		return false;
	}

	/**
	 * Guess the category for an arbitrary URL based on keywords.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function guess_category_for_url( $url ) {
		$url_lower = strtolower( $url );
		if ( false !== strpos( $url_lower, 'ads' ) || false !== strpos( $url_lower, 'adsense' ) || false !== strpos( $url_lower, 'doubleclick' ) ) {
			return 'marketing';
		}
		if ( false !== strpos( $url_lower, 'stats' ) || false !== strpos( $url_lower, 'analytics' ) ) {
			return 'statistics';
		}
		return 'preferences';
	}

	/**
	 * Guess the category of a plugin by name.
	 *
	 * @param string $name Plugin name.
	 * @return string
	 */
	private function guess_plugin_category( $name ) {
		$name_lower = strtolower( $name );
		if ( preg_match( '/analytic|stat|track|pixel|tag manager|matomo|hotjar/i', $name_lower ) ) {
			return 'statistics';
		}
		if ( preg_match( '/ads|advertising|marketing|popup|newsletter|mailchimp|hubspot|facebook|meta pixel|tiktok|linkedin/i', $name_lower ) ) {
			return 'marketing';
		}
		if ( preg_match( '/cache|security|seo|optimize|speed|lazy|cloudflare/i', $name_lower ) ) {
			return 'necessary';
		}
		return 'preferences';
	}

	/**
	 * Does the plugin likely require consent?
	 *
	 * @param string $name Plugin name.
	 * @return bool
	 */
	private function plugin_requires_consent( $name ) {
		$name_lower = strtolower( $name );
		return (bool) preg_match( '/analytic|stat|track|pixel|tag manager|matomo|hotjar|ads|advertising|marketing|popup|newsletter|mailchimp|hubspot|facebook|meta pixel|tiktok|linkedin|youtube|vimeo|maps|chat|gtag/i', $name_lower );
	}

	/**
	 * Risk level of a plugin by name.
	 *
	 * @param string $name Plugin name.
	 * @return string
	 */
	private function plugin_risk_level( $name ) {
		$name_lower = strtolower( $name );
		if ( preg_match( '/facebook|meta pixel|tiktok|linkedin|ads|advertising|marketing/i', $name_lower ) ) {
			return 'high';
		}
		if ( preg_match( '/analytic|stat|track|pixel|tag manager|matomo|hotjar|newsletter|mailchimp|hubspot|youtube|vimeo|chat/i', $name_lower ) ) {
			return 'medium';
		}
		return 'low';
	}

	/**
	 * Recommendation text for a plugin.
	 *
	 * @param string $name Plugin name.
	 * @return string
	 */
	private function plugin_recommendation( $name ) {
		$category = $this->guess_plugin_category( $name );
		switch ( $category ) {
			case 'marketing':
				return __( 'Plugin de marketing. Bloquea sus scripts hasta que el usuario acepte la categoría Marketing.', 'gdpr-consent-auditor' );
			case 'statistics':
				return __( 'Plugin de estadísticas. Usa Consent Mode v2 o cárgalo después del consentimiento de Estadísticas.', 'gdpr-consent-auditor' );
			case 'preferences':
				return __( 'Plugin funcional. Revisa si procesa datos personales; si no, puede considerarse necesario.', 'gdpr-consent-auditor' );
			default:
				return __( 'Probablemente necesario. Verifica el tratamiento de datos y considera dejarlo siempre activo.', 'gdpr-consent-auditor' );
		}
	}

	/**
	 * Guess the category for a cookie by name.
	 *
	 * @param string $name Cookie name.
	 * @return string
	 */
	private function guess_cookie_category( $name ) {
		$name_lower = strtolower( $name );
		if ( preg_match( '/wordpress|wp\-|logged|comment_author|wp_settings/i', $name_lower ) ) {
			return 'necessary';
		}
		if ( preg_match( '/_ga|_gid|_gat|_gcl|__utma|pk_id|pk_ses|mtm_|_hj/i', $name_lower ) ) {
			return 'statistics';
		}
		if ( preg_match( '/_fbp|fr|tr|_pin_|tt_|li_srt|dsid|IDE|test_cookie|NID/i', $name_lower ) ) {
			return 'marketing';
		}
		return 'preferences';
	}

	/**
	 * Does the cookie likely require consent?
	 *
	 * @param string $name Cookie name.
	 * @return bool
	 */
	private function cookie_requires_consent( $name ) {
		$category = $this->guess_cookie_category( $name );
		return ( 'necessary' !== $category );
	}

	/**
	 * Risk of a cookie by name.
	 *
	 * @param string $name Cookie name.
	 * @return string
	 */
	private function cookie_risk( $name ) {
		$category = $this->guess_cookie_category( $name );
		if ( 'marketing' === $category ) {
			return 'high';
		}
		if ( 'statistics' === $category ) {
			return 'medium';
		}
		if ( 'preferences' === $category ) {
			return 'low';
		}
		return 'low';
	}

	/**
	 * Recommendation for a cookie.
	 *
	 * @param string $name Cookie name.
	 * @return string
	 */
	private function cookie_recommendation( $name ) {
		$category = $this->guess_cookie_category( $name );
		switch ( $category ) {
			case 'marketing':
				return __( 'Cookie de marketing. Debe bloquearse antes del consentimiento.', 'gdpr-consent-auditor' );
			case 'statistics':
				return __( 'Cookie de estadísticas. Anonimiza la IP y supedítala al consentimiento de Estadísticas.', 'gdpr-consent-auditor' );
			case 'preferences':
				return __( 'Cookie de preferencias. Permítela solo tras el consentimiento de Preferencias, o elimínala si no se usa.', 'gdpr-consent-auditor' );
			default:
				return __( 'Cookie necesaria. Puede instalarse sin consentimiento.', 'gdpr-consent-auditor' );
		}
	}
}
