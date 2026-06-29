# Instrucciones para Agentes - GDPR Consent Auditor

Lee los siguientes archivos en orden antes de actuar:

1. `.agents/PROJECT_AGENT_GUIDE.md` — objetivo, estado, principios, skills y definicion de terminado.
2. `.agents/PRODUCT_COMPLETION_PLAN.md` — plan de finalizacion por fases (1-6).
3. `.agents/TECHNICAL_REFERENCES.md` — mapa de archivos y flujos criticos.
4. `.agents/NEXT_ITERATION_CHECKLIST.md` — checklist de pendientes.

Reglas principales:
- No hacer cambios cosmeticos aislados si el flujo funcional sigue roto.
- Todo cambio debe trazarse desde la interaccion hasta el almacenamiento y efecto visible.
- No prometer cumplimiento legal. Mantener disclaimers.
- Corregir mojibake antes de cerrar.
- Nonces, capabilities, sanitizacion, escaping y minimizacion de datos en cada endpoint.
- No bloquear scripts si el banner esta desactivado.
- No dejar controles admin sin efecto real en frontend.
