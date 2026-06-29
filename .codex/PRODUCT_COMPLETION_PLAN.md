# Plan de finalizacion profesional - GDPR Consent Auditor

## Fase 1 - Saneamiento base

Objetivo: dejar el repo legible, consistente y sin deuda obvia que impida avanzar.

Tareas:

- Corregir mojibake en PHP, JS, CSS, vistas admin, `readme.txt` y reportes.
- Revisar que todos los archivos esten en UTF-8 sin BOM.
- Revisar constantes de version: `Plugin Version`, `GDPR_CA_VERSION`, `readme.txt`.
- Revisar que el autoloader encuentra todas las clases.
- Revisar activacion/desactivacion/uninstall.
- Revisar que no hay dependencias externas no declaradas.
- Revisar naming publico: mantener `gdpr_ca_*` para funciones y `GdprCa_*` para clases.

Cierre:

- PHP lint en todos los `.php`.
- JS syntax check en `admin/js/admin.js` y `public/js/consent-banner.js`.
- Revision visual de caracteres especiales en admin y frontend.

## Fase 2 - Escaner y reportes

Objetivo: que `Run scan` sea confiable y explique sus limites.

Tareas:

- Confirmar que `admin/js/admin.js` no deja el boton bloqueado en error.
- Confirmar que `admin/class-admin.php::ajax_run_scan()` devuelve mensajes utiles.
- Mejorar `includes/class-scanner.php` si hay falsos negativos claros.
- Considerar parser HTML mas robusto para portada publica si las regex fallan.
- Separar claramente `warning` de `finding`.
- Asegurar que `includes/class-report-generator.php` incluye warnings, riesgos y recomendaciones.
- Confirmar export TXT/CSV/JSON con escaping/quoting correcto.

Cierre:

- Tests unitarios para warning de portada vacia, fallo HTTP y deduplicacion.
- QA manual con una portada que tenga scripts, estilos externos e iframes.

## Fase 3 - Banner y UX de consentimiento

Objetivo: banner profesional, configurable y obediente.

Tareas:

- Revisar `admin/views/banner-settings.php`: cada campo visual debe renderizar una vez, con helper de valor correcto.
- Revisar `admin/class-admin.php::sanitize_settings()`: cada campo guardado debe sanitizarse y tener limites razonables.
- Revisar `includes/class-activator.php`: defaults completos.
- Revisar `public/class-public.php`: valores guardados llegan al inline style, CSS vars y `banner_theme_css()`.
- Revisar `public/css/public.css`: responsive, accesible, sin depender de estilos del tema.
- Revisar `public/js/consent-banner.js`: focus, estados, errores REST visibles, reactivacion y revocacion.
- Agregar feedback visible si falla el guardado del consentimiento.
- Verificar que no hay doble banner cuando se renderiza en `wp_body_open` y `wp_footer`.

Cierre:

- QA en layouts `bar`, `modal`, `widget`.
- QA en posiciones `top` y `bottom`.
- QA de colores: fondo, texto, muted, borde, primario, acento, texto de boton.
- QA responsive mobile y desktop.

## Fase 4 - Bloqueo de scripts, iframes e integraciones

Objetivo: privacidad por defecto sin romper el sitio innecesariamente.

Tareas:

- Revisar `includes/class-script-blocker.php` con scripts locales, externos, manual rules y servicios conocidos.
- Revisar que los iframes ocultos no terminan cargando igual antes de consentimiento.
- Verificar que placeholders son accesibles y traducibles.
- Revisar shim dinamico en `public/class-public.php::inject_dynamic_script_blocker_shim()`.
- Validar Google Consent Mode v2: default denied antes de consentimiento, update correcto despues.
- Validar Meta Pixel: no cargar `fbevents.js` antes de marketing consent.
- Evitar bloquear scripts necesarios como seguridad, login o funcionamiento basico.

Cierre:

- QA con GTM, GA4, Meta Pixel y YouTube/Maps.
- Capturas o notas de consola confirmando antes/despues.

## Fase 5 - Seguridad y datos

Objetivo: cierre defensivo antes de pensar en entrega.

Tareas:

- Revisar todos los `wp_ajax_*`.
- Revisar REST `/wp-json/gdpr-ca/v1/consent`.
- Revisar exportaciones y cabeceras.
- Revisar logs de consentimiento: hashing, user agent truncado, user id, retencion.
- Revisar opciones guardadas y borrado en uninstall.
- Revisar si `gdpr_ca_get_client_ip()` debe soportar proxies confiables por filtro en vez de confiar headers.
- Revisar que no se almacenan datos personales innecesarios.

Cierre:

- Actualizar `security_best_practices_report.md`.
- Tests para cookie malformada, version obsoleta y categorias invalidas.

## Fase 6 - Empaquetado y documentacion

Objetivo: que alguien pueda instalar, usar y mantener el plugin.

Tareas:

- Actualizar `readme.txt` con instalacion, uso, limites, FAQ y changelog.
- Documentar que no sustituye asesoria legal.
- Documentar shortcodes, ajustes y exportaciones.
- Documentar servicios detectados y categorias.
- Preparar zip instalable sin tests, vendor innecesario ni archivos locales.
- Si se anade Composer tooling, documentar comandos exactos.

Cierre:

- Instalacion desde zip en WordPress limpio.
- Activacion, configuracion, escaneo y banner probados.
