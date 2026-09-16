# Browsers Soportados (Baseline 2024)

> **RNF-COMP-090**: Browsers soportados oficialmente: Chrome/Edge ≥ 107,
> Firefox ≥ 115, Safari ≥ 16.4.

## Definición

Baseline 2024 es un snapshot publicado por web.dev que captura el
conjunto de features web disponibles "de forma segura" en todos los
browsers con > 0.5% de uso global que fueron released en los últimos
2.5 años. Equivale aproximadamente a:

| Browser | Versión mínima | Released |
| --- | --- | --- |
| Chrome | 107 | oct 2022 |
| Edge (Chromium) | 107 | oct 2022 |
| Firefox | 115 | jul 2023 |
| Safari (macOS/iOS) | 16.4 | mar 2023 |
| Samsung Internet | 23 (= Chromium 107) | oct 2022 |
| Android WebView | 107 | oct 2022 |

## Qué NO soportamos

- **Internet Explorer 11** — EOL desde jun 2022.
- **Edge legacy (EdgeHTML)** — EOL desde mar 2021.
- **Opera < 78 (Chromium 85)** — Opera migró a Chromium en 2020, así
  que cualquier Opera actual está cubierto por Chrome.

Si necesitas soportar un browser fuera de esta lista, abrí un issue
con justificación (ej: usuario corporativo con parque legacy).

## Configuración

`frontend/.browserslistrc`:

```ini
[production]
chrome >= 107
firefox >= 115
safari >= 16.4
edge >= 107

[development]
last 2 chrome versions
last 2 firefox versions
last 2 safari versions
last 2 edge versions
```

## Cómo se aplica

- **Vite/Astro**: usa `.browserslistrc` automáticamente para targets de
  build (Babel + postcss-preset-env). Genera polyfills y sintaxis
  adecuada al browser más viejo soportado.
- **CSS**: `postcss-preset-env` aplica autoprefixer según targets.
- **TypeScript** (`tsconfig.json`): `target: "ES2022"` es el floor
  mínimo; los browsers Baseline 2024 soportan ES2022 nativamente.

## Verificación en CI

El CI corre `npx browserslist` para validar que la configuración
resuelve a una lista no vacía. Si `.browserslistrc` está roto, falla.

```bash
cd frontend
npx browserslist
# → lista de versiones detectadas
```

## Browserslist como dep explícita

`browserslist` es transitivo en este proyecto (lo trae Vite/Astro).
Para evitar sorpresas por upgrades de versiones transitivas, lo
declaramos como devDependency explícito.

```bash
pnpm add -D browserslist@^4.28
```

## Upgrade policy

Cuando un browser llega a EOL o Baseline cambia:

1. PR que actualiza `.browserslistrc` con los nuevos mínimos.
2. Verificar que los tests pasan (`pnpm run build && pnpm test`).
3. Actualizar esta doc con la fecha del nuevo snapshot.
4. Comunicación en CHANGELOG para usuarios.

## Referencias

- [web.dev Baseline 2024](https://web.dev/baseline/2024)
- [browserslist — GitHub](https://github.com/browserslist/browserslist)
- PR #101 — implementación de Baseline 2024.
