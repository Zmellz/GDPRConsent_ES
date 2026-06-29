# Referencias tecnicas del repositorio - GDPR Consent Auditor

## Mapa de archivos

- `gdpr-consent-auditor.php`: metadata del plugin, constantes, autoload, hooks de activacion/desactivacion y arranque en `plugins_loaded`.
- `includes/helpers.php`: opciones, version de consentimiento, almacenamiento de escaneo, catalogo de servicios, categorias, sanitizacion auxiliar.
- `includes/class-plugin.php`: singleton transversal, cron diario y ruta REST de consentimiento.
- `includes/class-activator.php`: defaults, tabla de logs y programacion inicial.
- `includes/class-deactivator.php`: limpieza de eventos programados.
- `includes/class-scanner.php`: scan de plugins, tema, scripts/styles registrados, portada publica, cookies, servicios conocidos, resumen y warnings.
- `includes/class-consent-manager.php`: acciones de consentimiento, cookie, logs y lectura del estado actual.
- `includes/class-script-blocker.php`: bloqueo de scripts, iframes y oEmbeds por categoria.
- `includes/class-report-generator.php`: reporte HTML/TXT/CSV/JSON.
- `includes/class-legal-templates.php`: textos legales de apoyo.
- `admin/class-admin.php`: menus, settings, sanitizacion, assets, AJAX, vistas y notices.
- `admin/views/*.php`: pantallas admin.
- `admin/js/admin.js`: Run scan, exportaciones, purge y reglas manuales.
- `public/class-public.php`: assets, GCM, Meta Pixel, shim dinamico, render banner y shortcode de revocacion.
- `public/js/consent-banner.js`: post REST, UI del banner, GCM update, Meta Pixel, reactivacion y revoke.
- `public/css/public.css`: banner publico y placeholders.
- `tests/phpunit/stubs/wp-stubs.php`: WordPress minimo para unit tests sin WP real.
- `tests/phpunit/unit/*.php`: pruebas unitarias actuales.
- `security_best_practices_report.md`: estado de auditoria tecnica anterior.

## Flujos criticos

### Run scan

1. Usuario pulsa `#gdpr-ca-run-scan`.
2. `admin/js/admin.js` envia `action=gdpr_ca_run_scan` a `admin-ajax.php`.
3. `admin/class-admin.php::ajax_run_scan()` valida nonce y capability.
4. Instancia `GdprCa_Scanner`.
5. `scan()` combina `scan_scripts()` y `scan_homepage_assets()`.
6. Guarda con `gdpr_ca_store_last_scan_results()`.
7. Devuelve `summary` y `warnings`.
8. El admin muestra notice y recarga.

Puntos fragiles:

- Portada inaccesible, HTML vacio o HTTP no 200.
- Regex HTML incompletas.
- Servicios cargados dinamicamente despues del render.
- Falsos positivos en stylesheets genericas.

### Consentimiento

1. `public/class-public.php` renderiza banner si no hay consentimiento valido.
2. `public/js/consent-banner.js` captura accion.
3. JS llama REST `gdpr-ca/v1/consent`.
4. `includes/class-plugin.php::rest_store_consent()` valida nonce.
5. `GdprCa_Consent_Manager::record_consent()` normaliza categorias, guarda cookie y log.
6. Respuesta devuelve categorias activas.
7. JS actualiza GCM, Meta Pixel, reactiva scripts/iframes y oculta banner.

Puntos fragiles:

- Cookie path/domain incorrectos al revocar.
- Version de consentimiento no sube cuando debe o sube cuando no debe.
- Error REST silencioso.
- Categoria `necessary` ausente en custom.

### Bloqueo

1. `GdprCa_Public` instancia `GdprCa_Script_Blocker` solo si banner esta habilitado.
2. `script_loader_tag` cambia scripts bloqueables a `type="text/plain"`.
3. `the_content`, `widget_text_content` y `oembed_result` reemplazan iframes por placeholders.
4. Shim dinamico intercepta scripts creados por JS si `block_dynamic_scripts=1`.
5. Al aceptar categorias, JS clona/reactiva scripts y carga iframes.

Puntos fragiles:

- Iframe original oculto que aun carga antes de consentimiento.
- Scripts con atributos no preservados.
- Shim dinamico que rompe scripts necesarios.
- Reglas manuales con regex invalidas.
