# Prevención de N+1 — Convenciones del backend

> **RF-PERF-024**: Todo listado que use relaciones debe declararlas
> con `with()` para evitar queries N+1; un linter en CI advertirá de
> accesos a relaciones en loops.

## Qué es un N+1

Un N+1 ocurre cuando:

1. Se itera sobre una colección de modelos Eloquent (`foreach`).
2. Dentro del loop, se accede a una relación (propiedad o método) que
   dispara una query individual POR CADA item.

```php
// ❌ N+1: 1 query para orders + N queries para orders[i].customer.
$orders = Order::all();
foreach ($orders as $order) {
    echo $order->customer->name; // query por cada iteración
}

// ✅ Sin N+1: 1 query para orders + 1 query para todos los customers.
$orders = Order::with('customer')->get();
foreach ($orders as $order) {
    echo $order->customer->name; // ya está en memoria
}
```

## Regla de oro

> **Si el loop accede a `$item->relation` o `$item->relation()`,
> la relación DEBE estar eager-loaded con `with([...])` ANTES del loop.**

```php
// ✅ Patrón correcto
$orders = Order::query()
    ->with(['customer', 'items.product', 'shippingAddress'])
    ->where('status', 'pending')
    ->get();

foreach ($orders as $order) {
    // Cualquier acceso a $order->customer, $order->items, etc. NO
    // genera queries adicionales.
}
```

## Eager loading disponible

| Tipo | API | Cuándo usar |
| --- | --- | --- |
| Listado inicial | `Model::with(['rel1', 'rel2'])->get()` | Colección que se itera con accesos a relaciones |
| Lazy load selectivo | `$model->load('relation')` | Modelo individual ya cargado, queremos cargar UNA relación |
| Filtrar relación | `Model::with(['items' => fn($q) => $q->where(...)])` | Necesitamos subset específico de la relación |
| Conteos | `Model::withCount('items')->get()` | Solo necesitamos el conteo, no los modelos |

## Detección automática

El script `scripts/check-n-plus-one.sh` (parte del CI) escanea `backend/app/`:

```bash
./scripts/check-n-plus-one.sh
```

Busca:

- `foreach (...)` sobre colecciones.
- Dentro del bloque (15 líneas siguientes): accesos `$var->method()` o
  `$var->prop->` donde `method`/`prop` es camelCase >=4 chars.
- Excluye atributos y métodos comunes de Eloquent (`id`, `update()`,
  `save()`, `created_at`, `*_id`, etc) via denylist interna.

### Suprimir falsos positivos

Si el script flaguea algo que **NO** es N+1 (ej:
`$invoice->paidAmountCents()` que es un método del modelo, no una
relación):

**Opción 1** (preferida): agregar `@phpstan-ignore-next-line` en la línea:

```php
foreach ($invoices as $invoice) {
    // @phpstan-ignore-next-line
    $paidCents = $invoice->paidAmountCents();
    // ...
}
```

**Opción 2**: agregar `path:line` en `.n-plus-one.ignore` (whitelist):

```text
backend/app/Services/Sales/OrderService.php:91
```

## Cuándo SÍ es aceptable una relación en loop

- **Bulk update/delete en loop**: `$item->update([...])` es legítimo y
  el script lo ignora (está en denylist).
- **Generación de side-effects**: `$item->notify()`, `$item->sendEmail()`
  — actions que ejecutan UNA operación por item y son intencionales.
  Si esto se vuelve un hotspot de performance, convertir a `Queue::push`
  en bulk.

## Cheat sheet de debugging

Para detectar N+1 en local con Telescope o Debugbar:

1. Activar query log: `DB::enableQueryLog()` en el método bajo test.
2. Hacer el foreach.
3. `dd(DB::getQueryLog())` — si hay N+ queries para la misma tabla
   con WHERE id = ?, es N+1.

## Tests de regresión

Cualquier test de feature que itere sobre una colección con relación
debe verificar que el número de queries es constante (no proporcional
al tamaño de la colección):

```php
DB::enableQueryLog();
$orders = $this->service->getOrders(); // itera 5 orders
$this->assertCount(7, DB::getQueryLog()); // número FIJO, no crece
```

## Referencias

- [Laravel Docs — Eloquent Relationships: Eager Loading](https://laravel.com/docs/11.x/eloquent-relationships#eager-loading)
- PR #97 — implementación del script + este doc.
