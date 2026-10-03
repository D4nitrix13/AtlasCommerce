<!-- markdownlint-disable MD013 MD003 MD041 MD022 MD026 MD028 MD033 MD051 MD001 MD032 MD036 MD040 MD005 MD007 MD033 -->

# Lista de tareas — Panda Estampados + Kitsune

> Tracking de implementación. **Este archivo NO sirve para implementar — solo para registrar qué tarea se está haciendo, cuál es la siguiente, y qué validaciones debe pasar antes de marcarse como completada.**
>
> Metodología: **tareas chicas con validación antes de avanzar**. Cada tarea individual toma entre 0.5 y 2 días. No se marca `completada` una tarea hasta que sus tests pasen y su criterio de aceptación esté verificado manualmente.

## Cómo se actualiza

- **Estado**: `pendiente` → `en-progreso` → `validacion` → `completada` (o `bloqueada` si no se puede avanzar).
- Cada tarea referencia uno o más YAMLs de `req/`.
- Al cambiar de estado, el orquestador (Claude) agrega una entrada en `## Bitácora` al final con el SHA del commit.
- Las fases siguen el orden de ejecución sugerido. **No se arranca la fase N+1 hasta que la fase N esté completa al 100%** (o se justifique explícitamente el bypass).

## Estado general

| Fase | Nombre | Progreso |
| --- | --- | --- |
| FASE 0 | Fundación técnica | 🔴 pendiente |
| FASE 1 | Autenticación base | 🟡 parcial (login admin hecho) |
| FASE 2 | Verificación de email | 🔴 pendiente |
| FASE 3 | 2FA (TOTP + recovery codes) | 🔴 pendiente |
| FASE 4 | Métodos alternativos (OTP, OAuth) | 🔴 pendiente |
| FASE 5 | Recovery consolidado | 🔴 pendiente |
| FASE 6 | Sección cliente (interfaz + módulos) | 🔴 pendiente |
| FASE 7 | Catálogo público | 🔴 pendiente |
| FASE 8 | Carrito y checkout | 🔴 pendiente |
| FASE 9 | Módulo tienda (público completo) | 🔴 pendiente |
| FASE 10 | Panel de administración | 🔴 pendiente |
| FASE 11 | Marketing y soporte | 🔴 pendiente |
| FASE 12 | Hardening no funcional | 🔴 pendiente |
| FASE 13 | Observabilidad y operaciones | 🔴 pendiente |

Leyenda: 🟢 completada · 🟡 parcial · 🔴 pendiente · ⚪ bloqueada

---

## Por qué este orden

1. **Fundación antes que funcionalidad**: tests, lint, CI. Sin esto, cada cambio posterior es un riesgo.
2. **Auth base antes que 2FA**: no se puede activar 2FA hasta que el login base funcione end-to-end y esté validado.
3. **Cliente antes que admin**: el dominio del cliente es más chico y se valida más rápido; el admin opera sobre los mismos modelos.
4. **Catálogo antes que checkout**: no se puede comprar lo que no se puede buscar.
5. **Carrito antes que checkout**: lo mismo.
6. **Hardening al final, no entremedio**: cualquier hardening implementado en paralelo se rompe con cada cambio de feature.

---

## FASE 0 — Fundación técnica

Objetivo: dejar el repo con guard rails que bloqueen código de baja calidad y tests que se ejecuten en cada PR. **Sin esta fase, ninguna tarea posterior puede marcarse como completada.**

### T-001 · Configurar PHPUnit como gate de CI

**Estado**: pendiente
**YAMLs asociados**: RNF-MAIN-050, RNF-MAIN-052
**Estimación**: 0.5 día

**Subtareas**:

- T-001.A: Habilitar `pcov` o `xdebug` como driver de coverage en `phpunit.xml`.
- T-001.B: Crear `composer test:coverage` que ejecuta tests + genera reporte + falla si coverage de los módulos críticos (Auth, Payments, Orders) < 70%.
- T-001.C: Crear workflow GitHub Actions `ci-backend.yml` que ejecuta el job en cada PR a `master` y `develop`.
- T-001.D: Habilitar protección de rama en `master` que requiera el check `ci-backend` verde.

**Criterio de aceptación**:

- PR con tests que fallan → el check `ci-backend` aparece rojo y el merge queda bloqueado.
- PR sin tests nuevos para un cambio de código en `app/Http/Controllers/Api/Auth/` → el check falla con mensaje "coverage decreased in Auth".

**Tests requeridos**: verificar en CI un PR sintético que rompa un test y observar que el check falla.

**Skills relevantes**: `quality-gate-pre-commit`, `laravel-feature-testing`.

---

### T-002 · Configurar Vitest como gate de CI

**Estado**: pendiente
**YAMLs asociados**: RNF-MAIN-050
**Estimación**: 0.5 día

**Subtareas**:

- T-002.A: Ajustar `frontend/vitest.config.ts` para que use `v8` provider con threshold explícito (85% lines, 80% branches).
- T-002.B: Crear `pnpm test:coverage` que falla si coverage de `src/components/` o `src/lib/` < 85%.
- T-002.C: Crear workflow GitHub Actions `ci-frontend.yml` que ejecuta el job en cada PR.
- T-002.D: Habilitar check `ci-frontend` como required en branch protection.

**Criterio de aceptación**:

- PR que baja coverage de `frontend/src/components/` por debajo de 85% → check rojo.
- PR que rompe un test de Vitest → check rojo.

**Tests requeridos**: PR sintético que elimine un componente y observe el check.

---

### T-003 · Configurar Playwright como gate de CI (smoke)

**Estado**: pendiente
**YAMLs asociados**: RNF-MAIN-050
**Estimación**: 1 día

**Subtareas**:

- T-003.A: Crear `tests/e2e/smoke/` con 3 tests críticos (login admin, ver dashboard, logout).
- T-003.B: Configurar `playwright.config.ts` para usar el stack dev del repo (no requiere docker externo).
- T-003.C: Workflow `ci-e2e.yml` que levanta el stack con `docker compose up -d`, espera healthcheck, corre Playwright, baja el stack.
- T-003.D: Marcar como required en branch protection.

**Criterio de aceptación**:

- Una regresión que rompa el login → smoke e2e falla en CI.
- Tiempo total del job < 5 min.

**Tests requeridos**: PR que cambie el endpoint de login y observe el smoke fallando.

**Skills relevantes**: `playwright-e2e-patterns`.

---

### T-004 · Activar phpstan nivel 5 con baseline

**Estado**: pendiente
**YAMLs asociados**: RNF-MAIN-052
**Estimación**: 1 día

**Subtareas**:

- T-004.A: Instalar `phpstan/phpstan` nivel 5.
- T-004.B: Generar baseline (`phpstan analyse --generate-baseline`) para errores heredados.
- T-004.C: Crear `composer analyse` que falla si hay errores no cubiertos por baseline.
- T-004.D: Workflow CI que ejecute `composer analyse`.

**Criterio de aceptación**:

- Código con tipo incorrecto en un método nuevo → `composer analyse` falla.
- Errores en código viejo siguen pasando (cubiertos por baseline).

**Skills relevantes**: `quality-gate-pre-commit`.

---

### T-005 · Documentar convenciones de commits en AGENTS.md

**Estado**: pendiente
**YAMLs asociados**: RNF-MAIN-053
**Estimación**: 0.25 día

