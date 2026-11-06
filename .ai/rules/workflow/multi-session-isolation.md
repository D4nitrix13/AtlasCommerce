# Aislamiento multi-sesión — worktrees, estado visible y entrega

> Regla load-bearing para toda sesión que trabaje en este repo mientras
> haya (o pueda haber) otra sesión en paralelo. Motivada por los
> incidentes 2026-11-05/06: sesiones compartiendo un working tree se
> pisaron commits, ramas y archivos (commits ajenos cayendo en la rama
> equivocada, ediciones i18n simultáneas, trabajo "Ready for PR" sin PR
> que desapareció de la web al cambiar de rama).

## 1. Worktree propio por sesión

- Cada sesión abre SU worktree al arrancar:
  `git -C ~/Code/AtlasCommerce worktree add ~/Code/AtlasCommerce-worktrees/<nombre> -b feat/<nombre> origin/master`
  - Ruta bajo `~/Code` (sibling `<repo>-worktrees/<nombre>`), **nunca**
    `/tmp` ni `/var/tmp`. Cada worktree lleva **su propio** `.codegraph/`
    (`codegraph init` propio; nunca copiar ni symlink el de otro checkout).
- **Nunca** trabajar en el checkout principal cuando otra sesión esté
  activa. El checkout principal es recurso compartido: solo se toca en el
  momento de integrar/entregar (§3) y de forma serializada.
- Prohibido entre sesiones:
  - editar/borrar archivos fuera del propio worktree;
  - `git checkout` / `git branch -f` de ramas de otra sesión;
  - `git commit` sin pathspec cuando haya cambios de terceros en el árbol;
  - `git add -A` / `git add .`;
  - re-apuntar el bind mount del dev frontend sin coordinar (ver
    `.ai/rules/tooling/dev-domains-and-session.md` y nota de colisión
    `:5321` en la memoria del proyecto).
- Verificación por worktree:
  - **Frontend**: `bun install` al crear el worktree; `bun run test` /
    `typecheck` / lint scoped corren dentro del worktree.
  - **Backend**: `scripts/docker-stack.sh exec ...` monta SIEMPRE el
    checkout principal — la suite backend pesada corre al integrar en
    master (§3) o en CI. No existe "docker compose directo" para
    re-apuntar el mount.

## 2. Estado de tareas siempre visible

El usuario trabaja con varias sesiones en paralelo y necesita ver, sin
preguntar, en qué va cada una:

- Cada sesión mantiene `odd/tasks/<feature>.md` con checklist canónica de
  IDs estables (`T1…Tn`) y un encabezado `> Estado: ⏳ n/m — <resumen>`.
- **Toda respuesta de trabajo termina con un bloque de estado compacto**:

  ```txt
  Estado: ✅ T1 T2 · ⏳ T3 · ⬜ T4 T5 — <qué sigue>
  ```

  `✅` listo (con evidencia: comando + resultado observado), `⏳` en
  progreso, `⬜` no iniciada. Nunca marcar ✅ sin evidencia observada.
- Al inicio de una sesión reanudada: leer el feature doc y reportar el
  estado reconciliado antes de seguir.

## 3. Entrega al terminar: push + PR + merge a master

Cuando las tareas principales del feature estén `✅` y verificadas, la
sesión **entrega** (autorización de pie del usuario, 2026-11-06: "cuando
termine de hacer las tareas principales pushea y PR + mergear a master"):

1. Verificación completa (tests + typecheck) del estado final.
2. `git push -u origin feat/<nombre>`.
3. `gh pr create --base master` (título Conventional Commit; body con
   resumen, verificación y fuera-de-alcance).
4. Merge local `--no-ff` a `master` con mensaje
   `Merge pull request #N from D4nitrix13/<rama>` + `git push origin
   master` (el PR queda MERGED); o `gh pr merge` si la policy del repo
   lo exige.

- **Entregas serializadas**: una sesión entrega a la vez (rama → PR →
  merge → push master); anunciar la intención en el bloque de estado.
- **Nada queda en "Ready for PR" sin PR**: el trabajo terminado sin
  entregar no existe para el usuario (fue el fallo 2026-11-05/06).
- Si el merge rompe tests: NO pushear master; dejar el merge local,
  reportar y corregir en la rama.
- Conflictos de cherry-pick entre ramas enredadas: entregar por
  **contenido** (cherry-picks limpios en rama nueva) y nunca mergear una
  rama que arrastre commits duplicados ajenos.
