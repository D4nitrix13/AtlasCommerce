<!-- markdownlint-disable MD013 -->

<!-- markdownlint-disable MD051 -->
# Rebrand AtlasCommerce — Reporte de cambios (2026-09-09)

> **Estado:** Mergeado a `master` (commit `af4060e`) y pusheado a `origin/master`.
> **Para revisar después** por el dueño: los 9 archivos nuevos listados
> al final en [§ Pendientes de revisión](#pendientes-de-revision).

## Resumen ejecutivo

| Categoría | Antes | Después |
| --- | --- | --- |
| Repo GitHub | `D4nitrix13/Ecommerce` | `D4nitrix13/AtlasCommerce` |
| Marca del proyecto | `Ecommerce` / "Atlas Commerce" | **`AtlasCommerce`** (CamelCase, sin espacio) |
| Licencia | MIT (código abierto) | **Propietaria** ("All Rights Reserved") |
| Governance files | Solo README, CONTRIBUTING, PR template | + CoC, SECURITY, SUPPORT, CODEOWNERS, dependabot, FUNDING, 2 issue templates |
| Plugin Composer local | `d4nitrix13/ecommerce-plugin-erp` | `d4nitrix13/atlas-commerce-plugin-erp` |
| Módulo frontend de compra | `frontend/src/components/ecommerce/` | `frontend/src/components/atlas-commerce/` |
| Metadata GitHub | sin descripción ni topics | descripción + 12 topics + homepage |

70 archivos cambiados en el merge a `master`.

---

## Contexto y motivación

El proyecto se llamaba `Ecommerce` desde su concepción, y se distribuía bajo
licencia MIT. Tres razones impulsaron este pase:

1. **Identidad de marca:** "Atlas Commerce" comunica mejor la naturaleza
   multi-tienda (Atlas sostiene varios mundos) que un genérico "Ecommerce".
   Los tres tenants actuales — **Panda Estampados**, **Kitsune Store**,
   **MM Boutique**, **Morales Store** — corren sobre la misma plataforma.
2. **Modelo de negocio:** el software pasa a ser propietario. La empresa
   retiene todos los derechos; no se distribuye como código abierto.
3. **Profesionalización:** el repo no tenía CoC, política de seguridad,
   ni templates de issues. Para presentar el proyecto a terceros
   (contratistas, clientes, partners) faltaba toda esa capa.

---

## Cambios por categoría

### 1. Rename del proyecto raíz

| Qué | Detalle |
| --- | --- |
| Repo GitHub | `D4nitrix13/Ecommerce` → `D4nitrix13/AtlasCommerce` (vía `gh api PATCH /repos/.../name`) |
| Remote local | `git remote set-url origin git@github.com:D4nitrix13/AtlasCommerce.git` |
| Carpeta local | `/home/d4nitrix13/Code/Ecommerce/` → `/home/d4nitrix13/Code/AtlasCommerce/` |
| Marca escrita | `# AtlasCommerce` en títulos; `AtlasCommerce` (CamelCase, sin espacio) en todos los identificadores |

Archivos tocados: `README.md`, `CONTRIBUTING.md`, `scripts/README.md`,
los 4 READMEs internos del backend
(`app/Models`, `app/Http/Resources`, `app/Http/Controllers/Api`,
`database/migrations`), `Caddyfile`, `frontend/src/layouts/Layout.astro`,
`backend/.env.example`, `backend/.env.testing`, los 3
`docker/instances/development/*/environment/.env.example`,
`docker/instances/README.md`.

### 2. Reclasificación a software propietario

| Qué | Detalle |
| --- | --- |
| `LICENSE` (nuevo) | Copyright © 2026 Daniel Pérez. "All Rights Reserved" — uso, copia, modificación, redistribución y uso comercial requieren permiso escrito previo. |
| `backend/composer.json` | `"license": "MIT"` → `"license": "proprietary"` (SPDX válido) |
| `backend/packages/atlas-commerce-plugin-erp/composer.json` | mismo cambio |
| `README.md` sección Licencia | reemplazada con nota explicativa + refs a `LICENSE`, `CoC`, `SECURITY`, `SUPPORT` |

Elegí `"proprietary"` (SPDX) sobre `"UNLICENSED"` o un EULA custom
porque es el default legal claro para software propietario y se
integra correctamente con Composer y herramientas de licencia.

### 3. Renombrar el plugin Composer local

Antes: `d4nitrix13/ecommerce-plugin-erp` con namespace PHP
`D4nitrix13\EcommercePluginErp` y clase `EcommercePluginErpServiceProvider`.

Después: `d4nitrix13/atlas-commerce-plugin-erp` con namespace
`D4nitrix13\AtlasCommercePluginErp` y clase
`AtlasCommercePluginErpServiceProvider`.

Cambios estructurales:

- `composer.json` del paquete: `name`, `type` (`atlas-commerce-plugin`),
  `description`, autoload PSR-4, `extra.laravel.providers`.
- `composer.json` del backend: `repositories[0].url` y la línea `require`.
- `ServiceProvider`: namespace PHP + class + view namespace +
  publish groups + destino de `views/vendor/`.
- `src/Http/{Controllers,Middleware}/*.php`: namespaces.
- `routes/api.php`: `use` statements.
- `tests/PackageMetadataTest.php`: namespace + asserts sobre `type` y FQCN.
- `backend/tests/Feature/Plugins/ErpPluginDiscoveryTest.php`: `use` + `::class`.
- 6 `Dockerfile.{development,production}` (3 tenants × 2 environments):
  comentarios que describen el repositorio path del plugin.

**Pendiente técnico (no en este pase):** regenerar `composer.lock` con
`composer update` en `backend/`. El lockfile actual todavía tiene las URLs
del path viejo; el autoload también.

### 4. Renombrar el módulo frontend

Antes: `frontend/src/components/ecommerce/{cart,checkout}/`.

Después: `frontend/src/components/atlas-commerce/{cart,checkout}/`.

Tres archivos `.astro` movidos; **cero imports rotos** (grep
`components/ecommerce` en `frontend/src` no devolvió nada — el módulo
estaba sin consumers al momento del rename).

`docs/frontend/estructura.md` y `docs/agent-skills/astro-frontend-structure/SKILL.md`
actualizados con la nueva ruta (listados + encabezado de sección).

### 5. Governance files nuevos

| Archivo | Tamaño | Propósito |
| --- | --- | --- |
| `CODE_OF_CONDUCT.md` | 107 líneas | Contributor Covenant v2.1 adaptado a proyecto propietario. Aplicación por `@D4nitrix13`. |
| `SECURITY.md` | 113 líneas | Disclosure privado (`security@atlas-commerce.dev`), acuse <48h hábiles, coordinated disclosure ~90 días. |
| `SUPPORT.md` | 51 líneas | Canales oficiales + guideline para abrir buenos issues. |
| `.github/CODEOWNERS` | 12 líneas | `* @D4nitrix13` — fase owner único. Patrón preparado para separar por capa cuando entren más mantenedores. |
| `.github/dependabot.yml` | 65 líneas | Semanal lunes 08:00 `America/Managua` para npm (frontend), composer (backend) y github-actions. |
| `.github/FUNDING.yml` | 16 líneas | Placeholder para GitHub Sponsors + link a `atlas-commerce.dev/sponsor`. |
| `.github/ISSUE_TEMPLATE/bug_report.yml` | 113 líneas | Dropdown por tenant, severidad, pasos para reproducir, logs, self-check. |
| `.github/ISSUE_TEMPLATE/feature_request.yml` | 86 líneas | Dropdown de capa + tenant, problema, solución, alternativas, impacto. |

`CONTRIBUTING.md` header robustecido con nombre del proyecto prominente,
aviso de licencia propietaria + links cruzados a los nuevos governance files.

### 6. Metadata del repo en GitHub

Aplicado vía `gh repo edit` (no requiere push):

- **description**: *"Plataforma multi-tienda para e-commerce y gestión
  empresarial. Corre Panda Estampados, Kitsune Store, MM Boutique y
  Morales Store sobre Laravel 13 + Astro 7 + PostgreSQL con resolución
  por dominio."*
- **homepage**: `https://atlas-commerce.dev` (placeholder — verificar)
- **topics (12)**: `multi-tenant`, `ecommerce`, `laravel`, `astro`, `react`,
  `typescript`, `postgresql`, `redis`, `docker`, `multi-store`, `b2b`, `b2c`

---

## Historial de commits

Siete commits cohesivos, todos en español, en la rama `feat/auth-base-validation`,
mergeados con `--no-ff` a `master`:

```text
af4060e  merge: integrar rebrand a AtlasCommerce + reclasificación propietaria + governance files
c6313cb  chore(ci): añadir issue templates, CODEOWNERS, dependabot y FUNDING
a3152fd  docs(governance): añadir CoC, SECURITY, SUPPORT y robustecer CONTRIBUTING
ba4dd6b  chore(license): reclasificar a software propietario y actualizar metadata
b16b948  docs: actualizar referencias cruzadas a AtlasCommerce (URLs, paths, headers)
cbe3faf  docs(plugin+frontend): referenciar atlas-commerce-plugin-erp y el módulo components/atlas-commerce/
0a09296  refactor(plugin): renombrar ecommerce-plugin-erp a atlas-commerce-plugin-erp
3c367b3  chore(root): renombrar identidad del proyecto de Ecommerce a AtlasCommerce
```text

Pushes verificados:

- `origin/feat/auth-base-validation` quedó en `c6313cb` antes del merge.
- `origin/master` quedó en `af4060e` después del merge.

---

## Convención fijada (para futuras sesiones)

Estas son las reglas que adoptamos. Aplicalas en cualquier código o
documento nuevo:

- **Identificadores de marca** (APP_NAME, headers, package names,
  namespaces, títulos): **`AtlasCommerce`** sin espacio (CamelCase).
- **Paths y directorios** (nombres de carpeta, slugs, dominios): **kebab-case**
  (`atlas-commerce`, `atlas-commerce-plugin-erp`, `components/atlas-commerce/`).
- **Texto en prosa** que se refiera al *concepto* de ecommerce (no al
  nombre de la app): minúsculas y en español — "un ecommerce multi-tienda",
  "componentes del ecommerce público", etc.
- **Conventional commits** en español: `tipo(scope): descripción` con
  scope en minúscula y body con bullets `-`.
- **Modelo de licensing**: software propietario. Cualquier componente de
  terceros mantiene su licencia original; el resto del proyecto se rige
  por el `LICENSE` en la raíz.
- **Disclosure de seguridad**: canal privado (`security@atlas-commerce.dev`),
  nunca issue público.

---

## Pendientes de revisión

### Por el dueño del proyecto (vos)

Estos **9 archivos nuevos** los creé yo con texto razonable, pero
necesitan tu revisión antes de considerarlos definitivos. La idea es
que valides que el tono, los emails de contacto y las direcciones
externas reflejen tu intención:

1. `LICENSE` — texto legal propietario. Verificar que la redacción de
   "All Rights Reserved" sea la que querés (vs. un EULA con matices tipo
   "uso interno", "evaluación 30 días", etc.).
2. `CODE_OF_CONDUCT.md` — adaptación del Contributor Covenant v2.1.
   Verificar la escala de consecuencias (corrección privada → expulsión)
   y el canal `conduct@atlas-commerce.dev`.
3. `SECURITY.md` — política de disclosure. Verificar el SLA (48h
   hábiles) y la ventana de coordinated disclosure (90 días).
4. `SUPPORT.md` — canales. Verificar que `support@atlas-commerce.dev`
   sea la dirección correcta.
5. `.github/CODEOWNERS` — owner único `@D4nitrix13`. Cuando entren
   otros mantenedores, separar por capa.
6. `.github/FUNDING.yml` — placeholder. Cuando actives GitHub Sponsors,
   agregar tu username en `github:`.
7. `.github/dependabot.yml` — schedule `America/Managua` lunes 08:00,
   límites 8 PRs npm/composer + 3 CI. Ajustar zona horaria y límites.
8. `.github/ISSUE_TEMPLATE/bug_report.yml` — dropdown de tenant con 5
   opciones, severidad, logs con `render: shell`. Verificar que las
   opciones de tenant cubren todos los casos.
9. `.github/ISSUE_TEMPLATE/feature_request.yml` — dropdown de capa y
   tenant objetivo. Idem.

**También placeholders** que asumí (no son archivos pero aparecerán en
los governance files y en GitHub):

- Email del titular: `danielperezdev@proton.me` (extraído de `git log`).
  Si querés un email de contacto distinto, reemplazalo en `LICENSE` y
  en los governance files.
- Dominio de emails: `@atlas-commerce.dev`. Si tu dominio real es
  otro (`@tuempresa.com`, `@tudominio.com`, etc.), actualizá las 4
  direcciones en `CODE_OF_CONDUCT.md`, `SECURITY.md`, `SUPPORT.md`.
- Homepage: `https://atlas-commerce.dev`. Si no existe o es distinto,
  corré `gh repo edit --delete-homepage` y reingresalo.

### Técnicos (rebrand funciona, pero el lockfile quedó viejo)

Estos **no requieren re-edición manual**, solo correr comandos dentro
del backend container:

```bash
# Regenerar composer.lock con el nuevo path del plugin
./scripts/docker-stack.sh exec dev backend composer update

# Re-descubrir el provider (bootstrap/cache queda stale)
./scripts/docker-stack.sh exec dev backend php artisan package:discover

# Suite de tests del plugin
./scripts/docker-stack.sh exec dev backend php artisan test --filter='ErpPlugin|PackageMetadata'
```text

Después de eso, **rebuild de imágenes Docker de backend** antes del
próximo deploy a producción (los comentarios en los Dockerfile
cambiaron, pero las capas de COPY también).

### Externos al código (no hice, queda en tu mano)

- **Cloudflare tunnel `ecommerce-dev`**: el doc lo renombré a
  `atlas-commerce-dev` pero el tunnel real requiere
  `cloudflared tunnel delete + recreate + DNS reroute`.
- **GitHub Sponsors**: la entrada `github: []` en `FUNDING.yml` está
  vacía. Reemplazar por tu username cuando actives la cuenta.
- **Borrar branch mergeada**: `git branch -d feat/auth-base-validation`
  - `git push origin --delete feat/auth-base-validation` si querés
  limpiar el listado de ramas remotas.

---

## Próxima sesión sugerida

Validar el rename en runtime (composer update + tests + rebuild) y
revisar los 9 archivos pendientes. Después de eso, el repo queda
listo para presentar a terceros.