**Subtareas**:

- T-005.A: Verificar que el hook husky + commitlint ya está instalado (ver `scripts/install-git-hooks.sh`).
- T-005.B: Agregar al `AGENTS.md` raíz un ejemplo concreto de `feat(scope): descripción en español` con cuerpo multilinea.
- T-005.C: Hacer un commit de prueba mal formado y verificar que el hook lo rechaza.

**Criterio de aceptación**:

- `git commit -m "cambio"` (sin tipo) → hook falla con mensaje claro.
- `git commit -m "feat(backend): agregar endpoint X"` → hook pasa.

**Skills relevantes**: `conventional-commits-and-prs`.

---

## FASE 1 — Autenticación base

Objetivo: login, register y logout funcionales con Sanctum, validados end-to-end. **El equipo ya tiene el login admin funcionando (RF-AUTH-001).** Falta adaptarlo a cliente y agregar logout/recovery base.

### T-101 · Login admin — validar implementación existente

**Estado**: 🟡 parcial
**YAMLs asociados**: RF-AUTH-001
**Estimación**: 0.5 día (revisión, no implementación)

**Subtareas**:

- T-101.A: Ejecutar manualmente el flujo login admin y documentar en `docs/auth/login-admin.md` los pasos exactos, output esperado, y screenshots.
- T-101.B: Verificar que `backend/tests/Feature/AuthApiTest.php` cubre: credenciales válidas, inválidas, lockout por rate limit, MFA challenge.
- T-101.C: Identificar gaps: si el test no cubre alguno de los casos, agregar el test primero.

**Criterio de aceptación**:

- Login admin funciona end-to-end desde `frontend/src/pages/[locale]/account/auth/login.astro`.
- Suite de tests Feature cubre todos los caminos: éxito, fallo, lockout, MFA.
- Documento `docs/auth/login-admin.md` describe el flujo con capturas.

**Tests requeridos**: `AuthApiTest::test_login_success`, `test_login_invalid_credentials`, `test_login_rate_limit`.

**Skills relevantes**: `laravel-feature-testing`, `developer-essentials-auth-implementation-patterns`.

---

### T-102 · Login cliente

**Estado**: pendiente
**YAMLs asociados**: RF-AUTH-001
**Estimación**: 2 días

**Subtareas**:

- T-102.A: Diseñar el endpoint `POST /api/v1/auth/login` que acepta email+contraseña y emite Sanctum token scoped a Customer.
- T-102.B: Validar que el cliente existe en `customers` Y está vinculado a `customer_stores` de la tienda actual (no a otra tienda).
- T-102.C: Diferenciar respuesta cuando el cliente tiene 2FA activa (devolver `requires_2fa: true` + token challenge efímero).
- T-102.D: Frontend: crear `frontend/src/pages/[locale]/account/auth/login.astro` con form intuitivo (responsive, a11y WCAG 2.2 AA, dark mode).
- T-102.E: Diferenciar el login admin del cliente en rutas (admin → `/admin/login`, cliente → `/account/login`).
- T-102.F: Tests Feature: éxito, fallo, cliente inexistente, cliente desactivado, cliente de otra tienda, MFA challenge.
- T-102.G: Test E2E Playwright: flujo completo desde la home, click en "Iniciar sesión", ingreso credenciales válidas, redirige a `/account`.

**Criterio de aceptación**:

- Cliente con credenciales válidas → recibe token Sanctum + redirige a `/account`.
- Cliente con credenciales inválidas → muestra error sin revelar si email existe.
- Cliente bloqueado por rate limit → muestra mensaje claro, no crashea.
- Cliente con 2FA → redirige a `/account/auth/totp` con challenge.
- Test E2E pasa en CI.
- WCAG 2.2 AA validado con axe-core (0 violaciones).

**Tests requeridos**: `tests/Feature/Http/CustomerAuthTest.php` (cubrir todas las ramas), `tests/e2e/customer/login.spec.ts`.

**Skills relevantes**: `laravel-feature-testing`, `accessibility-compliance-wcag-audit-patterns`, `astro-themes-and-palette`.

---

### T-103 · Register cliente con verificación de email

**Estado**: pendiente
**YAMLs asociados**: RF-AUTH-002 (parcial, cubre admin), RNF-SEC-016
**Estimación**: 3 días

**Subtareas**:

- T-103.A: Definir el modelo `Customer::user` (relación opcional) y `Customer::customer_type` default = 'retail'.
- T-103.B: Crear endpoint `POST /api/v1/auth/register` que crea User + Customer + UserStore + CustomerStore en transacción atómica.
- T-103.C: Validar: email único, contraseña cumple RNF-SEC-012 (bcrypt cost 12, min 12 chars), campos requeridos via FormRequest.
- T-103.D: Generar token de verificación de email (TTL 24h) y enviarlo vía notificación `VerifyEmailNotification`.
- T-103.E: Cliente queda `is_active=false` hasta verificar email.
- T-103.F: Frontend: `register.astro` con form completo (firstName, lastName, email, password, passwordConfirm, terms checkbox).
- T-103.G: Validación client-side con Zod + server-side con FormRequest.
- T-103.H: Página `/account/auth/verify-email/[token]` que consume el token.
- T-103.I: Página `/account/auth/verify-email/pending` con CTA "Reenviar email".
- T-103.J: Endpoint `POST /api/v1/auth/register/resend-verification` con rate limit.
- T-103.K: Tests Feature: éxito, email duplicado, contraseña débil, email inválido, cliente creado pero inactivo hasta verificación.
- T-103.L: Test E2E: flujo completo + verificación de email con Mailpit.

**Criterio de aceptación**:

- Registro exitoso → email de verificación llega a Mailpit.
- Click en el link del email → cuenta activada + redirige a `/account`.
- Intentar login antes de verificar email → error "verifica tu email primero".
- Reenvío de email respeta rate limit (máx 3 por hora).
- Test E2E completo pasa en CI.

**Tests requeridos**: `tests/Feature/Http/CustomerRegisterTest.php`, `tests/e2e/customer/register.spec.ts`.

**Skills relevantes**: `laravel-feature-testing`, `developer-essentials-auth-implementation-patterns`, `accessibility-compliance-wcag-audit-patterns`.

---

### T-104 · Logout + invalidación de tokens

**Estado**: pendiente
**YAMLs asociados**: RF-AUTH-001 (logout implícito)
**Estimación**: 1 día

**Subtareas**:

- T-104.A: Endpoint `POST /api/v1/auth/logout` que elimina el token actual del cliente (Sanctum `tokens()->delete()`).
- T-104.B: Endpoint `POST /api/v1/auth/logout-all` que elimina TODOS los tokens del cliente (logout de todas las sesiones).
- T-104.C: Frontend: botón "Cerrar sesión" en el header de `/account`, modal de confirmación.
- T-104.D: Al hacer logout, limpiar localStorage/cookies y redirigir a `/`.
- T-104.E: Tests Feature: logout normal, logout-all, intentar usar token revocado (debe fallar con 401).
- T-104.F: Test E2E: login → logout → intentar acceder a `/account` → redirige a `/account/login`.

**Criterio de aceptación**:

- Después de logout, intentar usar el token en una API call → 401.
- Cliente que presiona "Cerrar sesión en todos los dispositivos" → todos sus tokens Sanctum quedan inválidos.
- Audit log registra `auth.logout` con user_id, ip, user_agent.

