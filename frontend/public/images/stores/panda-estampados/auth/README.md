# Auth illustrations — Frontend

Ilustraciones estáticas servidas por el frontend (Astro) y consumidas
por las páginas de autenticación del admin panel
(`/[locale]/admin/auth/*`).

## Estructura

```txt
auth/
├── <slug>/
│   └── <nombre>-illustration.svg   # ilustración para una pantalla de auth
└── README.md (este archivo)
```

`<slug>` matchea `STORE_BRANDS` en `src/config/stores.ts`
(`panda-estampados`, `kitsune`, `mm-boutique`, `morales-store`). Si la
ilustración es compartida entre tiendas, vive directamente en `auth/`.

## Assets actuales

| Archivo                                              | Pantalla                  | Tenant         |
| ---------------------------------------------------- | ------------------------- | -------------- |
| `panda-estampados/forgot-password-illustration.svg`  | `forgot-password.astro`   | panda-estampados |

## Créditos y licencias

Las ilustraciones de este directorio son assets de terceros adaptados
al branding de cada tienda. Cada archivo debe mantener el crédito a su
fuente original:

- **`forgot-password-illustration.svg`** — adaptado de
  [Storyset](https://storyset.com) (Freepik). El archivo SVG conserva
  la licencia original de Storyset/Freepik y se incluye attribution
  aquí como referencia. Al reemplazar el SVG por un GIF u otro formato
  provisto por Storyset, mantener el crédito a
  [storyset.com](https://storyset.com).