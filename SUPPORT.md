# Soporte — AtlasCommerce

> Canales oficiales para pedir ayuda, reportar bugs y proponer features.

## Canales

| Canal | Caso de uso | SLA aproximado |
| ----- | ----------- | -------------- |
| **GitHub Issues** | Bugs, requests de features, preguntas técnicas | best-effort |
| **GitHub Discussions** | Preguntas generales, ideas, feedback | best-effort |
| **Email `support@atlas-commerce.dev`** | Consultas privadas, soporte comercial | 1-3 días hábiles |
| **Email `security@atlas-commerce.dev`** | **Vulnerabilidades** — ver [`SECURITY.md`](SECURITY.md) | 48h hábiles para acuse |
| **Conducta** — `conduct@atlas-commerce.dev` | Infracciones al [`CODE_OF_CONDUCT.md`](CODE_OF_CONDUCT.md) | 48h hábiles |

## Cómo abrir un buen issue

Antes de abrir un ticket:

1. Buscá en issues existentes — tal vez ya hay un hilo relacionado.
2. Para bugs: usá la **plantilla de bug report** (se completa sola al
   abrir el issue).
3. Para features: usá la **plantilla de feature request**.
4. Para preguntas generales: abrí una **Discussion**, no un issue.

Un buen issue incluye:

- Contexto mínimo reproducible.
- Resultado esperado vs resultado real.
- Entorno: branch, commit SHA, versiones de stack relevante.
- Logs, screenshots o stack traces (anonimizá datos sensibles).

## Tenants y soporte por tienda

Este monorepo soporta múltiples tiendas (Panda Estampados, Kitsune Store,
MM Boutique, Morales Store). Si el problema afecta a una tienda
**específica**, aclaralo en el título del issue:

```text
[tenant:panda-estampados] <descripción>
```

Eso ayuda a mantener el triage y los labels organizados.

## Versiones bajo soporte

Solo `master` recibe soporte oficial. Las ramas `feat/*` se consideran
en desarrollo.

---

Última actualización: 2026-09-09