**Tests requeridos**: `tests/Feature/Http/CustomerLogoutTest.php`.

**Skills relevantes**: `developer-essentials-auth-implementation-patterns`.

---

### T-105 · Validación end-to-end de auth base

**Estado**: pendiente
**YAMLs asociados**: RF-AUTH-001, RF-AUTH-002
**Estimación**: 1 día

**Subtareas**:

- T-105.A: Test E2E completo: register → email verification → login → navegar `/account` → logout.
- T-105.B: Test E2E de seguridad: 5 intentos fallidos de login → bloqueo temporal (verificar rate limiter `login`).
- T-105.C: Test E2E: sesión persiste al refrescar la página, se pierde al limpiar cookies (esperado).
- T-105.D: Verificar WCAG 2.2 AA con axe-core en todas las páginas de auth (login, register, verify-email, verify-pending).
- T-105.E: Documentar en `docs/auth/` los flujos con capturas.

**Criterio de aceptación**:

- Suite E2E completa pasa en CI.
- axe-core reporta 0 violaciones AA en las 4 páginas.
- Documentación revisada por el equipo.

**Skills relevantes**: `playwright-e2e-patterns`, `accessibility-compliance-wcag-audit-patterns`.

---

## FASE 2 — Verificación de email

Objetivo: que toda cuenta nueva pase por verificación de email antes de poder operar. Estado intermedio `pending_verification` claramente manejado.

### T-201 · Email verification post-register

**Estado**: parcial (cubierto en T-103.D como parte de register)
**YAMLs asociados**: RF-AUTH-002 (verificación)
**Estimación**: parte de T-103

(Implementado como parte de T-103.D. Esta tarea valida el flujo end-to-end.)

**Criterio de aceptación**: ver T-103.

---

### T-202 · Reenvío de email de verificación con rate limit

**Estado**: pendiente (cubierto en T-103.J)
**YAMLs asociados**: RNF-SEC-016
**Estimación**: parte de T-103

---

### T-203 · Cuenta bloqueada hasta verificar email

**Estado**: pendiente (cubierto en T-103.E)
**YAMLs asociados**: RF-AUTH-002
**Estimación**: parte de T-103

---

### T-204 · Validación: cliente no verificado intenta checkout

**Estado**: pendiente
**YAMLs asociados**: RF-AUTH-002, RNF-SEC-016
**Estimación**: 0.5 día

**Subtareas**:

- T-204.A: Test E2E: cliente se registra, intenta checkout sin verificar email → error claro.
- T-204.B: Mensaje user-friendly: "Verifica tu email para poder comprar. [Reenviar email]".

**Criterio de aceptación**: test E2E pasa.

---

## FASE 3 — 2FA (TOTP + recovery codes)

Objetivo: que admin y clientes puedan activar 2FA con TOTP, con códigos de respaldo, y que el login lo requiera cuando esté activa.

### T-301 · Setup TOTP (activar 2FA)

**Estado**: pendiente
**YAMLs asociados**: RF-AUTH-004, RNF-SEC-011
**Estimación**: 2 días

**Subtareas**:

- T-301.A: Endpoint `POST /api/v1/auth/totp/setup` que genera secret base32 (32 chars) y devuelve QR (SVG o data URL).
- T-301.B: Endpoint `POST /api/v1/auth/totp/confirm` que valida el primer código TOTP y persiste setup en `two_factor_settings`.
- T-301.C: Página `/account/security/2fa/setup.astro` con: (1) mostrar QR para escanear con app, (2) input para primer código, (3) warning sobre códigos de respaldo.
- T-301.D: Validar TOTP según RFC 6238 con ventana de ±1 step (90s total de tolerancia).
- T-301.E: Tests Feature: setup completo, código expirado, secret regenerado, secret hasheado en BD.
- T-301.F: Test E2E: setup completo con app TOTP simulada (generar código en runtime).

**Criterio de aceptación**:

- Cliente escanea QR en Google Authenticator → código aparece.
- Cliente ingresa código → 2FA queda activado.
- Logout + login con email+contraseña ahora pide código TOTP.
- Secret nunca se loguea ni se expone en respuestas GET.

**Tests requeridos**: `tests/Feature/Http/TotpSetupTest.php`, `tests/e2e/customer/totp-setup.spec.ts`.

**Skills relevantes**: `developer-essentials-auth-implementation-patterns`.

---

### T-302 · Verificación TOTP en login

**Estado**: pendiente
**YAMLs asociados**: RF-AUTH-004, RNF-SEC-011
**Estimación**: 1.5 días

**Subtareas**:

- T-302.A: Endpoint `POST /api/v1/auth/login/totp` que valida el código contra el challenge efímero (TTL 5 min).
- T-302.B: Después de validar, emitir Sanctum token normal.
- T-302.C: Página `/account/auth/totp.astro` que recibe el challenge token y muestra input de 6 dígitos.
- T-302.D: Auto-reenvío de email si el código expira.
- T-302.E: Rate limiter `totp-verify` (10/5min/IP) para mitigar brute force.
- T-302.F: Tests Feature: código válido, código inválido, código expirado, lockout por rate limit.
- T-302.G: Test E2E: login con 2FA activo completo.

**Criterio de aceptación**:

- Cliente con 2FA activo ve pantalla TOTP después de email+contraseña.
- Código válido → sesión iniciada.
- 10 códigos inválidos en 5 min → bloqueo temporal.
- Test E2E pasa.

**Tests requeridos**: `tests/Feature/Http/TotpVerifyTest.php`, `tests/e2e/customer/totp-login.spec.ts`.

**Skills relevantes**: `developer-essentials-auth-implementation-patterns`.

---

### T-303 · Recovery codes (códigos de respaldo)

**Estado**: pendiente
**YAMLs asociados**: RF-AUTH-005
**Estimación**: 2 días

**Subtareas**:

- T-303.A: Al activar 2FA, generar 10 recovery codes de 10 chars (alfanuméricos, sin 0/O/1/I/L).
- T-303.B: Persistir cada código hasheado (bcrypt) en tabla `recovery_codes` con flag `consumed_at`.
- T-303.C: Endpoint `POST /api/v1/auth/totp/recovery-codes/regenerate` que invalida los anteriores y crea 10 nuevos.
- T-303.D: Endpoint `POST /api/v1/auth/login/recovery-code` que acepta un código y emite token.
- T-303.E: Rate limiter `recovery-codes` (5/h/user) para evitar brute force.
- T-303.F: Página `/account/security/2fa/recovery-codes.astro` con: lista de códigos para descargar (formato texto), botón "Regenerar".
- T-303.G: Banner persistente en `/account/security` mostrando cuántos códigos quedan sin usar.
- T-303.H: Tests Feature: código válido, código ya usado, regenerar códigos, código regenerado invalida los anteriores.
- T-303.I: Test E2E: activar 2FA → ver códigos → cerrar sesión → login con recovery code.

**Criterio de aceptación**:

- Cliente que pierde acceso a su app TOTP puede entrar con un recovery code.
- Cada código solo se puede usar 1 vez.
- Regenerar códigos requiere confirmar contraseña actual.
- Audit log registra cada uso de recovery code.

**Tests requeridos**: `tests/Feature/Http/RecoveryCodeTest.php`.

**Skills relevantes**: `developer-essentials-auth-implementation-patterns`.

