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
- [x] `StorefrontLayout.astro` resuelve las redes activas (SSR) y las pasa al footer.
- [x] Fallback: si no hay filas configuradas, renderizar Facebook, Instagram,
      TikTok y WhatsApp **decorativos sin href** (no redirigen).
- [x] Con URL asignada → `<a target="_blank" rel="noopener noreferrer">`.
- [x] Checks: `bun run test`, `astro check`, `build`.

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
| T1 | hecha | `8e259118` | `bun run test`: 525 tests OK · `astro check`: 0 errors · `eslint` 3 archivos: exit 0 |
| T2 | hecha | `f24202f3` | `bun run test`: 525 OK · `astro check`: 0 errors · `build`: OK · `eslint`: exit 0 · capturas 1440/390 en `output/playwright/t2-footer-*` |
| T3 | hecha | `c5343593` | TDD: RED 7 failed (404 ruta inexistente) → GREEN `php artisan test --filter=StorefrontSocialLink`: 7 passed (43 assertions) |
| T4 | hecha | (este) | `bun run test`: 525 OK · `astro check`: 0 errors · `build`: OK · `eslint`: exit 0 · e2e con fila real en BD (captura `t4-footer-with-link-1440.png`) revertido después |
| T5 | hecha | (este) | `bun run test`: 525 OK · `astro check`: 0 errors · `build`: OK · `eslint`: 0 errors (7 warnings `max-lines-per-function` en contenido legal) · las 5 rutas responden 200 · captura `t5-terms-1440.png` |
| T6 | pendiente | — | — |

### Notas T1

- El path de Facebook del footer (glifo circular Material) reemplazó al del
  login (glifo cuadrado simple-icons): `FacebookIcon` cambió de glifo, mismo
  color/props/aria. Si se prefiere el cuadrado, es una línea en
  `social-icon-paths.ts`.
- El path de Instagram heredado estaba **corrupto** (había perdido los
  comandos de arco `a`/`Z` y renderizaba como un blob). Detectado en la
  captura visual de T2 y saneado con el glifo canónico.

### Notas T2

- Footer full-bleed: 4 columnas en `lg:` (brand | nav legal | contacto |
  redes + locale), 2 en `md:`, stack en mobile. Email de soporte movido a la
  columna de contacto; `StorefrontFooterLegal` queda solo con copyright.
- El bloque de locale pasó de texto estático ("ESPAÑOL") a un
  `LanguageSwitcher client:visible` (ya hidratado en el header, costo marginal).
- Huérfanos vacíos eliminados: `components/storefront/footer/` y
  `components/storefront/header/` (ambos `Storefront*.astro` de 0 bytes).
- Los hrefs de la nav legal siguen apuntando a `#` — T5 los conecta.
- El writer T2 murió con `ContentFilterError` (fallo de runtime, sin handoff
  según contrato) después de editar pero antes de reportar; la verificación
  funcional y visual la corrió el orquestador. El actor se recuperó después y
  confirmó los mismos resultados.
- **Dominio de dev**: el tenant Panda se sirve en `panda-estampados.com` (puerto
  80 y `:5321`); `panda-estampados.test` responde 403. Usar `.com` en capturas.
- **Playwright**: chromium NO está instalado — usar `--browser firefox` (único
  disponible, ya es la convención del repo). El wrapper del skill no es
  ejecutable: invocarlo con `bash "$PWCLI"`. En capturas de elemento el
  `astro-dev-toolbar` se superpone al footer: ocultarlo antes de capturar.
- **A11y preexistente**: `aria-label="Redes sociales"` sobre un `<div>` sin
  `role` es ignorado por los lectores de pantalla. Corregir en T4 (agregar
  `role="group"` o pasar el bloque a `<ul>`).
- Quedan `.gitkeep` en los dirs de componentes ya vacíos
  (`components/storefront/footer/`, `components/storefront/header/`).

### Notas T3

- `GET /api/v1/storefront/social-links` (público, grupo `storefront` con
  `store.domain` + `store.maintenance`). Devuelve solo `is_active` de la
  tienda resuelta por dominio, orden `sort_order` asc + `id` asc.
- Scope por tienda via `$store->socialLinks()` — nunca consulta otras tiendas.
  503 si el contexto queda vacío (misma convención que `StorefrontAuthController`).
- Tests backend **solo corren dentro del container**:
  `./scripts/docker-stack.sh exec development panda-estampados-and-kitsune backend php artisan test --filter=...`
  (el PHP local no tiene `pdo_pgsql`).
- Suite completa backend: 24 failed / 1485 passed — los 24 fallos son
  **preexistentes** (`ProductAttributeValueControllerTest`, `ProductSeederTest`,
  `BackfillVariantFinishCommandTest`) y no tocan esta feature.
- Pendiente: pint/phpstan sobre los archivos tocados (T6).

