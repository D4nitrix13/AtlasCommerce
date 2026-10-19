# Gibson Reference Images - Local Archive

Reference photos for Gibson-family products: guitars, basses, amplifiers,
pedals, strings, capos, cases, apparel, parts. Includes Gibson, Gibson Custom,
Epiphone, Kramer, Maestro and Mesa/Boogie brands. Downloaded from `gibson.com`
at the highest CDN width available (1680 px). Every product folder has a
`product.json` with price, SKU, weight, barcode, description and specs.

> This directory is git-ignored. Contents are reference material only -
> do not commit the binary assets.

## Estructura

```
gibson-images/
  guitars/<brand>/<model>/<finish>[/]<NN>-<role>.png + product.json
  basses/<brand>/<model>/<finish>[/]<NN>-<role>.png + product.json
  amps/{heads,combos}/<brand>/<finish>/<NN>-<role>.png + product.json
  pedals/<brand>/<finish>/<NN>-<role>.png + product.json
  strings/{acoustic,electric,bass}/<brand>/<product>/<NN>-<role>.png + product.json
  capos/<brand>/<product>/<NN>-<role>.png + product.json
  cases/{hard,soft}/<brand>/<product>/<NN>-<role>.png + product.json
  apparel/{headwear,shirts,sweatshirts}/<brand>/<product>/<NN>-<role>.png + product.json
  parts/{pickups,covers,tailpieces}/<brand>/<product>/<NN>-<role>.png + product.json
```

## Resumen

| Categoria | Productos | Imagenes | Tamano |
|---|---:|---:|---:|
| `guitars/` | 199 | 1679 | 2970 MB |
| `basses/` | 17 | 135 | 198 MB |
| `amps/` | 35 | 155 | 568 MB |
| `pedals/` | 16 | 83 | 143 MB |
| `strings/` | 33 | 132 | 241 MB |
| `capos/` | 10 | 20 | 17 MB |
| `cases/` | 37 | 173 | 362 MB |
| `apparel/` | 39 | 80 | 154 MB |
| `parts/` | 24 | 93 | 96 MB |
| **TOTAL** | **410** | **2550** | **4749 MB** |

## Schema

Cada `product.json` tiene este esquema:

```json
{
  "title": "Gibson Explorer 80s, Cherry",
  "vendor": "Gibson",
  "product_type": "Electric Guitar",
  "sku": "DSXE00CHCH1",
  "handle": "gibson-explorer-80s-cherry",
  "url": "https://www.gibson.com/products/gibson-explorer-80s-cherry",
  "price": { "regular": 2499.0, "currency": "USD", "compare_at": null },
  "weight_g": 10433,
  "barcode": "711106176147",
  "available": true,
  "long_description": {
    "title": "Embraced by the masters of metal and rock",
    "body": "When the Explorer was first introduced in 1958..."
  },
  "specifications": {
    "Body Specs": { "Body Finish": "...", "Body Material": "Mahogany" },
    "Neck Specs": { ... },
    "Electronics Specs": { ... },
    "Hardware Specs": { ... },
    "Misc Specs": { ... }
  },
  "metadata_status": "full" | "partial" | "minimal",
  "images_count": 8,
  "fetched_at": "2026-10-19T03:00:00+00:00"
}
```

### `metadata_status`

- **full**: tiene specifications completas + long_description + precio + SKU (guitarras y bajos con spec accordion)
- **partial**: tiene long_description + precio + SKU pero sin specs accordion (amps, pedales, cuerdas, etc.)
- **minimal**: solo los campos basicos (raro, por si el fetch fallo)

## Convenciones de archivos de imagen

| Archivo | Que se ve |
|---|---|
| `01-body.png` | Cuerpo visto de frente |
| `02-backbody.png` | Cuerpo visto de atras |
| `03-front.png` | Vista frontal completa |
| `04-back.png` | Vista trasera completa |
| `05-headstock.png` | Clavijero / pala |
| `06-back-headstock.png` | Clavijero de atras |
| `07-pickup.png` | Pastilla humbucker |
| `08-knobs.png` | Perillas de control |
| `09-side.png` | Vista lateral |
| `10-beauty.png` | Toma estetica adicional |
| `11-case.png` | Estuche / hardshell case |
| `12-binding.png` | Binding / fileteado |
| `13-fingerboard.png` | Diapason |
| `14-finish-back.png` | Acabado parte trasera |
| `15-finish-peghead.png` | Acabado de la pala |
| `16-finish-sides.png` | Acabado costados |
| `17-finish-top.png` | Acabado de la tapa |
| `18-pickups.png` | Set completo de pastillas |
| `19-sn-label.png` | Etiqueta con numero de serie |
| `20-tailpiece.png` | Stop tailpiece |

Los productos sin accordion de specs (cuerdas, fundas, playeras) suelen tener solo 2-6 imagenes.

## Re-descargar una imagen

```bash
curl -sSL "https://www.gibson.com/cdn/shop/files/<file>?v=<version>&width=1680" \
  -o <categoria>/<brand>/<finish>/<NN>-<descriptive>.png
```

## Fuentes

- Las imagenes se sirven desde `www.gibson.com/cdn/shop/files/`
- Los JSONs con metadata y `images` por producto se generan con los scripts en `/tmp/opencode/` (no en el repo)