---

### T-304 · 2FA enforcement para admin (obligatorio)

**Estado**: pendiente
**YAMLs asociados**: RF-AUTH-004, RNF-SEC-011
**Estimación**: 1 día

**Subtareas**:

- T-304.A: Middleware `RequireAdminTwoFactor` que bloquea cualquier request a `/admin/*` si el admin no tiene 2FA activa.
- T-304.B: Página `/admin/2fa/setup-required.astro` que muestra wizard de setup con CTA claro.
- T-304.C: Excepción para el primer login de un admin nuevo: se le permite configurar 2FA sin tenerla activa (pero al segundo login se le exige).
- T-304.D: Tests Feature: admin sin 2FA no puede acceder a `/admin/dashboard`, sí puede acceder a `/admin/2fa/setup-required`, después de activar sí accede a todo.
- T-304.E: Test E2E: flujo completo de bootstrap admin.

**Criterio de aceptación**:

- Admin sin 2FA → solo ve la página de setup, ninguna otra ruta admin responde.
- Admin con 2FA → acceso completo.
- Si un admin desactiva 2FA → vuelve a la pantalla de setup.

**Tests requeridos**: `tests/Feature/Http/AdminTwoFactorEnforcementTest.php`.

**Skills relevantes**: `laravel-feature-testing`.

---

### T-305 · Validación: flujo 2FA completo end-to-end

**Estado**: pendiente
**YAMLs asociados**: RF-AUTH-004, RF-AUTH-005, RNF-SEC-011
**Estimación**: 1 día

**Subtareas**:

- T-305.A: Test E2E: setup 2FA → logout → login email+password → pantalla TOTP → código válido → sesión iniciada.
- T-305.B: Test E2E: setup 2FA → logout → login email+password → TOTP inválido 3 veces → usar recovery code → sesión iniciada.
- T-305.C: Validar WCAG 2.2 AA en páginas de 2FA (QR + inputs + alertas).
- T-305.D: Documentar flujo en `docs/auth/two-factor.md`.

**Criterio de aceptación**: suite E2E pasa, axe-core 0 violaciones, doc revisado.

---

## FASE 4 — Métodos alternativos

Objetivo: agregar passwordless (OTP email) y OAuth social como alternativas a la combinación email+contraseña.

### T-401 · Login passwordless con OTP por email

**Estado**: pendiente
**YAMLs asociados**: RF-AUTH-007
**Estimación**: 3 días

**Subtareas**:

- T-401.A: Endpoint `POST /api/v1/auth/login/email-otp/request` que genera código de 6 dígitos y lo envía por email.
- T-401.B: Endpoint `POST /api/v1/auth/login/email-otp/verify` que valida el código (TTL 10 min) y emite Sanctum token.
- T-401.C: Rate limiter `email-otp` (3/h/email, 5/h/IP).
- T-401.D: Página `/account/auth/login-email.astro` con form de email + transición a `/account/auth/otp/[token]` para ingresar código.
- T-401.E: Tests Feature: código válido, expirado, reutilizado, rate limit.
- T-401.F: Test E2E: solicitar código → verificar email en Mailpit → ingresar código → sesión iniciada.

**Criterio de aceptación**:

- Cliente pide código → llega a Mailpit en <2s.
- Código válido → sesión iniciada sin pedir contraseña.
- Código expirado o usado → error claro.
- Rate limit bloquea abusadores.

**Tests requeridos**: `tests/Feature/Http/EmailOtpLoginTest.php`, `tests/e2e/customer/login-email-otp.spec.ts`.

**Skills relevantes**: `developer-essentials-auth-implementation-patterns`.

---

### T-402 · OAuth social (Google, Facebook)

**Estado**: pendiente
**YAMLs asociados**: RF-AUTH-006
**Estimación**: 4 días

**Subtareas**:

- T-402.A: Implementar Socialite con providers Google + Facebook.
- T-402.B: Endpoints `GET /api/v1/auth/oauth/{provider}/redirect` y `GET /api/v1/auth/oauth/{provider}/callback`.
- T-402.C: Modelo `SocialAccount` con columnas (provider, provider_user_id, user_id).
- T-402.D: Lógica de vinculación: si el email del provider matchea un Customer existente, vincular; si no, crear nuevo Customer.
- T-402.E: Página `/account/auth/oauth-callback.astro` que muestra estado y redirige.
- T-402.F: Configurar credenciales de Google/Facebook en `.env.example` (placeholders).
- T-402.G: Tests Feature: callback con código válido, callback con state inválido, vinculación de cuenta nueva, vinculación de cuenta existente.
- T-402.H: Test E2E: click "Continuar con Google" → mock OAuth provider → callback → sesión iniciada.

**Criterio de aceptación**:

- Botón "Continuar con Google" en login y register.
- Cliente OAuth nuevo se crea automáticamente.
- Cliente OAuth existente se vincula (no se duplica).
- Tokens del provider NUNCA se persisten (solo el provider_user_id).
- Test E2E con mock del OAuth provider pasa.

**Tests requeridos**: `tests/Feature/Http/OAuthLoginTest.php`.

**Skills relevantes**: `developer-essentials-auth-implementation-patterns`.

---

### T-403 · Validación: métodos alternativos end-to-end

**Estado**: pendiente
**YAMLs asociados**: RF-AUTH-006, RF-AUTH-007
**Estimación**: 0.5 día

**Subtareas**:

- T-403.A: Test E2E: suite completa de 4 métodos (email+pass, OTP email, recovery code, OAuth Google mock).
- T-403.B: Documentar en `docs/auth/login-methods.md`.

**Criterio de aceptación**: suite E2E pasa.

---

## FASE 5 — Recovery consolidado

Objetivo: un wizard unificado "olvidé mi acceso" que cubre todos los escenarios (contraseña, TOTP perdido, recovery codes agotados).

### T-501 · Wizard de recovery multi-step

**Estado**: pendiente
**YAMLs asociados**: RF-AUTH-003, RF-AUTH-005
**Estimación**: 4 días

**Subtareas**:

- T-501.A: Página `/account/auth/forgot.astro` con input de email → genera `OtpChallenge` (TTL 30 min) y envía email con link.
- T-501.B: Página `/account/auth/recover/[token].astro` que detecta qué métodos tiene disponibles el usuario:
  - Si tiene recovery codes → opción de usarlos.
  - Si no tiene → opción de OTP por email.
  - Si no tiene email configurado → opción "contactar soporte" (form que abre ticket RF-SUP-160).
- T-501.C: Endpoint `POST /api/v1/auth/recover/verify-identity` que valida el OTP inicial.
- T-501.D: Endpoint `POST /api/v1/auth/recover/set-new-password` que pide nueva contraseña (validada contra RNF-SEC-012).
- T-501.E: Después de recovery exitoso: invalidar todos los tokens existentes + invalidar 2FA pending setup + generar audit log `auth.account_recovered`.
- T-501.F: Wizard state machine: email → OTP → método → nueva password → success.
- T-501.G: Tests Feature: cada transición, cada combinación de métodos disponibles, recovery que no tiene email configurado.
- T-501.H: Test E2E: cliente olvidó contraseña → recibe email → ingresa OTP → elige recovery code → nueva contraseña → login normal.

**Criterio de aceptación**:

