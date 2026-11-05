# Checkout — rediseño UX (sesión 4, vista compra)

> Feature doc ODD. Espejo Engram: **pendiente** (Engram no disponible;
> resincronizar `odd/checkout-view-redesign/tasks` cuando vuelva).
> Continúa tras `cart-view-redesign.md` (PR #180, mergeado).

## Objetivo

Rediseñar `/{locale}/checkout` bajo el sistema boutique-recibo
(`.interface-design/system.md`) con flujo claro por pasos, validación
real, datos que persisten entre pasos y estados de error/éxito
diseñados — manteniendo el flujo **mock** (pedido simulado).

## Problema

Checkout actual: 3 pasos por `?step=` con navegación GET que **pierde
los datos del form** al cambiar de paso, sin validación entre pasos,
submit final vuelca inputs al query string, card fields visibles
siempre, success esmeralda fuera del sistema, slate + `#2D9D8E`
hardcodeado, resumen sin jerarquía de recibo. Además hay 12 componentes
legacy sin uso en `components/storefront/checkout/`.

## Por qué

Es el paso de conversión: un formulario que pierde datos o no valida
destruye la confianza. La vista debe ser coherente con el carrito ya
rediseñado (mismo panel-recibo, mismos tokens).

## Decisión de alcance (usuario, 2026-01-05)

**Rediseño UX en flujo mock.** El backend real (`CheckoutController`
preview/place/confirm) exige auth + `cart_id` servidor +
`shipping_address_id` + `payment_method_id`; integrarlo queda como
**hallazgo reportado**, fuera de este feature. Pedido simulado:
`?step=success` + número mock se conservan.

## Scope autorizado

- `frontend/src/pages/[locale]/checkout.astro`
- `frontend/src/components/storefront/checkout/**` (activos +
  eliminación de legacy sin uso, ver C7)
- Reutilización permitida: `stores/cart.ts` (solo lectura),
  `lib/formatters.ts`, `components/storefront/cart/FreeShippingMeter.tsx`
  y patrón de panel-recibo de `CartSummaryBlock`.
- `frontend/src/i18n/locales/{es,en}.ts` + `types.ts` — claves
  `storefront.checkout.*` (aditivo)
- Tests en `frontend/tests/unit/**`

## Restricciones

- Tokens `shop-canvas` únicamente (fallbacks para Storybook). Acento,
  no esmeralda, para success/"gratis".
- Límites frontend/AGENTS.md (simple 150 / mediano 250 / página 400).
- Contratos a preservar: `data-cart-href` + script de redirect por
  carrito vacío; deep-links `?step=shipping|payment|review|success`
  (el stepper y el CTA del PDP/'Comprar ya' apuntan a
  `/{locale}/checkout` sin step); totales vía `getCartTotals` +
  `formatCurrency` (paridad con el carrito); `TrustSignalsCompact`
  con las claves i18n `storefront.cart.v3.trust.*` ya creadas.
- Formularios: **react-hook-form + Zod** (stack del repo,
  frontend/AGENTS.md). Nuevos comportamientos en TDD RED→GREEN.
- Flujo mock: sin llamadas al backend de checkout.
- Frontend con `bun` (`~/.bun/bin/bun`). NO push/merge sin pedido.

## Tareas

- [x] **C1** Feature doc + diseño detallado bajo system.md (este
  archivo + brief al writer). _Ruta: inline._ — Hecho.
- [x] **C2** `CheckoutFlow` como isla única con estado de paso propio
  (sin recargas GET entre pasos): stepper accesible, `?step=` sincronizado
  por `replaceState` (deep-links intactos), datos persistidos entre
  pasos (sessionStorage draft). _Ruta: delegated writer._ — Hecho:
  `CheckoutFlow.tsx` (208 ln) + `CheckoutStepper.tsx` (108) +
  `checkoutDraft.ts` (73). Tests: gating de pasos, draft save/restore,
  deep-link `?step=` (9 en `CheckoutFlow.test.tsx`, 5 en
  `checkoutDraft.test.ts`) — RED→GREEN.
- [x] **C3** Pasos de formulario con RHF+Zod: envío (contacto+dirección),
  pago (radio-cards; campos de tarjeta solo si método=card), revisión
  (items editables). Validación al avanzar; errores por campo i18n.
  _Ruta: delegated writer (mismo)._ — Hecho: `checkoutSchemas.ts` (156),
  `CheckoutField.tsx` (54), `ShippingStepForm.tsx` (145),
  `PaymentStepForm.tsx` (169), `ReviewStep.tsx` (114). Tests: schemas
  válidos/inválidos (12), visibilidad de tarjeta + avance (en
  `CheckoutFlow.test.tsx`) — RED→GREEN.
- [x] **C4** Panel-resumen recibo (paridad visual con el carrito:
  desglose, divisor punteado, total Fraunces, medidor envío gratis,
  chips de confianza). Totales siempre vía `getCartTotals`.
  _Ruta: delegated writer (mismo)._ — Hecho: `CheckoutOrderSummary.tsx`
  reescrito (162 ln) como panel informativo con `FreeShippingMeter`,
  divisor punteado, total Fraunces 32px y trust chips
  (`storefront.cart.v3.trust.*`). Tests: 5 en
  `CheckoutOrderSummary.test.tsx` (paridad totales con carrito).
- [x] **C5** Estados: éxito rediseñado (acento, panel de orden
  punteado tipo recibo), banner de error de submit (slot diseñado),
  validación visible, empty-cart redirect intacto.
  _Ruta: delegated writer (mismo)._ — Hecho: `CheckoutSuccess.astro`
  (105 ln, check en círculo `--primary-soft` con acento —sin esmeralda—,
  panel punteado con número mock, CTAs `ui/Button`); banner de error en
  `CheckoutFlow.tsx` (`role="alert"`, i18n `error.title`/`error.message`)
  con test; script `data-cart-href` + redirect intacto en checkout.astro.
- [x] **C6** Responsive (stack + barra sticky mobile con total + CTA
  del paso) + i18n es/en (claves de validación, CTAs por paso,
  descripciones de método de pago; trust signals i18n — hoy
  hardcodeados en checkout.astro).
  _Ruta: delegated writer (mismo)._ — Hecho: grid
  `lg:grid-cols-[minmax(0,1fr)_380px]` + barra mobile fija
  (`CartMobileBar` pattern, solo `<lg`) con total + CTA del paso en
  `CheckoutFlow.tsx`; 12 keys nuevas `storefront.checkout.v3.*` en
  types/es/en (+ test `checkout-v3-redesign.test.ts`); trust y medidor
  reusan `storefront.cart.v3.trust.*` / `freeShipping*`.
- [x] **C7** Limpieza: eliminar los 12 componentes legacy sin uso de
  `checkout/` (verificados: nada los importa) y sus claves i18n
  `storefront.checkout.*` legacy si quedan huérfanas. _Ruta: delegated
  writer (mismo)._ — Hecho: borrados 16 (11 legacy verificados con grep
  uno a uno: ContactStep, ShippingStep, PaymentStep, ReviewStep,
  Stepper, Summary, SummaryBody, SummaryV3, TermsCheckbox, TrustBadges,
  ReviewStepV3 + 5 reemplazados por la isla: StepperV3, FormV3,
  ShippingStepV3, PaymentStepV3, ReviewItems.tsx y su test). Claves
  legacy `storefront.checkout.*` (no `.v3.`/`.success.`) huérfanas
  eliminadas de types/es/en (~95 por locale, 0 referencias restantes
  verificado con grep). Nota: `src/data/checkout-mock.ts` quedó
  huérfano (solo lo usaban los legacy) — fuera del alcance listado.
- [x] **C8** Cierre común — observado 2026-01-05: `bun run test`:
  58 archivos / 569 tests passed (spot-check parent) · `bun run
  astro check`: 0 errores/0 warnings · `bun run build`: OK · `bunx
  eslint <tocados>`: 0 errores nuevos · capturas en
  `output/playwright/checkout-redesign-{shipping,payment,payment-card,review,success}-1440.png`
  + `checkout-redesign-{shipping,review}-390.png`, revisadas
  visualmente (stepper, validación, radio-cards, recibo, success,
  mobile). Fix de parent: orden mobile form→resumen (el brief pedía
  form primero). `checkout-mock.ts` borrado (huérfano verificado).
  _Ruta: per-action workers._

## Criterios de aceptación

1. Flujo completo carrito → checkout → éxito sin recargas que pierdan
   datos; deep-links `?step=` siguen funcionando.
2. Validación por paso: no se avanza con campos inválidos; errores
   claros por campo (i18n es/en).
3. Card fields solo con método=card; radios de método claros.
4. Totales del resumen = `getCartTotals` (paridad con carrito).
5. Acento por tenant correcto; success sin esmeralda.
6. 0 errores nuevos (tests/lint); tests nuevos en RED→GREEN.
7. Capturas 1440/390 de los 3 pasos + success sin overlap ilegible.

## Presupuesto / delivery

- Forecast: ~600-900 líneas autoralas (incluye borrado legacy).
  Estrategia: **single-pr** (caché de la decisión del usuario en
  `cart-view-redesign`, aceptó size:exception explícita). Registrar
  `size:exception` en el PR como en #180.

## Checks aplicables

`~/.bun/bin/bun run test` · `bun run astro check` · `bun run build` ·
`bunx eslint <archivos>` (0 errores nuevos) · playwright firefox
1440/390 contra `:5321` (Host tenant).

## Progreso / evidencia

- C1: este doc + brief de diseño. Base: `decbf073` (master post-#180),
  worktree `~/Code/AtlasCommerce-worktrees/compra`, branch
  `feat/views/compra`.
- C2–C7 (writer delegado, sesión checkout): isla `CheckoutFlow` +
  pasos RHF+Zod + panel-recibo + success rediseñado + i18n es/en +
  borrado de 16 componentes legacy/reemplazados y claves huérfanas.
  Tests nuevos en RED→GREEN (schemas 12 · draft 5 · flow 9 · summary 5
  · i18n 5). Verificación: `bun run test` 58 files / 569 tests green ·
  `bun run astro check` 0 errors · `bun run build` OK · `bunx eslint`
  0 errores (11 warnings `max-lines-per-function`, mismo baseline que
  `CartView`/`CartLineRow`). Pendiente C8: capturas playwright
  1440/390 + spot-check parent.

## Próximo paso

C2–C7 con writer delegado; luego C8 (verificación + capturas) y commit
work-unit.
