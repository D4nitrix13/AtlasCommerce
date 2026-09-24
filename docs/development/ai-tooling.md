# AI Stack Setup — Bootstrap de agentes y skills

> Esta página documenta qué necesita estar instalado y configurado en
> una máquina nueva para que el orquestador `gentle-orchestrator`, las
> skills, los MCP servers y los sub-agentes funcionen idéntico a
> como funcionan hoy en la máquina de desarrollo principal.

## Cuándo leer esto

- Acabás de clonar el repo en una máquina nueva.
- Estás preparando una imagen de CI con agentes.
- Algo del stack dejó de funcionar y necesitás validar el bootstrap.

## 1. Dependencias de sistema

Antes de correr cualquier script del repo, la máquina necesita:

| Binario / servicio       | Para qué                | Cómo verificar           |
| ------------------------ | ----------------------- | ------------------------ |
| `opencode`               | Runtime de agentes      | `opencode --version`     |
| `gentle-ai`              | Orquestador + dispatcher| `gentle-ai --version`    |
| `engram`                 | Memoria persistente MCP | `engram --version`       |
| Servicio `engram serve`  | Backend de memoria      | `pgrep -f "engram serve"`|
| `gh`                     | Clonar wshobson plugins | `gh --version`           |
| `uv`                     | Build de plugins        | `uv --version`           |
| `node` + `npx`           | Instalar skills ad-hoc  | `npx --version`          |

Los MCP servers `codegraph`, `context7` y `engram` quedan registrados
en `~/.config/opencode/opencode.json` y los levanta `opencode`
automáticamente al iniciar una sesión, siempre que el binario/servicio
subyacente esté disponible.

### Notas

- `gentle-ai` se distribuye vía `linuxbrew` o GitHub Releases.
  Versión probada en este proyecto: `1.43.2`.
- El servicio `engram serve` se inicia como proceso de usuario.
  Validar que esté vivo antes de empezar cualquier sesión SDD.
- Si la máquina es throw-away, el bootstrap completo debería
  ejecutarse al provisionar (Dockerfile, devcontainer, etc.).

## 2. Bootstrap del proyecto (después de `git clone`)

```bash
# 1. Clonar
git clone <repo-url> AtlasCommerce
cd AtlasCommerce

# 2. Activar las skills commiteadas
./scripts/install-agent-skills.sh

# 3. Activar el git hook de pre-commit (Docker quality-check)
./scripts/install-git-hooks.sh
```

Los symlinks en `.claude/skills/` apuntan a `docs/agent-skills/`. Es
idempotente — se puede re-correr sin romper.

El git hook setea `core.hooksPath=.githooks` en este repo, así
`.githooks/pre-commit` corre automáticamente antes de cada commit.

Más detalles en
[scripts/README.md](scripts/README.md "scripts/README.md") y en
[.claude/skills/README.md](.claude/skills/README.md ".claude/skills/README.md").

## 3. Bootstrap global (una vez por máquina)

Tres piezas que viven en el home del usuario, **no en el repo**:

### 3.1 Marketplace wshobson

Plugins de comunidad (49 agentes, ~50 skills) que el orquestador y
los reviewers consumen.

```bash
./scripts/install-marketplace-skills.sh
```

Clona `wshobson/agents` a `~/agents-wshobson`, genera artefactos
compatibles con OpenCode y deja symlinks en
`~/.config/opencode/{skills,agents,commands}/`. Soporta `--clean`
para desinstalar.

### 3.2 Skills ad-hoc

Para skills descargadas vía `npx` (no son del repo, son globales
para el usuario). Ejemplo: la skill `redis-connections` instalada en
este proyecto vive en `~/.agents/skills/redis-connections`.

```bash
npx skills add <github-url> --skill <nombre> -g -y
```

Después de instalar, verificar manualmente que el symlink aparezca
en las rutas que `opencode` escanea (`~/.config/opencode/skills/`,
`~/.claude/skills/`) — `npx skills add -g` no siempre los crea.

### 3.3 MCP servers

Se configuran en `~/.config/opencode/opencode.json` bajo la clave
`mcp`. Este proyecto asume que existen:

- `codegraph` — índice de código del repo.
- `context7` — documentación de librerías al día.
- `engram` — memoria persistente entre sesiones.

