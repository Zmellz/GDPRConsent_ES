<?php
/**
 * Public bootstrap: enqueues the banner CSS/JS, injects the
 * Google Consent Mode v2 default state, renders the banner HTML,
 * registers the revocation shortcode, and instantiates the
 * Script_Blocker.
 *
 * @package GdprConsentAuditor
 */

if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Class GdprCa_Public
 */
class GdprCa_Public {

        /**
         * Consent manager instance (cached).
         *
         * @var GdprCa_Consent_Manager|null
         */
        private $consent = null;

        /**
         * Prevent duplicate banner output when multiple theme hooks fire.
         *
         * @var bool
         */
        private $banner_rendered = false;

        /**
         * Constructor.
         */
        public function __construct() {
                $this->consent = new GdprCa_Consent_Manager();

                // Script blocker (front-end only). Do not block if the banner is disabled,
                // otherwise visitors would have no built-in way to grant consent.
                if ( ! is_admin() && gdpr_ca_get_setting( 'banner_enabled', 1 ) ) {
                        new GdprCa_Script_Blocker();
                }

                // Enqueue banner assets.
                add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );

                // Inject GCM v2 default state as early as possible in <head>.
                add_action( 'wp_head', array( $this, 'inject_gcm_default' ), 0 );

                // Inject Meta Pixel consent default right after GCM (still in <head>).
                add_action( 'wp_head', array( $this, 'inject_meta_pixel_default' ), 1 );

                // Inject the dynamic-script-blocker shim BEFORE any other script
                // in <head>. Priority -1 ensures it runs ahead of wp_head priority 0.
                add_action( 'wp_head', array( $this, 'inject_dynamic_script_blocker_shim' ), -1 );

                // Render banner directly in body via wp_body_open and wp_footer.
                // The JS will re-mount it as a direct child of body to escape
                // Elementor/Hello Elementor stacking contexts.
                add_action( 'wp_body_open', array( $this, 'render_banner' ), 5 );
                add_action( 'wp_footer', array( $this, 'render_banner' ), 100 );
                add_action( 'elementor/body_start', array( $this, 'render_banner' ), 5 );


                // Revocation shortcode.
                add_shortcode( 'gdpr_ca_revoke', array( $this, 'render_revoke_shortcode' ) );

