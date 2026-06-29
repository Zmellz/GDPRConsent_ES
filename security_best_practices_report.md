# Informe de revisión técnica y seguridad

## Resumen ejecutivo

He realizado una segunda pasada centrada en funcionamiento real, robustez y seguridad defensiva del plugin. En esta iteración han quedado corregidos varios fallos que afectaban al flujo de consentimiento y a la fiabilidad del escaneo. No he podido ejecutar pruebas PHP automatizadas en este entorno porque no hay `php`, `composer` ni dependencias instaladas.

## Corregido en esta pasada

### [F-01] Invalidación innecesaria del consentimiento al guardar ajustes
- Severidad: media
- Estado: corregido
- Referencias: `admin/class-admin.php:239`, `admin/class-admin.php:253`
- Impacto: cualquier guardado en ajustes, incluso uno irrelevante como la retención de logs, hacía subir la versión del consentimiento y obligaba a repetir la aceptación.
- Corrección: ahora la versión solo cambia cuando se modifican campos que afectan realmente al alcance del consentimiento o al comportamiento del banner/bloqueo.

### [F-02] Lectura de la cookie de consentimiento demasiado permisiva
- Severidad: media
- Estado: corregido
- Referencias: `includes/class-consent-manager.php:185`, `includes/class-consent-manager.php:206`, `includes/class-consent-manager.php:214`
- Impacto: la cookie se aceptaba con una decodificación base64 no estricta y con categorías no normalizadas, lo que hacía el parser menos robusto ante valores malformados.
- Corrección: la decodificación pasa a modo estricto y las categorías/acciones recuperadas de la cookie se validan y normalizan antes de darlas por buenas.

### [F-03] Revocación poco fiable de la cookie en cliente
- Severidad: media
- Estado: corregido
- Referencias: `public/class-public.php:120`, `public/class-public.php:121`, `public/class-public.php:122`, `public/js/consent-banner.js:94`, `public/js/consent-banner.js:346`
- Impacto: al revocar, el navegador podía no borrar la cookie real si había sido creada con otra ruta o dominio, dejando estados incoherentes entre cliente y servidor.
- Corrección: el front ahora recibe nombre, ruta y dominio reales de la cookie y la elimina con esas mismas reglas.

### [F-04] Bloqueo de scripts aunque el banner estuviera desactivado
- Severidad: alta
- Estado: corregido
- Referencias: `public/class-public.php:35`, `public/class-public.php:296`
- Impacto: si el banner se desactivaba pero seguía activo el bloqueo, visitantes nuevos podían quedarse sin forma de conceder consentimiento mientras ciertos scripts continuaban bloqueados.
- Corrección: el bloqueador y el shim de scripts dinámicos ya no actúan cuando el banner está desactivado.

### [F-05] El escaneo no avisaba cuando la inspección de la portada fallaba
- Severidad: media
- Estado: corregido
- Referencias: `includes/class-scanner.php:194`, `includes/class-scanner.php:211`, `includes/class-scanner.php:222`, `includes/class-scanner.php:231`, `admin/class-admin.php:478`, `admin/views/scan-results.php:29`
- Impacto: el escaneo podía terminar “correctamente” pero con resultados incompletos si la petición a la portada pública fallaba o devolvía HTML vacío.
- Corrección: ahora el escáner acumula advertencias no fatales, las devuelve por AJAX y las muestra en resultados para que no pasen desapercibidas.

### [F-06] Dependencia excesiva del DOM del banner en el JS público
- Severidad: baja
- Estado: corregido
- Referencias: `public/js/consent-banner.js:289`, `public/js/consent-banner.js:391`, `public/class-public.php:67`, `public/class-public.php:144`
- Impacto: parte del comportamiento del front asumía que el banner siempre estaba presente, lo que hacía menos robusto el flujo de revocación.
- Corrección: el JS ahora se protege mejor cuando no hay banner renderizado y los assets públicos pueden seguir cargándose si la página usa el shortcode de revocación.

## Riesgos o limitaciones que siguen vigentes

### [R-01] La detección HTML de la portada sigue siendo heurística
- Severidad: baja
- Referencias: `includes/class-scanner.php:236`, `includes/class-scanner.php:237`, `includes/class-scanner.php:238`
- Detalle: la inspección de la portada se basa en expresiones regulares sobre el HTML y no en un parser del DOM. Eso significa que puede haber falsos positivos o falsos negativos en marcado poco estándar, atributos en distinto orden o recursos cargados de forma más compleja.
- Recomendación: si esta parte se vuelve crítica, merece una tercera mejora centrada en parseo más preciso y en diferenciar mejor hojas de estilo reales frente a otros `<link>`.

## Verificación realizada

- Comprobación sintáctica de `public/js/consent-banner.js` y `admin/js/admin.js` con Node: correcta.
- Verificación manual de los puntos tocados y de las rutas donde suben las advertencias del escaneo: correcta.
- Pruebas PHP automatizadas: no ejecutadas en este entorno por falta de runtime y dependencias.
