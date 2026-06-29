<?php
/**
 * Script Blocker: wraps external scripts and iframes in a way that
 * prevents them from executing until the corresponding consent category
 * has been accepted by the visitor.
 *
 * Strategy: at wp_print_scripts / wp_print_styles we inspect the list
 * of registered handles and rewrite the tag to type="text/plain" with
 * a data-gdpr-ca-category attribute. The front-end JS re-activates
 * them when consent is granted.
 *
 * @package GdprConsentAuditor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GdprCa_Script_Blocker
 */
class GdprCa_Script_Blocker {

	/**
	 * Constructor: register filters.
	 */
	public function __construct() {
		if ( ! gdpr_ca_get_setting( 'block_scripts', 1 ) ) {
			return;
		}

		// Filter every enqueued script tag.
		add_filter( 'script_loader_tag', array( $this, 'filter_script_tag' ), 20, 3 );

		// Filter iframe-based embeds.
		if ( gdpr_ca_get_setting( 'block_iframes', 1 ) ) {
			add_filter( 'the_content', array( $this, 'filter_content_iframes' ), 20 );
			add_filter( 'widget_text_content', array( $this, 'filter_content_iframes' ), 20 );
		}

		// Block oEmbeds that come from known tracking providers.
		add_filter( 'oembed_result', array( $this, 'filter_oembed' ), 20, 3 );
	}

	/**
	 * Decide the category that a script belongs to.
	 *
	 * @param string $handle Handle.
	 * @param string $src    Script URL.
	 * @return string|null   Category slug or null if not blocked.
	 */
	private function resolve_category( $handle, $src ) {
		// 1) Manual overrides: admin-configured list of patterns.
		$manual = gdpr_ca_get_setting( 'manual_blocks', array() );
		if ( is_array( $manual ) && ! empty( $manual ) ) {
			foreach ( $manual as $rule ) {
				if ( empty( $rule['pattern'] ) || empty( $rule['category'] ) ) {
					continue;
				}
                                $pattern = $rule['pattern'];
                                // Validate the regex pattern before matching.
                                $regex = '/' . str_replace( '/', '\/', $pattern ) . '/i';
                                if ( false !== preg_match( $regex, '' ) || preg_last_error() === PREG_NO_ERROR ) {
                                        if ( preg_match( $regex, $handle . ' ' . $src ) ) {
                                                return sanitize_key( $rule['category'] );
                                        }
                                }
                                // Plain substring match as a fallback.
                                if ( false !== stripos( $handle . ' ' . $src, $pattern ) ) {
                                        return sanitize_key( $rule['category'] );
                                }
			}
		}

		// 2) Known services.
		$services = gdpr_ca_known_services();
		foreach ( $services as $service ) {
			if ( empty( $service['requires_consent'] ) ) {
				continue;
			}
			foreach ( $service['patterns'] as $pattern ) {
				if ( preg_match( $pattern . 'i', $src ) ) {
					return $service['category'];
				}
			}
		}

		return null;
	}

	/**
	 * Filter the <script> tag.
	 *
	 * @param string $tag    HTML tag.
	 * @param string $handle Script handle.
	 * @param string $src    Script URL.
	 * @return string
	 */
	public function filter_script_tag( $tag, $handle, $src ) {
		// Skip admin-side.
		if ( is_admin() ) {
			return $tag;
		}

		// Local scripts are never blocked.
		if ( '/' === substr( $src, 0, 1 ) || '' === $src ) {
			return $tag;
		}

		$category = $this->resolve_category( $handle, $src );
		if ( null === $category ) {
			return $tag;
		}

		// If 'necessary' is the category, do not block.
		if ( 'necessary' === $category ) {
			return $tag;
		}

		// Replace type="text/javascript" with text/plain and add data attribute.
		$tag = preg_replace( '/type=[\'"]text\/javascript[\'"]/i', 'type="text/plain"', $tag );

		if ( false === strpos( $tag, 'type=' ) ) {
			$tag = preg_replace( '/<script\b/i', '<script type="text/plain"', $tag );
		}

		$tag = str_replace( '<script ', '<script data-gdpr-ca-category="' . esc_attr( $category ) . '" data-gdpr-ca-handle="' . esc_attr( $handle ) . '" ', $tag );

		return $tag;
	}

