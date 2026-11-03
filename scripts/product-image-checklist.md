# Checklist de imágenes a descargar — Panda Estampados y Kitsune

Cada producto necesita **2 fotos mínimo** (front + back). Opcionales
hasta 4 (`side`, `beauty`) si las conseguís.

## Dónde guardar cada foto

Creá esta estructura de carpetas y guardá las fotos así:

```
incoming/{tenant}/{marca}/{producto}/{variante}/{rol}.jpg
```

- **rol**: `front` (obligatoria) · `back` (obligatoria) · `side` · `beauty`
- Extensión: `.jpg` o `.png` (yo las convierto a WebP)
- Nombre exacto del archivo: `front.jpg`, `back.jpg` (sin más texto)

Ejemplo:

```
incoming/panda-estampados/panda/polo-pique/negro/front.jpg
incoming/panda-estampados/panda/polo-pique/negro/back.jpg
incoming/panda-estampados/panda/polo-pique/crema/front.jpg
incoming/panda-estampados/panda/polo-pique/crema/back.jpg
```

Cuando termines: `SEED=true ./scripts/ingest-product-images.sh`
(ordenar + convertir + hashear + subir al FTP).

---

## PANDA ESTAMPADOS — 20 piezas

Rutas bajo `incoming/panda-estampados/panda/{producto}/{variante}/`

### Textil (8)

| # | Producto | Qué buscar en internet | Variantes (carpetas) | Fotos |
|---|---|---|---|---|
| 1 | `polo-pique` | "polo shirt blank", "piqué polo product photo" | `negro`, `crema`, `marino`, `blanco` | front + back |
| 2 | `playera-manga-corta` | "blank t-shirt product photo", "cotton tee mockup" | `negro`, `blanco`, `rojo`, `azul` | front + back |
| 3 | `playera-manga-larga` | "long sleeve blank shirt" | `negro`, `blanco`, `gris` | front + back |
| 4 | `hoodie` | "blank hoodie product photo" | `marino`, `gris`, `negro` | front + back |
| 5 | `sudadera` | "crewneck sweatshirt blank" | `gris`, `negro` | front + back |
| 6 | `camisa-oxford` | "oxford shirt blank product" | `blanco`, `celeste` | front + back |
| 7 | `chaqueta-ligera` | "light windbreaker jacket product" | `negro`, `caqui` | front + back |
| 8 | `gorra-trucker` | "trucker cap blank product photo" | `negro`, `marino` | front + back |

### Drinkware (4)

| # | Producto | Qué buscar | Variantes | Fotos |
|---|---|---|---|---|
| 9 | `taza-11-oz` | "white ceramic mug product photo", "11 oz coffee mug" | `blanco`, `negro` | front + back |
| 10 | `taza-15-oz` | "large ceramic mug 15 oz" | `blanco` | front + back |
| 11 | `termo-500` | "insulated water bottle product photo", "stainless thermos" | `negro`, `acero` | front + back |
| 12 | `vaso-termico` | "travel mug tumbler product photo" | `negro`, `blanco` | front + back |

### Accesorios (8)

| # | Producto | Qué buscar | Variantes | Fotos |
|---|---|---|---|---|
| 13 | `tote-bag` | "canvas tote bag product photo" | `crudo`, `negro` | front + back |
| 14 | `boligrafo` | "ballpoint pen product photo" | `negro`, `azul` | front + back |
| 15 | `libreta` | "notebook journal product photo" | `kraft`, `negro` | front + back |
| 16 | `mousepad` | "mouse pad product photo" | `negro` | front + back |
| 17 | `llavero` | "metal keychain product photo" | `acero`, `negro` | front + back |
| 18 | `funda-celular` | "phone case product photo" | `negro`, `transparente` | front + back |
| 19 | `delantal` | "apron product photo" | `negro`, `crudo` | front + back |
| 20 | `portafolio` | "portfolio folder product photo" | `negro` | front + back |

---

## KITSUNE — 28 productos

Rutas bajo `incoming/kitsune/{marca}/{producto}/{variante}/`

### Figuras (10)

