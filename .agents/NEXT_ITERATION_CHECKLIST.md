# Checklist de siguiente iteracion

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
