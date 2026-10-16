# Plan: AtlasCommerce — Polish de producción Q1 2026

> **Status**: en ejecución. Branch de trabajo `chore/saas-production-polish` desde `master` (25d59bff).
>
> **Scope**: 4 tracks coordinados para llevar AtlasCommerce a un estado donde La Voz (primer cliente real SaaS) pueda salir a producción sin vergüenza y con cobros funcionando.
>
> **Tracks resumidos**:
> - **Track 1** — Docker + Bun cleanup (ESTE es ahora)
> - **Track 2** — Vistas Nike + guitargear.com.mx para La Voz (siguiente)
> - **Track 3** — Pasarela de pagos Nicaragua
> - **Track 4** — Vistas admin (al final, cliente primero)
>
> **Engram mirror**: `odd/atlascommerce-saas-production-polish/tasks`

---

## 0. Contexto y motivación

La Voz es el primer cliente real de AtlasCommerce SaaS. Migramos pnpm → bun, montamos 10 vistas Bonfire v3, configuramos multi-tenant. Pero hay **deuda técnica + funcionalidad faltante** antes de cobrar:

1. `frontend/bun.lock` sin commitear + `prebuild` con `pnpm run optimize:svgs` (deuda de la migración bun)
2. `docker-stack.sh` funciona pero el output es verboso y poco scannable (mejorar para onboarding)
3. Documentación dispersa y desactualizada (README no menciona bun ni SaaS ni La Voz)
4. Vistas usan lenguaje Bonfire, pero La Voz vende instrumentos musicales — el patrón no es ideal. Nike + guitargear.com.mx son mejores referencias para ese vertical.
5. Cero pasarela de pagos real (modelo `Payment` existe, ninguna integración).
6. Vistas admin están al final por pedido explícito del usuario.

---

## Track 1 — Docker + Bun cleanup (activo)

### T1.1 — Cerrar migración bun

**Problema**: `frontend/bun.lock` no está commitado. `package.json` aún dice `pnpm run optimize:svgs` en `prebuild`. Inconsistente con el commit `51deaddf` que ya adoptó bun.

**Cambios**:

| Archivo | Acción |
|---|---|
| `frontend/bun.lock` | `git add` (no modificar) |
| `frontend/package.json` | Reemplazar `prebuild: "pnpm run optimize:svgs"` por `prebuild: "bun run optimize:svgs"` |
| `.gitignore` raíz | Verificar que `bun.lockb` no esté ignorado (solo añadir binario si aplica) |

**Commit**: `chore(frontend): cerrar migración a bun (lockfile + prebuild)`

**Verificación**:
- `cd frontend && bun install` → exit 0
- `cd frontend && bun run optimize:svgs` → exit 0
- `cd frontend && bun run build` → exit 0
- `git grep -n "pnpm" frontend/package.json` → vacío

### T1.2 — Mejorar output de `scripts/docker-stack.sh`

**Problema**: el script tiene el `header` colorido y el `summary` previo a cada acción, pero la salida durante `up/build/pull` es stream crudo de docker compose. Para onboarding de nuevos devs del SaaS y para que el output sea diagnóstico-friendly, hay que:

1. Envolver la salida de `docker compose` con un banner que diga qué servicio está arrancando.
2. Spinner o progreso por servicio (sin reinventar — usar `--progress=plain` o equivalente).
3. Tabla de **status post-up** con columnas `Service | State | Health | Port | URL` (formato `docker compose ps` interpretado).
4. Timestamp visible al inicio de cada comando.
5. Resumen final con conteo de servicios healthy vs unhealthy.

**Cambios**:

| Archivo | Acción |
|---|---|
| `scripts/docker/_lib/summary.sh` | Tabla post-up estilo `printf` columnado |
| `scripts/docker/_lib/logging.sh` | Timestamps por línea + nivel (info/warn/error) |
| `scripts/docker/_lib/banner.sh` (nuevo) | Helper para banners ASCII por servicio |
| `scripts/docker-stack.sh` | Llamar al banner helper en `cmd_up` y mostrar la tabla post-up |

