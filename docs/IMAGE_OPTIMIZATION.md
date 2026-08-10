# Optimización de imágenes

Decisiones, herramientas y pipeline para que el frontend cargue
rápido sin sacrificar calidad visual.

## TL;DR

- **Ilustraciones flat** (Storyset, iconos, logos vectoriales) → **SVG**
  optimizado con [SVGO](https://github.com/svg/svgo). Escala a cualquier
  resolución, archivo chico, soporta CSS y themes.
- **Fotos** (productos, banners, screenshots) → **WebP** con
  `cwebp -q 80` (calidad perceptible vs PNG ~25-30% menos peso).
- **Logos con transparencia** (raster) → **WebP** lossless
  (`cwebp -z 9`) o **PNG** con `oxipng` si WebP no aplica.
- **NO conviertas SVG a WebP.** WebP es un formato raster; pierde la
  propiedad vectorial. Para una ilustración de 500x500 px que se ve a
  1000x1000 px, SVG renderiza con líneas crisp; WebP rasteriza y pixela.

## Por qué NO WebP para las ilustraciones

WebP es ideal para **fotos** (muchos colores, gradientes suaves, fotos
de productos). Para **ilustraciones flat** (vectoriales, pocos colores,
líneas limpias) WebP es peor porque:

| Métrica                | SVG (vector)                      | WebP (raster)                              |
| ---------------------- | --------------------------------- | ------------------------------------------ |
| Tamaño archivo         | ~10-30 KB                         | ~30-100 KB (a 2x resolution)               |
| Resolución             | Infinita (sharp a cualquier zoom) | Fija (pixelado al escalar)                 |
| Modificable en runtime | Sí (`currentColor`, CSS)          | No                                         |
| Costo de re-tamaños    | Cero (re-render)                  | Vuelves a exportar o generas @1x, @2x, @3x |
| Animaciones            | Sí (SMIL, CSS)                    | No                                         |

Las ilustraciones `forgot-password-amico.svg`, `login-amico.svg`,
`signup-amico.svg` pesan **~20-95 KB cada una** sin optimizar y **~20-85 KB**
optimizadas con SVGO. Convertir a WebP a 1x resolution (500x500) ocuparía
~30-50 KB y a 2x (1000x1000) ~80-150 KB, todo rasterizado.

## Pipeline de optimización

### Scripts (en `frontend/package.json`)

```json
{
  "scripts": {
    "optimize:svgs": "svgo --multipass public/images/**/*.svg",
    "prebuild": "pnpm run optimize:svgs"
  }
}
```

- **`optimize:svgs`**: corre SVGO con `--multipass` (estable: el output
  ya no se puede comprimir más con otra pasada) sobre todos los SVGs
  bajo `public/images/`. **Idempotente** — si no hay cambios, la salida
  es 0% diferente.
- **`prebuild`**: se ejecuta automáticamente antes de `astro build`
  (Astro respeta los scripts npm de `prebuild`). Garantiza que el
  bundle de producción siempre incluye los SVGs optimizados.

### Cómo aplicar a un SVG nuevo

```bash
# 1. Drop el SVG en frontend/public/images/{path}/archivo.svg
cp ~/Downloads/nuevo-icono.svg frontend/public/images/auth/

# 2. Optimiza manualmente
pnpm --filter frontend optimize:svgs

# 3. Verifica el cambio
git diff frontend/public/images/auth/

# Si está OK, commiteá junto al componente que lo usa.
```

SVGO en este proyecto aplica los siguientes presets de optimización:

- `removeViewBox` (false) — preserva el viewBox para que el SVG escale
- `removeMetadata` (true) — quita `<metadata>`, comentarios, generator info
- `removeComments` (true)
- `removeEmptyAttrs` (true)
- `removeEmptyContainers` (true)
- `removeUnusedNS` (true) — quita namespaces no usados
- `cleanupNumericValues` (true) — `0.50000` → `0.5`
- `convertColors` (true) — `#ff0000` → `red` cuando es más corto
- `removeUselessDefs` (true)
- `removeUnknownsAndDefaults` (true) — atributos con valor default
- `removeUselessStrokeAndFill` (true)
- `cleanupAttrs` (true) — mergea atributos
- `mergePaths` (true) — une paths duplicados
- `convertPathData` (true) — paths más compactos

El resultado: 10-15% de reducción promedio sin cambio visual. Los SVGs de
Storyset ya vienen optimizados razonablemente, así que el ahorro es menor
que el típico 30-50% que se ve en SVGs hechos a mano.

## Cuándo usar cada formato

| Contenido                                 | Formato                     | Optimizador                            | Comando                         |
| ----------------------------------------- | --------------------------- | -------------------------------------- | ------------------------------- |
| **Ilustraciones flat** (Storyset, iconos) | **SVG**                     | SVGO                                   | `pnpm run optimize:svgs` (auto) |
| **Iconos UI** (botones, controles)        | **SVG** o **Lucide React**  | SVGO o tree-shaking de lucide-react    | Ya manejado por Vite            |
| **Logos con transparencia**               | **SVG** o **WebP lossless** | SVGO o `cwebp -z 9`                    | Manual                          |
| **Fotos de productos**                    | **WebP q=80**               | `cwebp -q 80 input.jpg -o output.webp` | Manual al subir asset           |
| **Screenshots**                           | **WebP q=85**               | `cwebp -q 85`                          | Manual                          |
| **Banners marketing**                     | **WebP** o AVIF moderno     | `cwebp` o `avifenc`                    | Manual                          |

## Estructura de assets

```
frontend/public/images/
├── auth/                    # Ilustraciones globales del design system
│   ├── login-amico.svg
│   ├── signup-amico.svg
│   └── forgot-password-amico.svg
├── stores/                  # Assets específicos por tienda
│   ├── README.md           # Jerarquía de seeds por tenant
│   ├── panda-estampados/
│   │   ├── brand/
│   │   │   ├── favicon.webp   ← ya WebP
│   │   │   └── logo.webp      ← ya WebP
│   │   └── auth/             # overrides por tenant (vector)
│   ├── mm-boutique/
│   └── morals-store/
└── README.md               # Este archivo
```

**Precedencia de assets de auth**:

1. `frontend/public/images/stores/{slug}/auth/*` — override por tienda
2. `frontend/public/images/auth/*` — fallback global
3. Imagen hardcoded en el componente — última instancia (no recomendado)

## Verificación manual

Para ver el ahorro acumulado después de un cambio:

```bash
# Antes
cd frontend
du -sh public/images/

# Optimizar
pnpm run optimize:svgs

# Después
du -sh public/images/
```

Para confirmar que el sitio sigue renderizando igual después de la
optimización:

```bash
# 1. Build de producción
cd frontend
pnpm run build

# 2. Verificá visualmente con Playwright
# 3. Diff de bytes de la carpeta dist/ antes y después
```

## Métricas reales (este proyecto)

SVGs en `frontend/public/images/auth/` (Storyset Amico, 3 archivos):

| Archivo                     | Sin optimizar | Optimizado (SVGO) | Ahorro             |
| --------------------------- | ------------- | ----------------- | ------------------ |
| `forgot-password-amico.svg` | 22.5 KB       | 20.2 KB           | 10.5%              |
| `login-amico.svg`           | 26.9 KB       | 23.5 KB           | 12.4%              |
| `signup-amico.svg`          | 97.5 KB       | 84.8 KB           | 13.0%              |
| **Total**                   | **146.9 KB**  | **128.5 KB**      | **~18 KB (12.5%)** |

La reducción no es dramática porque los SVGs de Storyset ya están bien
optimizados. El principal ahorro está en la **compresión gzip en
tránsito** que hace Caddy/Astro automáticamente (típicamente 70% adicional).

## Por qué NO usar vite-plugin-svgo

Estuve a punto de integrar `vite-plugin-svgo` como plugin de Vite, pero
el paquete en su versión 3.0.0 está **mal armado** (`exports/import.mjs`
importa `../dist/index.js` que no existe). Además, el plugin de Vite
solo optimiza SVGs que se **importan** en código JS/TS/Astro como módulos
(`import svg from './foo.svg?raw'` o similar). En este proyecto los SVGs
se referencian como URLs (`<img src="/images/auth/login-amico.svg" />`),
no como imports. El script CLI cubre el 100% del caso de uso sin
acoplar el build.

Si en el futuro aparecen SVGs importados vía JS (ej. un logo SVG
inlineable como componente React), evaluá `@unplugin/svgo` u otra
alternativa. Mientras tanto, el CLI es la opción más simple y robusta.

## Referencias

- [SVGO docs](https://github.com/svg/svgo) — preset `default` y opciones
- [web.dev: Choose the right image format](https://web.dev/articles/choose-the-right-image-format/) — guía de Google para elegir formato
- [MDN: SVG](https://developer.mozilla.org/en-US/docs/Web/SVG) — referencia
- [cwebp CLI](https://developers.google.com/speed/webp/docs/cwebp) — para convertir fotos a WebP
- [Storyset Amico style](https://storyset.com/amico) — origen de las ilustraciones del design system
