<?php
/**
 * Minimal WordPress function stubs for unit testing.
 *
 * These stubs simulate just enough of the WP API surface for the
 * plugin's classes to be instantiable and testable without a real
 * WordPress environment. For integration tests, install the official
 * wordpress-develop test suite and set WP_TESTS_DIR.
 *
 * Stubs implement the *observable* behavior the plugin relies on,
 * not the full WP API. Tests that need real WP behavior should be
 * moved to the integration suite.
 *
 * @package GdprConsentAuditor
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'WEEK_IN_SECONDS' ) ) {
	define( 'WEEK_IN_SECONDS', 604800 );
}
if ( ! defined( 'MONTH_IN_SECONDS' ) ) {
	define( 'MONTH_IN_SECONDS', 2592000 );
}
if ( ! defined( 'YEAR_IN_SECONDS' ) ) {
	define( 'YEAR_IN_SECONDS', 31536000 );
}
if ( ! defined( 'AUTH_SALT' ) ) {
	define( 'AUTH_SALT', 'test-salt-auth' );
}
if ( ! defined( 'COOKIEPATH' ) ) {
	define( 'COOKIEPATH', '/' );
}
if ( ! defined( 'COOKIE_DOMAIN' ) ) {
	define( 'COOKIE_DOMAIN', false );
}

/**
 * In-memory option store.
 */
$GLOBALS['__gdpr_ca_options']       = array();
$GLOBALS['__gdpr_ca_transients']    = array();
$GLOBALS['__gdpr_ca_filters']       = array();
$GLOBALS['__gdpr_ca_actions_fired'] = array();
$GLOBALS['__gdpr_ca_cookies_set']   = array();

/* -----------------------------------------------------------------
 * Options API
 * --------------------------------------------------------------- */

function get_option( $name, $default = false ) {
	if ( isset( $GLOBALS['__gdpr_ca_options'][ $name ] ) ) {
		return $GLOBALS['__gdpr_ca_options'][ $name ];
	}
	return $default;
}

function add_option( $name, $value ) {
	$GLOBALS['__gdpr_ca_options'][ $name ] = $value;
	return true;
}

function update_option( $name, $value ) {
	$GLOBALS['__gdpr_ca_options'][ $name ] = $value;
	return true;
}

function delete_option( $name ) {
	unset( $GLOBALS['__gdpr_ca_options'][ $name ] );
	return true;
}

/* -----------------------------------------------------------------
 * Transients
 * --------------------------------------------------------------- */

function get_transient( $name ) {
	return isset( $GLOBALS['__gdpr_ca_transients'][ $name ] ) ? $GLOBALS['__gdpr_ca_transients'][ $name ] : false;
}

function set_transient( $name, $value, $ttl = 0 ) {
	$GLOBALS['__gdpr_ca_transients'][ $name ] = $value;
	return true;
}

function delete_transient( $name ) {
	unset( $GLOBALS['__gdpr_ca_transients'][ $name ] );
	return true;
}

/* -----------------------------------------------------------------
 * Hooks API
 * --------------------------------------------------------------- */

function add_filter( $tag, $cb, $priority = 10, $args = 1 ) {
	$GLOBALS['__gdpr_ca_filters'][ $tag ][] = array( $cb, $priority, $args );
	return true;
}

function add_action( $tag, $cb, $priority = 10, $args = 1 ) {
	return add_filter( $tag, $cb, $priority, $args );
}

function do_action( $tag, ...$args ) {
	$GLOBALS['__gdpr_ca_actions_fired'][] = array( $tag, $args );
	if ( ! empty( $GLOBALS['__gdpr_ca_filters'][ $tag ] ) ) {
		foreach ( $GLOBALS['__gdpr_ca_filters'][ $tag ] as $entry ) {
			call_user_func_array( $entry[0], $args );
		}
	}
}

function apply_filters( $tag, $value, ...$args ) {
	if ( ! empty( $GLOBALS['__gdpr_ca_filters'][ $tag ] ) ) {
		foreach ( $GLOBALS['__gdpr_ca_filters'][ $tag ] as $entry ) {
			$value = call_user_func( $entry[0], $value, ...$args );
		}
	}
	return $value;
}

function remove_filter( $tag, $cb, $priority = 10 ) {
	if ( empty( $GLOBALS['__gdpr_ca_filters'][ $tag ] ) ) {
		return false;
	}
	foreach ( $GLOBALS['__gdpr_ca_filters'][ $tag ] as $i => $entry ) {
		if ( $entry[0] === $cb && $entry[1] === $priority ) {
			unset( $GLOBALS['__gdpr_ca_filters'][ $tag ][ $i ] );
			return true;
		}
	}
	return false;
}

function remove_action( $tag, $cb, $priority = 10 ) {
	return remove_filter( $tag, $cb, $priority );
}

function wp_next_scheduled( $tag ) {
	return false;
}

