# Conventions First — Workflow de carga de reglas

> **Regla load-bearing.** Antes de CUALQUIER edición, commit, o
> investigación de código, **leer primero** las convenciones del scope
> en el que vas a trabajar. Si entrás a tocar un path sin leer las
> reglas, vas a violar convenciones automatizadas en CI.

## Por qué existe esta regla

En sessions previas el agente:
- Asumió pnpm cuando el proyecto usa bun (violación de convención
  load-bearing en `docs/operations/tech-stack.md`).
- Hardcodeó Fender/Gibson cuando el proyecto puede ser brand-agnostic
  (violación del principio de "factory + spec" introducido en Fase 6).
- Creó archivos en paths equivocados por no leer AGENTS.md del scope.
- Generó commits con scope/body en lugar de seguir conventional
  commits del proyecto.

Todos estos problemas se hubieran prevenido cargando las reglas
del scope ANTES de tocar código.

## Workflow obligatorio

Antes de CUALQUIER task que involucre edición:

1. **Identificar el scope** del path que vas a tocar (frontend, backend,
   database, docs, docker, assets).
2. **Cargar las reglas** del scope:
   - Path glob → reglas aplicables (ver `.ai/rules/index.md`)
   - `AGENTS.md` del scope (frontend, backend) si existe
   - Skill load-bearing si aplica (ver `.ai/rules/index.md` §"Cómo cargar")
3. **Confirmar entendimiento** — si una regla contradice tu intuición,
   la regla gana. Documentar la excepción en la memoria con
   evidencia si aplica.
4. **Proceder** con la edición siguiendo las convenciones.

## Cuándo se salta este workflow

- Investigación read-only sin edición (grep, read, code search).
- Debugging de runtime (logs, stack traces).
- Respuestas conversacionales que no tocan código.

## Cuándo falla este workflow

Si te das cuenta que ya tocaste código sin haber leído las reglas:
1. **STOP**. No continues.
2. `git diff` para ver qué cambiaste.
3. Volver a leer las reglas del scope.
4. Aplicar las correcciones necesarias antes de continuar.

## Cómo reportar cuando algo está mal en las reglas

- Si encontrás una regla obsoleta o incorrecta → abrir issue o PR
  con la corrección propuesta.
- Si una regla contradice AGENTS.md → gana AGENTS.md (este
  directorio complementa, no reemplaza).
- Si falta una regla para algo repetitivo → proponer el archivo
  en `.ai/rules/{categoria}/`.

## Memoria persistente

Cuando guardes memoria con `mem_save`, agregar SIEMPRE en el
`content` una línea que diga:

```
Convenciones cargadas: [.ai/rules/X, AGENTS.md/Y, skill/Z]
```

Esto permite a futuras sessions saber qué reglas se aplicaron.