- Cliente sin 2FA puede recuperar acceso con OTP email.
- Cliente con 2FA puede recuperar acceso con OTP email o recovery code.
- Cliente sin acceso a email puede abrir ticket de soporte desde el wizard.
- Después de recovery, todas las sesiones anteriores quedan invalidadas.
- Audit log captura el evento con IP y user_agent.

**Tests requeridos**: `tests/Feature/Http/AccountRecoveryTest.php`, `tests/e2e/customer/recover-account.spec.ts`.

**Skills relevantes**: `laravel-feature-testing`, `developer-essentials-auth-implementation-patterns`.

---

### T-502 · Auditoría de eventos de auth

**Estado**: pendiente
**YAMLs asociados**: RNF-OBS-070, RF-SYS-151
**Estimación**: 1 día

**Subtareas**:

- T-502.A: Definir enum `AuthEventType` (LOGIN_SUCCESS, LOGIN_FAILED, TOTP_SETUP, RECOVERY_USED, PASSWORD_CHANGED, etc).
- T-502.B: Listener `LogAuthEvent` que persiste en `Audit` table con user_id, event, ip, user_agent, metadata.
- T-502.C: Disparar el listener en todos los puntos de auth (login, logout, 2FA setup, recovery).
- T-502.D: Página `/account/security/activity.astro` con feed de los últimos 50 eventos del cliente.
- T-502.E: Tests Feature: cada evento se persiste con metadata correcta.
- T-502.F: Test E2E: cliente hace login → ve el evento en `/account/security/activity`.

**Criterio de aceptación**:

- Cada acción de auth genera un evento en `Audit`.
- El feed en `/account/security/activity` muestra los últimos eventos con timestamp legible.
- Eventos sensibles (cambio de contraseña, recuperación) tienen metadata adicional (IP, device).

**Tests requeridos**: `tests/Feature/AuthEventLoggingTest.php`.

**Skills relevantes**: `observability-monitoring-distributed-tracing`.

---

### T-503 · Validación: recovery + auditoría end-to-end

**Estado**: pendiente
**YAMLs asociados**: RF-AUTH-003, RF-AUTH-005, RNF-OBS-070
**Estimación**: 0.5 día

**Subtareas**:

- T-503.A: Test E2E completo del flujo de recovery.
- T-503.B: Verificar en `/account/security/activity` que el evento aparece.
- T-503.C: Documentar en `docs/auth/recovery-flow.md`.

**Criterio de aceptación**: suite E2E pasa, doc revisado.

---

## FASE 6 — Sección cliente (interfaz + módulos)

Objetivo: la sección `/account` del cliente con diseño moderno, intuitivo y accesible. Cubre dashboard, perfil, direcciones, wishlist, órdenes, tickets.

### T-601 · Diseño de la sección cliente

**Estado**: pendiente
**YAMLs asociados**: ninguno directo (define UX para RF-CUST-040..042, RF-CART-013)
**Estimación**: 2 días

**Subtareas**:

- T-601.A: Wireframes de baja fidelidad en `docs/design/client-section/` (markdown + imagen).
- T-601.B: Layout `/account` con sidebar a la izquierda: Mi cuenta, Mis órdenes, Direcciones, Wishlist, Reviews, Tickets, Seguridad, Notificaciones.
- T-601.C: Header con avatar + nombre + dropdown (cuenta, logout).
- T-601.D: Componentes reusables: `AccountSidebar`, `AccountHeader`, `AccountCard`, `EmptyState`.
- T-601.E: Tema visual: usar paleta universal `#2D9D8E` (teal) + tokens del theme adapter del tenant.
- T-601.F: Responsive: sidebar colapsable en mobile (<768px).
- T-601.G: Validar WCAG 2.2 AA con axe-core desde el wireframe.

**Criterio de aceptación**:

- Wireframes revisados por el equipo.
- Layout funciona en mobile, tablet, desktop.
- axe-core: 0 violaciones AA.

**Skills relevantes**: `interface-design`, `astro-themes-and-palette`, `accessibility-compliance-wcag-audit-patterns`.

---

### T-602 · Dashboard cliente

**Estado**: pendiente
**YAMLs asociados**: (nuevo, no tiene RF propio)
**Estimación**: 2 días

**Subtareas**:

- T-602.A: Página `/account/index.astro` con: saludo personalizado, próximas órdenes en tránsito, wishlist count, puntos/creditos si aplica, CTAs contextuales.
- T-602.B: Componente `NextShipmentCard` que muestra el envío más próximo con link a tracking.
- T-602.C: Componente `WishlistPreview` que muestra los últimos 4 items.
- T-602.D: Datos server-rendered (Astro SSR) con cache corto en Redis (5 min).
- T-602.E: Tests Feature: data del dashboard.
- T-602.F: Test E2E: cliente con órdenes + wishlist ve el dashboard correcto.

**Criterio de aceptación**:

- Cliente con datos ve información relevante.
- Cliente nuevo (sin órdenes) ve empty state con CTA "Explorar catálogo".
- WCAG 2.2 AA validado.

**Skills relevantes**: `interface-design`, `astro-frontend-structure`.

---

### T-603 · Historial de órdenes del cliente

**Estado**: pendiente
**YAMLs asociados**: RF-SALES-051, RF-SALES-054
**Estimación**: 2 días

**Subtareas**:

- T-603.A: Página `/account/orders/index.astro` con tabla paginada de órdenes.
- T-603.B: Página `/account/orders/[id].astro` con detalle + transiciones de estado + items + link a tracking.
- T-603.C: Botón "Cancelar orden" si está dentro de la ventana (RF-SALES-054).
- T-603.D: Filtros: por status, rango de fecha.
- T-603.E: Tests Feature + E2E.

**Criterio de aceptación**:

- Cliente ve todas sus órdenes paginadas.
- Click en orden abre detalle.
- Cancelación funciona para órdenes dentro de la ventana.

---

### T-604 · Libro de direcciones

**Estado**: pendiente
**YAMLs asociados**: RF-CUST-041
**Estimación**: 2 días

**Subtareas**:

- T-604.A: Página `/account/addresses/index.astro` con lista de direcciones + CRUD.
- T-604.B: Página `/account/addresses/new.astro` y `/account/addresses/[id]/edit.astro`.
- T-604.C: Validación: país, provincia, CP según país (usar `libpostal` o lista blanca).
- T-604.D: Marcar dirección como principal (autofill en checkout).
- T-604.E: Confirmación al eliminar (soft delete con posibilidad de restaurar 30 días).
- T-604.F: Tests Feature + E2E.

**Criterio de aceptación**:

- Cliente puede CRUD direcciones.
- Una dirección principal (autofill funciona).
- WCAG AA en formularios.

**Skills relevantes**: `accessibility-compliance-wcag-audit-patterns`.

---

### T-605 · Wishlist

**Estado**: pendiente
**YAMLs asociados**: RF-CART-013
**Estimación**: 2 días

**Subtareas**:

- T-605.A: Página `/account/wishlist/index.astro` con grid de productos.
- T-605.B: Botón "Agregar al carrito" desde wishlist → mueve el item.
- T-605.B: Botón "Quitar" con confirmación.
- T-605.C: Compartir wishlist vía link público con token UUID.
- T-605.D: Notificación por email si el producto de la wishlist baja de precio o vuelve a stock (job diario).
- T-605.E: Tests Feature + E2E.

**Criterio de aceptación**:

