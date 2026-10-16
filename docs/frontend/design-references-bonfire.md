# Bonfire — Sistema de diseño extraído (referencia de imitación)

> **Source**: extraído del HTML/CSS real de `https://www.bonfire.com/` (raíz). El sitio está construido con **Framer**: el CSS vive inline en el documento (642 KB), no en hojas externas. Todos los valores de abajo son los literales del sitio, no aproximaciones.
>
> **Propósito**: documentar la referencia visual para que futuras sesiones puedan replicar el lenguaje de Bonfire en las homes de Panda Estampados y Kitsune, respetando los colores propios de cada tenant.

---

## 1. Tokens de color

Extraídos de las variables `--token-*` de producción.

### Texto y superficies
| Token | Hex | Rol |
|---|---|---|
| `--token-32690604…` | `#3D4853` | Texto primario (59 usos) |
| — | `#687383` | Texto secundario |
| — | `#8693A6` | Texto muted / captions |
| — | `#BDC8D9` | Bordes, divisores, `inset` de sombra |
| `--token-4ded5efc…` | `#FFFFFF` | Superficie de cards y nav |
| `--token-562f8ab6…` | `#F9F6F1` | Fondo raíz de sección |
| `--token-fc77e612…` | `#F5F0EA` | Fondo alterno |
| `--token-13900d50…` | `#EDE9DC` | Fondo crema profundo |

### Verde — familia de marca
| Hex | Rol |
|---|---|
| `#2B8265` | **CTA primario** |
| `#1F5C47` | Verde oscuro, botones sobre claro |
| `#234338` | Verde bosque — card hero de sección |
| `#04DA8D` | Acento lima del logo |
| `#126849` | Verde profundo |
| `#CBECE1` | Menta pastel |
| `#EBFAF4` | Menta-2 (badges, chips) |
| `#F2F5F5` | Gris verdoso |

### Acentos
| Hex | Rol |
|---|---|
| `#D65931` | Rust — color del logo |
| `#B4411D` / `#5C2615` | Rust oscuro / roasted |
| `#951318` | Granate — badge "EXPLORE" |
| `#F6C36F` | Ámbar (squiggles, acentos) |
| `#FCDACF` | Melocotón (blobs) |
| `#FDD3D4` / `#FEF0F1` | Rosa pastel |
| `#3F70A2` / `#5B8FC2` | Azules |
| `#EAEFF7` / `#F5F8FC` / `#C7D9EA` | Azules claros |
| `#415F62` | Teal — card split |
| `#D1C59E` | Khaki — trazos a mano |
| `#F6C36F` / `#C97C18` / `#D1C59E` | Familia ámbar |

### Oscuros
`#1B1E24` (char/footer) · `#14293D` · `#1F3D5C` · `#2F577F` · `#415F62`

---

## 2. Tipografía

Cuatro familias, roles fijos. Los `.woff2` originales están en `../fonts/`.

| Familia | Rol | Archivos |
|---|---|---|
| **Soleil Bold** (700) | Display — todos los headings | `SoleilBold.woff2` |
| **Soleil SemiBold** (500) | Eyebrows, botones, quotes | `SoleilSemiBold.woff2` |
| **Soleil Regular** (400) | Texto display secundario | `SoleilRegular.woff2` |
| **Inter** (400/600/700) | Body y UI | `Inter400/600/700.woff2` |
| **Caprasimo** (400) | Display playful, decoración | `Caprasimo.woff2` |
| **Fragment Mono** (400) | Labels y eyebrows técnicos | `FragmentMono.woff2` |

### Escala (valores literales del sitio)
```
120px  90px  68px  50px  38px  28px  22px  18px  16px  15px  14px  12px  10px
```

- H1 hero: 66–90px · `line-height 1.05` · `letter-spacing −2.2px`
- H1 alterno: 68px · `lh 1.1` · `ls −0.9px`
- H2 sección: 50px · `lh 1.15` · `ls −0.5px`
- H3: 38px · `lh 1.25`
- Eyebrow: 11.5px · `ls 1.7px` · MAYÚSCULAS
- Body: 15–16px · `lh 1.7`
- Button: 14.5px Soleil SemiBold

---

## 3. Forma

