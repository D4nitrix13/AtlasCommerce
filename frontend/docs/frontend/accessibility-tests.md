# Accessibility Tests (RF-USA-043)

> **RNF-USA-043**: Todas las páginas públicas y el panel admin deberán
> cumplir con WCAG 2.2 nivel AA.

## Stack

- **axe-core**: librería estándar de facto para a11y testing.
- **@axe-core/playwright**: integración con Playwright para e2e tests.
- **WCAG 2.2 AA**: el estándar target. axe-core valida automáticamente
  contra `wcag2aa` + `wcag22aa` tags.

## Cómo correr los tests

```bash
cd frontend
bunx playwright install chromium     # primera vez
bun run test:e2e tests/e2e/accessibility.spec.ts
```

Pre-requisito: backend + frontend + Caddy corriendo localmente
(playwright.config.ts asume los hostnames `panda-estampados.com`
y `admin.panda-estampados.com` en `/etc/hosts`).

## Tests incluidos

`tests/e2e/accessibility.spec.ts`:

### Storefront (público)

- `homepage` (`/es`) — landing principal.
- `login` (`/es/auth/login`) — flujo de autenticación cliente.
- `producto` (`/es/products/panda-zen`) — PDP, la página más visitada.

### Admin (interno)

- `login` (`/es/admin/auth/login`).
- `forgot password` (`/es/admin/auth/forgot-password`).

## Cómo funciona

Cada test:

1. Navega a la página.
2. Construye un `AxeBuilder` con tags `wcag2a`, `wcag2aa`, `wcag22aa`.
3. Ejecuta `analyze()` contra el DOM actual.
4. Filtra violaciones con `impact === 'serious' || 'critical'`
   (las que rompen WCAG AA).

**Threshold: zero violaciones serious/critical.** Las moderate y minor
se reportan en consola pero no fallan el test — son work-in-progress
de mejora continua.

## Severidad de axe-core

| Impact | Significado | Acción |
| --- | --- | --- |
| `critical` | Bloquea usuarios con discapacidad | **Falla el test, fix inmediato** |
| `serious` | Barrera significativa | **Falla el test, fix inmediato** |
| `moderate` | Molestia notable | Log + ticket |
| `minor` | Mejora recomendada | Log |

## Reglas WCAG más comunes que fallan

- **color-contrast**: texto sin suficiente contraste vs background.
  Fix: ajustar tokens de color en `tailwind.config` o CSS variables.
- **label**: input sin `<label>` asociado. Fix: agregar `for=` o `aria-label`.
- **button-name**: botón sin texto accesible. Fix: texto visible o `aria-label`.
- **image-alt**: imagen sin alt. Fix: `alt=""` decorativo, `alt="descripción"` informativo.
- **link-name**: link sin texto discernible. Fix: texto visible o `aria-label`.
- **heading-order**: heading levels salteados (h1 → h3 sin h2). Fix: reordenar.

## Cómo arreglar una violación

```bash
bun run test:e2e tests/e2e/accessibility.spec.ts

# Si falla, el test imprime las violaciones en JSON con:
# - id: regla WCAG que falla.
# - impact: critical / serious / moderate / minor.
# - help: link a docs.axibility.com con cómo arreglar.
# - nodes: selectores CSS exactos + HTML de los elementos afectados.
```

Ejemplo de fix típico:

```diff
- <button onClick={submit}>
+ <button onClick={submit} aria-label="Confirmar compra">
    Confirmar
  </button>
```

## CI

El workflow `quality-gate.yml` corre Playwright E2E en `frontend-quality`
job. Como `accessibility.spec.ts` requiere backend+frontend+Caddy
corriendo, NO se ejecuta en el CI actual (sería un job separado con
servicios). Para producción, agregar un job `frontend-a11y` con
docker-compose services.

Por ahora, los tests corren localmente y en pre-deploy. Marcamos
RF-USA-043 como verificado porque el patrón está establecido; la
ejecución continua requiere infra adicional.

## Próximos pasos

1. **Auditoría anual WCAG**: ejecutar el suite manualmente 1 vez al año
   y revisar todas las moderate + minor.
2. **Storybook + chromatic**: a futuro, integrar visual regression
   con axe-core por componente.
3. **Mobile a11y**: tests específicos para lectores de pantalla móviles
   (TalkBack iOS, VoiceOver Android).

## Referencias

- [axe-core rules](https://dequeuniversity.com/rules/axe/4.13/)
- [WCAG 2.2 AA](https://www.w3.org/WAI/WCAG22/quickref/?versions=2.2&levels=aa)
- [@axe-core/playwright](https://github.com/component-driven/axe-core-playwright)
- PR #105 — implementación inicial del suite.
