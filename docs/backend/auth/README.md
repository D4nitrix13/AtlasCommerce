# `docs/backend/auth/`

Mecanismos de autenticación del backend: cómo el user se identifica,
cómo se valida su identidad en cada request, cómo recupera el
acceso si pierde su password o device.

## Contenido

- `two-factor.md` — 2FA con TOTP (RFC 6238), incluyendo setup, verify
  y recovery con backup codes.
- `recovery.md` — métodos de recuperación de cuenta: email
  password reset, recovery codes, contact support.

## Audiencia

Devs backend que tocan el flujo de auth. Devs frontend que
integran el form de 2FA setup, el challenge de login, o el flow
de forgot-password. Security reviewers que validen el sistema.

NO incluye la auth API de Sanctum (eso está en
`backend/api/authentication.md`) — esto es solo 2FA y recovery.