```css
/* Radius */
--r-card: 16px;    /* 9 usos — todo card y botón */
--r-inner: 8px;    /* 3 usos */
--r-small: 6px;
--r-pill: 999px;

/* Sombra — azul fría, nunca negra */
box-shadow:
  1px 1px 4px  0 rgba(204,219,240,.2),
  2px 2px 8px  0 rgba(204,219,240,.2),
  4px 4px 16px 0 rgba(204,219,240,.2),
  inset 0 0 0 1px rgb(189,202,216);

/* Segunda variante (cards elevadas) */
box-shadow: 8px 12px 32px #EADFCD4D, 4px 4px 16px #EADFCD4D, 2px 2px 8px #EADFCD4D;

/* Layout */
max-width: 1248px;   /* contenido */
canvas: 1440px;
grid producto: 4 columnas;
cards split: 2 columnas;

/* Espaciado (base 8) */
8 · 16 · 24 · 48 · 96 · 144

/* Botones */
padding: 13px 24px;  border-radius: 16px;  font: Soleil SemiBold 14.5px;
```

---

## 4. Lenguaje decorativo

Es lo que hace que el sitio no se vea genérico:

1. **Fotos de personas rotadas** flotando en los bordes del hero, entre `−8°` y `+13°`,
   con `box-shadow` suave y radio ~12px. Se superponen al contenido, no se alinean a la grilla.
2. **Blobs orgánicos** sueltos en melocotón / ámbar / menta, con
   `border-radius: 62% 38% 45% 55% / 55% 48% 52% 45%` — nunca círculos perfectos.
3. **Trazos a mano** tipo squiggle en ámbar `#F6C36F` o khaki `#D1C59E`, un solo trazo,
   unos 3px, `stroke-linecap: round`, siempre parcialmente fuera del frame.
4. **Ilustraciones naïve** (caras, manos, plantas) con línea de 2px en `#687383`.
5. Todo lo anterior vive **detrás** del contenido (`z-index` bajo) y nunca reduce el contraste.

---

## 5. Estructura de la página (secciones en orden)

```
nav blanco, 64px, logo + 4 items + search pill + login + CTA verde pill
HERO        crema #F9F6F1 · badge pill + H1 centrado + sub + CTA verde
            + trustpilot stars + screenshot del producto emergiendo abajo (radius solo arriba)
GRID PROD   crema · H2 centrado + 4 cards blancas con badge "New"/"Popular"
            + marquee horizontal de pills menta
CARD OSCURA #234338 · eyebrow + H3 blanco + CTA blanco + mock del producto + squiggle ámbar
CARD SPLIT  #FFFFFF  +  #415F62 lado a lado, radios 16
TESTIMONIAL card blanca · foto rotada + quote Soleil + stats grandes
FAQ         crema · columna de título + accordions con `+` en círculo #415F62
CTA FINAL   #1B1E24, botón de ancho completo con borde blanco 2px
FOOTER      #1B1E24 · 5 columnas de links + social + badges SOC 2
```

---

## 6. Prompt para IA de imagen

```
Landing page e-commerce de moda con estética "calidez editorial + optimism de system design".

FONDO: crema cálido #F9F6F1, no blanco puro. Alterna con bloques verde bosque #234338
y footer #1B1E24.

TEXTO: azul-pizarra #3D4853, nunca negro. Secundario #687383, muted #8693A6.
Titulares en Soleil Bold (geométrica humanista, redondeada) 38-66px, tracking −2px,
muy apretado. Body Inter 15px line-height 1.7.

ACENTO: verde #2B8265 para CTA. #1F5C47/#234338 para secciones hero.
Pasteles de apoyo: #CBECE1 menta, #FCDACF melocotón, #F6C36F ámbar,
#EAEFF7 azul, #FDD3D4 rosa. #D65931 rust solo en el logo.

COMPONENTES: radio 16px en todo. Botón 13/24px Soleil SemiBold.
Sombra azul difusa de 3 capas con inset 0 0 0 1px #BDCAD8, nunca negra.
Grid de 4 columnas, cards blancas radius 16 sobre crema.

HERO: H1 centrado de 2 líneas, badge pill arriba, CTA verde, estrellas de reseña,
y un screenshot del producto emergiendo por abajo con radius solo en la parte superior.

DECORACIÓN (clave): fotos de personas rotadas −8° a +13° flotando alrededor del hero,
blobs orgánicos sueltos en melocotón/ámbar/menta, trazos a mano tipo squiggle en
ámbar o khaki. Desordenado pero con grilla perfecta. Gradas 8/16/24/48/96/144.

PIES: FAQ acordeón con separadores #E6E0D6, footer #1B1E24 con CTA de ancho completo
con borde blanco, 5 columnas de links, iconos sociales y badges de certificación.
```

