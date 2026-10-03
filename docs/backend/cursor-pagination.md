# Cursor Pagination — Performance escalable para listados

> **RNF-PERF-022**: Paginación cursor-based obligatoria en todos los
> listados que puedan crecer más allá de ~10K registros.

## Por qué cursor vs offset

`->paginate(N)` en Laravel usa **OFFSET** internamente:

```sql
SELECT * FROM orders ORDER BY id DESC LIMIT 20 OFFSET 100000;
```

Esto obliga al motor a **escanear + descartar 100.000 filas** antes de
devolver 20. El costo crece **linealmente** con la profundidad de
paginación: `O(n + k)`.

`->cursorPaginate(N)` usa un **CURSOR** (encoded last_id):

```sql
SELECT * FROM orders WHERE id < 9999 ORDER BY id DESC LIMIT 20;
```

Es un **range scan** sobre el índice. Costo constante: `O(log n + k)`.
Con 1 millón de filas, página 1000: cursor tarda milisegundos,
OFFSET tarda segundos.

## Cuándo usar cada uno

| Escenario | Recomendación |
| --- | --- |
| Tabla con < 1.000 registros | `->paginate()` es OK (UX simple) |
| Tabla con > 10.000 registros | **Cursor obligatorio** (perf) |
| Feed / scroll infinito | **Cursor obligatorio** (UX) |
| Admin tables con sort complejo | Cursor con orden estable |

## Implementación: trait `CursorPaginates`

El trait en `app/Http/Concerns/CursorPaginates.php` provee:

```php
use App\Http\Concerns\CursorPaginates;

class CustomerController extends Controller
{
    use CursorPaginates;

    public function index(Request $request): JsonResponse
    {
        // El último orderBy DEBE ser único + indexado (típicamente `id`).
        $query = Customer::query()->where(...)->orderBy('email')->orderBy('id');

        return $this->cursorPaginatedResponse(
            request: $request,
            query: $query,
            resourceClass: AdminCustomerResource::class,
        );
    }
}
```

## Shape del response

```json
{
  "data": [
    { "id": 123, "email": "...", ... },
    ...
  ],
  "meta": {
    "next_cursor": "eyJpZCI6MTIzfQ==",     // base64, para próxima página
    "prev_cursor": "eyJpZCI6NDZ9",         // null en primera página
    "has_more": true,                        // false en última página
    "per_page": 20                           // efectivo después de clamp
  }
}
```

**Importante**: cursor pagination NO retorna `total`. Eso requeriría un
COUNT(*) extra (caro a escala). Para conocer el total el cliente usa
`has_more` + navegación.

## Convenciones

1. **`orderBy` debe terminar en columna única e indexada.** Laravel
   requiere esto para garantizar cursor estable:

    ```php
    $query->orderBy('email')->orderBy('id'); // ✓ email para sort, id para cursor
    $query->orderBy('email');                 // ✗ email no es único
    ```

2. **`per_page` se clampea a 100.** Más que eso degrada la experiencia
   y satura la DB. Para exports masivos usar endpoint dedicado
   (`GET /api/v1/admin/exports/orders`).

3. **`per_page` mínimo es 1.** Valores como 0 o negativos se tratan
   como 1.

4. **Cursor inválido se ignora.** Si el cliente envía un `?cursor=`
   corrupto, Laravel retorna la primera página sin error.

## Frontend

El cliente sigue la navegación así:

```ts
let cursor: string | null = null;
async function loadMore() {
    const url = cursor
        ? `/api/v1/admin/customers?cursor=${encodeURIComponent(cursor)}`
        : `/api/v1/admin/customers`;
    const { data, meta } = await apiFetch(url);
    render(data);
    cursor = meta.next_cursor; // null = última página
}
```

## Tests

`tests/Feature/Concerns/CursorPaginatesTest.php` cubre:

- Shape del response estable.
- `per_page` clamp a 1..100.
- `has_more` correcto en primera/última página.
- Navegación cursor a través de 3 páginas.
- Cursor inválido se ignora silenciosamente.

## Migración gradual

El trait está disponible — cualquier controller puede adoptarlo sin
breaking change del resto. Recomendado para la próxima iteración:

| Controller | Status |
| --- | --- |
| `Admin/CustomerController` | ✅ Migrado (PR #100) |
| `Sales/OrderController` | ✅ Migrado (PR #100) |
| `Admin/ProductController` | Pendiente |
| `Admin/CouponController` | Pendiente |
| Resto | Pendiente (PRs siguientes) |

## Referencias

- [Laravel Docs — Cursor Pagination](https://laravel.com/docs/11.x/pagination#cursor-pagination)
- PR #100 — implementación del trait + CustomerController + OrderController.