| # | Producto | Marca (carpeta) | Qué buscar | Variantes | Fotos |
|---|---|---|---|---|---|
| 1 | `figura-escala-1-7` | `good-smile` | "1/7 scale anime figure product photo" | `original`, `variant` | front + back |
| 2 | `nendoroid` | `good-smile` | "nendoroid product photo" | `original` | front + back |
| 3 | `figma` | `max-factory` | "figma action figure product photo" | `original` | front + back |
| 4 | `prize-figure` | `bandai` | "prize figure product photo" | `original` | front + back |
| 5 | `statue-gk` | `kotobukiya` | "garage kit statue product photo" | `original` | front + back |
| 6 | `funko-pop` | `funko` | "funko pop product photo" | `original` | front + back |
| 7 | `figura-chibi` | `good-smile` | "chibi figure product photo" | `original` | front + back |
| 8 | `figura-articulada` | `bandai` | "articulated figure product photo" | `original` | front + back |
| 9 | `diorama` | `kotobukiya` | "figure diorama display base" | `original` | front + back |
| 10 | `figura-economica` | `sega` | "small anime figure product photo" | `original` | front + back |

### Manga (6)

| # | Producto | Marca | Qué buscar | Variantes | Fotos |
|---|---|---|---|---|---|
| 11 | `mango-tomo` | `shueisha` | "manga volume book product photo" | `original` | front + back |
| 12 | `manga-2-en-1` | `shueisha` | "manga omnibus product photo" | `original` | front + back |
| 13 | `manga-box-set` | `shueisha` | "manga box set product photo" | `original` | front + back |
| 14 | `light-novel` | `kodansha` | "light novel book product photo" | `original` | front + back |
| 15 | `artbook` | `shueisha` | "anime artbook product photo" | `original` | front + back |
| 16 | `guia-oficial` | `kodansha` | "official guide book product photo" | `original` | front + back |

### Accesorios (12)

| # | Producto | Marca | Qué buscar | Variantes | Fotos |
|---|---|---|---|---|---|
| 17 | `llavero-acrylic` | `kitsune` | "acrylic keychain product photo" | `original` | front + back |
| 18 | `standee-acrylic` | `kitsune` | "acrylic stand figure product photo" | `original` | front + back |
| 19 | `poster` | `kitsune` | "anime poster product photo" | `original` | front + back |
| 20 | `badge-pin` | `kitsune` | "enamel pin button badge product" | `original` | front + back |
| 21 | `playera-anime` | `kitsune` | "graphic anime t-shirt product photo" | `negro`, `blanco` | front + back |
| 22 | `hoodie-otaku` | `kitsune` | "graphic hoodie product photo" | `negro`, `gris` | front + back |
| 23 | `taza-tematica` | `kitsune` | "printed ceramic mug product photo" | `blanco`, `negro` | front + back |
| 24 | `mochila` | `kitsune` | "backpack product photo" | `negro`, `azul` | front + back |
| 25 | `mousepad-tematico` | `kitsune` | "desk mat mouse pad product" | `negro` | front + back |
| 26 | `sticker-pack` | `kitsune` | "vinyl sticker pack product photo" | `original` | front + back |
| 27 | `body-pillow` | `kitsune` | "body pillow product photo" | `original` | front + back |
| 28 | `washi-tape` | `kitsune` | "washi tape set product photo" | `original` | front + back |

---

## Resumen de cantidad

| Tienda | Productos | Variantes | Fotos mínimas (front+back) |
|---|---|---|---|
| Panda Estampados | 20 | 45 | 90 |
| Kitsune | 28 | 31 | 62 |
| **Total** | **48** | **76** | **152** |

## Recomendaciones de foto

- **Fondo blanco o neutro** — se ve mucho mejor en la grilla.
- **Producto centrado**, sin manos ni texto de marca ajena.
- **Cuanto más alta la resolución mejor** (mínimo 800×800; yo genero 1600/1200/800/400).
- `back` = el mismo producto visto por detrás (o el reverso). Es la que
  aparece al pasar el cursor por encima de la card.
