# Tamaño y slicing de PRs — un PR = un comportamiento

> Regla load-bearing para planificar y entregar trabajo. Escala la
> revisión: PRs revisables en una sentada, sin mezclar comportamientos
> independientes. Presupuesto alineado con el delivery budget de ODD
> (~400 líneas autoras).

## Presupuesto

- **Un PR = un comportamiento revisable** (fix + sus tests + sus docs):
  una historia completa que se puede aprobar o revertir sola.
- **~400 líneas autoras** (adiciones + eliminaciones, sin archivos
  generados) como techo orientativo por PR/work-unit. No es un hard cap:
  si la solución correcta lo excede, decir por qué y continuar — nunca
  recortar tests ni dividir artificialmente para cumplir el número.
- Si una feature supera el presupuesto: **encadenar PRs** (slices) con
  límites explícitos — cada slice entrega algo funcionando, no andamio
  muerto. Usar los skills `chained-pr` y `work-unit-commits` (resolverlos
  por nombre de registro; no hardcodear paths). Las fronteras de slice
  quedan anotadas en `odd/tasks/<feature>.md`.

## Commits dentro del PR

- Cada commit es una work-unit: código + tests + docs juntos, Conventional
  Commits (`CONTRIBUTING.md` §7). Nada de "fix typo" al final corrigiendo
  el propio commit anterior del PR.
- Un commit que rompe la suite no debe existir en la rama (el PR entero
  debe estar verde — `workflow/quality-gates.md`).
- Sin refactorings mezclados con el fix que se revisa: el foco del PR es
  su comportamiento; el ruido de estilo se va en PR aparte.

## Merge

- Estrategia según convenga (squash para slices chicos ruidosos, rebase
  para historial limpio) — `CONTRIBUTING.md` §7. El merge local
  `--no-ff` con `Merge pull request #N …` + push master es el flujo
  estándar de este repo (ver `workflow/multi-session-isolation.md` §3).
