<?php
/**
 * Classifier: takes raw scan results and adds GDPR-style
 * per-element classification that the report generator consumes.
 *
 * @package GdprConsentAuditor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GdprCa_Classifier
 */
class GdprCa_Classifier {

	/**
	 * Classify a full scan result set.
	 *
	 * @param array $scan_results Output of GdprCa_Scanner::scan().
	 * @return array Same shape, with a 'classification' key added
	 *               to every element and a 'classified_at' timestamp.
	 */
	public function classify( $scan_results ) {
		if ( empty( $scan_results ) || ! is_array( $scan_results ) ) {
			return $scan_results;
		}

		foreach ( array( 'plugins', 'scripts', 'services', 'cookies' ) as $section ) {
			if ( isset( $scan_results[ $section ] ) && is_array( $scan_results[ $section ] ) ) {
				foreach ( $scan_results[ $section ] as &$item ) {
					$item['classification'] = $this->classify_item( $item );
				}
			}
		}

		// Theme.
		if ( isset( $scan_results['theme'] ) && is_array( $scan_results['theme'] ) ) {
			$scan_results['theme']['classification'] = $this->classify_item( $scan_results['theme'] );
		}

		$scan_results['classified_at'] = current_time( 'mysql' );
		return $scan_results;
	}

	/**
	 * Classify a single element into a structured privacy fingerprint.
	 *
	 * @param array $item Element to classify.
	 * @return array {
	 *     @type string $final_category        necessary|preferences|statistics|marketing
	 *     @type bool   $final_requires_consent
	 *     @type string $final_risk            low|medium|high
	 *     @type string $summary_line
	 *     @type array  $legal_basis           GDPR basis suggested
	 * }
	 */
	private function classify_item( $item ) {
		$category         = isset( $item['category'] ) ? $item['category'] : 'preferences';
		$requires_consent = isset( $item['requires_consent'] ) ? (bool) $item['requires_consent'] : true;
		$risk             = isset( $item['risk'] ) ? $item['risk'] : 'medium';

		// Necessary items never require consent.
		if ( 'necessary' === $category ) {
			$requires_consent = false;
		}

		$summary = $this->build_summary_line( $item, $category, $requires_consent, $risk );

		return array(
			'final_category'         => $category,
			'final_requires_consent' => $requires_consent,
			'final_risk'             => $risk,
			'summary_line'           => $summary,
			'legal_basis'            => $this->suggest_legal_basis( $category, $requires_consent ),
		);
	}

	/**
	 * Build a one-line human-readable summary for an item.
	 *
	 * @param array  $item              Item.
	 * @param string $category          Category.
	 * @param bool   $requires_consent  Requires consent?
	 * @param string $risk              Risk level.
	 * @return string
	 */
	private function build_summary_line( $item, $category, $requires_consent, $risk ) {
		$name = isset( $item['name'] ) ? $item['name'] : ( isset( $item['handle'] ) ? $item['handle'] : '' );

		/* translators: 1: element name, 2: category, 3: risk level. */
		$fmt = __( '%1$s — category: %2$s, risk: %3$s.', 'gdpr-consent-auditor' );
		$line = sprintf( $fmt, $name, $category, $risk );

		if ( $requires_consent ) {
			$line .= ' ' . __( 'Requires explicit consent before activation.', 'gdpr-consent-auditor' );
		} else {
			$line .= ' ' . __( 'May run without prior consent.', 'gdpr-consent-auditor' );
		}

		return $line;
	}

	/**
	 * Suggest a GDPR legal basis for an item.
	 *
	 * This is a TECHNICAL suggestion — not legal advice.
	 *
	 * @param string $category         Category.
	 * @param bool   $requires_consent Requires consent?
	 * @return array
	 */
	private function suggest_legal_basis( $category, $requires_consent ) {
		if ( ! $requires_consent ) {
			return array(
				'basis'    => 'legitimate_interest',
				'article'  => 'GDPR Art. 6(1)(f)',
				'note'     => __( 'Suggested basis: legitimate interest. Document the LIA and offer an opt-out.', 'gdpr-consent-auditor' ),
			);
		}
		return array(
			'basis'    => 'consent',
			'article'  => 'GDPR Art. 6(1)(a) + ePrivacy Art. 5(3)',
			'note'     => __( 'Suggested basis: consent. Must be granular, freely given, and revocable.', 'gdpr-consent-auditor' ),
		);
	}
}
