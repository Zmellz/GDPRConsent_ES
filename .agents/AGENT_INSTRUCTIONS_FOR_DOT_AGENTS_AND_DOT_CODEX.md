# Instrucciones para agentes - GDPR Consent Auditor

Este documento es el paquete maestro que debe vivir repartido entre `.agents` y `.codex`.

Nota de entorno: en esta sesion las carpetas `.agents` y `.codex` tienen ACL con `Deny Write`, por lo que no se pudo escribir directamente dentro de ellas aunque el permiso de la sesion fue concedido. El contenido esta preparado aqui y en `install-agent-instructions.ps1` para instalarlo cuando el bloqueo local se retire.

## Objetivo del proyecto

Construir y terminar un plugin profesional de WordPress para auditoria tecnica de privacidad, cookies, scripts de terceros y consentimiento granular. El plugin debe ayudar a detectar riesgos, generar reportes utiles y ofrecer un banner configurable que bloquee scripts/iframes hasta que exista consentimiento valido.

Este producto aun no debe tratarse como terminado. La direccion correcta es convertir lo ya construido en una herramienta robusta, verificable, mantenible y usable en WordPress real.

Importante: el plugin es una herramienta tecnica de apoyo. No debe prometer cumplimiento legal completo. Mantener siempre el disclaimer de revision por profesional cualificado.

## Estado actual conocido

- Entrada principal: `gdpr-consent-auditor.php`.
- Bootstrap transversal: `includes/class-plugin.php`.
- Admin y AJAX: `admin/class-admin.php`, `admin/js/admin.js`.
- Escaneo: `includes/class-scanner.php`.
- Consentimiento: `includes/class-consent-manager.php`.
- Bloqueo de scripts/iframes: `includes/class-script-blocker.php`.
- Frontend publico y banner: `public/class-public.php`, `public/js/consent-banner.js`, `public/css/public.css`.
- Ajustes del banner: `admin/views/banner-settings.php`.
- Reportes: `includes/class-report-generator.php`.
- Tests unitarios con stubs WP: `tests/phpunit`.
- Informe tecnico existente: `security_best_practices_report.md`.

Trabajo previo relevante:

- `Run scan` ya fue reconectado para usar `includes/class-plugin.php` y para inspeccionar tambien la portada publica con `wp_remote_get`.
- El escaner ahora devuelve advertencias no fatales cuando no puede leer la portada publica.
- El banner ya tiene mas controles visuales y estilos mas fuertes para vencer CSS de tema o Elementor.
- Se corrigieron problemas de revocacion de cookie, versionado de consentimiento y bloqueo cuando el banner esta desactivado.
- No se pudo ejecutar PHPUnit ni lint PHP en el entorno anterior porque no habia `php` ni `composer`.

## Principios de trabajo

1. No asumir que una UI que "se ve" funciona. Trazar siempre desde el click hasta almacenamiento, respuesta AJAX/REST, render y efecto visible.
2. No declarar listo nada de privacidad sin pruebas funcionales y comprobacion manual en WordPress real.
3. Mantener enfoque defensivo: nonces, capacidades, sanitizacion, escaping, permisos, datos minimos y degradacion segura.
4. Evitar refactors amplios. Arreglar por flujo y por contrato observable.
5. En cambios de banner, el frontend debe consumir directamente los ajustes guardados. No basta con agregar campos en wp-admin.
6. En cambios de escaner, diferenciar hallazgos seguros, heuristicas y advertencias de cobertura incompleta.
7. En cambios legales, no inventar promesas. Usar texto claro y editable, con disclaimer.
8. Corregir mojibake antes de cerrar. El repo contiene textos en espanol con caracteres rotos como `PolÃ­tica`, `selecciÃ³n`, `â€”`.

## Skills locales para usar

### Skill: `wp-plugin-finisher`

Usar para cualquier tarea de finalizacion global del plugin.

Checklist:

- Leer primero `.agents/PROJECT_AGENT_GUIDE.md`, `.agents/PRODUCT_COMPLETION_PLAN.md` y `.agents/TECHNICAL_REFERENCES.md`.
- Identificar el flujo afectado: escaneo, banner, consentimiento, bloqueo, reportes, admin, tests o empaquetado.
- Revisar los archivos fuente y la vista afectada antes de editar.
- Confirmar que el cambio cubre admin, almacenamiento, frontend y tests cuando corresponda.
- Actualizar docs si el comportamiento publico cambia.
- Al final, reportar que se valido y que queda pendiente si el entorno no permite ejecutar PHP.

