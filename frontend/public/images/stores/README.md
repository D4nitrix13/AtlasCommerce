# Assets por tienda — Frontend

Logos, social icons y assets de branding servidos por el **frontend**
(Astro) al browser del usuario final.

## Estructura

```txt
stores/
├── <slug>/
│   ├── brand/
│   │   └── logo.webp       # logo principal del storefront
│   ├── social/
│   │   ├── facebook.webp   # icono para link a Facebook
│   │   └── instagram.webp  # icono para link a Instagram
│   ├── banners/.gitkeep    # banners del home (vacío por ahora)
│   ├── products/.gitkeep   # imágenes de productos por defecto
│   └── auth/               # ilustraciones para pantallas de auth
└── README.md (este archivo)
```

`<slug>` matchea `STORE_BRANDS` en `src/config/stores.ts`:
`panda-estampados`, `kitsune`, `mm-boutique`, `morales-store`.

## ¿Por qué estos archivos están acá y no solo en el backend?

El frontend (Astro) sirve `public/` como raíz estática. Cuando el browser
pide `https://panda-estampados.com/images/stores/panda-estampados/brand/logo.webp`,
la respuesta la da **el frontend**, no Laravel. Esto es por diseño:

- El logo aparece en cada página del storefront público (header,
  footer, favicon, OG image). Pedirlo al backend en cada page load
  sería un round-trip a Laravel solo para devolver un archivo estático.
- Astro resuelve los assets en build-time cuando se referencian desde
  componentes. Menos latencia, menos carga sobre el backend.
- En el admin panel se hace lo mismo: los componentes de Astro
  consumen los assets directamente de `public/`.

## Cómo se referencia desde el código

```ts
// frontend/packages/themes/<slug>/src/index.ts
export const <slug>Theme = {
  brand: {
    logoPath: '/images/stores/<slug>/brand/logo.webp',
    loginLogoPath: '/images/stores/<slug>/brand/logo.webp',
    // ...
  },
};
```

Todos los themes apuntan a `.webp`. Si en el futuro se generan versiones
`.svg` optimizadas (para favicons, OG images que escalen en retina, o
casos que necesiten `currentColor`), se agregan como segundo `logoPath`
opcional y el componente consumidor decide cuál servir según el
contexto.

## Subcarpeta `auth/`

Cada tienda puede tener ilustraciones propias para las pantallas de
autenticación del admin (`/admin/auth/login`, `forgot-password`,
`reset-password`, etc.). Conviven con los assets de marca porque
también se sirven estáticamente desde el frontend.

Detalles y atribuciones de los archivos viven en
[`<slug>/auth/README.md`](panda-estampados/auth/README.md). Los
assets de auth suelen venir de terceros (ej. [Storyset](https://storyset.com)),
así que cada uno mantiene su propio README de créditos.

## ¿Por qué entonces también en backend/public/images/stores/?

Para el **envío de emails** y para los endpoints de API. Ver
[`backend/public/images/stores/README.md`](../../../../../backend/public/images/stores/README.md)
para esa parte. La regla corta: si el consumidor final es el browser del
usuario → frontend. Si el consumidor es un cliente de correo, un script
externo, o un `<img src>` absoluto embebido en otro sistema → backend.
