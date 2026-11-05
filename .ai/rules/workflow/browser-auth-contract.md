# Auth del browser — contrato del login, 2FA y credenciales dev

> Regla load-bearing para tocar `frontend/src/stores/{auth,customer-auth}.ts`,
> `frontend/src/components/**/auth/**` o los flujos de login. Verificada
> de punta a punta el 2026-11-05 (login admin → challenge 2FA → verify →
> sesión).

## Contrato del login con 2FA

`POST /v1/auth/login` responde una de dos formas:

- **Success**: `{ token, user, customer }` → aplicar sesión.
- **2FA**: `{ status: '2fa_required', challenge_token, method, expires_at }`
  **sin token ni user**. El caller DEBE navegar a
  `/es/account/2fa?token=<challenge_id>&redirect=<path>` — esa página
  (`TwoFactorChallengeForm`) ya existe. Tragar el challenge (como hacía
  el customer store) produce un loop silencioso al login.

El verify del challenge es `POST /v1/auth/2fa/verify-totp-login`
(**público**; devuelve token + arranca la sesión web). NO confundir con
`POST /v1/auth/2fa/totp/verify`, que es el verify **autenticado** del
setup de TOTP y responde 401 en este flujo.

## Credenciales dev (smoke / e2e)

- `admin@panda-estampados.test` / `password` — role admin, con **2FA
  TOTP activo**. La password es el `DEFAULT_PASSWORD` de
  `RecoveryCodesTestSeeder` (no existe un `AdminUserSeeder`; ojo con
  helpers e2e que citan uno).
- Para smoke manual hay que generar el TOTP del secreto en BD:
  `php -r` con bootstrap + `ReflectionMethod` sobre
  `TotpService::decryptSecret`/`generateCodeAtStep` (step
  `intdiv(time(), 30)`, verify acepta ±1 step). `tinker --execute`
  NO imprime output vía `docker-stack.sh exec` — usar `php -r`.

## apiFetch en cliente

`resolveApiUrl` resuelve `PUBLIC_API_URL` relativo en este orden:
`INTERNAL_API_ORIGIN` (solo SSR) → `origin` del caller →
`window.location.origin` (browser) → throw. Todo call site cliente
depende del fallback; no lo quites. En SSR, los endpoints autenticados
necesitan `Cookie` + `Origin` reenviados (ver
`tooling/dev-domains-and-session.md`).