---

## 7. Adaptación a Panda Estampados y Kitsune

> Las tiendas del repo NO usan exactamente los colores de Bonfire. Cada tenant tiene su propio acento de marca. La estructura y el lenguaje visual de Bonfire sí se reaprovechan, pero los colores se mapean.

### 7.1 Panda Estampados — mapeo de tokens

| Rol Bonfire | Hex Bonfire | Panda equivalente | Hex Panda |
|---|---|---|---|
| CTA primario | `#2B8265` | `accentColor` del tema Panda | `#2D9D8E` |
| CTA hover | `#1F5C47` | `accentHoverColor` del tema | `#258777` |
| Verde bosque / dark card | `#234338` | Derivado del accent | `#1F4F47` |
| Fondo raíz sección | `#F9F6F1` | Equivalente neutral | `#F8FAFC` (slate-50 Tailwind) |
| Fondo alterno | `#F5F0EA` | Equivalente neutral | `#F1F5F9` (slate-100) |
| Fondo crema profundo | `#EDE9DC` | Equivalente neutral | `#E2E8F0` (slate-200) |
| Texto primario | `#3D4853` | Equivalente neutral | `#0F172A` (slate-900) |
| Texto secundario | `#687383` | Equivalente neutral | `#475569` (slate-600) |
| Texto muted | `#8693A6` | Equivalente neutral | `#64748B` (slate-500) |
| Borde | `#BDC8D9` | Equivalente neutral | `#CBD5E1` (slate-300) |
| Char/footer oscuro | `#1B1E24` | Equivalente neutral | `#0F172A` (slate-900) |
| Char/secondary | `#415F62` | Equivalente neutral | `#334155` (slate-700) |
| Menta accent | `#CBECE1` / `#EBFAF4` | Adecuado al teal | `#A7F3D0` / `#D1FAE5` |
| Melocotón / ámbar | `#FCDACF` / `#F6C36F` | Sin cambios (decorativo, no branded) | mismo |
| Rust | `#D65931` | NO usar (es color del logo Bonfire) | — |

### 7.2 Kitsune — mapeo de tokens

| Rol | Kitsune hex (del tema) |
|---|---|
| CTA primario | `#7C3AED` (violeta del tema) |
| CTA hover | variante más oscura del violeta, p. ej. `#6D28D9` |
| Dark card hero | variante oscura, p. ej. `#4C1D95` |
| Neutrales | mismos slate-50/100/200/900/600 que Panda |

### 7.3 Pasteles y decorativos compartidos

Los pasteles (menta, melocotón, ámbar, rosa, azul claro) NO son branded — sirven en ambos tenants. En Panda pueden teñirse ligeramente hacia el verde teal del accent; en Kitsune hacia el violeta.

---

## 8. Implementación pendiente

Pendiente llevar al código:

- [ ] Tailwind config extendido con tokens de radio, sombra, escala.
- [ ] Capas decorativas (squiggles SVG inline, blobs, dashed strokes).
- [ ] Mockups SVG de productos (playera, hoodie, mug, hat) con logo estampado genérico.
- [ ] Avatares SVG de clientes (círculo con iniciales + gradiente del tenant, rotación entre `−8°` y `+13°`).
- [ ] Aplicar el lenguaje decorativo a `TenantHero`, `StatsBar`, `ProcessSteps`, `ClientLogos`, `CustomerStories`, `FaqAccordion`, `MaterialsCard`.
- [ ] Crear `CtaFinal` y `DarkHeroCard` para sections nuevas que Bonfire tiene y el sprint anterior omitió.
- [ ] Refactor tipográfico: aplicar la escala de Bonfire (H1 66-90px, H2 50px, etc.) a los componentes existentes respetando los tamaños actuales de Tailwind.
