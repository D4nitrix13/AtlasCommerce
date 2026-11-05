# Footer full-width, redes sociales desde BD y páginas legales

> **Rama**: `feat/storefront-footer-legal` · **Estado**: en progreso
> **Relación**: trabajo previo a Sesión 1 (Home) de
> [`vistas-cliente-sessions.md`](vistas-cliente-sessions.md) — header/footer
> viven en `layouts/StorefrontLayout.astro`, compartidos por todas las vistas.
> **TDD**: estricto (config de sesión `gentle-ai:strict-tdd-mode`). Backend:
> tests primero (PHPUnit). Frontend Astro estático: checks funcionales
> (`bun run test`, `astro check`, `build`) + capturas playwright.
> **Engram mirror** (`odd/storefront-footer-legal/tasks`): pendiente
> (Engram no disponible en esta sesión).

## Objetivo

Footer que aproveche el ancho completo de la pantalla, con redes sociales
reutilizables y configurables desde el panel admin (BD), más las 5 vistas
legales/informativas que hoy apuntan a `#`.

## Problema

1. El footer usa `max-w-[1280px]` centrado: en pantallas anchas se desperdicia
   espacio a los costados. El header ya es full-bleed — el footer no acompaña.
2. Los iconos sociales están hardcodeados (3 variantes, sin href) y no salen de
   la BD, pese a que `store_social_links` + CRUD admin (RF-STOR-133) ya existen.
3. `SocialIcon.astro` no cubre `whatsapp` ni las otras plataformas del enum de
   BD. El path de Facebook está duplicado en el login (`SocialIcons.tsx`, React).
4. Las 5 páginas legales (Acerca de, Términos, Privacidad, Ayuda, Contacto)
   no existen: los links del footer son `#`.

## Alcance autorizado (confirmado por el usuario 2026-11-05)

- Footer full-width + header en coherencia (solo lo necesario).
- `SocialIcon` escalable a las 8 plataformas del enum de BD; paths SVG
  compartidos para eliminar duplicación con el login.
- Redes sociales desde BD (`is_active` + `sort_order` + `url`) con fallback
  decorativo sin link para Facebook, Instagram, TikTok, WhatsApp.
- 5 páginas legales con contenido según legislación **Argentina**
  (Ley 24.240 Defensa del Consumidor + Ley 25.326 Protección de Datos
  Personales + cookies).
- i18n es/en de todo lo anterior.

## Fuera de scope

- Admin UI de redes sociales (el CRUD API ya existe; la pantalla es otro plan).
- Pantalla de login / OAuth (solo se comparten los paths SVG).
- Otras vistas del storefront (Home, PDP, …).

## Restricciones

- Frontend: `~/.bun/bin/bun` exclusivamente. Nunca npm/pnpm.
- Containers solo por `scripts/docker-stack.sh`.
- Migraciones backend: `docs/architecture/migration-pattern.md` — sin guards
  `Schema::has*`. **No hace falta migración nueva**: `store_social_links` ya existe.
- Convención de links SSR sin islas (ver `workflow/decisions-storefront-rendering.md`).
- Tests filtrados durante iteración; lint por archivo (baseline enorme).
- No pushear ni mergear sin que el usuario lo pida.

## Tareas

### T1 — Paths SVG compartidos + `SocialIcon` escalable
- [ ] Crear `frontend/src/components/ui/social-icon-paths.ts` con los paths de
      las 8 plataformas del enum BD (`instagram, facebook, tiktok, whatsapp,
      youtube, x, linkedin, other`), incluyendo el path de Google si hace falta
      para des-duplicar `SocialIcons.tsx`.
- [ ] `SocialIcon.astro`: ampliar `SocialVariant` a las 8 plataformas, consumir
      el módulo de paths. Mantener contrato: sin `href` → `<span>` decorativo.
- [ ] Actualizar `SocialIcons.tsx` (login) para consumir el módulo compartido.
- [ ] Checks: `bun run test`, `astro check`.

