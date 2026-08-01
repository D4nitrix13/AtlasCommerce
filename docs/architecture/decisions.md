# Decisiones Arquitectónicas (ADR)

Registro de decisiones de arquitectura
significativas. Cada entrada describe una decisión,
su contexto, alternativas consideradas y
consecuencias.

---

## ADR-001: Una sola base de datos para ambas tiendas

**Fecha:** 2026-06
**Estado:** Aceptada

### ADR-001 - Contexto

Panda Estampados y Kitsune son dos tiendas con
identidad separada pero operación compartida. Se
necesita decidir si cada tienda tiene su propia
base de datos o si comparten una sola instancia.

### ADR-001 - Alternativas consideradas

1. **Database per tenant** — Una instancia
   `PostgreSQL` por tienda.
2. **Schema per tenant** — Un esquema
   `PostgreSQL` por tienda en la misma instancia.
3. **Shared database con `store_id`** — Una sola
   instancia, tablas con campo `store_id`.

### ADR-001 - Decisión

Shared database con `store_id` en las tablas
transaccionales.

### ADR-001 - Consecuencias

- **Positivas:** Una sola instancia que mantener,
  respaldar y escalar. Consultas cross-store
  simples. Inventario compartido natural.
- **Negativas:** Riesgo de filtrar datos entre
  tiendas si el `store_id` no se aplica
  correctamente. Las migraciones afectan a todas
  las tiendas.
- **Mitigación:** Middleware que inyecta
  `store_id` automáticamente. Policies que
  validan acceso por tienda.

---

## ADR-002: Un solo backend API centralizado

**Fecha:** 2026-06
**Estado:** Aceptada

### ADR-002 - Contexto

Se necesita decidir si cada tienda tiene su propia
`API` o si ambas comparten una sola.

### ADR-002 - Alternativas consideradas

1. **API per tenant** — Una instancia `Laravel`
   por tienda.
2. **API centralizada** — Una sola instancia con
   contexto de tienda.

### ADR-002 - Decisión

`API` centralizada. El contexto de tienda se
resuelve por dominio (storefront) o por
parámetro/header (admin).

### ADR-002 - Consecuencias

- **Positivas:** Una sola base de código, una
  sola despliegue, una sola superficie de
  seguridad.
- **Negativas:** La lógica de filtrado por tienda
  debe ser consistente en todos los controladores.
- **Mitigación:** Middleware `ResolveStoreFromDomain`
  y scope global en modelos.

---

## ADR-003: Un solo frontend multi-dominio

**Fecha:** 2026-06
**Estado:** Aceptada

### ADR-003 - Contexto

Se necesita decidir si cada tienda tiene su propio
repositorio frontend o si comparten uno.

### ADR-003 - Alternativas consideradas

1. **Frontend per tenant** — Un repo por tienda.
2. **Frontend multi-tenant** — Un solo repo que
   resuelve tienda por dominio.

### ADR-003 - Decisión

Frontend multi-tenant con `Astro`. El mismo build
sirve Panda y Kitsune. La tienda actual se resuelve
a partir del dominio de origen.

### ADR-003 - Consecuencias

- **Positivas:** Una sola base de código,
  componentes compartidos, despliegue único.
- **Negativas:** Complejidad adicional en el
  frontend para resolver el contexto de tienda.
- **Mitigación:** `src/lib/store-context.ts`
  resuelve la tienda desde el dominio.
  Configuración por tienda en
  `src/config/stores.ts`.

---

## ADR-004: `Caddy` como reverse proxy

**Fecha:** 2026-06
**Estado:** Aceptada

### ADR-004 - Contexto

Se necesita un reverse proxy que gestione TLS y
enrute tráfico a los servicios internos.

### ADR-004 - Alternativas consideradas

1. **Nginx** — Reverse proxy popular, configuración
   manual de certificados.
2. **Caddy** — Reverse proxy con TLS automático
   via Let's Encrypt.
3. **Traefik** — Reverse proxy con integración
   `Docker` nativa.

### ADR-004 - Decisión

`Caddy`. TLS automático, configuración mínima,
integración sencilla con `Docker`.

### ADR-004 - Consecuencias

- **Positivas:** Certificados TLS automáticos,
  configuración simple, renovación automática.
- **Negativas:** Menor ecosistema de plugins que
  Nginx.
- **Mitigación:** Para este caso de uso (reverse
  proxy + TLS), `Caddy` es suficiente.

---

## ADR-005: Inventario compartido inicialmente

**Fecha:** 2026-06
**Estado:** Aceptada

### ADR-005 - Contexto

Ambas tiendas venden productos del mismo almacén.
Se necesita decidir si el inventario es global o
por tienda.

### ADR-005 - Alternativas consideradas

1. **Inventario global** — Un solo stock para
   ambas tiendas.
2. **Inventario por tienda** — Stock separado por
   tienda.
3. **Inventario por almacén-tienda** — Stock por
   combinación almacén × tienda.

### ADR-005 - Decisión

Inventario global. Las tablas `inventories` y
`warehouses` no tienen `store_id`.

### ADR-005 - Consecuencias

- **Positivas:** Simpleza, sin riesgo de stock
  duplicado, sin necesidad de sincronización.
- **Negativas:** No se puede tener stock diferente
  por tienda para el mismo producto.
- **Mitigación:** Si se necesita en el futuro, se
  agrega `warehouse_store` o similar.

---

## ADR-006: Documentación en español con nombres técnicos en inglés

**Fecha:** 2026-06
**Estado:** Aceptada

### ADR-006 - Contexto

El equipo habla español pero el código fuente está
en inglés.

### ADR-006 - Decisión

La documentación se escribe en español. Los nombres
técnicos (clases, archivos, rutas, endpoints,
tablas) se mantienen en inglés.

### ADR-006 - Consecuencias

- **Positivas:** Documentación accesible para el
  equipo, nombres técnicos consistentes con el
  código.
- **Negativas:** Mezcla de idiomas que puede
  resultar informal.

---

## ADR-007: Sanctum Bearer tokens para autenticación

**Fecha:** 2026-06
**Estado:** Aceptada

### ADR-007 - Contexto

La `API` necesita autenticar peticiones del
frontend (storefront y admin).

### ADR-007 - Alternativas consideradas

1. **Session-based** — Sesiones tradicionales de
   `Laravel`.
2. **Sanctum SPA tokens** — Tokens basados en
   cookies.
3. **Sanctum Bearer tokens** — Tokens de `API`
   stateless.
4. **JWT** — JSON Web Tokens con paquete externo.

### ADR-007 - Decisión

Sanctum Bearer tokens. Stateless, escalable,
compatible con cualquier cliente.

### ADR-007 - Consecuencias

- **Positivas:** Sin estado en el servidor,
  compatible con `Astro`, `React`, móviles.
- **Negativas:** No hay invalidación inmediata de
  tokens (depende de expiración).
- **Mitigación:** Tokens con expiración corta,
  refresh tokens si se necesita.
