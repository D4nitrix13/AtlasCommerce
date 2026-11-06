# Contratos de interfaz compartida — envelope de errores API y locale

> Regla load-bearing para crear o consumir la frontera entre backend y
> frontend (y entre sesiones). Lo que dos sesiones consumen es CONTRATO
> y se documenta acá en el mismo PR que lo crea — no se re-inventa por
> formulario.

## Envelope de errores API (vigente)

- Todo error de validación/estado responde JSON con:
  ```json
  { "message": "resumen", "errors": { "campo": ["mensaje 1"] } }
  ```
  (`message`: string siempre presente; `errors`: `Record<string,
  string[]>`, solo para errores con campo). Forma Laravel estándar — el
  frontend la tipa como `ApiError` en `frontend/src/lib/api.ts`.
- **Consumo en el frontend**: los errores de campo se mapean al campo
  (inline, `role="alert"`, foco al primero) — patrón
  `components/storefront/auth/authFieldError.ts` (`mapApiFieldErrors`).
  El banner superior es solo para errores sin campo (`message`). Todo
  form nuevo sigue este patrón; no volver al "todo en un banner".
- Errores de dominio fuera de validación (409/503…) usan el mismo
  envelope con `message` localizado vía `trans()`.

## Locale de mensajes (vigente)

- El frontend manda **`X-Locale`** en cada request con el locale de la UI
  (derivado de la URL) — `frontend/src/lib/api.ts`.
- El backend resuelve `X-Locale` → `Accept-Language` → `es` (middleware
  `SetRequestLocale`) y traduce con `lang/{es,en}/*.php`. Nada de strings
  de usuario hardcodeados (ver `workflow/i18n-and-references.md`).
- El `message` de errores de auth (credenciales, throttle, 2FA) también
  es locale-aware (`lang/*/auth.php`).

## Contratos relacionados (no duplicar acá)

- Auth/cookies/2FA: `workflow/browser-auth-contract.md`.
- Dominio/sesión/CSRF: `tooling/dev-domains-and-session.md`.

## Meta-regla

- Toda interfaz compartida nueva (shape de API, estado compartido de
  stores como `stores/cart.ts`, contrato de un componente reutilizable)
  nace documentada como regla en `.ai/rules/` en el mismo PR que la
  implementa, con su forma exacta y su consumidor conocido.
- Contratos previstos y aún no escritos: carrito/checkout (estado de
  `stores/cart.ts`, envelope de cupones/promos), formato de precios por
  moneda de tenant. Al tocar esos frentes, escribir el contrato primero.