### T2 — Footer full-width + header en coherencia
- [ ] `StorefrontFooter.astro`: quitar `max-w-[1280px]`, padding simétrico
      (mismo ritmo que el header: `px-4 sm:px-6 lg:px-10`), grid que aproveche
      el ancho (brand + tagline | nav legal | redes + locale).
- [ ] Revisar `StorefrontHeader.astro` para coherencia visual; solo tocar lo
      necesario.
- [ ] Eliminar los archivos vacíos huérfanos
      `components/storefront/footer/StorefrontFooter.astro` y
      `components/storefront/header/StorefrontHeader.astro`.
- [ ] Checks: `bun run test`, `astro check`, `build`.

### T3 — Endpoint público de redes sociales (backend, TDD)
- [ ] RED: test de `GET /api/v1/storefront/social-links` (solo `is_active`,
      orden `sort_order`, tenant por dominio).
- [ ] GREEN: controller + resource + ruta en el grupo `storefront` de `routes/api.php`.
- [ ] Checks: `php artisan test` filtrado, lint PHP.

### T4 — Footer consume redes desde BD
- [ ] `StorefrontLayout.astro` resuelve las redes activas (SSR) y las pasa al footer.
- [ ] Fallback: si no hay filas configuradas, renderizar Facebook, Instagram,
      TikTok y WhatsApp **decorativos sin href** (no redirigen).
- [ ] Con URL asignada → `<a target="_blank" rel="noopener noreferrer">`.
- [ ] Checks: `bun run test`, `astro check`, `build`.

### T5 — 5 páginas legales (Argentina)
- [ ] `frontend/src/pages/[locale]/{about,terms,privacy,help,contact}.astro`.
- [ ] Contenido en i18n `storefront.legal.*` (es/en): Acerca de, Términos y
      condiciones (Ley 24.240), Privacidad y datos personales (Ley 25.326),
      Ayuda (FAQ/soporte), Contacto (datos del tenant).
- [ ] Footer apunta a las rutas reales (reemplazar `#`).
- [ ] Checks: `bun run test`, `astro check`, `build`.

### T6 — i18n completo + cierre de sesión
- [ ] Claves `storefront.footer.*` y `storefront.legal.*` en `es.ts` + `en.ts` + `types.ts`.
- [ ] Cierre común: `~/.bun/bin/bun run test` · `bun run astro check` ·
      `bun run build` (con `PATH="$HOME/.bun/bin:$PATH"`), lint por archivo
      (`bunx eslint <archivos>`, 0 errores nuevos vs HEAD).
- [ ] Capturas playwright (firefox): desktop 1440 y mobile 390, Host del tenant
      contra `:5321`.
- [ ] Reportar: qué cambió, checks `<comando>: <resultado>`, capturas, y qué
      quedó fuera.

## Criterios de aceptación

- Footer ocupa el ancho completo en 1440 y 390 sin romper el layout.
- Redes sociales del footer vienen de la BD cuando hay filas activas; el admin
  puede mostrar/ocultar y asignar URLs sin deploy (ya soportado por
  `store_social_links` + RF-STOR-133).
- Sin filas en BD: se muestran Facebook, Instagram, TikTok y WhatsApp sin link.
- `SocialIcon` es un solo primitive para las 8 plataformas; sin duplicación de
  paths con el login.
- Las 5 páginas legales existen, navegan desde el footer y tienen contenido
  real (legislación Argentina), i18n es/en.
- 0 errores nuevos de lint/test/build.

## Evidencia de progreso

_(se completa por tarea)_

| Tarea | Estado | Commit | Verificación |
|---|---|---|---|
| T1 | pendiente | — | — |
| T2 | pendiente | — | — |
| T3 | pendiente | — | — |
| T4 | pendiente | — | — |
| T5 | pendiente | — | — |
| T6 | pendiente | — | — |

## Próximo paso

T1 — paths SVG compartidos + `SocialIcon` escalable.