- Cliente ve su wishlist.
- Mover al carrito elimina de wishlist.
- Link compartido funciona para usuarios no autenticados.

---

### T-606 · Reviews y Q&A

**Estado**: pendiente
**YAMLs asociados**: RF-SUP-161, RF-SUP-162
**Estimación**: 2 días

**Subtareas**:

- T-606.A: Página `/account/reviews/index.astro` con reviews del cliente + estado (pending/approved/rejected).
- T-606.B: Form de nueva review desde confirmación de orden recibida.
- T-606.C: Página `/account/questions/index.astro` con Q&A hechas por el cliente.
- T-606.D: Tests Feature + E2E.

---

### T-607 · Tickets de soporte (cliente)

**Estado**: pendiente
**YAMLs asociados**: RF-SUP-160
**Estimación**: 3 días

**Subtareas**:

- T-607.A: Página `/account/tickets/index.astro` con tabla paginada.
- T-607.B: Página `/account/tickets/new.astro` con form: categoría, mensaje, adjuntos.
- T-607.C: Página `/account/tickets/[id].astro` con chat bidireccional cliente-admin.
- T-607.D: Adjuntar archivos (max 5MB, tipos permitidos).
- T-607.E: Notificación email cuando admin responde.
- T-607.F: Tests Feature + E2E.

---

### T-608 · Centro de seguridad

**Estado**: pendiente
**YAMLs asociados**: (consolida auth)
**Estimación**: 2 días

**Subtareas**:

- T-608.A: Página `/account/security/index.astro` con cards: cambiar contraseña, 2FA, recovery codes, sesiones activas, actividad reciente.
- T-608.B: Cambiar contraseña con confirmación de la actual.
- T-608.C: Listar sesiones activas (device, IP, última actividad) con botón "Cerrar sesión" individual.
- T-608.D: Link al feed de actividad (T-502.D).
- T-608.E: Tests Feature + E2E.

**Criterio de aceptación**:

- Cliente puede cambiar contraseña.
- Cliente puede cerrar sesiones individuales.
- Cliente ve auditoría de eventos recientes.

---

### T-609 · GDPR: export y delete

**Estado**: pendiente
**YAMLs asociados**: RF-CUST-042
**Estimación**: 3 días

**Subtareas**:

- T-609.A: Página `/account/privacy/index.astro` con CTAs "Exportar mis datos" y "Eliminar mi cuenta".
- T-609.B: Endpoint `POST /api/v1/account/export` que genera JSON con perfil + órdenes + direcciones + reviews + tickets + audit.
- T-609.C: Email con link firmado al JSON (válido 24h).
- T-609.D: Endpoint `POST /api/v1/account/delete` con confirmación doble (modal + email).
- T-609.E: Job que anonimiza: nombre → "DELETED_USER_<hash>", email → null, direcciones hard-deleted, órdenes históricas con customer_id = null pero preservadas.
- T-609.F: Audit log registra el evento `account.deleted`.
- T-609.G: Tests Feature: export contiene todo, delete anonimiza correctamente.
- T-609.H: Test E2E: cliente pide delete → confirma → recibe email → cuenta queda anonimizada.

**Criterio de aceptación**:

- Cliente puede descargar export completo.
- Cliente puede eliminar cuenta con confirmación doble.
- Después de delete, intentos de login con el email fallan.

---

### T-610 · Validación: sección cliente end-to-end

**Estado**: pendiente
**YAMLs asociados**: (todos los de fase 6)
**Estimación**: 2 días

**Subtareas**:

- T-610.A: Test E2E exhaustivo que recorre todas las páginas de `/account/*`.
- T-610.B: axe-core: 0 violaciones AA en todas las páginas.
- T-610.C: Performance: Lighthouse mobile ≥ 90 en `/account`.
- T-610.D: Documentar flujos clave con capturas.

**Criterio de aceptación**: suite E2E + axe + Lighthouse pasan.

---

## FASE 7 — Catálogo público

Objetivo: el storefront público muestra productos navegables con búsqueda full-text.

### T-701 · Catálogo público (RF-CAT-020/021/022)

**Estado**: parcialmente implementado (cubre los 3 RF)
**YAMLs asociados**: RF-CAT-020, RF-CAT-021, RF-CAT-022
**Estimación**: 1 día (revisión + gaps)

**Subtareas**:

- T-701.A: Validar que las páginas `/products`, `/products/[slug]`, `/categories/[slug]` funcionan.
- T-701.B: Agregar breadcrumbs, meta tags SEO, OpenGraph.
- T-701.C: Validar paginación con cursor (RNF-PERF-022).
- T-701.D: Test E2E: navegación home → categoría → subcategoría → producto.

---

### T-702 · Búsqueda full-text

**Estado**: pendiente
**YAMLs asociados**: RF-CAT-028, RNF-PERF-022
**Estimación**: 4 días

**Subtareas**:

- T-702.A: Crear índice GIN en `products` con `to_tsvector('spanish', title || ' ' || description || ' ' || tags)`.
- T-702.B: Endpoint `GET /api/v1/search/products?q=&category=&min_price=&max_price=&sort=` con paginación.
- T-702.C: Frontend: barra de búsqueda global en el header, página `/search` con resultados.
- T-702.D: Resaltar coincidencias en los resultados (`<mark>`).
- T-702.E: Sugerencias de búsqueda (autocomplete) en `/api/v1/search/suggest?q=`.
- T-702.F: Tests Feature: stemming español, ranking, performance con 10k productos.
- T-702.G: Test E2E: búsqueda → resultados → click → PDP.

**Criterio de aceptación**:

- Búsqueda devuelve resultados relevantes en <500ms (p95).
- Soporta acentos y sinónimos básicos.
- Autocomplete aparece después de 2 chars.

---

### T-703 · Variantes y atributos (RF-CAT-023/027)

**Estado**: parcialmente implementado (RF-CAT-023) + RF-CAT-027 pendiente
**YAMLs asociados**: RF-CAT-023, RF-CAT-027
**Estimación**: 3 días

**Subtareas**:

- T-703.A: Página admin de atributos (`frontend/src/pages/[locale]/admin/catalog/attributes/`).
- T-703.B: Selector de variantes en PDP (talla + color) que actualiza precio/stock sin recargar.
- T-703.C: Selector de variantes en el custom design editor.
- T-703.D: Tests Feature + E2E.

---

### T-704 · Precios mayoristas

**Estado**: pendiente
**YAMLs asociados**: RF-CAT-029
**Estimación**: 2 días

**Subtareas**:

- T-704.A: Endpoint admin para CRUD de tiers de precio wholesale.
- T-704.B: WholesalePricingService extendido con caching.
- T-704.C: PDP muestra precio aplicable al tipo de cliente (retail/wholesale/vip).
- T-704.D: Tests Feature: cliente retail ve precio retail, wholesale ve su tier.

---

### T-705 · Marcas (RF-CAT-024)

**Estado**: parcialmente implementado
**YAMLs asociados**: RF-CAT-024
**Estimación**: 0.5 día

**Subtareas**:

- T-705.A: Endpoint backend para CRUD de marcas (gap detectado).
- T-705.B: Página `/brands/[slug]` con productos filtrados.
- T-705.C: Footer con logos de marcas destacadas.

---

### T-706 · Validación: catálogo end-to-end

**Estado**: pendiente
**YAMLs asociados**: (todos los de fase 7)
**Estimación**: 1 día