                // REST endpoint already registered in GdprCa_Plugin - no duplication.
        }

        /**
         * Enqueue public assets.
         *
         * @return void
         */
        public function enqueue_assets() {
                if ( ! $this->should_enqueue_public_assets() ) {
                        return;
                }

                // Inline component variables so the active theme cannot easily override them.
                $primary          = gdpr_ca_get_setting( 'primary_color', '#1a73e8' );
                $accent           = gdpr_ca_get_setting( 'accent_color', '#202124' );
                $background_color = gdpr_ca_get_setting( 'background_color', '#ffffff' );
                $text_color       = gdpr_ca_get_setting( 'text_color', '#202124' );
                $muted_color      = gdpr_ca_get_setting( 'muted_color', '#5f6368' );
                $border_color     = gdpr_ca_get_setting( 'border_color', '#d9dee7' );
                $button_text      = gdpr_ca_get_setting( 'button_text_color', '#ffffff' );
                $radius           = max( 0, min( 32, absint( gdpr_ca_get_setting( 'banner_radius', 18 ) ) ) );
                $max_width        = max( 320, min( 1600, absint( gdpr_ca_get_setting( 'banner_max_width', 1040 ) ) ) );

                $custom_css = sprintf(
                        '#gdpr-ca-banner{--gdpr-ca-primary:%1$s;--gdpr-ca-accent:%2$s;--gdpr-ca-bg:%3$s;--gdpr-ca-text:%4$s;--gdpr-ca-muted:%5$s;--gdpr-ca-border:%6$s;--gdpr-ca-button-text:%7$s;--gdpr-ca-radius:%8$spx;--gdpr-ca-max-width:%9$spx;} .gdpr-ca-backdrop{z-index:99998;}',
                        esc_attr( $primary ),
                        esc_attr( $accent ),
                        esc_attr( $background_color ),
                        esc_attr( $text_color ),
                        esc_attr( $muted_color ),
                        esc_attr( $border_color ),
                        esc_attr( $button_text ),
                        $radius,
                        $max_width
                );

                wp_register_style(
                        'gdpr-ca-public-css',
                        GDPR_CA_PLUGIN_URL . 'public/css/public.css',
                        array(),
                        GDPR_CA_VERSION
                );
                wp_add_inline_style( 'gdpr-ca-public-css', $custom_css );
                wp_enqueue_style( 'gdpr-ca-public-css' );

                wp_enqueue_script(
                        'gdpr-ca-consent-banner',
                        GDPR_CA_PLUGIN_URL . 'public/js/consent-banner.js',
                        array(),
                        GDPR_CA_VERSION,
                        true
                );

                // Localize strings + endpoints + nonce + current consent state.
                $current = $this->consent->get_current_consent();

                $labels = array(
                        'title'         => gdpr_ca_get_setting( 'banner_title', __( 'Valoramos tu privacidad', 'gdpr-consent-auditor' ) ),
                        'message'       => gdpr_ca_get_setting( 'banner_message', '' ),
                        'acceptAll'     => gdpr_ca_get_setting( 'accept_all_label', __( 'Aceptar todo', 'gdpr-consent-auditor' ) ),
                        'rejectAll'     => gdpr_ca_get_setting( 'reject_all_label', __( 'Rechazar todo', 'gdpr-consent-auditor' ) ),
                        'configure'     => gdpr_ca_get_setting( 'configure_label', __( 'Configurar', 'gdpr-consent-auditor' ) ),
                                'save'          => gdpr_ca_get_setting( 'save_label', __( 'Guardar selección', 'gdpr-consent-auditor' ) ),
                                'policyLabel'   => gdpr_ca_get_setting( 'policy_link_label', __( 'Política de cookies', 'gdpr-consent-auditor' ) ),
                                'categories'    => $this->localized_categories(),
                                'loadContent'   => __( 'Cargar contenido', 'gdpr-consent-auditor' ),
                                'revokeConfirm' => __( 'Tus preferencias de consentimiento se han restablecido. Recarga la página para actualizar la experiencia.', 'gdpr-consent-auditor' ),
                );

                wp_localize_script(
                        'gdpr-ca-consent-banner',
                        'GdprCaBanner',
                        array(
                                'restUrl'         => esc_url_raw( rest_url( 'gdpr-ca/v1/consent' ) ),
                                'nonce'           => wp_create_nonce( 'gdpr_ca_consent_action' ),
                                'cookieName'      => GDPR_CA_CONSENT_COOKIE_NAME,
                                'cookiePath'      => COOKIEPATH ? COOKIEPATH : '/',
                                'cookieDomain'    => COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
                                'hasConsent'      => $current['has_consent'],
                                'currentAction'   => $current['action'],
                                'currentCategories' => $current['categories'],
                                'consentVersion'  => gdpr_ca_get_consent_version(),
                                'gcmEnabled'      => (bool) gdpr_ca_get_setting( 'gcm_v2_enabled', 0 ),
                                'metaPixelEnabled' => (bool) gdpr_ca_get_setting( 'meta_pixel_consent_enabled', 0 ),
                                'metaPixelId'     => preg_replace( '/[^0-9]/', '', (string) gdpr_ca_get_setting( 'meta_pixel_id', '' ) ),
                                'labels'          => $labels,
                                'policyUrl'       => $this->policy_url(),
                        )
                );
        }

        /**
         * Decide whether the public CSS/JS should be enqueued.
         *
         * Keep assets available when the revoke shortcode is present, even if the
         * banner itself is disabled.
         *
         * @return bool
         */
        private function should_enqueue_public_assets() {
                if ( gdpr_ca_get_setting( 'banner_enabled', 1 ) ) {
                        return true;
                }

                if ( ! function_exists( 'has_shortcode' ) ) {
                        return false;
                }

                global $post;
                return is_object( $post ) && ! empty( $post->post_content ) && has_shortcode( (string) $post->post_content, 'gdpr_ca_revoke' );
        }

        /**
         * Get the URL of the policy page if configured.
         *
         * @return string
         */
        private function policy_url() {
                $page_id = (int) gdpr_ca_get_setting( 'policy_page_id', 0 );
                if ( ! $page_id ) {
                        return '';
                }
                return get_permalink( $page_id );
        }

        /**
         * Localize category labels + descriptions for the banner.
         *
         * @return array
         */
        private function localized_categories() {
                $cats     = gdpr_ca_get_setting( 'categories', array() );
                $known    = gdpr_ca_known_categories();
                $out      = array();
                foreach ( $known as $key => $meta ) {
                        $cfg = isset( $cats[ $key ] ) ? $cats[ $key ] : array();
                        $out[ $key ] = array(
                                'label'       => isset( $cfg['label'] ) ? $cfg['label'] : $meta['label'],
                                'description' => isset( $cfg['description'] ) ? $cfg['description'] : '',
                                'always_on'   => ( 'necessary' === $key ) ? true : ( isset( $cfg['always_on'] ) && $cfg['always_on'] ),
                        );
                }
                return $out;
        }

        /**
         * Inject the GCM v2 default consent state at the top of <head>.
         *
         * @return void
         */
        public function inject_gcm_default() {
                if ( ! gdpr_ca_get_setting( 'gcm_v2_enabled', 0 ) ) {
                        return;
                }
                $ads    = gdpr_ca_get_setting( 'gcm_v2_default_ads', 'denied' );
                $anal   = gdpr_ca_get_setting( 'gcm_v2_default_analytics', 'denied' );
                $func   = gdpr_ca_get_setting( 'gcm_v2_default_functional', 'denied' );
                $pads   = gdpr_ca_get_setting( 'gcm_v2_default_personalized_ads', 'denied' );
                $url    = gdpr_ca_get_setting( 'gcm_v2_url_passthrough', 'https://example.com/privacy' );

                // If the user has already consented, prefer their stored state.
                $current = $this->consent->get_current_consent();
                if ( $current['has_consent'] ) {
                        $active = $current['categories'];
                        $ads    = in_array( 'marketing', $active, true ) ? 'granted' : 'denied';
                        $anal   = in_array( 'statistics', $active, true ) ? 'granted' : 'denied';
                        $func   = in_array( 'preferences', $active, true ) ? 'granted' : 'denied';
                        $pads   = in_array( 'marketing', $active, true ) ? 'granted' : 'denied';
                }

                echo '<script>' . "\n";
                echo 'window.dataLayer = window.dataLayer || [];' . "\n";
                echo 'function gtag(){dataLayer.push(arguments);}' . "\n";
                echo "gtag('consent', 'default', {" . "\n";
                echo "  'ad_storage': '" . esc_js( $ads ) . "'," . "\n";
                echo "  'ad_user_data': '" . esc_js( $ads ) . "'," . "\n";
                echo "  'ad_personalization': '" . esc_js( $pads ) . "'," . "\n";
                echo "  'analytics_storage': '" . esc_js( $anal ) . "'," . "\n";
                echo "  'functionality_storage': '" . esc_js( $func ) . "'," . "\n";
                echo "  'security_storage': 'granted'," . "\n";
                echo "  'wait_for_update': 500" . "\n";
                echo '});' . "\n";
                echo '</script>' . "\n";
        }

        /**
         * Inject the Meta Pixel (Facebook Pixel) consent default state.
         *
         * Strategy:
         *   1. Always set up window.fbq as a no-op queue so other code that
         *      calls fbq(...) before consent does not throw.
         *   2. Call fbq('consent', 'revoke') by default (no data sent).
         *   3. If a Pixel ID is configured AND the visitor already has
         *      Marketing consent (e.g. on a returning visit), call
         *      fbq('consent', 'grant') and fbq('init', id) + fbq('track', 'PageView').
         *
         * The actual fbq library is NOT loaded by this plugin; if the visitor
         * has not granted Marketing consent, the queue stays as a no-op so
         * nothing is sent. When consent is granted later, consent-banner.js
         * loads fbevents.js and flushes the queue.
         *
         * @return void
         */
        public function inject_meta_pixel_default() {
                if ( ! gdpr_ca_get_setting( 'meta_pixel_consent_enabled', 0 ) ) {
                        return;
                }

                $pixel_id = (string) gdpr_ca_get_setting( 'meta_pixel_id', '' );
                $pixel_id = preg_replace( '/[^0-9]/', '', $pixel_id );

                // Read current consent to decide the default state.
                $current     = $this->consent->get_current_consent();
                $has_mk_cons = ( $current['has_consent'] && in_array( 'marketing', $current['categories'], true ) );

                echo '<script>' . "\n";
                // Stubs that capture calls in a queue.
                echo '!function(f,b,e,v,n,t,s){if(f.fbq)return;n=f.fbq=function(){n.callMethod?n.callMethod.apply(n,arguments):n.queue.push(arguments)};if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version="2.0";n.queue=[];}(window,document,"script");' . "\n";

                // Always revoke first - privacy by design.
                echo "fbq('consent', 'revoke');\n";

                if ( $has_mk_cons ) {
                        // Visitor already accepted Marketing: grant + init + track PageView.
                        echo "fbq('consent', 'grant');\n";
                        if ( '' !== $pixel_id ) {
                                echo "fbq('init', '" . esc_js( $pixel_id ) . "');\n";
                                echo "fbq('track', 'PageView');\n";
                        }
                }
                echo '</script>' . "\n";
        }

        /**
         * Inject the dynamic-script-blocker shim.
         *
         * This shim runs at the very top of <head>, before any other script.
         * It patches:
         *   1. document.createElement - when a <script> element is created with
         *      a src that matches a known blocking pattern, the element is
         *      pre-tagged with type="text/plain" and data-gdpr-ca-category.
         *   2. Element.prototype.setAttribute - when 'src' is set on a script
         *      element after creation, the same check is applied.
         *
         * The actual list of patterns is passed via window.__gdprCaBlockRules
         * so the shim can decide without needing another HTTP request.
         *
         * The shim is intentionally tiny (~2 KB) and dependency-free.
         *
         * @return void
         */
        public function inject_dynamic_script_blocker_shim() {
                if ( ! gdpr_ca_get_setting( 'banner_enabled', 1 ) ) {
                        return;
                }
                if ( ! gdpr_ca_get_setting( 'block_dynamic_scripts', 0 ) ) {
                        return;
                }
                if ( is_admin() ) {
                        return;
                }

                // Build the rule list: array of { pattern: string, category: string }.
                $rules = array();

                // 1) Manual block rules (highest priority).
                $manual = gdpr_ca_get_setting( 'manual_blocks', array() );
                if ( is_array( $manual ) ) {
                        foreach ( $manual as $rule ) {
                                if ( empty( $rule['pattern'] ) || empty( $rule['category'] ) ) {
                                        continue;
                                }
                                $rules[] = array(
                                        'pattern'  => (string) $rule['pattern'],
                                        'category' => sanitize_key( $rule['category'] ),
                                );
                        }
                }

                // 2) Known services that require consent.
                foreach ( gdpr_ca_known_services() as $service ) {
                        if ( empty( $service['requires_consent'] ) ) {
                                continue;
                        }
                        if ( 'necessary' === $service['category'] ) {
                                continue;
                        }
                        // Use the first pattern as the matcher; the others are
                        // redundant for the dynamic case (they cover inline scripts).
                        if ( ! empty( $service['patterns'][0] ) ) {
                                // Convert PHP regex delimiter form to a JS-source string
                                // (strip leading/trailing slashes + flags).
                                $pat = $service['patterns'][0];
                                $pat = ltrim( $pat, '/' );
                                // Strip trailing / and any flags (i).
                                $pat = preg_replace( '/\/[a-z]*$/', '', $pat );
                                $rules[] = array(
                                        'pattern'  => $pat,
                                        'category' => $service['category'],
                                );
                        }
                }

                $rules_json = wp_json_encode( $rules );

                // Read current consent to decide whether the shim should
                // auto-allow already-accepted categories.
                $current = $this->consent->get_current_consent();
                $active  = ( $current['has_consent'] && is_array( $current['categories'] ) ) ? $current['categories'] : array( 'necessary' );
                $active_json = wp_json_encode( $active );

                // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- inline script with JSON-encoded data.
                ?>
<script id="gdpr-ca-dynamic-blocker-shim">
(function () {
  if (window.__gdprCaShimInstalled) { return; }
  window.__gdprCaShimInstalled = true;

  var rules = <?php echo $rules_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
  var activeCategories = <?php echo $active_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
  var blockScripts = true;

  // Pre-compile regexes once.
  var compiled = [];
  try {
    rules.forEach(function (r) {
      if (!r || !r.pattern || !r.category) { return; }
      try {
        compiled.push({ re: new RegExp(r.pattern, 'i'), category: r.category });
      } catch (e) {
        // If the regex is invalid, fall back to substring match.
        compiled.push({ substr: r.pattern.toLowerCase(), category: r.category });
      }
    });
  } catch (e) {}

  function categoryAllowed(cat) {
    return activeCategories.indexOf(cat) > -1;
  }

  function matchRule(src) {
    if (!src || typeof src !== 'string') { return null; }
    var lower = src.toLowerCase();
    for (var i = 0; i < compiled.length; i++) {
      var r = compiled[i];
      if (r.re) {
        if (r.re.test(src)) { return r.category; }
      } else if (r.substr && lower.indexOf(r.substr) > -1) {
        return r.category;
      }
    }
    return null;
  }

  function wrapScript(el, src) {
    if (!el || el.tagName !== 'SCRIPT') { return el; }
    if (el.getAttribute('data-gdpr-ca-category')) { return el; }
    var cat = matchRule(src || el.getAttribute('src') || '');
    if (!cat || cat === 'necessary') { return el; }
    if (categoryAllowed(cat)) { return el; }

    el.setAttribute('type', 'text/plain');
    el.setAttribute('data-gdpr-ca-category', cat);
    el.setAttribute('data-gdpr-ca-dynamic', '1');
    return el;
  }

  // 1) Patch document.createElement.
  var origCreate = document.createElement;
  document.createElement = function (tagName) {
    var el = origCreate.apply(document, arguments);
    if (typeof tagName === 'string' && tagName.toLowerCase() === 'script') {
      // Defer the check until src is set - observe attribute changes via setAttribute.
      var origSetAttr = el.setAttribute;
      el.setAttribute = function (name, value) {
        origSetAttr.call(el, name, value);
        if (name && name.toLowerCase() === 'src') {
          wrapScript(el, value);
        }
      };
      // Also catch direct assignment to .src.
      try {
        var srcDesc = Object.getOwnPropertyDescriptor(el, 'src') || Object.getOwnPropertyDescriptor(HTMLScriptElement.prototype, 'src');
        if (srcDesc && srcDesc.set) {
          Object.defineProperty(el, 'src', {
            configurable: true,
            enumerable: srcDesc.enumerable,
            get: srcDesc.get,
            set: function (v) {
              srcDesc.set.call(el, v);
              wrapScript(el, v);
            }
          });
        }
      } catch (e) {}
    }
    return el;
  };

  // 2) Patch Element.prototype.setAttribute globally for SCRIPT elements.
  var origSetAttrProto = Element.prototype.setAttribute;
  Element.prototype.setAttribute = function (name, value) {
    origSetAttrProto.call(this, name, value);
    if (this && this.tagName === 'SCRIPT' && name && name.toLowerCase() === 'src') {
      wrapScript(this, value);
    }
  };

  // 3) Expose an API so the banner can update activeCategories later.
  window.__gdprCaUpdateActiveCategories = function (cats) {
    activeCategories = Array.isArray(cats) ? cats : [];
  };
})();
</script>
<?php
                // phpcs:enable
        }

        /**
         * Compute a deterministic inline style for banner placement.
         *
         * This keeps the configured location stable even when the active theme
         * or builder injects generic fixed-position rules.
         *
         * @param string $layout Banner layout.
         * @param string $position Banner position.
         * @return string
         */
        private function banner_inline_style( $layout, $position ) {
                $primary          = gdpr_ca_get_setting( 'primary_color', '#1a73e8' );
                $accent           = gdpr_ca_get_setting( 'accent_color', '#202124' );
                $background_color = gdpr_ca_get_setting( 'background_color', '#ffffff' );
                $text_color       = gdpr_ca_get_setting( 'text_color', '#202124' );
                $muted_color      = gdpr_ca_get_setting( 'muted_color', '#5f6368' );
                $border_color     = gdpr_ca_get_setting( 'border_color', '#d9dee7' );
                $button_text      = gdpr_ca_get_setting( 'button_text_color', '#ffffff' );
                $radius           = max( 0, min( 32, absint( gdpr_ca_get_setting( 'banner_radius', 18 ) ) ) );
                $max_width        = max( 320, min( 1600, absint( gdpr_ca_get_setting( 'banner_max_width', 1040 ) ) ) );
                $offset           = max( 0, min( 80, absint( gdpr_ca_get_setting( 'banner_offset', 20 ) ) ) );

                // Opacities (0-100).
                $primary_o   = absint( gdpr_ca_get_setting( 'primary_color_opacity', 100 ) );
                $accent_o    = absint( gdpr_ca_get_setting( 'accent_color_opacity', 100 ) );
                $bg_o        = absint( gdpr_ca_get_setting( 'background_color_opacity', 100 ) );
                $text_o      = absint( gdpr_ca_get_setting( 'text_color_opacity', 100 ) );
                $muted_o     = absint( gdpr_ca_get_setting( 'muted_color_opacity', 100 ) );
                $border_o    = absint( gdpr_ca_get_setting( 'border_color_opacity', 100 ) );
                $btn_text_o  = absint( gdpr_ca_get_setting( 'button_text_color_opacity', 100 ) );

                $primary_rgba   = gdpr_ca_hex_to_rgba( $primary, $primary_o );
                $accent_rgba    = gdpr_ca_hex_to_rgba( $accent, $accent_o );
                $bg_rgba        = gdpr_ca_hex_to_rgba( $background_color, $bg_o );
                $text_rgba      = gdpr_ca_hex_to_rgba( $text_color, $text_o );
                $muted_rgba     = gdpr_ca_hex_to_rgba( $muted_color, $muted_o );
                $border_rgba    = gdpr_ca_hex_to_rgba( $border_color, $border_o );
                $btn_text_rgba  = gdpr_ca_hex_to_rgba( $button_text, $btn_text_o );

                // Padding.
                $pt = absint( gdpr_ca_get_setting( 'banner_padding_top', 24 ) );
                $pr = absint( gdpr_ca_get_setting( 'banner_padding_right', 24 ) );
                $pb = absint( gdpr_ca_get_setting( 'banner_padding_bottom', 24 ) );
                $pl = absint( gdpr_ca_get_setting( 'banner_padding_left', 24 ) );

                // Font sizes.
                $fs_title  = absint( gdpr_ca_get_setting( 'font_size_title', 20 ) );
                $fs_msg    = absint( gdpr_ca_get_setting( 'font_size_message', 14 ) );
                $fs_btn    = absint( gdpr_ca_get_setting( 'font_size_buttons', 14 ) );
                $align     = gdpr_ca_get_setting( 'banner_text_align', 'center' );

                $base = 'position:fixed !important;z-index:99999 !important;'
                        . '--gdpr-ca-primary:' . esc_attr( $primary_rgba ) . ';'
                        . '--gdpr-ca-accent:' . esc_attr( $accent_rgba ) . ';'
                        . '--gdpr-ca-bg:' . esc_attr( $bg_rgba ) . ';'
                        . '--gdpr-ca-text:' . esc_attr( $text_rgba ) . ';'
                        . '--gdpr-ca-muted:' . esc_attr( $muted_rgba ) . ';'
                        . '--gdpr-ca-border:' . esc_attr( $border_rgba ) . ';'
                        . '--gdpr-ca-button-text:' . esc_attr( $btn_text_rgba ) . ';'
                        . '--gdpr-ca-radius:' . $radius . 'px;'
                        . '--gdpr-ca-max-width:' . $max_width . 'px;'
                        . '--gdpr-ca-pt:' . $pt . 'px;--gdpr-ca-pr:' . $pr . 'px;--gdpr-ca-pb:' . $pb . 'px;--gdpr-ca-pl:' . $pl . 'px;'
                        . '--gdpr-ca-fs-title:' . $fs_title . 'px;--gdpr-ca-fs-msg:' . $fs_msg . 'px;--gdpr-ca-fs-btn:' . $fs_btn . 'px;'
                        . '--gdpr-ca-align:' . esc_attr( $align ) . ';';

                if ( 'modal' === $layout ) {
                        return $base . 'top:50% !important;bottom:auto !important;left:50% !important;right:auto !important;transform:translate(-50%, -50%) !important;';
                }

                if ( 'widget' === $layout ) {
                        $vertical = ( 'top' === $position ) ? 'top:' . $offset . 'px !important;bottom:auto !important;' : 'bottom:' . $offset . 'px !important;top:auto !important;';
                        return $base . $vertical . 'right:' . $offset . 'px !important;left:auto !important;transform:none !important;';
                }

                $vertical = ( 'top' === $position ) ? 'top:' . $offset . 'px !important;bottom:auto !important;' : 'bottom:' . $offset . 'px !important;top:auto !important;';
                return $base . $vertical . 'left:50% !important;right:auto !important;transform:translateX(-50%) !important;';
        }

        /**
         * Build hardcoded theme CSS for the banner instance.
         *
         * @return string
         */
        private function banner_theme_css() {
                $primary          = gdpr_ca_get_setting( 'primary_color', '#1a73e8' );
                $accent           = gdpr_ca_get_setting( 'accent_color', '#202124' );
                $background_color = gdpr_ca_get_setting( 'background_color', '#ffffff' );
                $text_color       = gdpr_ca_get_setting( 'text_color', '#202124' );
                $muted_color      = gdpr_ca_get_setting( 'muted_color', '#5f6368' );
                $border_color     = gdpr_ca_get_setting( 'border_color', '#d9dee7' );
                $button_text      = gdpr_ca_get_setting( 'button_text_color', '#ffffff' );
                $radius           = max( 0, min( 32, absint( gdpr_ca_get_setting( 'banner_radius', 18 ) ) ) );
                $max_width        = max( 320, min( 1600, absint( gdpr_ca_get_setting( 'banner_max_width', 1040 ) ) ) );

                $primary_o   = absint( gdpr_ca_get_setting( 'primary_color_opacity', 100 ) );
                $accent_o    = absint( gdpr_ca_get_setting( 'accent_color_opacity', 100 ) );
                $bg_o        = absint( gdpr_ca_get_setting( 'background_color_opacity', 100 ) );
                $text_o      = absint( gdpr_ca_get_setting( 'text_color_opacity', 100 ) );
                $muted_o     = absint( gdpr_ca_get_setting( 'muted_color_opacity', 100 ) );
                $border_o    = absint( gdpr_ca_get_setting( 'border_color_opacity', 100 ) );
                $btn_text_o  = absint( gdpr_ca_get_setting( 'button_text_color_opacity', 100 ) );

                $primary_rgba   = gdpr_ca_hex_to_rgba( $primary, $primary_o );
                $accent_rgba    = gdpr_ca_hex_to_rgba( $accent, $accent_o );
                $bg_rgba        = gdpr_ca_hex_to_rgba( $background_color, $bg_o );
                $text_rgba      = gdpr_ca_hex_to_rgba( $text_color, $text_o );
                $muted_rgba     = gdpr_ca_hex_to_rgba( $muted_color, $muted_o );
                $border_rgba    = gdpr_ca_hex_to_rgba( $border_color, $border_o );
                $btn_text_rgba  = gdpr_ca_hex_to_rgba( $button_text, $btn_text_o );

                $font_size_title   = absint( gdpr_ca_get_setting( 'font_size_title', 20 ) );
                $font_size_message = absint( gdpr_ca_get_setting( 'font_size_message', 14 ) );
                $font_size_buttons = absint( gdpr_ca_get_setting( 'font_size_buttons', 14 ) );
                $align             = gdpr_ca_get_setting( 'banner_text_align', 'center' );

                return sprintf(
                        '#gdpr-ca-banner{background:%1$s !important;color:%2$s !important;border-color:%3$s !important;border-radius:%4$spx !important;max-width:%5$spx !important;text-align:%10$s !important;}' .
                        '#gdpr-ca-banner .gdpr-ca-banner__head h2{color:%6$s !important;font-size:%11$spx !important;}' .
                        '#gdpr-ca-banner .gdpr-ca-banner__message{color:%2$s !important;font-size:%12$spx !important;}' .
                        '#gdpr-ca-banner .gdpr-ca-banner__policy a,#gdpr-ca-banner .gdpr-ca-btn--config{color:%7$s !important;}' .
                        '#gdpr-ca-banner .gdpr-ca-btn{border-radius:%4$spx !important;font-size:%13$spx !important;}' .
                        '#gdpr-ca-banner .gdpr-ca-btn--accept,#gdpr-ca-banner .gdpr-ca-btn--save{background:%7$s !important;color:%8$s !important;border-color:%7$s !important;}' .
                        '#gdpr-ca-banner .gdpr-ca-btn--reject{background:%1$s !important;color:%6$s !important;border-color:%3$s !important;}' .
                        '#gdpr-ca-banner .gdpr-ca-btn--reject:hover{background:%1$s !important;filter:brightness(0.98);}' .
                        '#gdpr-ca-banner .gdpr-ca-banner__preferences{border-top-color:%3$s !important;}' .
                        '#gdpr-ca-banner .gdpr-ca-banner__preferences label{background:%1$s !important;border-color:%3$s !important;border-radius:%4$spx !important;}' .
                        '#gdpr-ca-banner .gdpr-ca-banner__preferences strong{color:%6$s !important;}' .
                        '#gdpr-ca-banner .gdpr-ca-banner__desc{color:%9$s !important;}' .
                        '#gdpr-ca-banner .gdpr-ca-iframe-placeholder{border-color:%3$s !important;border-radius:%4$spx !important;background:%1$s !important;color:%9$s !important;}' .
                        '#gdpr-ca-banner .gdpr-ca-iframe-placeholder button{background:%7$s !important;color:%8$s !important;border-radius:%4$spx !important;}',
                        esc_attr( $bg_rgba ),
                        esc_attr( $text_rgba ),
                        esc_attr( $border_rgba ),
                        $radius,
                        $max_width,
                        esc_attr( $accent_rgba ),
                        esc_attr( $primary_rgba ),
                        esc_attr( $btn_text_rgba ),
                        esc_attr( $muted_rgba ),
                        esc_attr( $align ),
                        $font_size_title,
                        $font_size_message,
                        $font_size_buttons
                );
        }

        /**
         * Render the banner HTML.
         *
         * The actual interaction is handled by consent-banner.js.
         *
         * @return void
         */
        public function render_banner() {
                if ( $this->banner_rendered ) {
                        return;
                }
                if ( ! gdpr_ca_get_setting( 'banner_enabled', 1 ) ) {
                        return;
                }

                $current = $this->consent->get_current_consent();
                $layout  = gdpr_ca_get_setting( 'banner_layout', 'bar' );
                $pos     = gdpr_ca_get_setting( 'banner_position', 'bottom' );

                // Hidden if user already has a valid (matching version) consent.
                $hidden = $current['has_consent'];

                $classes = array(
                        'gdpr-ca-banner',
                        'gdpr-ca-banner--' . $layout,
                        'gdpr-ca-banner--' . $pos,
                );
                if ( $hidden ) {
                        $classes[] = 'gdpr-ca-banner--hidden';
                }

                $title   = gdpr_ca_get_setting( 'banner_title', '' );
                $message = gdpr_ca_get_setting( 'banner_message', '' );
                $accept  = gdpr_ca_get_setting( 'accept_all_label', __( 'Aceptar todo', 'gdpr-consent-auditor' ) );
                $reject  = gdpr_ca_get_setting( 'reject_all_label', __( 'Rechazar todo', 'gdpr-consent-auditor' ) );
                $config  = gdpr_ca_get_setting( 'configure_label', __( 'Configurar', 'gdpr-consent-auditor' ) );
                $save    = gdpr_ca_get_setting( 'save_label', __( 'Guardar selección', 'gdpr-consent-auditor' ) );
                $policy_label = gdpr_ca_get_setting( 'policy_link_label', __( 'Política de cookies', 'gdpr-consent-auditor' ) );
                $policy_url   = $this->policy_url();

                $cats    = $this->localized_categories();
                $style   = $this->banner_inline_style( $layout, $pos );
                $theme_css = $this->banner_theme_css();
                $this->banner_rendered = true;
                ?>
                <style id="gdpr-ca-banner-theme"><?php echo wp_strip_all_tags( $theme_css ); ?></style>
                <?php if ( 'modal' === $layout ) : ?>
                        <div class="gdpr-ca-backdrop<?php echo $hidden ? ' gdpr-ca-banner--hidden' : ''; ?>" data-gdpr-ca-backdrop></div>
                <?php endif; ?>
                <div id="gdpr-ca-banner" class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>" style="<?php echo esc_attr( $style ); ?>" role="dialog" aria-modal="true" aria-labelledby="gdpr-ca-banner-title" aria-hidden="<?php echo $hidden ? 'true' : 'false'; ?>">
                        <div class="gdpr-ca-banner__inner">
                                <div class="gdpr-ca-banner__head">
                                        <h2 id="gdpr-ca-banner-title"><?php echo esc_html( $title ); ?></h2>
                                        <p class="gdpr-ca-banner__message"><?php echo esc_html( $message ); ?></p>
                                        <?php if ( ! empty( $policy_url ) ) : ?>
                                                <p class="gdpr-ca-banner__policy">
                                                        <a href="<?php echo esc_url( $policy_url ); ?>"><?php echo esc_html( $policy_label ); ?></a>
                                                </p>
                                        <?php endif; ?>
                                </div>

                                <div class="gdpr-ca-banner__actions">
                                        <button type="button" class="gdpr-ca-btn gdpr-ca-btn--accept" data-gdpr-ca-action="accept_all"><?php echo esc_html( $accept ); ?></button>
                                        <button type="button" class="gdpr-ca-btn gdpr-ca-btn--reject" data-gdpr-ca-action="reject_all"><?php echo esc_html( $reject ); ?></button>
                                        <button type="button" class="gdpr-ca-btn gdpr-ca-btn--config" data-gdpr-ca-toggle="configure"><?php echo esc_html( $config ); ?></button>
                                </div>

                                <div class="gdpr-ca-banner__preferences" data-gdpr-ca-panel="configure" hidden>
                                        <ul>
                                        <?php foreach ( $cats as $key => $cat ) : ?>
                                                <li>
                                                        <label>
                                                                <input
                                                                        type="checkbox"
                                                                        name="gdpr-ca-cat[]"
                                                                        value="<?php echo esc_attr( $key ); ?>"
                                                                        <?php echo $cat['always_on'] ? 'checked disabled' : ''; ?>
                                                                        data-gdpr-ca-cat="<?php echo esc_attr( $key ); ?>"
                                                                />
                                                                <strong><?php echo esc_html( $cat['label'] ); ?></strong>
                                                                <span class="gdpr-ca-banner__desc"><?php echo esc_html( $cat['description'] ); ?></span>
                                                        </label>
                                                </li>
                                        <?php endforeach; ?>
                                        </ul>
                                        <div class="gdpr-ca-banner__preferences-actions">
                                                <button type="button" class="gdpr-ca-btn gdpr-ca-btn--save" data-gdpr-ca-action="custom"><?php echo esc_html( $save ); ?></button>
                                        </div>
                                </div>
                        </div>
                </div>
                <?php
        }

        /**
         * Shortcode to render a "Cookie preferences" / revoke link.
         *
         * Usage: [gdpr_ca_revoke label="Cookie preferences"]
         *
         * @param array $atts Shortcode attributes.
         * @return string
         */
        public function render_revoke_shortcode( $atts ) {
                $atts = shortcode_atts(
                        array(
                                'label' => __( 'Preferencias de cookies', 'gdpr-consent-auditor' ),
                        ),
                        $atts,
                        'gdpr_ca_revoke'
                );

                return sprintf(
                        '<a href="#" class="gdpr-ca-revoke-link" data-gdpr-ca-action="revoke">%s</a>',
                        esc_html( $atts['label'] )
                );
        }
}



