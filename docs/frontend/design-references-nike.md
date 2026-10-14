# AtlasCommerce — Design system reference Nike (v1)

> **Propósito**: referencia de diseño Nike.com para la próxima tanda de prompts (home redo + admin views + login/signup). Esta referencia reemplaza Bonfire para vistas nuevas. La Bonfire system en `design-references-bonfire.md` queda como referencia histórica de los sprints ya mergeados.

---

## 1. Tokens base Nike

Nike.com usa un design system minimalista con blanco/negro dominante. Paleta extractada del CSS de producción:

### Colores
| Token | Hex | Rol |
|---|---|---|
| `--nike-white` | `#FFFFFF` | Fondo principal y superficies de cards |
| `--nike-black` | `#111111` | Texto, CTAs primarios, borders |
| `--nike-gray-100` | `#F5F5F5` | Backgrounds secundarios (hovers) |
| `--nike-gray-200` | `#E5E5E5` | Borders, dividers |
| `--nike-gray-400` | `#757575` | Texto secundario, captions |
| `--nike-gray-600` | `#333333` | Texto principal hover |
| `--nike-volt` | `#CEFF00` | Accent (usar solo en highlights) |
| `--nike-orange` | `#FA5400` | Accent secundario (badges, alerts) |

### Tipografía
Nike usa su propia sans-serif **Nike Futura ND** (no disponible open source). Fallbacks:
- **Helvetica Now** (más cercana)
- **Inter** (open source, similar feel)
- **system-ui** (último fallback)

Escala:
- H1: 56-72px, font-weight 700-900, line-height 0.95, letter-spacing -0.04em
- H2: 36-48px, font-weight 600
- H3: 24-32px, font-weight 600
- Body: 16-18px, line-height 1.4
- Caption: 12-14px, uppercase, letter-spacing 0.05em

### Radius
- Cards: 0-4px (Nike NO usa rounded-2xl, prefiere edges más rectos)
- Buttons: 0px (rectangular) o 4px
- Pills: 999px (full round)

### Sombras
Nike usa sombras muy sutiles o ninguna:
- `--shadow-nike-sm`: `0 1px 2px rgba(0,0,0,0.04)`
- `--shadow-nike-md`: `0 2px 8px rgba(0,0,0,0.06)`
- `--shadow-nike-lg`: `0 4px 16px rgba(0,0,0,0.08)`

---

## 2. Lenguaje visual Nike

Nike.com es **action sports minimalism**. Características:

1. **Heroes full-bleed** que ocupan 100vh con imagen de producto grande centrada o a un lado
2. **Tipografía bold** que ocupa gran parte del viewport en headlines
3. **CTAs rectangulares negros** "Shop Now", "Add to Bag" sin rounded
4. **Sticky header blanco** con logo + mega-menu de categorías al hover
5. **Cards de producto** simples: imagen grande + nombre + precio + tallas
6. **Grid estricto** sin elementos decorativos orgánicos (no squiggles, no blobs)
7. **Acentos coloridos ocasionales** (volt, orange) solo en highlights
8. **Action imagery**: fotos de atletas en movimiento, no lifestyle editorial
9. **Whitespace generoso** entre secciones (py-16 a py-24)
10. **Sticky ATC (Add to Cart)** bar en mobile en product detail

---

## 3. Mapeo a AtlasCommerce

### Tokens de marca (NO Nike — son tenant-specific)

| Tenant | Color | Equivalente Nike |
|---|---|---|
| Panda B2B | Teal `#2D9D8E` | Aplica Nike minimalismo pero con teal en CTAs |
| Kitsune B2C | Violeta `#7C3AED` | Aplica Nike sport aesthetic con violeta |
| MM B2C | Pink `#DB2777` | Aplica Nike minimalista boutique con pink |
| Morales B2C | Azul `#2563EB` | Aplica Nike retail con azul |

### Mapeo de componentes Nike → AtlasCommerce

| Nike | AtlasCommerce equivalent | Notas |
|---|---|---|
| Nike mega-menu | `StorefrontHeader.astro` (extender) | Categorías anidadas con hover |
| Product Card Nike | nuevo `ProductCardNike.astro` | Sin rounded, sin shadow, imagen grande |
| ATC sticky bar | nuevo `AddToCartStickyBar.astro` | Mobile-only, bottom fixed |
| Action hero | adaptar `TenantHero.astro` | Imagen grande + headline bold |
| Carousel Nike | usar `Embla` o CSS scroll-snap | Sin deps nuevas si es posible |