Los prompts detallados de cada MCP server viven fuera del repo
(`~/.config/opencode/`) y se documentan como personalization — no
son parte del proyecto.

## 4. Verificación

Tras correr los pasos 2 y 3:

```bash
# Skills del proyecto activas
ls .claude/skills/ | wc -l    # debería mostrar 16+

# Marketplace cargado
ls ~/.config/opencode/agents/ | wc -l   # ~49

# Registry fresco
gentle-ai skill-registry refresh --cwd . --force

# Memoria operativa
engram mem_search query="sdd-init/atlas-commerce" project="atlas-commerce"
```

Si algún paso falla, revisar la sección "Solución de problemas"
más abajo antes de pedir ayuda.

## 5. Project vs Global vs Personal

Tres niveles de configuración, con reglas claras:

| Nivel     | Ubicación                                       | Commiteado | Quién lo controla |
| --------- | ----------------------------------------------- | ---------- | ----------------- |
| Proyecto  | `docs/agent-skills/`, `backend/opencode.json`, `frontend/AGENTS.md`, este doc, `scripts/` | Sí         | El equipo         |
| Global    | `~/.config/opencode/`, `~/.agents/skills/`, `~/.claude/skills/` | No         | La máquina del dev |
| Personal  | Prompts custom, aliases de shell, preferencias del orquestador | No         | El dev individual |

Reglas:

- **Si es del proyecto → va al repo.** Si un dev nuevo lo necesita,
  tiene que estar commiteado para que el `git clone` lo traiga.
- **Si es del usuario → vive en `~/`.** No se commitea, se documenta
  acá cómo se regenera.
- **No mezcles ambos.** Si una config "personal" resulta útil para
  todo el equipo, promovéla a "proyecto" en un PR.

## 6. Limitación conocida del skill registry

`.atl/skill-registry.md` se regenera vía
`gentle-ai skill-registry refresh --force`. Es un índice pensado
para que el orquestador sepa qué skills inyectar al lanzar
sub-agentes.

Hoy **sólo lista skills con alcance `user` o `project` que están
symlinkeadas al momento del scan**. Como las skills commiteadas en
`docs/agent-skills/` requieren correr primero
`scripts/install-agent-skills.sh` para aparecer en
`.claude/skills/`, el registry puede omitirlas tras un clone fresco.

Workaround hasta que se arregle el scanner:

```bash
./scripts/install-agent-skills.sh
gentle-ai skill-registry refresh --cwd . --force
```

El campo `Scope` en el registry distingue `project` (vive en el
repo, `docs/agent-skills/`) de `user` (vive en la máquina,
`~/.config/opencode/skills/` u otros globales).

## 7. Solución de problemas

### Las skills del proyecto no aparecen en el registry

```bash
ls .claude/skills/                      # ¿están los symlinks?
./scripts/install-agent-skills.sh        # re-correr, idempotente
gentle-ai skill-registry refresh --cwd . --force
```

### El orquestador dice "sub-agent not found"

Falta instalar el marketplace.

```bash
./scripts/install-marketplace-skills.sh
ls ~/.config/opencode/agents/ | head    # debería listar sdd-*, jd-*, review-*
```

### `mem_save` falla con error de MCP

El servicio `engram` no está corriendo.

```bash
pgrep -f "engram serve" || engram serve &
```

### Las skills `npx` quedan solo en `~/.agents/`

`npx skills add -g -y` no siempre simlinkea a las rutas que `opencode`
escanea. Completar manualmente:

```bash
ln -s ~/.agents/skills/<nombre> ~/.config/opencode/skills/<nombre>
ln -s ~/.agents/skills/<nombre> ~/.claude/skills/<nombre>
```

## Referencias

- [scripts/install-agent-skills.sh](../scripts/install-agent-skills.sh "../scripts/install-agent-skills.sh")
- [scripts/install-marketplace-skills.sh](../scripts/install-marketplace-skills.sh "../scripts/install-marketplace-skills.sh")
- [scripts/install-git-hooks.sh](../scripts/install-git-hooks.sh "../scripts/install-git-hooks.sh")
- [.claude/skills/README.md](../.claude/skills/README.md ".claude/skills/README.md")
- [.atl/skill-registry.md](../.atl/skill-registry.md ".atl/skill-registry.md")