### Skill: `privacy-security-review`

Usar para auditoria de seguridad, privacidad o cumplimiento tecnico.

Enfoque:

- Revisar nonces y capabilities en todos los endpoints admin.
- Revisar permisos REST publicos y validacion de nonce.
- Revisar escaping por contexto: HTML, attr, URL, JS, textarea.
- Revisar sanitizacion de opciones y payloads de consentimiento.
- Revisar cookies: version, ruta, dominio, SameSite, secure, httponly, expiracion.
- Revisar logs: minimizacion de datos, hash IP, retencion, exportacion y purga.
- Revisar SQL: `$wpdb->prepare`, tablas internas, no interpolar entrada usuario.
- Revisar bloqueo antes de consentimiento y reactivacion posterior.
- Revisar que no haya tracking propio del plugin.

Salida esperada:

- Hallazgos ordenados por severidad.
- Archivo y linea.
- Impacto practico.
- Correccion concreta.
- Prueba o comprobacion que demuestra el cierre.

### Skill: `banner-consent-flow`

Usar para todo lo relacionado con banner, estilos, consentimiento, revocacion, Google Consent Mode v2, Meta Pixel o bloqueo/reactivacion.

Contrato funcional:

- Visitante sin consentimiento ve banner si `banner_enabled=1`.
- `Aceptar todo` activa `necessary`, `preferences`, `statistics`, `marketing`.
- `Rechazar todo` deja solo `necessary`.
- `Configurar` permite seleccion granular y siempre incluye `necessary`.
- Al guardar, se registra cookie con version actual.
- Al cambiar campos que afectan consentimiento, sube la version y el consentimiento anterior queda obsoleto.
- El shortcode `[gdpr_ca_revoke]` debe poder borrar la cookie con ruta/dominio correctos.
- Si el banner esta desactivado, no debe quedar bloqueo activo sin mecanismo para consentir.
- Los colores y dimensiones configurados deben verse en frontend aunque el tema tenga CSS agresivo.
- Scripts e iframes bloqueados deben reactivarse al aceptar la categoria correspondiente.

Pruebas minimas:

- Unitarias de `GdprCa_Consent_Manager`.
- Prueba manual en navegador sin cookie, con accept all, reject all, custom y revoke.
- Inspeccionar `document.cookie`.
- Confirmar que GCM cambia de `denied` a `granted` segun categoria.
- Confirmar que Meta Pixel no carga antes de marketing consent.
- Confirmar que un iframe de YouTube/Maps queda placeholder antes de consentimiento y carga despues.

### Skill: `scanner-reporting-flow`

Usar para `Run scan`, clasificacion, hallazgos, warnings y exportaciones.

Contrato funcional:

- Click en `Run scan` llama a `wp_ajax_gdpr_ca_run_scan`.
- El boton muestra estado de carga, exito, advertencia o error.
- El servidor valida nonce y capability.
- El escaner combina activos registrados en WP con recursos reales detectados en la portada publica.
- Los resultados se guardan en `gdpr_ca_last_scan_results`, en ajustes legacy y en transient.
- La pantalla de resultados muestra warnings si la portada no pudo inspeccionarse.
- Exportaciones TXT, CSV y JSON descargan el ultimo reporte.

Pruebas minimas:

- Probar con portada accesible.
- Probar con fallo HTTP de portada y confirmar warning visible.
- Probar servicios conocidos: Google Analytics, GTM, Meta Pixel, YouTube, Vimeo, Google Maps, Hotjar, TikTok, LinkedIn.
- Probar deduplicacion por `type|source`.
- Probar que el resumen cuente plugins, scripts, externos, servicios, cookies, warnings y riesgo.

## Plan de finalizacion profesional

### Fase 1 - Saneamiento base

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

### Fase 2 - Escaner y reportes

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

### Fase 3 - Banner y UX de consentimiento

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

### Fase 4 - Bloqueo de scripts, iframes e integraciones

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

### Fase 5 - Seguridad y datos

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

### Fase 6 - Empaquetado y documentacion

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

## Referencias tecnicas del repo

### Mapa de archivos

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

### Flujos criticos

#### Run scan

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

#### Consentimiento

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

#### Bloqueo

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

## Instrucciones para Codex