function wp_schedule_event( $ts, $recurrence, $hook ) {
	return true;
}

function wp_unschedule_event( $ts, $hook ) {
	return true;
}

function wp_clear_scheduled_hook( $hook ) {
	return true;
}

/* -----------------------------------------------------------------
 * Capabilities / current user
 * --------------------------------------------------------------- */

function current_user_can( $cap ) {
	return true; // default: permissive
}

function get_current_user_id() {
	return isset( $GLOBALS['__gdpr_ca_current_user_id'] ) ? (int) $GLOBALS['__gdpr_ca_current_user_id'] : 0;
}

function wp_create_nonce( $action = -1 ) {
	return 'test_nonce_' . md5( $action );
}

function wp_verify_nonce( $nonce, $action = -1 ) {
	return ( $nonce === wp_create_nonce( $action ) ) ? 1 : false;
}

function check_admin_referer( $action = -1, $query_arg = '_wpnonce' ) {
	return true;
}

function check_ajax_referer( $action = -1, $query_arg = false, $die = true ) {
	return true;
}

/* -----------------------------------------------------------------
 * Sanitization / escaping
 * --------------------------------------------------------------- */

function sanitize_text_field( $str ) {
	return is_string( $str ) ? trim( strip_tags( $str ) ) : '';
}

function sanitize_textarea_field( $str ) {
	return is_string( $str ) ? strip_tags( $str ) : '';
}

function sanitize_key( $str ) {
	return is_string( $str ) ? preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $str ) ) : '';
}

function absint( $maybeint ) {
	return abs( (int) $maybeint );
}

function wp_kses_post( $str ) {
	return strip_tags( $str, '<a><b><i><em><strong><br><p>' );
}

function esc_html( $str ) {
	return htmlspecialchars( (string) $str, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $str ) {
	return htmlspecialchars( (string) $str, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $str ) {
	return (string) $str;
}

function esc_url_raw( $str ) {
	return (string) $str;
}

function esc_textarea( $str ) {
	return htmlspecialchars( (string) $str, ENT_QUOTES, 'UTF-8' );
}

function esc_js( $str ) {
	return htmlspecialchars( (string) $str, ENT_QUOTES, 'UTF-8' );
}

function esc_html__( $str, $domain = '' ) {
	return $str;
}

function esc_attr__( $str, $domain = '' ) {
	return $str;
}

function esc_html_e( $str, $domain = '' ) {
	echo esc_html( $str );
}

function __( $str, $domain = '' ) {
	return $str;
}

function _e( $str, $domain = '' ) {
	echo $str;
}

function _x( $str, $ctx, $domain = '' ) {
	return $str;
}

function _n( $singular, $plural, $count, $domain = '' ) {
	return $count == 1 ? $singular : $plural;
}

function wp_unslash( $v ) {
	return is_string( $v ) ? stripslashes( $v ) : $v;
}

function wp_json_encode( $data, $options = 0, $depth = 512 ) {
	return json_encode( $data, $options, $depth );
}

/* -----------------------------------------------------------------
 * URLs and paths
 * --------------------------------------------------------------- */

function site_url( $path = '' ) {
	return 'https://example.test' . ( $path ? '/' . ltrim( $path, '/' ) : '' );
}

function home_url( $path = '' ) {
	return site_url( $path );
}

function admin_url( $path = '' ) {
	return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
}

function plugin_dir_path( $file ) {
	return dirname( $file ) . '/';
}

function plugin_dir_url( $file ) {
	return 'https://example.test/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
}

function plugin_basename( $file ) {
	return 'gdpr-consent-auditor/' . basename( $file );
}

function rest_url( $path = '' ) {
	return 'https://example.test/wp-json/' . ltrim( $path, '/' );
}

function get_bloginfo( $key = '' ) {
	switch ( $key ) {
		case 'name':     return 'Test Site';
		case 'language': return 'en-US';
		default:         return '';
	}
}

function get_option_admin_email() {
	return 'admin@example.test';
}

function get_permalink( $id ) {
	return 'https://example.test/?p=' . (int) $id;
}

function get_pages() {
	return array();
}

/* -----------------------------------------------------------------
 * i18n
 * --------------------------------------------------------------- */

function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = false ) {
	return true;
}

/* -----------------------------------------------------------------
 * Misc WP functions
 * --------------------------------------------------------------- */

function wp_die( $msg = '' ) {
	throw new RuntimeException( is_string( $msg ) ? $msg : 'wp_die called' );
}

function current_time( $type = 'mysql' ) {
	if ( 'mysql' === $type ) {
		return gmdate( 'Y-m-d H:i:s' );
	}
	return time();
}

function date_i18n( $fmt, $ts = false ) {
	return gmdate( $fmt, $ts ?: time() );
}

function get_option_date_format() {
	return 'Y-m-d';
}

function flush_rewrite_rules() {
	return true;
}

function nocache_headers() {
	return true;
}

function wp_send_json_success( $data = null, $status = 200 ) {
	echo json_encode( array( 'success' => true, 'data' => $data ) );
}

function wp_send_json_error( $data = null, $status = 500 ) {
	echo json_encode( array( 'success' => false, 'data' => $data ) );
}

function wp_create_dir_or_skip() {}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $code;
		private $message;
		public function __construct( $code = '', $message = '' ) {
			$this->code    = $code;
			$this->message = $message;
		}
		public function get_error_message() { return $this->message; }
		public function get_error_code() { return $this->code; }
	}
}
function is_wp_error( $thing ) { return $thing instanceof WP_Error; }
function wp_remote_get( $url, $args = array() ) {
	return array(
		'response' => array( 'code' => 200 ),
		'body'     => '',
	);
}
function wp_remote_retrieve_response_code( $response ) {
	return isset( $response['response']['code'] ) ? (int) $response['response']['code'] : 0;
}
function wp_remote_retrieve_body( $response ) {
	return isset( $response['body'] ) ? $response['body'] : '';
}

