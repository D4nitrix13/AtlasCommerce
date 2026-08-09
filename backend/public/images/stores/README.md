# Assets por tienda — Backend

Logos, social icons y assets de branding servidos por el **backend**
(Laravel) para uso en emails, API REST y consumidores externos.

## Estructura

```txt
stores/
├── <slug>/
│   ├── brand/
│   │   └── logo.webp       # logo principal (referenciado por email layout y API)
│   ├── social/
│   │   ├── facebook.webp   # icono para link a Facebook (en email footer)
│   │   └── instagram.webp  # icono para link a Instagram (en email footer)
│   ├── banners/.gitkeep
│   └── products/.gitkeep
└── README.md (este archivo)
```

`<slug>` matchea `docker/instances/<instancia>` y los directorios en
`frontend/public/images/stores/`: `panda-estampados`, `kitsune`,
`mm-boutique`, `morales-store`.

## Uso concreto: emails

El layout base de los emails transaccionales vive en
`backend/resources/views/components/email/layout.blade.php`. En su
header se renderiza el logo de la tienda con:

```php
'logoUrl' => asset(
    path: (string) config(
        key: 'brand.default_logo_path',
        default: 'images/shared/brand/app-logo.webp',
    ),
)
```

Y los social icons del footer usan la misma mecánica:

```php
'facebookIconUrl'  => asset(config('brand.social.facebook_icon_path')),
'instagramIconUrl' => asset(config('brand.social.instagram_icon_path')),
```

`asset()` resuelve a `APP_URL + path relativo a public/`. El `<img>` que
termina en el HTML del email queda como
`https://panda-estampados.com/images/shared/brand/app-logo.webp`.

**¿Por qué el backend y no el frontend?** Los clientes de correo
(Gmail, Outlook, Apple Mail) son consumidores externos: hacen una
request HTTP directa a la URL del `<img>`. Si esa URL apunta al
frontend, el browser no interviene y el cliente de correo necesita que
el endpoint responda sin cookies de sesión, sin JS, sin HTML — solo el
binario. El frontend de Astro, en cambio, está pensado para servir
HTML al browser del usuario y agrega headers que pueden romper
algunos clientes de correo (CORS, CSP, MIME type negotiation). El
backend de Laravel sirve `public/` como archivos estáticos planos,
que es lo que esperan los `<img>` embebidos.

Mismo patrón documentado en `app/Notifications/README.md` como
guía de buenas prácticas.

## Uso concreto: API REST

`StoreTheme` tiene una columna `logo_path` (path relativo a `public/`).
Cuando el backend responde `/api/storefront/me` o
`/api/storefront/theme`, devuelve ese path tal cual. El consumidor
(frontend, app mobile, integración externa) lo compone con su propio
base URL. Tener los archivos en `public/` del backend garantiza que
si el consumidor decide usar `APP_URL` como base, la URL resuelve.

## Fallbacks (cuando la tienda no tiene logo propio)

`backend/config/brand.php` define defaults que viven en
`backend/public/images/shared/`:

```php
'default_logo_path'        => 'images/shared/brand/app-logo.webp',
'default_cover_image_path' => 'images/shared/placeholders/store-cover-placeholder.webp',
'social.facebook_icon_path'  => 'images/shared/icons/social/facebook.webp',
'social.instagram_icon_path' => 'images/shared/icons/social/instagram.webp',
```

Estos archivos **compartidos** (no por tienda) viven en `shared/` y se
usan cuando `StoreTheme->logo_path` es `null` o cuando la tienda no
tiene logo propio configurado.

## Convenciones

- **Formato:** `.webp` para todo (logos, social icons, placeholders).
  Soporta transparencia, es ~30% más liviano que PNG y es universal.
- **Tamaño del logo:** idealmente cuadrado, mínimo 256x256, máximo 512x512.
  El email layout lo renderiza a 96x96, pero tener el original en
  mayor resolución permite reuso en OG images, banners, etc.
- **NUNCA hardcodear la URL completa** en código. Siempre
  `asset(config('brand.logo_path'))` para que el path sea portable
  entre dev / staging / producción.

## ¿Por qué también en frontend/public/images/stores/?

Para el **browser del usuario final** en el storefront público. Ver
[`frontend/public/images/stores/README.md`](../../../../../frontend/public/images/stores/README.md)
para esa parte. Regla corta: si el consumidor final es el browser del
usuario → frontend. Si el consumidor es un cliente de correo, un script
externo, o un `<img src>` absoluto embebido en otro sistema → backend.