### Lo que NO copio de Nike

- Nike logo (marca registrada)
- Nike Futura ND font (no open source, uso Inter como fallback)
- Nike "Just Do It" slogan y copy trademarked
- Color volt `#CEFF00` como accent único de Nike
- Lifestyle imagery de atletas profesionales (necesitamos material propio)

---

## 4. Convenciones para vistas Nike-style

- **Backgrounds**: `bg-white` o `bg-nike-gray-100` (nunca cream)
- **Cards**: `bg-white border border-nike-gray-200` (sin rounded-2xl, usar rounded-sm o rounded-none)
- **Botones primarios**: `bg-nike-black text-white px-6 py-3 rounded-none font-semibold uppercase tracking-wide`
- **Botones secundarios**: `bg-white text-nike-black border border-nike-black`
- **Heroes**: `min-h-[80vh] flex items-center` con imagen a un lado
- **Tipografía**: bold sans-serif, jerarquía clara
- **Whitespace**: generoso, py-20 a py-32 entre secciones
- **NO elementos decorativos**: nada de squiggles, blobs, sunbursts, etc.

### Accesibilidad

- `<section>`, `<header>`, `<main>`, `<nav>` semánticos
- Focus rings con `outline-2 outline-nike-black outline-offset-2`
- Contraste: texto negro sobre blanco es WCAG AAA; texto blanco sobre negro también
- Scroll-driven animations: aplicables pero más sutiles que Bonfire (translateY 12px vs 24px)

---

## 5. Por qué Nike funciona para AtlasCommerce (y Bonfire para B2B)

**Nike aesthetic** es ideal para B2C (Kitsune, MM Boutique, Morales Store):
- Productos visualmente heroicos, gran variedad, decision de compra rapida
- Whitespace ayuda a que las imagenes de producto respiren
- Tipografia bold da personalidad de marca
- CTAs claros sin distracciones

**Bonfire aesthetic** (sprint anterior) es mejor para B2B (Panda Estampados):
- Contenido denso: cotizaciones, RFQs, pedidos mayoristas
- Trust signals importantes: testimonios, certificaciones, social proof
- Editorial layout comunica seriedad y profesionalismo
- Decoro Bonfire (squiggles, blobs) humaniza el flujo de B2B

**Regla de decision**:
- Si la vista es B2C cliente → Nike
- Si la vista es B2B admin o B2B cliente (Panda) → Bonfire editorial
- Si la vista es mixta → Bonfire base con acentos Nike en CTAs primarios

---

## 6. Tokens nuevos para el design system Nike

```css
/* nike.css */
:root {
  --nike-white: #FFFFFF;
  --nike-black: #111111;
  --nike-gray-100: #F5F5F5;
  --nike-gray-200: #E5E5E5;
  --nike-gray-400: #757575;
  --nike-gray-600: #333333;
  --nike-volt: #CEFF00;
  --nike-orange: #FA5400;
  
  /* Sombras sutiles */
  --shadow-nike-sm: 0 1px 2px rgba(0,0,0,0.04);
  --shadow-nike-md: 0 2px 8px rgba(0,0,0,0.06);
  --shadow-nike-lg: 0 4px 16px rgba(0,0,0,0.08);
}
```

Esto se integra con el CSS ya existente de `frontend/src/styles/`. Las utilities Tailwind (`bg-white`, `text-black`, etc.) siguen funcionando — los tokens Nike son un layer adicional para casos especificos.

---

## 7. Plan de prompts actualizado

Ver `odd/tasks/atlascommerce-prompts-plan.md` para la version actualizada de la categorizacion de prompts.

Resumen:
- **Prompt home** → IA usa Nike inspiration
- **Prompts admin** → Nike minimalista (vistas más densas que B2C pero con limpieza Nike)
- **Prompt login/signup B2C** → Nike sport aesthetic
- **Prompt login admin** → Bonfire editorial denso (auth requiere claridad sobre estilo)

---

**Version**: v1 (2026-10-14)
**Reemplaza**: nada (convive con `design-references-bonfire.md` que queda como referencia historica)
**Proxima revision**: cuando cambien los tokens Nike o el prompt v2 de la IA externa
