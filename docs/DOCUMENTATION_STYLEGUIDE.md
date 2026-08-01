# Guía de Estilo de Documentación

Reglas de formato Markdown para toda la documentación del proyecto
Panda Estampados y Kitsune.

## Encabezados

- Un solo `#` (H1) por archivo, usado como título principal.
- Usar `##` para secciones principales.
- Usar `###` para subsecciones.
- No saltar niveles de encabezado (`##` → `####`).
- Los encabezados deben ser únicos dentro del mismo archivo.
  En ADRs, prefijar con el identificador:

  ```txt
  ### ADR-001 - Contexto
  ### ADR-001 - Decisión
  ### ADR-001 - Consecuencias
  ```

## Longitud de línea

- **Máximo recomendado: 80 caracteres por línea.**
- Dividir párrafos largos manualmente.
- Los elementos de lista también deben respetar 80 caracteres.
- Las tablas Markdown que generen líneas超过 80 caracteres
  deben convertirse en listas o subsecciones.

Incorrecto:

```txt
El sistema cuenta con una arquitectura que permite administrar
varias tiendas desde una sola plataforma centralizada.
```

Correcto:

```txt
El sistema administra varias tiendas desde una sola plataforma
centralizada.
```

## Bloques de código

- Todos los bloques de código deben indicar lenguaje.
- Usar lenguajes adecuados:

  | Tipo         | Lenguaje     |
  | ------------ | ------------ |
  | Texto plano  | `txt`        |
  | Comandos     | `bash`       |
  | PHP          | `php`        |
  | TypeScript   | `ts`         |
  | Astro        | `astro`      |
  | JSON         | `json`       |
  | Variables    | `env`        |
  | YAML         | `yaml`       |
  | SQL          | `sql`        |
  | Mermaid      | `mermaid`    |
  | Dockerfile   | `dockerfile` |
  | Caddyfile    | `caddyfile`  |

Incorrecto:

````md
```
contenido
```
````

Correcto:

````md
```txt
contenido
```
````

## Enlaces internos

- Los enlaces a archivos deben incluir título.
- Si el enlace supera 80 caracteres, usar enlace por referencia.

Incorrecto:

```md
[architecture/overview.md](architecture/overview.md)
```

Correcto:

```md
[architecture/overview.md](architecture/overview.md "architecture/overview.md")
```

Enlace por referencia:

```md
Consulta la [guía de estilo][styleguide].

[styleguide]: DOCUMENTATION_STYLEGUIDE.md
  "DOCUMENTATION_STYLEGUIDE.md"
```

## Formato de texto

- Usar `**negrita**` para conceptos importantes.
- Usar `*cursiva*` solo para énfasis suave.
- Usar `` `código` `` para nombres técnicos, archivos,
  rutas, endpoints, variables y comandos.
- No abusar de negritas ni cursivas.

Nombres técnicos que usan `código`:

- Frameworks: `Laravel`, `Astro`, `PostgreSQL`, `Caddy`
- Entidades: `Store`, `ProductStore`, `UserStore`
- Campos: `store_id`, `enabled`, `active`
- Variables: `PUBLIC_API_URL`, `APP_ENV`
- Rutas: `/api/products`, `/admin/orders`
- Archivos: `docs/frontend/storefront.md`

## Tablas

- Evitar tablas con líneas超过 80 caracteres.
- Si una tabla es demasiado ancha, convertirla en lista
  o subsecciones.
- Usar tablas solo cuando aportan claridad.

## Documentación de funcionalidades

- No presentar funcionalidades pendientes como implementadas.
- Usar etiquetas claras:

  | Etiqueta | Uso |
  | --- | --- |
  | Implementado | Función completa y operativa |
  | Pendiente | Aún no implementado |
  | Recomendado | Sugerencia arquitectónica |
  | Fase futura | Planeado para otra iteración |
  | Debe verificarse en el código | Requiere revisión |

## Redacción

- Evitar frases repetitivas o genéricas:

  - "En la actualidad"
  - "Cabe destacar"
  - "Es importante mencionar"
  - "Asimismo"
  - "Por consiguiente"

- Ser concreto y técnico.
- No escribir párrafos vacíos ni genéricos.
- Documentación en español, nombres técnicos en inglés.

## Multi-tienda

- La documentación describe el sistema como multi-tienda.
- Las entidades `store_id` se documentan como parte del modelo.
- Los dominios, tiendas y relaciones multi-tienda
  se documentan en `docs/architecture/multi-store.md`.
- El módulo Stores se documenta en
  `docs/backend/stores/stores-module.md`.

## Estructura de archivos

```txt
docs/
├── README.md
├── DOCUMENTATION_STYLEGUIDE.md
├── architecture/
├── backend/
├── database/
├── frontend/
└── operations/
```

Cada subdirectorio debe tener un `README.md` como índice.