	/**
	 * Replace <iframe> in post/widget content with a placeholder until consent.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function filter_content_iframes( $content ) {
		if ( empty( $content ) ) {
			return $content;
		}

		// Match <iframe src="..."></iframe>.
		$pattern = '#<iframe\b([^>]*)>(.*?)</iframe>#is';

		return preg_replace_callback(
			$pattern,
			array( $this, 'wrap_iframe' ),
			$content
		);
	}

	/**
	 * Wrap an iframe into a click-to-load placeholder.
	 *
	 * @param array $m preg_replace_callback matches.
	 * @return string
	 */
	public function wrap_iframe( $m ) {
		$attrs    = $m[1];
		$inner     = $m[2];

		// Find the src.
		if ( ! preg_match( '/src=[\'"]([^\'"]+)[\'"]/i', $attrs, $src_match ) ) {
			return $m[0];
		}
		$src      = $src_match[1];
		$category = $this->resolve_category( 'iframe', $src );

		// If local or necessary, leave it alone.
		if ( null === $category || 'necessary' === $category ) {
			return $m[0];
		}

		$escaped_src   = esc_attr( $src );
		$escaped_attrs = esc_attr( $attrs );

		$placeholder = sprintf(
			'<div class="gdpr-ca-iframe-placeholder" data-gdpr-ca-category="%1$s" data-gdpr-ca-src="%2$s" data-gdpr-ca-attrs="%3$s">
				<p>%4$s</p>
				<button type="button" class="gdpr-ca-iframe-load" data-gdpr-ca-load>%5$s</button>
			</div>',
			esc_attr( $category ),
			$escaped_src,
			$escaped_attrs,
			esc_html__( 'Este contenido incrustado requiere tu consentimiento para la siguiente categoría:', 'gdpr-consent-auditor' ) . ' ' . esc_html( ucfirst( $category ) ),
			esc_html__( 'Cargar contenido', 'gdpr-consent-auditor' )
		);

		// Keep the original iframe hidden as a fallback.
		$hidden = str_replace( '<iframe ', '<iframe data-gdpr-ca-original style="display:none;" ', $m[0] );
		return $placeholder . $hidden;
	}

	/**
	 * Wrap oEmbed HTML the same way as content iframes.
	 *
	 * @param string $html  Embed HTML.
	 * @param string $url   Embed URL.
	 * @param array  $attr  Attributes.
	 * @return string
	 */
	public function filter_oembed( $html, $url, $attr ) {
		if ( empty( $html ) ) {
			return $html;
		}
		$category = $this->resolve_category( 'oembed', $url );
		if ( null === $category || 'necessary' === $category ) {
			return $html;
		}

		// Already wrapped?
		if ( false !== strpos( $html, 'gdpr-ca-iframe-placeholder' ) ) {
			return $html;
		}

		$placeholder = sprintf(
			'<div class="gdpr-ca-iframe-placeholder" data-gdpr-ca-category="%1$s" data-gdpr-ca-html="%2$s">
				<p>%3$s</p>
				<button type="button" class="gdpr-ca-iframe-load" data-gdpr-ca-load>%4$s</button>
			</div>',
			esc_attr( $category ),
			esc_attr( $html ),
			esc_html__( 'Este contenido incrustado requiere tu consentimiento para la siguiente categoría:', 'gdpr-consent-auditor' ) . ' ' . esc_html( ucfirst( $category ) ),
			esc_html__( 'Cargar contenido', 'gdpr-consent-auditor' )
		);
		return $placeholder;
	}
}
