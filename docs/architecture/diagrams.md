# Diagramas de Arquitectura — Archify

> Cómo generar, versionar y mantener los diagramas visuales
> del proyecto usando la skill local **Archify**.

## 1. Qué es Archify

`Archify` (`.agents/skills/archify/`) es una skill que
genera diagramas standalone en HTML a partir de descripciones
en lenguaje natural o `Mermaid`. Los diagramas resultantes
se sirven localmente sin servidor y soportan:

- Exportar a PNG / JPEG / WebP / SVG / WebM.
- Temas dark / light.
- Animaciones de trace (motion) opcionales.
- Layouts para flowchart, sequence, data-flow y lifecycle.
- Validación previa al render.

La skill vive **solo en este repo** (no es dependencia de
runtime del backend o del frontend). Se invoca desde el CLI o
vía el servidor MCP `archify` cuando está disponible.

## 2. Ubicación de los diagramas

Los renders generados viven **fuera del repo versionado**:

```txt
.archify/
├── architecture-atlascommerce-<YYYYMMDD-HHMMSS>/
│   ├── atlascommerce-architecture.html          # diagrama final
│   ├── atlascommerce-architecture.delivery.json # contrato de entrega
│   ├── candidate.json                           # Mermaid candidato
│   ├── atlascommerce-architecture.finalize.json # repair log
│   ├── atlascommerce-architecture.finalize-summary.json
│   └── atlascommerce-architecture.browser-check.json
```

El directorio `.archify/` está ignorado por `git` (ver
`.gitignore`). Cada ejecución crea un snapshot nuevo con
timestamp.

## 3. Diagrama actual

El último diagrama generado vive en:

```txt
.archify/architecture-atlascommerce-20260930-173500/
└── atlascommerce-architecture.html
```

Cubre la arquitectura multi-instancia (3 instancias Docker,
4 marcas) con Caddy, Astro 7, Laravel 13, PostgreSQL 18 +
PostGIS, Redis 7 y el middleware `ResolveStoreFromDomain`.
Para abrirlo, doble click sobre el `.html` desde el file
manager, o servirlo con:

```bash
cd .archify/architecture-atlascommerce-20260930-173500/
python3 -m http.server 8000
# abrir http://localhost:8000/atlascommerce-architecture.html
```

## 4. Cómo generar un diagrama nuevo

### 4.1 Vía MCP `archify_explore` (preferido)

```text
Archify: flowchart de la arquitectura multi-instancia con
3 stacks Docker independientes (Panda+Kitsune / MM Boutique
/ Morales Store), Caddy como reverse proxy, Astro 7 como
frontend y Laravel 13 como backend.
```

La herramienta devuelve el HTML standalone + el contrato
de entrega.

### 4.2 Vía CLI

```bash
cd .agents/skills/archify
./bin/archify generate --spec "flowchart multi-instance" \
    --output ../../../.archify/architecture-atlascommerce-$(date +%Y%m%d-%H%M%S)/
```

> Los flags exactos dependen de la versión. Consultar
> `.agents/skills/archify/SKILL.md` para el contrato de
> invocación vigente.

### 4.3 Vía invocación programática

```bash
node .agents/skills/archify/scripts/render.mjs \
    --input architecture.mermaid \
    --output .archify/architecture-atlascommerce-$(date +%Y%m%d-%H%M%S)/
```

## 5. Cuándo regenerar

Regenerar el diagrama cuando cambie:

- Topología de instancias (nueva marca, nuevo VPS).
- Stack técnico (cambio de Astro 7 → otra versión mayor).
- Modelo de datos (tablas globales con/sin `store_id`).
- Middleware / resolución de tenant (cambio en
  `ResolveStoreFromDomain`).
- Flujos cross-tenant (auth, payment, RRSS).

Si solo cambian pantallas internas (admin, account), no es
necesario regenerar — el diagrama es a nivel de
infraestructura y módulos.

## 6. Versionado en docs

Los diagramas NO se commitean al repo. En su lugar:

1. Generar el diagrama y dejarlo en `.archify/`.
2. En la doc afectada (overview, flow, etc.), referenciar
   por **ruta local** con timestamp:

   ```md
   Ver `.archify/architecture-atlascommerce-<TIMESTAMP>/
   atlascommerce-architecture.html` para el diagrama visual.
   ```

3. Si el diagrama necesita revisión en PR, adjuntar el
   `.html` (o un screenshot PNG exportado desde el viewer)
   a la conversación, no al commit.

## 7. Recursos

- [Skill Archify — SKILL.md](../../.agents/skills/archify/SKILL.md
  ".agents/skills/archify/SKILL.md")
- [Authoring contract](../../.agents/skills/archify/references/authoring-contract.md
  ".agents/skills/archify/references/authoring-contract.md")
- [Delivery contract](../../.agents/skills/archify/references/delivery-contract.md
  ".agents/skills/archify/references/delivery-contract.md")
- [Repository authoring](../../.agents/skills/archify/references/repository-authoring.md
  ".agents/skills/archify/references/repository-authoring.md")