**Commit**: `feat(scripts): mejorar output del orquestador docker-stack`

**Verificación**:
- `./scripts/docker-stack.sh up dev panda-estampados` → banners por servicio + tabla post-up
- `./scripts/docker-stack.sh status dev panda-estampados` → tabla con health + ports

### T1.3 — Documentación de tecnologías

**Problema**: documentación dispersa, sin diagrama de stack, sin mención de bun, sin link al SaaS ni a La Voz.

**Cambios**:

| Archivo | Acción |
|---|---|
| `README.md` | Reemplazar lista vieja de stack por sección mantenible + tabla `Tecnología | Versión | Por qué` + link al `docs/operations/tech-stack.md` |
| `docs/operations/tech-stack.md` (NUEVO) | Diagrama ASCII del stack + decisiones + tradeoffs + roadmap de migraciones futuras (FrankenPHP, Scalar, etc.) |
| `docker/COMPOSE.md` | Sección "Stack moderno" con bun + astro 7 + node 22 |
| `frontend/AGENTS.md` | Buscar/reemplazar comandos para usar `bun` no `pnpm` |
| `backend/AGENTS.md` (si existe) | Sin cambios estructurales |

**Commit**: `docs: actualizar stack tecnológico + crear docs/operations/tech-stack.md`

**Verificación**:
- `git grep -n "pnpm" docs/ frontend/AGENTS.md` → vacío
- `ls docs/operations/tech-stack.md` → existe y es legible

### Riesgos Track 1

- **bun.lock**: el lockfile binario está en `.gitignore` por defecto en algunos proyectos. Verificar antes del `git add`. Si está bloqueado, documentar.
- **docker-stack.sh**: ya tiene lib modular. NO romper convenciones (cada script <100 líneas, dispatcher delgado).
- **Documentación**: hacer edit-mínimo. La versión actual no es mala, solo incompleta.

---

## Track 2 — Vistas Nike + guitargear.com.mx (siguiente, tras Track 1)

> **Estado**: pendiente de iniciar. Documento detallado en `odd/tasks/atlascommerce-nike-guitargear-redesign.md` cuando se arranque.

**Idea**: La Voz vende instrumentos musicales. Las 10 vistas Bonfire v3 están bien para Panda B2B, pero para B2C nicho instrumentos, mejores referencias son:

- **guitargear.com.mx** para el **catálogo y detalle de producto** (específico a instrumentos musicales, layout probado en el vertical)
- **nike.com** para el **flujo de conversión** (ATC, checkout, mega menu, mobile-first)

**Sprint 2.1 — Cliente primero** (las 6 críticas):

1. Home La Voz con hero nicho
2. Catálogo de instrumentos por categoría
3. Detalle de producto (galería + spec técnica + ATC prominente)
4. Carrito
5. Checkout multi-step con pasarelas
6. Mis órdenes

**Sprint 2.2 — Admin al final** (las 4 admin):

7. Admin dashboard
8. Admin orders
9. Admin catalog
10. Admin customers

**Por qué Nike para conversión**: nike.com tiene ATC prominent, mega menu hover, mobile-first, sistemas de tallas/colors en cards, hero storytelling. Hay 20 años de CRO en ese site.

**Por qué guitargear para catálogo**: el site es nicho y demuestra que se puede vender instrumentos sin un mega catálogo, con filtros por marca/categoría y descripción técnica prominente.

---

## Track 3 — Pasarela de pagos Nicaragua (pendiente)

> **Estado**: pendiente. Investigación de proveedores reales en docs/operations/payments-nicaragua.md cuando se arranque.

**Realidad del mercado Nicaragua** (investigada):

