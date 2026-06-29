<?php
/**
 * View: Legal templates.
 *
 * @package GdprConsentAuditor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $settings */

$templates = GdprCa_Legal_Templates::template_list();
echo GdprCa_Legal_Templates::disclaimer_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped internally.
?>
<div class="wrap gdpr-ca-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<form method="post" action="options.php">
		<?php settings_fields( 'gdpr_ca_settings_group' ); ?>

		<?php foreach ( $templates as $key => $label ) :
			$field_name = GDPR_CA_OPTION_NAME . '[legal_' . $key . '_text]';
			$value      = isset( $settings[ 'legal_' . $key . '_text' ] ) ? $settings[ 'legal_' . $key . '_text' ] : '';
			if ( '' === $value ) {
				$value = GdprCa_Legal_Templates::defaults();
				$value = isset( $value[ $key ] ) ? $value[ $key ] : '';
			}
			?>
			<h2><?php echo esc_html( $label ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Puedes usar estos marcadores:', 'gdpr-consent-auditor' ); ?>
				<code>{site_name}</code>, <code>{site_url}</code>, <code>{contact_email}</code>, <code>{date}</code>
			</p>
			<textarea rows="14" class="large-text code gdpr-ca-legal-textarea" name="<?php echo esc_attr( $field_name ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
		<?php endforeach; ?>

		<?php submit_button( __( 'Guardar plantillas legales', 'gdpr-consent-auditor' ) ); ?>
	</form>
</div>
