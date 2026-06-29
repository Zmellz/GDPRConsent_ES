# Guia para Agentes - GDPR Consent Auditor

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
