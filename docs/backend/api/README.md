# `docs/backend/api/`

Contratos HTTP del backend: routing, autenticación, versionado
y CSRF. Todo lo que un cliente HTTP (browser, mobile, script) ve
del backend.

## Contenido

- `routes.md` — mapa completo de rutas registradas, agrupadas por
  middleware y prefijo.
- `authentication.md` — `Laravel Sanctum`: tokens, stateful,
  cookie de sesión vs Bearer token.
- `csrf.md` — convenciones para endpoints stateful (double-submit
  cookie, header `X-XSRF-TOKEN`).
- `versioning.md` — cómo se versiona la API (`/v1/`, deprecation).

## Audiencia

Devs frontend que consumen la API. Devs backend que agregan o
modifican endpoints. Devs mobile/integración que escriben clientes
HTTP.
