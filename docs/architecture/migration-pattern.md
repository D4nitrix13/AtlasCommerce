# Migration Pattern — AtlasCommerce

> **Status**: formalizado en Fase 4 del image architecture plan (Q1 2026).
> Última actualización: 2026-10-17.

Este documento define el patrón de migraciones del proyecto. Es la referencia
canónica para nuevos schemas y la base para revisar migraciones existentes en
code review.

---

## 1. Principio rector

Cada tenant del monorepo multi-instancia corre las **mismas migraciones** sobre
su propia base de datos. La separación entre tenants es **infraestructural**
(instancias separadas con seeds distintos), no **de schema** (no hay columnas
discriminadoras tipo `tenant_id`).

Implicaciones:

- Una migración que falla en un tenant falla conceptualmente para todos.
- No existe `if (Schema::hasColumn('users', 'foo'))` con intención de
  customizar por tenant — eso es customización de schema, no se permite.
- Features que requieren datos específicos de un tenant usan **JSON columns**
  o **tablas de extensión**, no columnas dedicadas.

---

## 2. Default — sin guards en migrations

El default del proyecto es **NO** escribir:

```php
if (Schema::hasColumn(table: 'users', column: 'last_login_at')) { ... }
if (Schema::hasTable(table: 'foo')) { ... }
if (Schema::hasIndex(...)) { ... }
```

Si la lógica es condicional (ej. "agregar columna solo si no existe"),
vive en código de runtime — un Factory, un Seeder, un Repository —
**nunca** en una migración. Las migraciones son universales por
contrato y la lógica condicional rompe ese contrato.

La razón operativa: un grep `Schema::has*` en migraciones debe devolver
0 hits por default. Si tu feature requiere un guard, debe estar en
sección §3 con justificación explícita.

---

## 3. Excepciones legítimas

Un guard en una migración es aceptable solo si cumple **todas** estas
condiciones:

1. **Defensa contra half-applied migrations** — la migración B depende de
   que la migración A haya corrido completa. Si A falló y se retomó desde
   un punto intermedio, B necesita el guard para no duplicar trabajo.
2. **Docblock obligatorio** — la migración lleva un comentario PHPDoc de
   al menos 3 líneas explicando el "por qué" del guard, el escenario que
   cubre y la alternativa considerada.
3. **Idempotencia verificable** — la migración puede correrse dos veces
   seguidas sin fallar ni duplicar datos. El guard está en el **estado
   NUEVO**, no en el estado VIEJO (ver §4 anti-pattern).

### Ejemplo legítimo — backfill defensivo

`backend/database/migrations/catalog/2026_06_22_032014_create_category_product_table.php`:

```php
public function up(): void
{
    Schema::create('category_product', function (Blueprint $table): void {
        // ... definición de pivot ...
    });

    // Backfill: poblar la pivot con la categoría principal de cada producto.
    // `products.category_id` se mantiene como "categoría principal" para
    // queries del storefront; la pivot habilita la asignación M:N.
    if (Schema::hasTable('products') && Schema::hasColumn('products', 'category_id')) {
        DB::table('category_product')->insertUsing(
            ['category_id', 'product_id', 'created_at', 'updated_at'],
            DB::table('products')
                ->select('category_id', 'id', 'created_at', 'updated_at')
                ->whereNotNull('category_id'),
        );
    }
}
```

El guard existe porque el backfill depende de que la tabla `products` exista
con la columna `category_id` poblada. Si la migración de `products` falló a
mitad de camino, esta migración no debe crashear — solo omitir el backfill
que se puede correr manualmente después.

### Ejemplo legítimo — defensa contra baseline incompleto

`backend/database/migrations/auth/2026_08_17_000002_add_last_login_at_to_users.php`:

```php
/**
 * Garantiza que `users.last_login_at` exista.
 *
 * La migración original `2026_06_22_032005_create_users_table.php`
 * ya incluye la columna (línea 46), pero esta migración queda
 * como defensa contra escenarios donde el baseline se aplicó sin
 * esa columna (instalaciones legacy). El guard `hasColumn` la hace
 * idempotente — la re-corrida no falla.
 */
public function up(): void
{
    if (! Schema::hasColumn(table: 'users', column: 'last_login_at')) {
        Schema::table(table: 'users', callback: function (Blueprint $table): void {
            $table->timestamp(column: 'last_login_at')
                ->nullable()
                ->after(column: 'password_changed_at');
        });
    }
}
```

El guard existe para hacer la migración idempotente — si la columna ya
existe (porque el baseline la creó), no se duplica. La condición se chequea
en el **estado NUEVO** (la columna que esta migración intenta agregar),
que es la forma correcta.

---

## 4. Anti-patterns

### Guard en el estado VIEJO (rompe idempotencia)

`backend/database/migrations/shipping/2026_07_29_000001_convert_shipping_zones_base_cost_to_cents.php`:

```php
public function up(): void
{
    Schema::table('shipping_zones', function (Blueprint $table): void {
        $table->unsignedBigInteger('base_cost_cents')->default(0)->after('description');
    });

    if (Schema::hasColumn('shipping_zones', 'base_cost')) {
        // Migrar valores existentes: 12.34 → 1234.
        DB::statement(...);
        Schema::table('shipping_zones', function (Blueprint $table): void {
            $table->dropColumn('base_cost');
        });
    }
}
```

**Problema**: el guard está en el estado **VIEJO** (`base_cost` existe),
pero la columna NUEVA (`base_cost_cents`) se agrega **sin guard**. Re-correr
la migración falla porque `base_cost_cents` ya existe.

**Fix correcta**: invertir el guard.

