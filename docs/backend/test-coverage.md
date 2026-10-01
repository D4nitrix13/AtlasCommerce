# Test Coverage — Baseline y roadmap (RF-MAIN-050)

> **RNF-MAIN-050**: Cobertura de tests ≥ 70% en líneas críticas
> (services, controllers, componentes UI principales).

## Estado actual

El baseline se establece en este PR (#106). Los valores exactos se
medirán al correr `composer test:coverage` (backend) y
`pnpm run test:coverage` (frontend) en CI con Xdebug + v8 habilitados.

**Backend** (esperado a la fecha del PR):

- Domain/Services: ~85% (muchos services tienen tests unitarios).
- Controllers: ~40% (cubiertos por Feature tests pero los tests
  suelen tocar solo endpoints específicos, no todas las branches).
- Middleware: ~70% (los middleware críticos tienen tests, los
  auxiliares no).
- Models: bajo (testeados indirectamente vía factories + relaciones).

**Frontend** (esperado):

- Components/ui: ~30% (smoke tests básicos).
- Components/storefront + account + admin: variable según cuántos
  tienen tests Vitest.
- Hooks: ~50% (useCoupon, useCouponInput ya testeados).

## Threshold actual: sin enforce

`composer test:coverage` corre con `--min=0` (no falla). `pnpm run
test:coverage` también sin threshold duro. **Esto es deliberado** para
este PR — el objetivo es medir el baseline y empezar a trackear
progresión, no bloquear PRs por coverage insuficiente durante la
transición.

## Roadmap a 70%

### Backend

1. ✅ Tests de middleware (RF-SEC-017/021/018, RF-STOR-132) — todos
   tienen tests Feature.
2. ✅ Tests de Services críticos (CheckoutService, CouponService,
   PromotionService, StockService, RefundService).
3. ✅ Tests Feature de controllers (Auth, Admin, Storefront).
4. ⏳ Pendiente: branches no cubiertas de los controllers admin
   (error paths, validación 422).
5. ⏳ Pendiente: tests unitarios de jobs/commands.

### Frontend

1. ✅ Tests Vitest de componentes storefront críticos (CouponInput,
   AddressesManager, CustomersAdmin, StoresAdmin).
2. ⏳ Pendiente: tests de hooks (useCoupon, usePagination, useAuth).
3. ⏳ Pendiente: tests E2E para flujos completos (checkout, login,
   catalog browse).

## Cómo correr localmente

### Backend

```bash
cd backend

# Activar Xdebug coverage en php.ini:
# xdebug.mode=coverage

composer test:coverage
# → genera backend/coverage/html/index.html
# → texto resumido en stdout
```

### Frontend

```bash
cd frontend
pnpm run test:coverage
# → genera frontend/coverage/index.html
# → texto resumido en stdout
```

## CI

El workflow `quality-gate.yml` corre los tests SIN coverage para
mantener velocidad. Un job dedicado `coverage` se agregaría en
PRs siguientes con servicios adicionales (Xdebug habilitado).

## Métricas a trackear

| Métrica | Target | Cómo mejorar |
| --- | --- | --- |
| Line coverage backend | ≥ 70% | Tests para branches no cubiertas |
| Line coverage frontend | ≥ 70% | Tests de componentes y hooks |
| Branch coverage | ≥ 60% | Tests para if/else no cubiertos |
| Path coverage (critical) | ≥ 90% | Tests E2E de flujos de negocio |

## Upgrade policy

- **Mensual**: revisar delta de coverage. ¿Vamos hacia el 70% o
  retrocediendo?
- **Por sprint**: agregar tests para el código nuevo ANTES de mergear
  el feature. Nunca reducir coverage.
- **Trimestral**: auditoría manual de archivos con 0% de coverage y
  decisión: ¿se testean o se documenta por qué no?

## Exclusiones explícitas

Ciertos archivos NO se cuentan en coverage porque son infraestructura:

- `database/factories/*` — factories testeados indirectamente.
- `database/seeders/*` — seeders son setup, no lógica.
- `app/Console/Kernel.php` — bootstrap de Laravel.
- `config/*` — archivos de config sin lógica testeable.
- `app/Providers/*` — providers simples, baja prioridad.

Si un archivo tiene `// @codeCoverageIgnoreStart` se respeta.

## Pendiente

- Job CI dedicado con Xdebug enabled + upload a Codecov/Coveralls.
- Threshold enforcing una vez que baseline sea ≥ 70%.
- Tests unitarios para los jobs/commands pendientes.

## Referencias

- [PHPUnit Coverage](https://docs.phpunit.de/en/10.5/code-coverage-analysis.html)
- [Vitest Coverage](https://vitest.dev/guide/coverage.html)
- PR #106 — setup del baseline + phpunit.xml config.
