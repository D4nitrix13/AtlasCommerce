# Herramientas de desarrollo

Este documento resume las herramientas auxiliares usadas durante el
desarrollo del ecommerce.

Incluye herramientas para:

- documentación técnica asistida por IA;
- túneles seguros hacia servicios locales;
- gestión de repositorios y pull requests;
- pruebas automatizadas end-to-end.

## Context7

`Context7` permite que agentes de IA consulten documentación actualizada de
librerías y frameworks durante el desarrollo.

### Enlaces de Context7

- **Sitio oficial:** [Context7](https://context7.com/ "Context7")
- **GitHub:** [upstash/context7](https://github.com/upstash/context7 "upstash/context7")

### Uso de Context7 en el proyecto

En este proyecto, `Context7` sirve para mejorar las respuestas de agentes de
código cuando trabajan con tecnologías como `Laravel`, `Astro`,
`Tailwind CSS`, `React`, `PostgreSQL` y herramientas relacionadas.

Su objetivo es reducir errores causados por documentación desactualizada o por
APIs que cambian entre versiones.

### Instalación de Context7 en Arch Linux y CachyOS

`Context7` puede ejecutarse con `npx`, por lo que no es obligatorio instalarlo
globalmente.

Primero verifica que tengas `Node.js` y `npm`:

```bash
node --version
npm --version
```

Si no los tienes instalados:

```bash
sudo pacman -S nodejs npm
```

Luego ejecuta la configuración para `OpenCode`:

```bash
npx ctx7@latest setup --opencode
```

También puedes usar:

```bash
npx ctx7 setup --opencode
```

### Instalación global de Context7

Si prefieres instalar `ctx7` globalmente:

```bash
npm install -g ctx7
```

Luego ejecuta:

```bash
ctx7 setup --opencode
```

### Verificación de Context7

Después de ejecutar la configuración, revisa los cambios generados:

```bash
git status --short
git diff
```

También revisa archivos como:

```txt
opencode.json
AGENTS.md
.opencode/
```

### Eliminación de Context7

Si necesitas revertir la configuración creada por `Context7`:

```bash
npx ctx7 remove
```

Si instalaste `ctx7` globalmente:

```bash
npm uninstall -g ctx7
```

## Cloudflared

`cloudflared` es el cliente de línea de comandos de `Cloudflare Tunnel`.

Permite exponer servicios locales o privados a Internet mediante túneles
seguros, sin abrir puertos directamente en el router.

### Enlaces de cloudflared

- **GitHub:** [cloudflare/cloudflared][cloudflared]
- **Documentación:** [Cloudflare Tunnel][cloudflare-tunnel]

### Uso de cloudflared en el proyecto

En este proyecto, `cloudflared` puede servir para:

- probar el `frontend` local con un dominio temporal;
- exponer una API local para pruebas externas;
- probar webhooks durante desarrollo;
- validar flujos desde dispositivos móviles;
- crear túneles seguros hacia servicios internos.

### Instalación de cloudflared en Arch Linux y CachyOS

Instala el paquete con `pacman`:

```bash
sudo pacman -S cloudflared
```

Verifica la instalación:

```bash
cloudflared --version
```

### Túnel temporal con cloudflared

Para exponer un servicio local de forma temporal:

```bash
cloudflared tunnel --url http://localhost:4321
```

Ejemplo para un frontend `Astro`:

```bash
pnpm dev --host 0.0.0.0
cloudflared tunnel --url http://localhost:4321
```

Ejemplo para una API `Laravel`:

```bash
php artisan serve --host=0.0.0.0 --port=8000
cloudflared tunnel --url http://localhost:8000
```

### Túnel autenticado con cloudflared

Para iniciar sesión con tu cuenta de `Cloudflare`:

```bash
cloudflared tunnel login
```

Para crear un túnel permanente:

```bash
cloudflared tunnel create ecommerce-dev
```

Para listar túneles:

```bash
cloudflared tunnel list
```

### Consideraciones de seguridad para cloudflared

No expongas servicios sensibles sin autenticación.

Antes de compartir un enlace generado por `cloudflared`, verifica que el
servicio local no muestre:

- paneles administrativos sin protección;
- archivos `.env`;
- rutas de depuración;
- logs internos;
- credenciales;
- datos reales de clientes.

## GitHub CLI

`GitHub CLI` permite trabajar con GitHub desde la terminal usando el comando
`gh`.

Sirve para crear pull requests, revisar issues, autenticar la cuenta y
gestionar flujos de colaboración sin salir de la terminal.

### Enlaces de GitHub CLI

- **GitHub:** [cli/cli][gh-cli]
- **Documentación:** [GitHub CLI Manual][gh-cli-manual]

### Uso de GitHub CLI en el proyecto

En este proyecto, `gh` se usa para:

- autenticar la cuenta de GitHub;
- crear pull requests;
- revisar el estado de pull requests;
- abrir PRs en el navegador;
- revisar repositorios desde terminal;
- complementar el flujo de commits y ramas.

### Instalación de GitHub CLI en Arch Linux y CachyOS

Instala `GitHub CLI` con `pacman`:

```bash
sudo pacman -S github-cli
```

Verifica la versión:

```bash
gh --version
```

### Autenticación con GitHub CLI

Inicia sesión:

```bash
gh auth login
```

Verifica el estado de autenticación:

```bash
gh auth status
```

### Crear pull request con GitHub CLI

Primero crea y sube una rama:

```bash
git checkout -b feature/example-feature
git push -u origin feature/example-feature
```

Luego crea el pull request:

```bash
gh pr create \
  --base master \
  --head feature/example-feature \
  --title "feat(project): add example feature" \
  --body-file /tmp/pr-body.md
```

Si la rama base del repositorio es `main`, usa:

```bash
gh pr create \
  --base main \
  --head feature/example-feature \
  --title "feat(project): add example feature" \
  --body-file /tmp/pr-body.md
```

### Plantilla de descripción para pull request

La descripción del pull request debe estar en español y usar Markdown claro.

```md
## Resumen

Describe de forma breve qué cambia este PR y por qué.

## Cambios principales

- **Cambio principal:** explica el cambio más importante.
- **Área afectada:** indica si afecta backend, frontend, base de datos o docs.
- **Resultado esperado:** explica qué mejora o habilita este cambio.

## Motivo del cambio

Explica la necesidad técnica, funcional o de arquitectura.

## Áreas afectadas

- `backend`
- `frontend`
- `database`
- `docs`

## Validación realizada

- [ ] Se revisó `git status --short`.
- [ ] Se revisó `git diff --stat`.
- [ ] Se ejecutaron pruebas relevantes.
- [ ] Se verificó que no existan secretos ni archivos temporales.

## Notas técnicas

Agrega detalles importantes para revisión.

## Riesgos o consideraciones

Explica posibles riesgos, migraciones o compatibilidad.

## Checklist

- [ ] Los commits están divididos por intención lógica.
- [ ] Los mensajes siguen Conventional Commits.
- [ ] La rama tiene nombre en inglés.
- [ ] El PR tiene título en inglés.
- [ ] La descripción del PR está en español.
```

### Revisar pull requests con GitHub CLI

Ver estado general:

```bash
gh pr status
```

Listar pull requests:

```bash
gh pr list
```

Abrir el PR actual en el navegador:

```bash
gh pr view --web
```

## Playwright

`Playwright` es una herramienta para pruebas end-to-end en navegadores.

Permite automatizar flujos reales del usuario en navegadores como `Chromium`,
`Firefox` y `WebKit`.

### Enlaces de Playwright

- **GitHub:** [microsoft/playwright](https://github.com/microsoft/playwright "microsoft/playwright")
- **Documentación:** [Playwright](https://playwright.dev/ "Playwright")

### Uso de Playwright en el proyecto

En este proyecto, `Playwright` puede servir para probar:

- inicio de sesión;
- navegación del panel administrativo;
- carrito de compras;
- checkout;
- formularios;
- rutas protegidas;
- flujos multi-tienda;
- vistas públicas del storefront.

### Instalación de Playwright en Arch Linux y CachyOS

Dentro del frontend o del workspace del proyecto, instala `Playwright`:

```bash
pnpm add -D @playwright/test
```

Instala los navegadores necesarios:

```bash
npx playwright install
```

También puedes instalar dependencias del sistema si Playwright las solicita:

```bash
npx playwright install-deps
```

En Arch Linux y CachyOS, normalmente también conviene tener instalados
navegadores del sistema:

```bash
sudo pacman -S chromium firefox
```

### Inicializar Playwright

Para crear la configuración inicial:

```bash
pnpm create playwright
```

O usando `npm`:

```bash
npm init playwright@latest
```

### Ejecutar pruebas con Playwright

Ejecutar todas las pruebas:

```bash
npx playwright test
```

Ejecutar pruebas con interfaz visual:

```bash
npx playwright test --ui
```

Ejecutar pruebas en modo visible:

```bash
npx playwright test --headed
```

Ejecutar una prueba específica:

```bash
npx playwright test tests/example.spec.ts
```

### Ver reporte de Playwright

Después de ejecutar pruebas:

```bash
npx playwright show-report
```

### Ejemplo básico de prueba Playwright

```ts
import { expect, test } from "@playwright/test";

test("muestra la página principal", async ({ page }) => {
  await page.goto("/");

  await expect(page).toHaveTitle(/Ecommerce/);
});
```

## Flujo recomendado

Antes de trabajar en una nueva funcionalidad:

```bash
git checkout master
git pull --ff-only
git checkout -b feature/example-feature
```

Durante el desarrollo:

```bash
git status --short
git diff --stat
```

Antes de crear commits:

```bash
git diff
git diff --cached
```

Después de crear commits:

```bash
git log --oneline -5
```

Subir rama:

```bash
git push -u origin feature/example-feature
```

Crear pull request:

```bash
gh pr create \
  --base master \
  --head feature/example-feature \
  --title "feat(project): add example feature" \
  --body-file /tmp/pr-body.md
```

## Checklist de herramientas

Antes de guardar cambios relacionados con herramientas, verifica:

```txt
[ ] Los enlaces apuntan a documentación oficial o repositorios oficiales.
[ ] Los comandos usan bloques con lenguaje `bash`.
[ ] Las rutas y nombres técnicos usan backticks.
[ ] No hay encabezados duplicados.
[ ] No hay bloques de código sin lenguaje.
[ ] Las líneas respetan 80 caracteres cuando es razonable.
[ ] No se documentan funcionalidades pendientes como implementadas.
[ ] No se incluyen tokens, claves API ni credenciales.
```

[cloudflared]: https://github.com/cloudflare/cloudflared "cloudflare/cloudflared"
[cloudflare-tunnel]: https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/ "Cloudflare Tunnel"
[gh-cli]: https://github.com/cli/cli "cli/cli"
[gh-cli-manual]: https://cli.github.com/manual/ "GitHub CLI Manual"
