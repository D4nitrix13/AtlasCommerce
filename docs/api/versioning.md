# API Versioning — Convenciones

> **RNF-MAIN-054**: Todos los endpoints de la API están bajo el prefijo
> `/api/v1/`, dejando libre `/api/` para futuras versiones.

## URL actual

Todas las rutas activas:

```
/api/v1/{recurso}
```

Ejemplos:

- `POST /api/v1/auth/login`
- `GET /api/v1/storefront/promotions`
- `GET /api/v1/admin/customers/{id}`
- `POST /api/v1/account/addresses/{id}/primary`

## Versiones futuras

Cuando se introduzca una versión incompatible:

1. Crear `routes/api-v2.php` con las rutas actualizadas.
2. Registrar en `bootstrap/app.php` con prefix `api/v2`.
3. Mantener `api/v1` durante un período de deprecación.
4. Marcar `v1` como deprecated vía middleware (header `Deprecation`).
5. Documentar la fecha de sunset en este archivo.

## Breaking changes policy

Un cambio es **breaking** (requiere nueva versión) si:

- Elimina un endpoint.
- Cambia el shape del response de manera incompatible.
- Cambia el significado de un campo existente.
- Cambia el status code esperado.

Un cambio es **non-breaking** (mantener v1) si:

- Agrega un endpoint nuevo.
- Agrega campos opcionales al response.
- Agrega parámetros opcionales al query string.
- Agrega un nuevo valor a un enum existente.

## Convenciones para el versionado

- **Prefijo URL**: `/api/v{n}/` donde `n` es entero incremental.
- **No usar header** `Accept: application/vnd.api+json;version=1` — el
  versionado por URL es más explícito y cache-friendly.
- **Documentar changelog**: cada release con breaking changes debe
  actualizar `docs/api/CHANGELOG.md` con la lista de cambios incompatibles.

## Tests

Los tests de feature deben apuntar a `/api/v1/`:

```php
$response = $this->getJson(uri: "http://{$this->host}/api/v1/storefront/promotions");
```

Helpers en `tests/TestCase.php` para centralizar el host + v1 prefix.

## Frontend

El objeto `API_ENDPOINTS` en `frontend/src/lib/endpoints.ts` usa el
prefijo `/v1/` consistente:

```typescript
export const API_ENDPOINTS = {
  auth: { login: '/v1/auth/login' },
  storefront: { promotions: '/v1/storefront/promotions' },
  // ...
};
```

Cuando se cree `v2`, agregar `API_ENDPOINTS_V2` y un helper para
seleccionar según feature flag.

## Estado actual

| Versión | Status | Sunset |
| --- | --- | --- |
| `/api/v1/` | **Activa** | — |
| `/api/` | Eliminada en este PR (breaking change) | n/a |

## Referencias

- PR #98 — implementación del versionado inicial.
- [Microsoft API Guidelines — Versioning](https://github.com/microsoft/api-guidelines/blob/vNext/Guidelines.md#12-versioning)
