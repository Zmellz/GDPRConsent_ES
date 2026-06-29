<?php
/**
 * View: Consent logs.
 *
 * @package GdprConsentAuditor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $settings */
/** @var array $logs */
/** @var int $total */
/** @var int $page */
/** @var int $pages */
?>
<div class="wrap gdpr-ca-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<p class="description">
		<?php esc_html_e( 'Los registros de consentimiento solo se guardan en el servidor si la opción correspondiente está activada. Las direcciones IP se seudonimizan por defecto mediante hash SHA-256 con una sal única del sitio.', 'gdpr-consent-auditor' ); ?>
	</p>

	<p>
		<button type="button" class="button button-secondary" id="gdpr-ca-purge-logs">
			<?php esc_html_e( 'Purgar registros caducados', 'gdpr-consent-auditor' ); ?>
		</button>
		<span class="gdpr-ca-muted">
			<?php
			$days = isset( $settings['log_retention_days'] ) ? (int) $settings['log_retention_days'] : 365;
			echo esc_html( sprintf(
				/* translators: %d: retention days. */
				__( 'Retención: %d días', 'gdpr-consent-auditor' ),
				$days
			) );
			?>
		</span>
	</p>

	<?php if ( empty( $logs ) ) : ?>
		<div class="notice notice-info"><p><?php esc_html_e( 'Todavía no hay registros de consentimiento.', 'gdpr-consent-auditor' ); ?></p></div>
	<?php else : ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Fecha', 'gdpr-consent-auditor' ); ?></th>
					<th><?php esc_html_e( 'Identificador', 'gdpr-consent-auditor' ); ?></th>
					<th><?php esc_html_e( 'ID de usuario', 'gdpr-consent-auditor' ); ?></th>
					<th><?php esc_html_e( 'User agent', 'gdpr-consent-auditor' ); ?></th>
					<th><?php esc_html_e( 'Acción', 'gdpr-consent-auditor' ); ?></th>
					<th><?php esc_html_e( 'Categorías', 'gdpr-consent-auditor' ); ?></th>
					<th><?php esc_html_e( 'Versión', 'gdpr-consent-auditor' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $logs as $row ) : ?>
				<tr>
					<td><?php echo esc_html( $row->consent_date ); ?></td>
					<td><code><?php echo esc_html( gdpr_ca_truncate_text( $row->consent_hash, 16 ) ); ?>…</code></td>
					<td><?php echo esc_html( $row->user_id ? (int) $row->user_id : '—' ); ?></td>
					<td><?php echo esc_html( gdpr_ca_truncate_text( $row->user_agent, 60 ) ); ?></td>
					<td><span class="gdpr-ca-action gdpr-ca-action-<?php echo esc_attr( $row->action ); ?>"><?php echo esc_html( $row->action ); ?></span></td>
					<td>
						<?php
						$cats = json_decode( $row->categories, true );
						if ( is_array( $cats ) ) {
							echo esc_html( implode( ', ', $cats ) );
						}
						?>
					</td>
					<td>v<?php echo esc_html( $row->consent_version ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>

		<?php
		$base = admin_url( 'admin.php?page=gdpr-ca-consent-logs' );
		echo '<p class="tablenav">';
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( $page > 1 ) {
			echo '<a class="button button-secondary" href="' . esc_url( add_query_arg( 'paged', $page - 1, $base ) ) . '">' . esc_html__( '« Anterior', 'gdpr-consent-auditor' ) . '</a> ';
		}
		if ( $page < $pages ) {
			echo '<a class="button button-secondary" href="' . esc_url( add_query_arg( 'paged', $page + 1, $base ) ) . '">' . esc_html__( 'Siguiente »', 'gdpr-consent-auditor' ) . '</a>';
		}
		// phpcs:enable
		echo '<span class="gdpr-ca-muted"> ' . esc_html( sprintf( /* translators: 1: page, 2: total pages, 3: total items */ __( 'Page %1$d of %2$d · %3$d total', 'gdpr-consent-auditor' ), $page, $pages, $total ) ) . '</span>';
		echo '</p>';
		?>
	<?php endif; ?>
</div>
