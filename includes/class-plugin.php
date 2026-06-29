<?php
/**
 * Core plugin bootstrap class.
 *
 * This class is intentionally light: the real work lives in
 * GdprCa_Scanner, GdprCa_Consent_Manager, GdprCa_Script_Blocker,
 * GdprCa_Report_Generator, GdprCa_Legal_Templates and the
 * Admin / Public classes.
 *
 * @package GdprConsentAuditor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GdprCa_Plugin
 *
 * Holds cross-cutting wiring: daily cron, AJAX/REST endpoints
 * registration, and shared filter/actions.
 */
class GdprCa_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var GdprCa_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton.
	 *
	 * @return GdprCa_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Hook into WP.
	 */
	private function __construct() {
		// Daily re-scan hook.
		add_action( 'gdpr_ca_daily_scan', array( $this, 'run_daily_scan' ) );

		// REST endpoints for consent capture (front-end).
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	}

	/**
	 * Run the daily scan and store the result.
	 *
	 * @return void
	 */
	public function run_daily_scan() {
		$scanner = new GdprCa_Scanner();
		$results = $scanner->scan();

		gdpr_ca_update_setting( 'last_scan_at', current_time( 'mysql' ) );
		gdpr_ca_store_last_scan_results( $results );
	}

	/**
	 * Register REST routes for the consent banner.
	 *
	 * @return void
	 */
	public function register_rest_routes() {
		register_rest_route(
			'gdpr-ca/v1',
			'/consent',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rest_store_consent' ),
				'permission_callback' => '__return_true', // Public endpoint — validated via nonce.
				'args'                => array(
					'action'     => array(
						'type'              => 'string',
						'required'          => true,
						'enum'              => array( 'accept_all', 'reject_all', 'custom', 'revoke' ),
						'sanitize_callback' => 'sanitize_key',
					),
					'categories' => array(
						'type'              => 'array',
						'required'          => false,
						'default'           => array(),
						'sanitize_callback' => 'gdpr_ca_sanitize_categories',
					),
					'nonce'      => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * REST handler: store the consent choice.
	 *
	 * @param WP_REST_Request $request REST request object.
	 * @return WP_REST_Response
	 */
	public function rest_store_consent( WP_REST_Request $request ) {
		// Verify the public nonce created by the banner.
		$nonce = $request->get_param( 'nonce' );
		if ( ! wp_verify_nonce( $nonce, 'gdpr_ca_consent_action' ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Nonce no válido o caducado. Recarga la página e inténtalo de nuevo.', 'gdpr-consent-auditor' ),
				),
				403
			);
		}

		$action     = $request->get_param( 'action' );
		$categories = $request->get_param( 'categories' );
		$categories = is_array( $categories ) ? $categories : array();

		$manager = new GdprCa_Consent_Manager();
		$ok      = $manager->record_consent( $action, $categories );

		if ( is_wp_error( $ok ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $ok->get_error_message(),
				),
				400
			);
		}

		// Return the categories that should now be active on the client.
		$active = $manager->get_active_categories_for_action( $action, $categories );

		return new WP_REST_Response(
			array(
				'success'         => true,
				'active_categories' => $active,
				'consent_version' => gdpr_ca_get_consent_version(),
			),
			200
		);
	}
}

// Boot the core.
GdprCa_Plugin::instance();
