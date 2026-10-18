# Tenant: instrumentos-musicales-la-voz

> **Estado:** Pendiente. Esta carpeta está creada para que el equipo de
> merchandising suba los archivos de branding (logo + favicon) cuando
> estén listos. Los archivos esperados son:
>
> - `logo.webp` (logo principal — formato horizontal, recomendado 400×120)
> - `favicon.webp` (favicon del browser — cuadrado 64×64 mínimo)
> - `og-image.webp` (Open Graph para redes sociales — 1200×630)
> - `cover.webp` (hero del home — 1920×1080 o superior)
>
> Después de subir los archivos, el Artisan command
> `php artisan assets:sync-from-public --store=instrumentos-musicales-la-voz`
> los sube al FTP bajo la convención `tenants/instrumentos-musicales-la-voz/branding/`.

Este es el primer cliente real del SaaS (La Voz Instrumentos, tienda
física de instrumentos musicales en Nicaragua). El theme de frontend
ya existe en `frontend/packages/themes/instrumentos-musicales-la-voz/`
y está integrado en `frontend/src/config/stores.ts`. Falta solo la
carpeta de assets físicos.
