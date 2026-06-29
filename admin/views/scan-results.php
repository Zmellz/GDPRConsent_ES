<?php
/**
 * View: Scan results.
 *
 * @package GdprConsentAuditor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $settings */
/** @var array $results */

$warnings = isset( $results['warnings'] ) && is_array( $results['warnings'] ) ? $results['warnings'] : array();

$sections = array(
	'plugins'  => __( 'Plugins', 'gdpr-consent-auditor' ),
	'theme'    => __( 'Tema', 'gdpr-consent-auditor' ),
	'scripts'  => __( 'Scripts y estilos', 'gdpr-consent-auditor' ),
	'services' => __( 'Servicios de terceros', 'gdpr-consent-auditor' ),
	'cookies'  => __( 'Cookies (lado servidor)', 'gdpr-consent-auditor' ),
);

$risk_counts = array( 'high' => 0, 'medium' => 0, 'low' => 0 );
foreach ( $sections as $key => $label ) {
	$rows = isset( $results[ $key ] ) ? $results[ $key ] : array();
	if ( 'theme' === $key && is_array( $rows ) && ! isset( $rows[0] ) ) {
		$rows = array( $rows );
	}
	if ( is_array( $rows ) ) {
		foreach ( $rows as $row ) {
			$r = isset( $row['risk'] ) ? $row['risk'] : 'low';
			if ( isset( $risk_counts[ $r ] ) ) {
				$risk_counts[ $r ]++;
			}
		}
	}
}
?>
<div class="wrap gdpr-ca-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php if ( empty( $results ) ) : ?>
		<div class="notice notice-warning"><p>
			<?php esc_html_e( 'Todavia no hay resultados. Ejecuta primero un escaneo desde el panel.', 'gdpr-consent-auditor' ); ?>
		</p></div>
	<?php else : ?>

		<div class="gdpr-ca-cards">
			<div class="gdpr-ca-card">
				<span class="gdpr-ca-card-label"><?php esc_html_e( 'Riesgo alto', 'gdpr-consent-auditor' ); ?></span>
				<span class="gdpr-ca-card-value gdpr-ca-risk-high" style="background:none;padding:0;font-size:28px;"><?php echo esc_html( $risk_counts['high'] ); ?></span>
			</div>
			<div class="gdpr-ca-card">
				<span class="gdpr-ca-card-label"><?php esc_html_e( 'Riesgo medio', 'gdpr-consent-auditor' ); ?></span>
				<span class="gdpr-ca-card-value gdpr-ca-risk-medium" style="background:none;padding:0;font-size:28px;"><?php echo esc_html( $risk_counts['medium'] ); ?></span>
			</div>
			<div class="gdpr-ca-card">
				<span class="gdpr-ca-card-label"><?php esc_html_e( 'Riesgo bajo', 'gdpr-consent-auditor' ); ?></span>
				<span class="gdpr-ca-card-value gdpr-ca-risk-low" style="background:none;padding:0;font-size:28px;"><?php echo esc_html( $risk_counts['low'] ); ?></span>
			</div>
		</div>

		<?php if ( ! empty( $warnings ) ) : ?>
			<div class="notice notice-warning"><p>
				<strong><?php esc_html_e( 'Advertencias del escaneo:', 'gdpr-consent-auditor' ); ?></strong>
			</p><ul>
				<?php foreach ( $warnings as $warning ) : ?>
					<li><?php echo esc_html( $warning ); ?></li>
				<?php endforeach; ?>
			</ul></div>
		<?php endif; ?>

		<p class="description">
			<?php esc_html_e( 'Elementos detectados durante el escaneo mas reciente. La clasificacion es heuristica: revisa cada elemento antes de actuar.', 'gdpr-consent-auditor' ); ?>
		</p>

		<?php foreach ( $sections as $key => $label ) : ?>
			<?php
			$rows = isset( $results[ $key ] ) ? $results[ $key ] : array();
			if ( empty( $rows ) ) {
				continue;
			}
			$count = is_array( $rows ) ? count( $rows ) : 1;
			?>
			<div class="gdpr-ca-scan-section">
				<h3>
					<?php echo esc_html( $label ); ?>
					<span class="count-badge"><?php echo esc_html( $count ); ?></span>
				</h3>
				<table class="widefat striped gdpr-ca-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Nombre / handle', 'gdpr-consent-auditor' ); ?></th>
							<th><?php esc_html_e( 'Origen', 'gdpr-consent-auditor' ); ?></th>
							<th><?php esc_html_e( 'Tipo', 'gdpr-consent-auditor' ); ?></th>
							<th><?php esc_html_e( 'Categoria', 'gdpr-consent-auditor' ); ?></th>
							<th><?php esc_html_e( 'Requiere consentimiento', 'gdpr-consent-auditor' ); ?></th>
							<th><?php esc_html_e( 'Riesgo', 'gdpr-consent-auditor' ); ?></th>
							<th><?php esc_html_e( 'Recomendacion', 'gdpr-consent-auditor' ); ?></th>
						</tr>
					</thead>
					<tbody>
					<?php
					if ( 'theme' === $key && is_array( $rows ) && ! isset( $rows[0] ) ) {
						$rows = array( $rows );
					}
					foreach ( $rows as $row ) :
						$name = isset( $row['name'] ) ? $row['name'] : ( isset( $row['handle'] ) ? $row['handle'] : '' );
						?>
						<tr>
							<td><?php echo esc_html( $name ); ?>
								<?php if ( ! empty( $row['version'] ) ) : ?>
									<span class="gdpr-ca-muted">v<?php echo esc_html( $row['version'] ); ?></span>
								<?php endif; ?>
								<?php if ( isset( $row['active'] ) && ! $row['active'] ) : ?>
									<span class="gdpr-ca-tag gdpr-ca-tag-inactive"><?php esc_html_e( 'inactivo', 'gdpr-consent-auditor' ); ?></span>
								<?php endif; ?>
								<?php if ( ! empty( $row['matched_service'] ) ) : ?>
									<span class="gdpr-ca-tag gdpr-ca-tag-service"><?php echo esc_html( $row['matched_service'] ); ?></span>
								<?php endif; ?>
							</td>
							<td><code><?php echo esc_html( gdpr_ca_truncate_text( isset( $row['source'] ) ? $row['source'] : '', 90 ) ); ?></code></td>
							<td><?php echo esc_html( isset( $row['type'] ) ? $row['type'] : '-' ); ?></td>
							<td><?php echo esc_html( ucfirst( isset( $row['category'] ) ? $row['category'] : '' ) ); ?></td>
							<td><?php echo ! empty( $row['requires_consent'] ) ? esc_html__( 'Si', 'gdpr-consent-auditor' ) : esc_html__( 'No', 'gdpr-consent-auditor' ); ?></td>
							<td><span class="gdpr-ca-risk gdpr-ca-risk-<?php echo esc_attr( isset( $row['risk'] ) ? $row['risk'] : 'low' ); ?>"><?php echo esc_html( gdpr_ca_translate_risk_label( isset( $row['risk'] ) ? $row['risk'] : 'low' ) ); ?></span></td>
							<td><?php echo esc_html( isset( $row['recommendation'] ) ? $row['recommendation'] : '' ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endforeach; ?>

	<?php endif; ?>
</div>