### Notas T4

- Fetch SSR siguiendo la convención de `pages/[locale]/index.astro`:
  `apiFetch` + `API_ENDPOINTS.storefront.socialLinks` (nuevo en
  `lib/endpoints.ts`) con `origin`/`host` de `Astro.url`, try/catch
  fail-soft → `[]`. El footer aplica el fallback cuando la lista
  mapeada queda vacía (fetch falló, 0 filas, o plataformas inválidas).
- Bloque de redes ahora es `<ul aria-label={t['storefront.footer.socialLabel']}>`
  con `<li>` por icono — corrige el bug de a11y del `<div>` sin `role`.
  Clave nueva `storefront.footer.socialLabel` en es/en/types.
- Mapeo `platform` → variante con guard `value in SOCIAL_ICON_PATHS`
  (lee el mismo mapa de `social-icon-paths.ts`): plataformas desconocidas
  se ignoran sin romper. Orden = `sort_order` del backend, sin reordenar.
- Verificación con datos: fila `instagram` activa (store 1) → el icono
  pasó a `<a target="_blank" rel="noopener noreferrer">` con la URL
  (snapshot + `t4-footer-social-from-db.png`); fila eliminada después
  (0 filas restantes) y el fallback volvió a renderizar los 4 spans.
- `bunx eslint` exige `T[]` y no `Array<T>` (`@typescript-eslint/array-type`).

### Notas T4

- `StorefrontLayout.astro` resuelve las redes via `apiFetch(API_ENDPOINTS.storefront.socialLinks)`
  (misma convención que el resto del frontend) y se las pasa a `StorefrontFooter`.
  **Fail-soft**: si el fetch falla, devuelve `[]` y el footer cae al fallback —
  el footer nunca tumba la página.
- `StorefrontFooter.astro` mappea `platform` → variante de `SocialIcon` ignorando
  con gracia plataformas fuera del enum. Si hay filas, muestra SOLO esas (que es
  lo pedido: el admin decide qué redes se exponen). Sin filas → las 4 por defecto
  (facebook, instagram, tiktok, whatsapp) decorativas, sin href.
- A11y corregido: el bloque de redes ahora es `<ul aria-label={t['storefront.footer.socialLabel']}>`
  con `<li>` por icono (antes era `<div>` sin `role`, y el label lo ignoraban los
  lectores de pantalla). Clave i18n nueva `storefront.footer.socialLabel` en
  es/en/types.
- **Verificación e2e**: se insertó una fila `store_social_links` (instagram con
  URL) via `psql`, el footer la renderizó como `<a href="https://instagram.com/...">`
  (confirmado en el snapshot de playwright), y la fila se revirtió después
  (tabla quedó en 0 filas).
- El writer T4 se estancó (76 turnos sin cerrar) y fue cancelado; el
  orquestador corrió la verificación completa y la e2e.

### Notas T5

- 5 páginas bajo `pages/[locale]/` (`about`, `terms`, `privacy`, `help`,
  `contact`) — cascarones finos que renderizan `LegalContent`.
- El contenido legal vivo en `src/i18n/legal/` (módulo tipado por locale,
  no el diccionario plano): son párrafos extensos que cambian por
  legislación, no por traducción de UI. `LegalContext` interpola
  `brandName`/`supportEmail` — nunca se hardcodean datos de la marca.
- Legislación Argentina cubierta: Ley 24.240 (desistimiento 10 días
  corridos art. 34, garantía legal, fuero del consumidor), Decreto 424/98,
  Ley 25.326 (derechos ARCO, AAIP), Ley 11.723 / 22.426 (propiedad
  intelectual), excepción de productos personalizados.
- Datos fiscales del negocio que faltan quedan como `[completar: …]` —
  no inventados. Ver en `terms.ts` y `privacy.ts`.
- Chrome i18n `storefront.legal.*` (títulos, breadcrumb, labels de
  contacto) en es/en/types.
- Links del footer ahora apuntan a `/{locale}/{about,terms,privacy,help,contact}`.
- **Hallazgo operativo**: el container frontend no veía los archivos nuevos
  pese al bind mount `../../../../frontend:/app`. `restart` no alcanza
  (preserva el mount viejo); hizo falta `down` + `up` del servicio frontend
  para recrear el container. Aplica cuando se crean directorios/archivos
  nuevos: recrear, no solo reiniciar.
- El writer T5 murió otra vez con `ContentFilterError` (filtro del provider
  sobre texto legal) tras dejar completo el contenido (~840 líneas en
  `i18n/legal/`) y los links del footer. El orquestador escribió las 5
  páginas, corrigió claves i18n duplicadas (el writer y el orquestador las
  agregaron por separado) y corrió la verificación.

## Próximo paso

T6 — i18n completo + cierre de sesión (checks, capturas 1440/390, reporte).