Antes de actuar, leer:

1. `.agents/PROJECT_AGENT_GUIDE.md`
2. `.agents/PRODUCT_COMPLETION_PLAN.md`
3. `.agents/TECHNICAL_REFERENCES.md`
4. `security_best_practices_report.md`

Regla principal: no hagas cambios cosmeticos aislados si el flujo funcional sigue roto. Para cada peticion, identifica el flujo completo y cierra el contrato observable.

Ejemplos:

- Si se toca un campo de estilo del banner, debe guardarse en admin, sanitizarse, persistirse, renderizarse en frontend y verse realmente.
- Si se toca `Run scan`, debe funcionar desde el click del admin hasta resultados persistidos y visibles.
- Si se toca consentimiento, debe cerrar cookie, REST, logs, version, bloqueo y reactivacion.

Validacion minima:

- PHP: `php -l` sobre archivos tocados si PHP existe; `composer test` si composer y dependencias existen.
- JS: `node --check admin/js/admin.js` o `node --check public/js/consent-banner.js` si se tocan.
- Banner: probar sin cookie, accept all, reject all, custom, revoke, colores y responsive.
- Escaner: probar admin AJAX, resultados guardados, warnings visibles y exportaciones.
- Seguridad: nonce, capability, sanitizacion, escaping, datos minimos, SQL preparado y cookies defensivas.

Prioridades actuales:

1. Corregir encoding/mojibake.
2. Completar QA del banner y estilos reales.
3. Fortalecer escaneo de portada y advertencias.
4. Agregar tests para los fixes recientes.
5. Revisar bloqueo de iframes para asegurar que no cargan antes del consentimiento.
6. Mejorar errores visibles en frontend cuando falla REST.
7. Actualizar `readme.txt` y reporte de seguridad.
8. Preparar paquete instalable.

No hacer:

- No prometer cumplimiento legal.
- No eliminar disclaimers.
- No confiar en headers de proxy para IP sin filtro/configuracion explicita.
- No bloquear scripts si el banner esta apagado.
- No guardar datos personales innecesarios.
- No anadir dependencias pesadas sin justificar.
- No dejar nuevos controles admin sin efecto real en frontend.

## Checklist de siguiente iteracion

- [ ] Leer esta guia y `security_best_practices_report.md`.
- [ ] Buscar mojibake: `rg -n "Ã|Â|â€”|â€¦|â€|ð" .`
- [ ] Revisar `admin/views/banner-settings.php`.
- [ ] Revisar `public/class-public.php`.
- [ ] Revisar `public/js/consent-banner.js`.
- [ ] Revisar `includes/class-script-blocker.php`.
- [ ] Revisar `includes/class-scanner.php`.
- [ ] Confirmar que todos los ajustes visuales del banner se guardan y se ven.
- [ ] Confirmar que el banner no se duplica con `wp_body_open` y `wp_footer`.
- [ ] Confirmar que el shortcode de revocacion carga assets aunque el banner este apagado.
- [ ] Confirmar que el escaneo muestra warnings cuando la portada no puede leerse.
- [ ] Confirmar que iframes bloqueados no cargan antes de consentimiento.
- [ ] Confirmar que Meta Pixel y GCM v2 no conceden permisos antes de aceptar.
- [ ] Confirmar export TXT/CSV/JSON.
- [ ] Agregar tests para cookie malformada, categorias invalidas, version obsoleta, warnings del scanner y deduplicacion.

## Definicion de terminado

No marcar el proyecto como terminado hasta cumplir todo esto:

- Instalacion limpia en WordPress soportado.
- Activacion sin fatal errors.
- Ajustes por defecto creados correctamente.
- Menu admin navegable.
- `Run scan` funciona y muestra resultados reales.
- Reportes TXT/CSV/JSON descargan contenido util.
- Banner aparece, se configura, respeta estilos y no se duplica.
- Consentimiento se guarda, se lee, se invalida por version y se revoca.
- Bloqueo de scripts/iframes funciona antes de consentimiento y reactivacion despues.
- GCM v2 y Meta Pixel obedecen el estado de consentimiento.
- Logs se guardan si procede, se minimizan y se purgan.
- Textos en espanol sin mojibake.
- Tests automatizados pasan en entorno con PHP/composer.
- QA manual documentado en WordPress real.
- `readme.txt` refleja el comportamiento real, limitaciones y requisitos.