**Criterio de aceptación**: suite E2E cubre navegación + búsqueda + PDP + variantes.

---

## FASE 8 — Carrito y checkout

### T-801 · Carrito (RF-CART-010/011/012)

**Estado**: implementado
**YAMLs asociados**: RF-CART-010, RF-CART-011, RF-CART-012
**Estimación**: 1 día (revisión)

**Subtareas**:

- T-801.A: Validar tests existentes.
- T-801.B: Validar UX: cambio de cantidad, eliminar item, ver subtotal reactivo.

---

### T-802 · Wishlist en carrito (RF-CART-013)

Cubierto en T-605.

---

### T-803 · Checkout (RF-SALES-050)

**Estado**: implementado
**YAMLs asociados**: RF-SALES-050
**Estimación**: 2 días (revisión + gaps)

**Subtareas**:

- T-803.A: Validar flujo end-to-end.
- T-803.B: Verificar cupones (RF-MKT-060) y promociones (RF-MKT-061) ya implementados.
- T-803.C: Validar cálculo server-side de impuestos (RNF-TAX-110).
- T-803.D: Test E2E: add to cart → checkout → pago → confirmación.

---

### T-804 · Cancelación por cliente (RF-SALES-054)

Cubierto en T-603.

---

### T-805 · Tracking público (RF-SHIP-083)

**Estado**: pendiente
**YAMLs asociados**: RF-SHIP-083
**Estimación**: 2 días

**Subtareas**:

- T-805.A: Endpoint público `GET /api/v1/tracking/{number}?email=`.
- T-805.B: Página `/tracking` con form de búsqueda.
- T-805.C: Rate limiter para evitar enumeration.
- T-805.D: Test E2E: tracking con número válido devuelve estado, número inválido devuelve 404 sin filtrar.

---

### T-806 · Validación: carrito + checkout + tracking

**Estado**: pendiente
**YAMLs asociados**: RF-CART-010..013, RF-SALES-050/054, RF-SHIP-083
**Estimación**: 1 día

---

## FASE 9 — Módulo tienda (público completo)

Consolida la presencia pública de la tienda: home, navegación, búsquedas, páginas estáticas.

### T-901 · Home pública

**Estado**: pendiente
**YAMLs asociados**: (no tiene RF específico)
**Estimación**: 3 días

**Subtareas**:

- T-901.A: Hero section configurable por tienda.
- T-901.B: Secciones: productos destacados, categorías top, marcas, últimas novedades.
- T-901.C: Newsletter signup.
- T-901.D: Componentes reusables con theme adapter.

---

### T-902 · Custom Design Editor público (RF-CUSTOM-030..037)

**Estado**: implementado (parcial)
**YAMLs asociados**: RF-CUSTOM-030..037
**Estimación**: 1 día (validación)

**Criterio de aceptación**: cliente puede diseñar custom, persistir snapshot, agregar al carrito.

---

### T-903 · Modo mantenimiento (RF-STOR-132)

**Estado**: pendiente
**YAMLs asociados**: RF-STOR-132
**Estimación**: 1 día

**Subtareas**:

- T-903.A: Middleware `MaintenanceMode` que retorna 503 si la tienda está en mantenimiento.
- T-903.B: Página de mantenimiento `/mantenimiento` con tiempo estimado.
- T-903.C: Admin sigue operando (rutas admin no afectadas).
- T-903.D: Toggle en `/admin/settings/maintenance`.

---

### T-904 · Validación: tienda pública completa

**Estado**: pendiente
**Estimación**: 2 días

**Criterio de aceptación**: Lighthouse ≥ 90 en todas las páginas públicas, axe-core 0 violaciones.

---

## FASE 10 — Panel de administración

Objetivo: el admin puede operar TODO el sistema desde el panel, con UX moderna y permisos granulares.

### T-1001 · Layout y navegación del admin

**Estado**: pendiente
**YAMLs asociados**: RF-AUTH-008
**Estimación**: 2 días

**Subtareas**:

- T-1001.A: Layout `/admin/_layout.astro` con sidebar persistente + topbar.
- T-1001.B: Sistema de permisos: cada item del sidebar requiere un permiso específico.
- T-1001.C: Componentes: `AdminSidebar`, `AdminTopbar`, `AdminCard`, `AdminTable`.
- T-1001.D: Responsive: sidebar colapsable en mobile.
- T-1001.E: WCAG 2.2 AA en todo el panel.

---

### T-1002 · Dashboard admin con métricas en tiempo real

**Estado**: pendiente
**YAMLs asociados**: RNF-OBS-072
**Estimación**: 3 días

**Subtareas**:

- T-1002.A: Página `/admin/dashboard/index.astro` con métricas: ventas hoy, órdenes pendientes, productos sin stock, MRR, conversion.
- T-1002.B: Gráficos con Chart.js (ventas 7d, top productos, top categorías).
- T-1002.C: Endpoint `/api/v1/admin/dashboard/metrics` con cache 5min.
- T-1002.D: Auto-refresh cada 60s.

---

### T-1003 · Gestión de usuarios admin (RF-AUTH-009)

**Estado**: pendiente
**YAMLs asociados**: RF-AUTH-009
**Estimación**: 3 días

**Subtareas**:

- T-1003.A: Endpoint `GET/POST/PUT/DELETE /api/v1/admin/users`.
- T-1003.B: Página `/admin/users/index.astro` con tabla paginada + búsqueda.
- T-1003.C: Página `/admin/users/[id].astro` con detalle + acciones (reset password, forzar 2FA, desactivar).
- T-1003.D: Validar que solo admins con permiso `users:write` accedan.

---

### T-1004 · Roles y permisos (RF-AUTH-008)

**Estado**: pendiente
**YAMLs asociados**: RF-AUTH-008
**Estimación**: 3 días

**Subtareas**:

- T-1004.A: Endpoint `GET/POST/PUT/DELETE /api/v1/admin/roles`.
- T-1004.B: Página `/admin/roles/index.astro` con lista de roles + permisos asignados.
- T-1004.C: Editor de permisos con matriz módulo × acción.
- T-1004.D: Audit log de cambios de permisos.

---

### T-1005 · CRUD productos (RF-CAT-026)

**Estado**: pendiente
**YAMLs asociados**: RF-CAT-026
**Estimación**: 5 días

**Subtareas**:

- T-1005.A: Endpoints REST para productos.
- T-1005.B: Página `/admin/catalog/products/` con tabla + filtros + bulk actions.
- T-1005.C: Página `/admin/catalog/products/new` y `[id]/edit` con form completo (variantes, atributos, imágenes).
- T-1005.D: Upload de imágenes con preview.
- T-1005.E: Soft delete + restore.

---

### T-1006 · CRUD categorías (RF-CAT-025)

**Estado**: pendiente
**YAMLs asociados**: RF-CAT-025
**Estimación**: 2 días

---

### T-1007 · CRUD atributos (RF-CAT-027)

**Estado**: pendiente
**YAMLs asociados**: RF-CAT-027
**Estimación**: 2 días

---

### T-1008 · CRUD clientes (RF-CUST-040)

**Estado**: pendiente
**YAMLs asociados**: RF-CUST-040
**Estimación**: 2 días

---

### T-1009 · Gestión de tiendas (RF-STOR-132/133)