/* -----------------------------------------------------------------
 * $wpdb stub
 * --------------------------------------------------------------- */

class WPDB_Stub {
	public $prefix = 'wp_';

	public function get_charset_collate() {
		return 'DEFAULT CHARSET=utf8';
	}

	public function get_var( $q = null ) { return null; }
	public function get_results( $q = null ) { return array(); }
	public function get_row( $q = null ) { return null; }
	public function insert( $t, $data, $fmt = null ) { return 1; }
	public function query( $q = null ) { return 1; }
	public function prepare( $q, ...$args ) {
		// Very crude sprintf-like substitution: %s, %d.
		$i = 0;
		return preg_replace_callback( '/%[sd]/', function ( $m ) use ( &$i, $args ) {
			$v = $args[ $i ] ?? '';
			$i++;
			return 'd' === $m[0]{1} ? (string) (int) $v : "'" . addslashes( $v ) . "'";
		}, $q );
	}
}

$GLOBALS['wpdb'] = new WPDB_Stub();

function dbDelta( $sql ) {
	return array();
}

/* -----------------------------------------------------------------
 * Scripts / styles (no-op for tests)
 * --------------------------------------------------------------- */

class WP_Dependencies {
	public $registered = array();
}

class WP_Scripts extends WP_Dependencies {}
class WP_Styles  extends WP_Dependencies {}

function wp_scripts() {
	global $wp_scripts;
	if ( ! $wp_scripts instanceof WP_Scripts ) {
		$wp_scripts = new WP_Scripts();
	}
	return $wp_scripts;
}

function wp_styles() {
	global $wp_styles;
	if ( ! $wp_styles instanceof WP_Styles ) {
		$wp_styles = new WP_Styles();
	}
	return $wp_styles;
}

function wp_enqueue_script( $h, $src = '', $deps = array(), $ver = false, $footer = false ) {}
function wp_enqueue_style( $h, $src = '', $deps = array(), $ver = false, $media = 'all' ) {}
function wp_register_style( $h, $src = '', $deps = array(), $ver = false, $media = 'all' ) {}
function wp_add_inline_style( $h, $css ) {}
function wp_localize_script( $h, $name, $data ) {}
function wp_script_is( $h, $list = 'enqueued' ) { return false; }

/* -----------------------------------------------------------------
 * Misc helpers used by the plugin
 * --------------------------------------------------------------- */

function wp_salt( $scheme = 'auth' ) {
	return 'test-salt-' . $scheme;
}

function is_ssl() { return true; }
function is_admin() { return isset( $GLOBALS['__gdpr_ca_is_admin'] ) ? (bool) $GLOBALS['__gdpr_ca_is_admin'] : false; }

function setcookie( $name, $value = '', $options = array() ) {
	$GLOBALS['__gdpr_ca_cookies_set'][ $name ] = array(
		'value'   => $value,
		'options' => $options,
	);
	return true;
}

function register_activation_hook( $file, $cb ) {}
function register_deactivation_hook( $file, $cb ) {}
function register_uninstall_hook( $file, $cb ) {}

function get_plugins() { return array(); }

function get_current_screen() { return null; }

function add_menu_page( $page_title, $menu_title, $cap, $slug, $cb = '', $icon = '', $pos = null ) {}
function add_submenu_page( $parent, $page_title, $menu_title, $cap, $slug, $cb = '' ) {}

function register_setting( $group, $name, $args = array() ) {}
function settings_fields( $group ) {}
function submit_button( $text = '', $type = 'primary', $name = 'submit', $wrap = true, $other = '' ) {}

function get_the_ID() { return 0; }
function apply_filters_deprecated( $tag, $args, $version, $replacement = null ) { return $args[0]; }
