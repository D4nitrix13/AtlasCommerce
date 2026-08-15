# Assets por tienda — Frontend

Logos, social icons y assets de branding servidos por el **frontend**
(Astro) al browser del usuario final.

## Estructura

```txt
stores/
└── <slug>/
    ├── brand/
    │   └── logo.webp       # logo principal del storefront
    ├── social/
    │   ├── facebook.webp   # icono para link a Facebook
    │   └── instagram.webp  # icono para link a Instagram
    ├── banners/.gitkeep    # banners del home (vacío por ahora)
    ├── products/.gitkeep   # imágenes de productos por defecto
    └── amico/
        └── auth/           # ilustraciones para pantallas de auth
            ├── forgot-password-amico.svg
            ├── login-amico.svg
            ├── reset-password-amico.svg
            ├── signup-amico.svg
            ├── success-amico.svg
            └── verified-amico.svg
```

`<slug>` matchea `STORE_BRANDS` en `src/config/stores.ts`:
`panda-estampados`, `kitsune`, `mm-boutique`, `morales-store`.

## Subcarpeta `amico/auth/`

Cada tienda tiene su set completo de ilustraciones auth (estilo Amico de
Storyset) servidas desde `public/` al browser del usuario final. Cada
archivo SVG se replica en cada tienda — el `AuthShell.astro` y los
pages resuelven la ruta dinámicamente según el tenant activo:

```ts
// src/pages/[locale]/admin/auth/forgot-password.astro
illustration={`/images/stores/${Astro.locals.tenant?.tenantSlug ?? "panda-estampados"}/amico/auth/forgot-password-amico.svg`}
```

Las 6 ilustraciones cubren:
- `forgot-password-amico.svg` — pantalla de solicitud de email
- `reset-password-amico.svg` — pantalla de nueva contraseña
- `login-amico.svg` — pantalla de login
- `signup-amico.svg` — pantalla de registro (cuenta cliente)
- `success-amico.svg` — reset / verificación exitosa
- `verified-amico.svg` — cuenta verificada / email confirmado

Detalles y atribuciones de los archivos viven en
`<slug>/amico/auth/README.md`. Los assets de auth suelen venir de
terceros (ej. [Storyset](https://storyset.com)), así que cada uno
mantiene su propio README de créditos.

## ¿Por qué estos archivos están acá y no solo en el backend?

El frontend (Astro) sirve `public/` como raíz estática. Cuando el browser
pide `https://panda-estampados.com/images/stores/panda-estampados/amico/auth/login-amico.svg`,
la respuesta la da **el frontend**, no Laravel. Esto es por diseño:

- Las ilustraciones aparecen en cada página de auth (login, forgot,
  reset, signup). Pedirlas al backend en cada page load sería un
  round-trip a Laravel solo para devolver un archivo estático.
- Astro resuelve los assets en build-time cuando se referencian desde
  componentes. Menos latencia, menos carga sobre el backend.
- En el admin panel se hace lo mismo: los componentes de Astro
  consumen los assets directamente de `public/`.

## Cómo se referencia desde el código

```ts
// src/pages/[locale]/admin/auth/forgot-password.astro
illustration={`/images/stores/${Astro.locals.tenant?.tenantSlug ?? "panda-estampados"}/amico/auth/forgot-password-amico.svg`}
```

`Astro.locals.tenant.tenantSlug` lo setea el middleware `ResolveStoreFromDomain`
en cada request según el `Host` header. El fallback `"panda-estampados"`
asegura una ruta válida cuando el contexto no provee tenant (ej. error pages).

## ¿Por qué entonces también en backend/public/images/stores/?

Para el **envío de emails** y para los endpoints de API. Ver
`backend/public/images/stores/README.md` para esa parte. La regla corta:
si el consumidor final es el browser del usuario → frontend. Si el
consumidor es un cliente de correo, un script externo, o un `<img src>`
absoluto embebido en otro sistema → backend.