**Estado**: pendiente
**YAMLs asociados**: RF-STOR-132, RF-STOR-133
**Estimación**: 2 días

---

### T-1010 · Cupones (RF-MKT-060) y promociones (RF-MKT-061)

**Estado**: pendiente
**YAMLs asociados**: RF-MKT-060, RF-MKT-061
**Estimación**: 4 días

---

### T-1011 · Reportes (RF-RPT-140/141)

**Estado**: implementado parcialmente
**YAMLs asociados**: RF-RPT-140, RF-RPT-141
**Estimación**: 2 días (revisión)

---

### T-1012 · Notificaciones y auditoría (RF-SYS-150/151)

**Estado**: implementado
**YAMLs asociados**: RF-SYS-150, RF-SYS-151
**Estimación**: 1 día (revisión)

---

### T-1013 · Tickets de soporte admin (RF-SUP-160)

**Estado**: pendiente
**YAMLs asociados**: RF-SUP-160
**Estimación**: 3 días

### T-1014 · Reviews y Q&A moderation (RF-SUP-161/162)

**Estado**: pendiente
**YAMLs asociados**: RF-SUP-161, RF-SUP-162
**Estimación**: 2 días

---

### T-1015 · Validación: panel admin end-to-end

**Estado**: pendiente
**YAMLs asociados**: (todos los RF admin)
**Estimación**: 3 días

**Criterio de aceptación**: suite E2E cubre flujo admin completo, axe-core 0 violaciones.

---

## FASE 11 — Marketing y soporte

### T-1101 .. T-1106 · Marketing features

(Consolidados en T-1010 y T-1014.)

---

### T-1107 · Validación final marketing + soporte

**Estado**: pendiente
**Estimación**: 1 día

---

## FASE 12 — Hardening no funcional

Objetivo: cerrar los RNF de seguridad, performance, mantenibilidad antes de cualquier release público.

### T-1201 · Cabeceras de seguridad HTTP (RNF-SEC-017)

**Estado**: pendiente
**YAMLs asociados**: RNF-SEC-017
**Estimación**: 1 día

**Subtareas**:

- T-1201.A: Crear middleware `SecurityHeaders` que aplica CSP, HSTS, X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy.
- T-1201.B: Registrar en `bootstrap/app.php` globalmente.
- T-1201.C: CSP no rompe los inline scripts del frontend Astro (usar nonces).
- T-1201.D: Tests Feature: cada header presente en respuestas.
- T-1201.E: Test E2E: verificar headers con curl.

---

### T-1202 · CSRF en rutas web (RNF-SEC-018)

**Estado**: pendiente
**YAMLs asociados**: RNF-SEC-018
**Estimación**: 1 día

---

### T-1203 · Rate limiting documentado (RNF-SEC-016)

**Estado**: implementado
**YAMLs asociados**: RNF-SEC-016
**Estimación**: 0.5 día (revisión)

---

### T-1204 · Escaneo de dependencias (RNF-SEC-019)

**Estado**: pendiente
**YAMLs asociados**: RNF-SEC-019
**Estimación**: 1 día

**Subtareas**:

- T-1204.A: Workflow CI que ejecuta `composer audit` y `npm audit --audit-level=high`.
- T-1204.B: Configurar Renovate bot (sugerencia via PR).
- T-1204.C: Test: PR con dependencia vulnerable falla el check.

---

### T-1205 · Headers anti-clickjacking admin (RNF-SEC-021)

**Estado**: pendiente
**YAMLs asociados**: RNF-SEC-021
**Estimación**: 0.5 día

---

### T-1206 · Rotación de secrets (RNF-SEC-020)

**Estado**: pendiente
**YAMLs asociados**: RNF-SEC-020
**Estimación**: 1 día

**Subtareas**:

- T-1206.A: Documentar runbook en `docs/runbooks/secret-rotation.md`.
- T-1206.B: Probar el procedimiento de rotación en dev.

---

### T-1207 · WCAG 2.2 AA formal (RNF-USA-043)

**Estado**: pendiente
**YAMLs asociados**: RNF-USA-043
**Estimación**: 2 días

**Subtareas**:

- T-1207.A: Tests axe-core en CI para todas las páginas públicas + cliente + admin.
- T-1207.B: Auditoría manual anual (dejar agendado en el calendario).

---

### T-1208 · Emails transaccionales i18n (RNF-USA-044)

**Estado**: pendiente
**YAMLs asociados**: RNF-USA-044
**Estimación**: 2 días

---

### T-1209 · Prevención N+1 (RNF-PERF-024)

**Estado**: pendiente
**YAMLs asociados**: RNF-PERF-024
**Estimación**: 2 días

---

### T-1210 · Versionado de API (RNF-MAIN-054)

**Estado**: pendiente
**YAMLs asociados**: RNF-MAIN-054
**Estimación**: 1 día

---

### T-1211 · Browsers Baseline 2024 (RNF-COMP-090)

**Estado**: pendiente
**YAMLs asociados**: RNF-COMP-090
**Estimación**: 0.5 día

---

### T-1212 · Validación: hardening completo

**Estado**: pendiente
**YAMLs asociados**: (todos los de fase 12)
**Estimación**: 2 días

**Criterio de aceptación**: suite E2E + headers verificados + axe-core + Lighthouse ≥ 90.

---

## FASE 13 — Observabilidad y operaciones

### T-1301 · SLA RPO/RTO (RNF-REL-063)

**Estado**: pendiente
**YAMLs asociados**: RNF-REL-063
**Estimación**: 1 día

### T-1302 · Healthcheck Caddy (RNF-REL-064)

**Estado**: pendiente
**YAMLs asociados**: RNF-REL-064
**Estimación**: 0.5 día

### T-1303 · Tracing distribuido (RNF-OBS-071)

**Estado**: pendiente
**YAMLs asociados**: RNF-OBS-071
**Estimación**: 4 días

### T-1304 · Métricas de negocio (RNF-OBS-072)

**Estado**: pendiente
**YAMLs asociados**: RNF-OBS-072
**Estimación**: 3 días

### T-1305 · Imágenes multi-arch (RNF-PORT-083)

**Estado**: pendiente
**YAMLs asociados**: RNF-PORT-083
**Estimación**: 2 días

### T-1306 · Auto-scaling (RNF-SCAL-033)

**Estado**: pendiente
**YAMLs asociados**: RNF-SCAL-033
**Estimación**: 4 días

### T-1307 · Validación final operaciones

**Estado**: pendiente
**Estimación**: 2 días

---

## Bitácora

| Fecha | Tarea | Estado anterior | Estado nuevo | Commit |
|---|---|---|---|---|
| 2026-09-02 | T-101 | parcial | parcial (revisión iniciada) | (sin commit todavía) |
| 2026-09-02 | RF-AUTH-001 | n/a | implementado (login admin en repo) | f1293be + historial |

---

## Resumen ejecutivo

- **Total de tareas**: ~80
- **Tareas completadas**: 0 explícitas (FASE 1 parcial = login admin ya en repo)
- **Próxima tarea a arrancar**: **T-101** (validar login admin existente) o **T-102** (login cliente) según decisión del equipo
- **Bloqueante identificado**: FASE 0 (T-001..T-005) es prerrequisito — sin CI con gates, no podemos marcar nada como completado con confianza
- **Estimación total del backlog**: ~120 días-hombre (asumiendo 1 desarrollador full-time)
