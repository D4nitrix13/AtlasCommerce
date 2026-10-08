# `docs/backend/stores/`

Multi-tienda dentro de la misma instancia Docker: el modelo
`Store`, el `StoreContext` que resuelve qué tienda atender cada
request, y los permisos por tienda.

## Contenido

- `module.md` — el módulo `Stores`: modelos, migraciones, factories,
  eventos.
- `context.md` — `Store Context`: cómo se resuelve la tienda activa
  en cada request (Host header, subdominio, sesión, etc).
- `permissions.md` — qué permisos filtran por tienda y cómo
  configurarlos.

## Audiencia

Devs backend que tocan cualquier endpoint tenant-scoped. Devs
frontend que necesiten entender por qué un user ve solo ciertas
tiendas. QA que valide aislamiento de datos.

NO confundir con multi-instancia (eso vive en
`architecture/multi-instance.md`) — este directorio es para
multi-store DENTRO de la misma instancia.
