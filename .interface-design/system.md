# Sistema de diseño — Storefront AtlasCommerce

> Dirección visual comprometida para las vistas de cliente. Creado tras
> el rediseño del carrito (sesión 4, `feat/views/compra`). Aplicar en
> las vistas siguientes; si una decisión de acá se cambia, actualizar
> este archivo en el mismo PR.

## Dirección y feel

**Mostrador boutique de estampados/papelería.** Papel crema, tinta
cálida, un solo acento (el del tenant), estética de recibo de compra
para los paneles de totales. Cálido, artesanal, confiable — nunca
SaaS genérico. Metáforas del dominio: recibo, bolsa de kraft,
etiqueta estampada, packing slip, sello de tinta.

## Tokens (existentes — `shop-canvas.ts` + `styles/global.css`)

| Token | Uso |
|---|---|
| `--paper` `#f3efe8` | lienzo de página |
| `--ink` `#1c1917` | texto primario; muted = `--ink` con opacidad (`/60`, `/40`) |
| `--primary` / `--primary-hover` | acento tenant (Panda teal `#2D9D8E`, Kitsune violet `#7C3AED`, …) |
| `--primary-soft` | tintes: chips, hover de stepper, fondos suaves |
| `--line` `color-mix(accent 22%, #e7e5e4)` | hairlines, bordes de inputs, divisores |
| fallbacks | `var(--primary,#475569)` etc. obligatorios para Storybook |

Un solo acento por pantalla. "Gratis"/éxito usa el acento, no verde.
Rojo semántico solo para hover destructivo (quitar). Sin `ring` nativo
de Tailwind: `focus-visible:ring-2` con acento (patrón HomeHero).

## Profundidad y espaciado

- **Estrategia única**: hairlines (`--line`) para divisores/inputs +
  sombras susurrantes para superficies elevadas
  (`0 0 0 1px rgba(0,0,0,.05), 0 1px 2px -1px rgba(0,0,0,.06),
  0 2px 4px rgba(0,0,0,.04)`). Sin bordes duros ni sombras marcadas.
- **Base de espaciado 4px**. Padding de panel 24–32px; filas 16–20px
  verticales; gaps 16px. Full-width, sin `max-w` estrechos, padding
  simétrico.

## Jerarquía

- **Escala tipográfica ratio 1.25** desde 14px: 11 · 14 · 16 · 18 ·
  22 · 28 · 32 display.
- **Fraunces** (`--font-display`) solo para el `h1` de página (regla
  global `.shop-canvas h1`) y números-héroe (el total).
  **Figtree** (`--font-sans`) para todo lo demás.
- Etiquetas: 12px/500/muted/uppercase con tracking. Valores:
  14px/500-600/ink con `tabular-nums`. Total: 32px/600 Fraunces
  tabular, tracking ligeramente negativo en tipografía grande.
- **Foco por vista = una acción primaria** (en el carrito: total +
  CTA). Lo demás se demota con peso/opacidad, no con bordes.

## Patrones de componente (valores reales)

| Patrón | Valores |
|---|---|
| **Fila de ítem** (una superficie blanca `rounded-2xl`, filas con divisor `border-t border-[var(--line)]/60`, hover tinte paper) | imagen 88px `rounded-lg` + outline inset `rgba(0,0,0,0.1)`; nombre 15px/600 link; chip variante 12px `bg-[var(--primary-soft)]` `rounded-full px-2`; total línea 15px/600 tabular a la derecha; quitar = icon button `ink/40` → hover `red-600`/`red-50` |
| **Stepper pill** | `inline-flex rounded-full border border-[var(--line)] bg-white`; botones hover `--primary-soft`; disabled en límites 1..max; `aria-label` de i18n; `:active` scale 0.97 |
| **Resumen-recibo** (sticky `lg:top-24`, ~360px) | orden: medidor envío gratis → desglose (label muted 13px / valor 14px/500 tabular) → divisor **punteado** `border-dashed` → total display 32px Fraunces → CTA full-width `h-12 rounded-lg` → link "continuar comprando" muted → chips de confianza |
| **CTA primario** | `h-12 rounded-lg bg-[var(--primary)] text-white` hover `--primary-hover`, `:active` scale 0.97, `focus-visible:ring-2` |
| **Medidor envío gratis** | barra `h-1.5 rounded-full` (track `--primary-soft`, fill `--primary`), transición width 300ms `cubic-bezier(0.23,1,0.32,1)`; umbral = `FREE_SHIPPING_THRESHOLD_CENTS` del store; label "Te faltan {amount}" / check acento "Tenés envío gratis" |
| **Chips de confianza** | fila compacta icon+label, muted, small |
| **Barra mobile sticky** | `fixed bottom-0`, `bg-[var(--paper)]/95 backdrop-blur`, `border-t border-[var(--line)]`, total tabular + CTA compacto; solo `< lg` y con items |
| **Estado vacío** | círculo `--primary-soft` con icono bolsa, heading Fraunces, hint muted, CTA primario |
| **Mini-progreso de flujo** | "1 Bolsa · 2 Checkout · 3 Listo", paso activo en pastilla de acento, resto muted, 13px |

## Motion

Solo `transform`/`opacity`. Press `scale(0.97)` en `:active`; entradas
con `data-reveal-stagger` (scroll-fx.css); barras con transición de
width. Duraciones UI < 300ms, easing `cubic-bezier(0.23, 1, 0.32, 1)`
para interacción. Respetar `prefers-reduced-motion`. Nunca
`transition: all`.

## Defaults rechazados (no volver a hacer)

- Lista de tarjetas blancas planas → una superficie con filas y
  divisores hairline.
- Stepper de 3 botones sueltos → control pill segmentado.
- Caja de totales genérica → panel-recibo con ritmo y total display.
- Iconos de confianza sueltos → chips compactos.
- Jerarquía por tamaño solo → peso + opacidad + color hacen el trabajo.

## Multi-tenant

Los componentes nunca hardcodean el acento: todo pasa por los tokens
de `shop-canvas` (cada theme en `frontend/packages/themes/*/src/index.ts`
sobreescribe `--primary`). Verificar en Panda (teal) y Kitsune (violeta)
al tocar una vista.