| Proveedor | API real | Costo | Recomendado |
|---|---|---|---|
| **BAC Nicaragua** | Developer portal con onboarding empresarial (semanas/meses) | Costo fijo mensual + % | Solo si se justifica volumen |
| **Banpro** | Plataforma e-commerce propia, integración por iframe | Costo fijo mensual + % | Solo si se justifica volumen |
| **Lafise** | LafisePay, onboarding similar | Costo fijo mensual + % | Solo si se justifica volumen |
| **PayPal** | Estándar, sandbox inmediato | % por transacción, sin mínimo | Sí para MVP y clientes internacionales |
| **Stripe** | Sandbox inmediato, soporta NIC con tarjeta internacional | 2.9% + 0.30 USD | Sí para tarjetas internacionales |
| **Pago contra entrega (COD)** | N/A, manual | 0% en fee, costo operativo | Sí para mercado local NIC |
| **Transferencia con comprobante** | N/A, manual | 0% en fee | Sí para BAC/Banpro/Lafise sin integración |
| **Efectivo tienda física** | N/A, manual | 0% en fee | Sí para La Voz (tiene local físico) |

**Recomendación MVP**:

1. **Transferencia bancaria** con upload de comprobante (BAC, Banpro, Lafise) — soporta los 3 bancos que pediste
2. **Pago contra entrega** (COD) — muy común en NIC
3. **PayPal** — para clientes internacionales
4. **Stripe** — para tarjetas internacionales
5. **Efectivo en tienda física** — solo La Voz

**Fase 2 (post-MVP)** si hay volumen:

- Integraciones reales con BAC/Banpro/Lafise (requieren contrato comercial)

**Componentes a construir**:
- Modelo Laravel `PaymentGateway` (interfaz) + `BankTransferGateway`, `CodGateway`, `PayPalGateway`, `StripeGateway`
- Componente Astro `PaymentMethodSelector` en checkout
- Componente Astro `BankTransferInstructions` con datos de cada banco
- Componente Astro `PaymentProofUploader` (cliente sube comprobante)
- Admin panel para confirmar pagos manuales

---

## Track 4 — Vistas admin (al final)

Por pedido explícito del usuario. Se arrancará tras Track 2 y 3. Las 4 vistas admin (dashboard, orders, catalog, customers) se pulen con el sistema visual Nike+guitargear aplicado a admin patterns (Filament-like density, sidebar colapsable, dark mode opcional).

---

## Tracking

### Branch strategy

- Track 1: `chore/saas-production-polish` ← **rama activa**
- Track 2: `feat/la-voz-views-nike-guitargear` ← siguiente
- Track 3: `feat/payments-nicaragua`
- Track 4: `feat/admin-views-polish`

Cada track → 1 PR con N commits work-unit (límite 400 LOC por PR, dividido si excede).

### Tareas actuales (Track 1)

- [ ] **T1.1** — Cerrar migración bun (commit `chore(frontend): cerrar migración a bun`)
- [ ] **T1.2** — Mejorar output `docker-stack.sh` (commit `feat(scripts): mejorar output del orquestador docker-stack`)
- [ ] **T1.3** — Actualizar documentación de stack (commit `docs: actualizar stack tecnológico + crear docs/operations/tech-stack.md`)
- [ ] Merge a `master` cuando los 3 commits estén verdes (bun build + docker stack up dev + docs lint)

### Siguiente

- Confirmar cierre de T1.1, T1.2, T1.3.
- Crear `odd/tasks/atlascommerce-nike-guitargear-redesign.md` para Track 2.
- Preguntar al usuario si arrancar Track 2 con las 6 vistas cliente, o ir a Track 3 (pagos) primero.

---

## Riesgos globales

| Riesgo | Mitigación |
|---|---|
| Track 1 deja archivos sin commitear (los vi en git status) | Commit primero, después T1.1 |
| Pasarela de pagos depende de Checkout aún no pulido | Track 2 (cliente) debe terminar antes que Track 3 (pagos) para tener checkout presentable |
| Admin al final requiere que tracks anteriores estén estables | Documentar y trackear en Engram |
| Stack cambia (FrankenPHP, Scalar, etc.) y rompe otros tracks | Cerrar primero lo que ya está en uso; nuevas migraciones evaluadas en su propia tarea |
| Memoria crece rápido con 4 tracks | Cada task cerrada dispara `mem_session_summary` con sus observaciones |