```php
public function up(): void
{
    if (Schema::hasColumn('shipping_zones', 'base_cost_cents')) {
        return; // Ya aplicada, idempotente.
    }

    Schema::table('shipping_zones', function (Blueprint $table): void {
        $table->unsignedBigInteger('base_cost_cents')->default(0)->after('description');
    });

    if (Schema::hasColumn('shipping_zones', 'base_cost')) {
        DB::statement(...);
        Schema::table('shipping_zones', function (Blueprint $table): void {
            $table->dropColumn('base_cost');
        });
    }
}
```

**Status**: este refactor NO se aplica en Fase 4 porque la migración está
en producción en todos los tenants. Programado para Fase 6 cleanup con
procedimiento de rollback coordinado.

### Otros anti-patterns

- **`if (Schema::hasTable(...))` envolviendo toda la migración** — significa
  que tu feature depende del schema de otra tabla. Usá foreign key
  constraint o mové la lógica a un Seeder.
- **Migración que modifica el schema según el resultado de un query** —
  `if (DB::table('foo')->exists())` para decidir si agregar columna.
  Idempotencia imposible.
- **`try { ... } catch (...) { ... }` en migración** — enmascara errores
  reales del schema. Si una migración puede fallar legítimamente, dividila
  en dos migraciones con guards explícitos.

---

## 5. Datos tenant-specific

Cuando una feature requiere datos que solo aplican a ciertos tenants
(por ejemplo, configuración regional, flags de feature, metadata
custom), hay tres opciones. La decisión depende del tamaño y la
naturaleza del dato.

### Opción A — columna JSON en tabla existente

Para valores chicos (<10KB total por fila) y schema flexible.

```php
$table->json('custom_properties')->nullable();
```

Ejemplo existente: `media.custom_properties` (Spatie-shaped, dead code
al momento de escribir este doc — ver §7).

**Cuándo usar**: configuración por instancia, flags binarios, metadatos
puntuales, listas cortas de pares clave-valor.

**Cuándo NO usar**: datos relacionales (FK a otras tablas), datos que
requieren índices específicos, datos que superan ~10KB.

### Opción B — tabla de extensión por tenant

Para configuraciones grandes o que requieren queries específicas.

```php
Schema::create('tenant_metadata', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('tenant_id')->constrained();
    $table->string('key', 100);
    $table->json('value')->nullable();
    $table->timestamps();
    $table->unique(['tenant_id', 'key']);
});
```

**Cuándo usar**: configuraciones por feature que suman >10KB, valores
que se consultan frecuentemente, datos que requieren versionado.

**Cuándo NO usar**: una sola feature con poco dato (preferir Opción A),
entidades con identidad propia (preferir Opción C).

### Opción C — tabla por feature (universal)

Para entidades nuevas que son parte del core del schema.

```php
Schema::create('product_variants', function (Blueprint $table): void {
    $table->id();
    $table->foreignId('product_id')->constrained();
    $table->string('sku', 64)->unique();
    // ... columnas específicas del feature ...
});
```

**Cuándo usar**: entidades con identidad propia, relaciones con otras
tablas, queries que requieren índices dedicados.

**Cuándo NO usar**: si el dato es opcional o específico de algunos
tenants — preferir A o B.

### Decision tree

```text
¿El dato tiene identidad propia (id, FK a otras tablas)?
├── Sí → Opción C (tabla por feature)
└── No
        ├── ¿Supera ~10KB total por fila?
        │   ├── Sí → Opción B (tabla de extensión)
        │   └── No → ¿Requiere queries/índices específicos?
        │           ├── Sí → Opción B
        │           └── No → Opción A (columna JSON)
```

---

## 6. Refactors peligrosos

Las siguientes migraciones contienen guards que **no se refactorizan sin
coordinación explícita**:

| Migration | Status actual | Riesgo de refactor |
|---|---|---|
| `convert_shipping_zones_base_cost_to_cents.php` | Anti-pattern documentado §4 | Migración histórica aplicada en todos los tenants. Refactor requiere procedimiento zero-downtime (shadow column + cutover). Programado para Fase 6. |
| `add_last_login_at_to_users.php` | Legítimo §3 | Si se refactoriza el guard, validar que tenants con baseline incompleto sigan funcionando. Probablemente requiere nueva migración, no modificación de la existente. |
| `create_category_product_table.php` | Legítimo §3 | Bajo riesgo — el guard es puramente opcional (skip backfill si tabla no existe). Refactor no aporta valor. |

**Regla general**: una migración aplicada en producción es **histórica**.
Cualquier cambio a su lógica debe ser una **nueva migración** que
revierta o ajuste, no una modificación del archivo original. La historia
de git debe mostrar el orden de aplicación real.

---

## 7. Referencias

- [`assets-naming-convention.md`](assets-naming-convention.md) §6 — phased
  rollout del image architecture plan; §6.3 cubre runtime protections
  que aplican este patrón.
- `media` table (`backend/database/migrations/2026_06_28_193338_create_media_table.php`)
  — schema Spatie-shaped con `custom_properties` JSON. Dead code al
  momento de escribir este doc; ver si se adopta como contenedor de
  metadata por instancia o se deja en desuso.
- `CONTRIBUTING.md` §3 — convenciones de SQL/Migraciones (foreign keys,
  índices, soft deletes, nombres snake_case, money en centavos).
- `backend/AGENTS.md` — convenciones PHP load-bearing.

---

## 8. Changelog

- **2026-10-17** — Creado en Fase 4 del image architecture plan.
  Baseline de audit: 3 migraciones con condicionales, 2 justificadas +
  1 anti-pattern documentado para refactor futuro.
