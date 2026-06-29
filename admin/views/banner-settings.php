<?php
/**
 * View: Banner settings.
 *
 * @package GdprConsentAuditor
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var array $settings */

$categories = isset( $settings['categories'] ) ? $settings['categories'] : array();
$pages      = get_pages();

$val = function( $key, $default = '' ) use ( $settings ) {
	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
};

$color_palette = array(
	array( 'primary_color',       '#1a73e8', __( 'Principal', 'gdpr-consent-auditor' ) ),
	array( 'accent_color',        '#202124', __( 'Acento', 'gdpr-consent-auditor' ) ),
	array( 'background_color',    '#ffffff', __( 'Fondo', 'gdpr-consent-auditor' ) ),
	array( 'text_color',          '#202124', __( 'Texto principal', 'gdpr-consent-auditor' ) ),
	array( 'muted_color',         '#5f6368', __( 'Texto secundario', 'gdpr-consent-auditor' ) ),
	array( 'border_color',        '#d9dee7', __( 'Borde', 'gdpr-consent-auditor' ) ),
	array( 'button_text_color',   '#ffffff', __( 'Texto de botones', 'gdpr-consent-auditor' ) ),
);
?>
<div class="wrap gdpr-ca-wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<form method="post" action="options.php">
		<?php settings_fields( 'gdpr_ca_settings_group' ); ?>

		<!-- ========== 1. ACTIVACION ========== -->
		<div class="gdpr-ca-settings-card">
			<h2><span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'Activacion', 'gdpr-consent-auditor' ); ?></h2>
			<label>
				<input type="checkbox" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[banner_enabled]" value="1" <?php checked( $val( 'banner_enabled', 1 ), 1 ); ?> />
				<?php esc_html_e( 'Mostrar el banner de consentimiento en la parte publica', 'gdpr-consent-auditor' ); ?>
			</label>
		</div>

		<!-- ========== 2. APARIENCIA (colores + radio) ========== -->
		<div class="gdpr-ca-settings-card">
			<h2><span class="dashicons dashicons-art"></span> <?php esc_html_e( 'Apariencia', 'gdpr-consent-auditor' ); ?></h2>
			<div class="gdpr-ca-color-grid">
				<?php foreach ( $color_palette as $i => $c ) :
					$key     = $c[0];
					$default = $c[1];
					$label   = $c[2];
					$hex     = $val( $key, $default );
					$opacity = $val( $key . '_opacity', 100 );
				?>
				<div class="gdpr-ca-color-item">
					<input type="color" class="gdpr-ca-color" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $hex ); ?>" title="<?php echo esc_attr( $label ); ?>" />
					<span class="gdpr-ca-color-label"><?php echo esc_html( $label ); ?></span>
					<span class="gdpr-ca-opacity-wrap">
						<input type="range" min="0" max="100" step="5" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[<?php echo esc_attr( $key ); ?>_opacity]" value="<?php echo esc_attr( $opacity ); ?>" class="gdpr-ca-opacity-range" />
						<span class="gdpr-ca-opacity-val"><?php echo esc_html( $opacity ); ?>%</span>
					</span>
				</div>
				<?php endforeach; ?>
			</div>
			<div class="gdpr-ca-field-row" style="margin-top:14px;padding-top:14px;border-top:1px solid #f0f0f1;">
				<div class="gdpr-ca-field">
					<label><?php esc_html_e( 'Radio de esquinas (px)', 'gdpr-consent-auditor' ); ?></label>
					<input type="number" min="0" max="32" step="1" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[banner_radius]" value="<?php echo esc_attr( $val( 'banner_radius', 18 ) ); ?>" />
				</div>
			</div>
		</div>

		<!-- ========== 3. DISENO ========== -->
		<div class="gdpr-ca-settings-card">
			<h2><span class="dashicons dashicons-layout"></span> <?php esc_html_e( 'Diseno', 'gdpr-consent-auditor' ); ?></h2>
			<div class="gdpr-ca-field-row">
				<div class="gdpr-ca-field">
					<label><?php esc_html_e( 'Tipo', 'gdpr-consent-auditor' ); ?></label>
					<select name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[banner_layout]">
						<option value="bar" <?php selected( $val( 'banner_layout', 'bar' ), 'bar' ); ?>><?php esc_html_e( 'Barra', 'gdpr-consent-auditor' ); ?></option>
						<option value="modal" <?php selected( $val( 'banner_layout', 'bar' ), 'modal' ); ?>><?php esc_html_e( 'Modal centrado', 'gdpr-consent-auditor' ); ?></option>
						<option value="widget" <?php selected( $val( 'banner_layout', 'bar' ), 'widget' ); ?>><?php esc_html_e( 'Widget flotante', 'gdpr-consent-auditor' ); ?></option>
					</select>
				</div>
				<div class="gdpr-ca-field">
					<label><?php esc_html_e( 'Posicion', 'gdpr-consent-auditor' ); ?></label>
					<select name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[banner_position]">
						<option value="bottom" <?php selected( $val( 'banner_position', 'bottom' ), 'bottom' ); ?>><?php esc_html_e( 'Inferior', 'gdpr-consent-auditor' ); ?></option>
						<option value="top" <?php selected( $val( 'banner_position', 'bottom' ), 'top' ); ?>><?php esc_html_e( 'Superior', 'gdpr-consent-auditor' ); ?></option>
					</select>
				</div>
				<div class="gdpr-ca-field">
					<label><?php esc_html_e( 'Separacion exterior (px)', 'gdpr-consent-auditor' ); ?></label>
					<input type="number" min="0" max="80" step="1" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[banner_offset]" value="<?php echo esc_attr( $val( 'banner_offset', 20 ) ); ?>" />
				</div>
			</div>
		</div>

		<!-- ========== 4. DIMENSIONES ========== -->
		<div class="gdpr-ca-settings-card">
			<h2><span class="dashicons dashicons-editor-expand"></span> <?php esc_html_e( 'Dimensiones', 'gdpr-consent-auditor' ); ?></h2>
			<div class="gdpr-ca-field-row">
				<div class="gdpr-ca-field">
					<label><?php esc_html_e( 'Ancho maximo (px)', 'gdpr-consent-auditor' ); ?></label>
					<input type="number" min="320" max="1600" step="10" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[banner_max_width]" value="<?php echo esc_attr( $val( 'banner_max_width', 1040 ) ); ?>" />
				</div>
			</div>
			<div class="gdpr-ca-field-row">
				<div class="gdpr-ca-field" style="flex:0;min-width:0;">
					<label style="margin-bottom:2px;"><?php esc_html_e( 'Relleno interior (px)', 'gdpr-consent-auditor' ); ?></label>
					<div class="gdpr-ca-padding-grid">
						<div class="gdpr-ca-padding-item">
							<input type="number" min="0" max="80" step="2" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[banner_padding_top]" value="<?php echo esc_attr( $val( 'banner_padding_top', 24 ) ); ?>" />
							<label><?php esc_html_e( 'Sup', 'gdpr-consent-auditor' ); ?></label>
						</div>
						<div class="gdpr-ca-padding-item">
							<input type="number" min="0" max="80" step="2" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[banner_padding_right]" value="<?php echo esc_attr( $val( 'banner_padding_right', 24 ) ); ?>" />
							<label><?php esc_html_e( 'Der', 'gdpr-consent-auditor' ); ?></label>
						</div>
						<div class="gdpr-ca-padding-item">
							<input type="number" min="0" max="80" step="2" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[banner_padding_bottom]" value="<?php echo esc_attr( $val( 'banner_padding_bottom', 24 ) ); ?>" />
							<label><?php esc_html_e( 'Inf', 'gdpr-consent-auditor' ); ?></label>
						</div>
						<div class="gdpr-ca-padding-item">
							<input type="number" min="0" max="80" step="2" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[banner_padding_left]" value="<?php echo esc_attr( $val( 'banner_padding_left', 24 ) ); ?>" />
							<label><?php esc_html_e( 'Izq', 'gdpr-consent-auditor' ); ?></label>
						</div>
					</div>
				</div>
			</div>
		</div>

		<!-- ========== 5. TEXTOS Y TIPOGRAFIA ========== -->
		<div class="gdpr-ca-settings-card">
			<h2><span class="dashicons dashicons-editor-textcolor"></span> <?php esc_html_e( 'Textos y tipografia', 'gdpr-consent-auditor' ); ?></h2>

			<div class="gdpr-ca-field-row">
				<div class="gdpr-ca-field" style="flex:1;">
					<label><?php esc_html_e( 'Titulo', 'gdpr-consent-auditor' ); ?></label>
					<input type="text" class="regular-text" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[banner_title]" value="<?php echo esc_attr( $val( 'banner_title' ) ); ?>" />
				</div>
				<div class="gdpr-ca-field">
					<label><?php esc_html_e( 'Tamano (px)', 'gdpr-consent-auditor' ); ?></label>
					<input type="number" min="12" max="48" step="1" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[font_size_title]" value="<?php echo esc_attr( $val( 'font_size_title', 20 ) ); ?>" />
				</div>
			</div>
			<div class="gdpr-ca-field-row">
				<div class="gdpr-ca-field" style="flex:1;">
					<label><?php esc_html_e( 'Mensaje', 'gdpr-consent-auditor' ); ?></label>
					<textarea rows="3" class="large-text" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[banner_message]"><?php echo esc_textarea( $val( 'banner_message' ) ); ?></textarea>
				</div>
				<div class="gdpr-ca-field">
					<label><?php esc_html_e( 'Tamano (px)', 'gdpr-consent-auditor' ); ?></label>
					<input type="number" min="10" max="36" step="1" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[font_size_message]" value="<?php echo esc_attr( $val( 'font_size_message', 14 ) ); ?>" />
				</div>
			</div>
			<div class="gdpr-ca-field-row">
				<div class="gdpr-ca-field">
					<label><?php esc_html_e( 'Aceptar todo', 'gdpr-consent-auditor' ); ?></label>
					<input type="text" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[accept_all_label]" value="<?php echo esc_attr( $val( 'accept_all_label' ) ); ?>" />
				</div>
				<div class="gdpr-ca-field">
					<label><?php esc_html_e( 'Rechazar todo', 'gdpr-consent-auditor' ); ?></label>
					<input type="text" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[reject_all_label]" value="<?php echo esc_attr( $val( 'reject_all_label' ) ); ?>" />
				</div>
				<div class="gdpr-ca-field">
					<label><?php esc_html_e( 'Configurar', 'gdpr-consent-auditor' ); ?></label>
					<input type="text" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[configure_label]" value="<?php echo esc_attr( $val( 'configure_label' ) ); ?>" />
				</div>
				<div class="gdpr-ca-field">
					<label><?php esc_html_e( 'Guardar seleccion', 'gdpr-consent-auditor' ); ?></label>
					<input type="text" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[save_label]" value="<?php echo esc_attr( $val( 'save_label' ) ); ?>" />
				</div>
				<div class="gdpr-ca-field">
					<label><?php esc_html_e( 'Tamano botones (px)', 'gdpr-consent-auditor' ); ?></label>
					<input type="number" min="10" max="28" step="1" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[font_size_buttons]" value="<?php echo esc_attr( $val( 'font_size_buttons', 14 ) ); ?>" />
				</div>
				<div class="gdpr-ca-field">
					<label><?php esc_html_e( 'Alineacion', 'gdpr-consent-auditor' ); ?></label>
					<select name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[banner_text_align]">
						<option value="left" <?php selected( $val( 'banner_text_align', 'center' ), 'left' ); ?>><?php esc_html_e( 'Izquierda', 'gdpr-consent-auditor' ); ?></option>
						<option value="center" <?php selected( $val( 'banner_text_align', 'center' ), 'center' ); ?>><?php esc_html_e( 'Centrado', 'gdpr-consent-auditor' ); ?></option>
						<option value="right" <?php selected( $val( 'banner_text_align', 'center' ), 'right' ); ?>><?php esc_html_e( 'Derecha', 'gdpr-consent-auditor' ); ?></option>
					</select>
				</div>
			</div>
			<div class="gdpr-ca-field-row">
				<div class="gdpr-ca-field" style="min-width:160px;">
					<label><?php esc_html_e( 'Pagina de politica', 'gdpr-consent-auditor' ); ?></label>
					<select name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[policy_page_id]">
						<option value="0"><?php esc_html_e( '- Ninguna -', 'gdpr-consent-auditor' ); ?></option>
						<?php foreach ( $pages as $p ) : ?>
							<option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $val( 'policy_page_id', 0 ), $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="gdpr-ca-field">
					<label><?php esc_html_e( 'Texto del enlace', 'gdpr-consent-auditor' ); ?></label>
					<input type="text" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[policy_link_label]" value="<?php echo esc_attr( $val( 'policy_link_label' ) ); ?>" />
				</div>
			</div>
		</div>

		<!-- ========== 6. CATEGORIAS ========== -->
		<div class="gdpr-ca-settings-card">
			<h2><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Categorias de consentimiento', 'gdpr-consent-auditor' ); ?></h2>
			<p><?php esc_html_e( 'Edita las etiquetas y descripciones de cada categoria. "Necesarias" esta siempre activa y los visitantes no pueden desactivarla.', 'gdpr-consent-auditor' ); ?></p>
			<table class="gdpr-ca-cat-settings-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Clave', 'gdpr-consent-auditor' ); ?></th>
						<th><?php esc_html_e( 'Etiqueta', 'gdpr-consent-auditor' ); ?></th>
						<th><?php esc_html_e( 'Descripcion', 'gdpr-consent-auditor' ); ?></th>
						<th><?php esc_html_e( 'Siempre activa', 'gdpr-consent-auditor' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( gdpr_ca_known_categories() as $cat_key => $cat_meta ) :
					$cfg = isset( $categories[ $cat_key ] ) ? $categories[ $cat_key ] : array(
						'label'       => $cat_meta['label'],
						'description' => '',
						'always_on'   => $cat_meta['always_on'] ? 1 : 0,
					);
					?>
					<tr>
						<td><code><?php echo esc_html( $cat_key ); ?></code></td>
						<td><input type="text" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[categories][<?php echo esc_attr( $cat_key ); ?>][label]" value="<?php echo esc_attr( isset( $cfg['label'] ) ? $cfg['label'] : '' ); ?>" /></td>
						<td><textarea rows="2" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[categories][<?php echo esc_attr( $cat_key ); ?>][description]"><?php echo esc_textarea( isset( $cfg['description'] ) ? $cfg['description'] : '' ); ?></textarea></td>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[categories][<?php echo esc_attr( $cat_key ); ?>][always_on]" value="1" <?php checked( isset( $cfg['always_on'] ) ? (int) $cfg['always_on'] : 0, 1 ); ?> <?php disabled( $cat_key, 'necessary' ); ?> />
								<?php esc_html_e( 'Siempre activa', 'gdpr-consent-auditor' ); ?>
							</label>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<!-- ========== 7. BLOQUEO MANUAL ========== -->
		<div class="gdpr-ca-settings-card">
			<h2><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'Bloqueo manual de scripts', 'gdpr-consent-auditor' ); ?></h2>
			<p><?php esc_html_e( 'Anade una subcadena o patron regex. El script quedara bloqueado hasta que el visitante acepte la categoria elegida.', 'gdpr-consent-auditor' ); ?></p>
			<table class="widefat striped" id="gdpr-ca-manual-blocks">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Patron', 'gdpr-consent-auditor' ); ?></th>
						<th><?php esc_html_e( 'Categoria', 'gdpr-consent-auditor' ); ?></th>
						<th><?php esc_html_e( 'Eliminar', 'gdpr-consent-auditor' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				$manual = isset( $settings['manual_blocks'] ) ? $settings['manual_blocks'] : array();
				if ( ! is_array( $manual ) ) {
					$manual = array();
				}
				if ( empty( $manual ) ) {
					$manual = array( array( 'pattern' => '', 'category' => 'statistics' ) );
				}
				foreach ( $manual as $i => $rule ) :
					?>
					<tr class="gdpr-ca-manual-row">
						<td><input type="text" name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[manual_blocks][<?php echo esc_attr( $i ); ?>][pattern]" value="<?php echo esc_attr( isset( $rule['pattern'] ) ? $rule['pattern'] : '' ); ?>" placeholder="p. ej. googletagmanager" /></td>
						<td>
							<select name="<?php echo esc_attr( GDPR_CA_OPTION_NAME ); ?>[manual_blocks][<?php echo esc_attr( $i ); ?>][category]">
								<?php foreach ( gdpr_ca_known_categories() as $cat_key => $cat_meta ) : ?>
									<option value="<?php echo esc_attr( $cat_key ); ?>" <?php selected( isset( $rule['category'] ) ? $rule['category'] : '', $cat_key ); ?>><?php echo esc_html( $cat_meta['label'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
						<td><button type="button" class="button gdpr-ca-remove-row"><?php esc_html_e( 'Eliminar', 'gdpr-consent-auditor' ); ?></button></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
			<p><button type="button" class="button button-secondary" id="gdpr-ca-add-row"><?php esc_html_e( 'Anadir regla', 'gdpr-consent-auditor' ); ?></button></p>
		</div>

		<?php submit_button( __( 'Guardar ajustes del banner', 'gdpr-consent-auditor' ) ); ?>
	</form>
</div>
