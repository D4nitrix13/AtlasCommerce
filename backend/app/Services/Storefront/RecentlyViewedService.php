<?php

declare(strict_types=1);

namespace App\Services\Storefront;

use App\Models\Catalog\Product;
use App\Models\Storefront\RecentlyViewedProduct;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Productos vistos recientemente en la PDP, por identidad.
 *
 * La identidad es excluyente: `user_id` (cliente autenticado) o
 * `guest_token` (visitante). La ventana de retención es permanente —
 * no hay reset periódico — con cap de `MAX_ENTRIES` por identidad:
 * cada re-visita mueve el producto al frente (bump de `viewed_at`) y
 * lo que quede fuera del cap se poda del más viejo al más nuevo.
 */
final class RecentlyViewedService
{
    /**
     * Máximo de productos únicos retenidos por identidad.
     */
    public const MAX_ENTRIES = 20;

    /**
     * Registra una vista de producto para la identidad dada.
     *
     * Upsert por identidad + producto: una re-visita solo bump-ea
     * `viewed_at`. Después poda la identidad a las `MAX_ENTRIES`
     * entradas más nuevas.
     *
     * @throws InvalidArgumentException cuando no hay ninguna identidad.
     */
    public function record(?int $userId, ?string $guestToken, int $productId): void
    {
        $identity = $this->resolveIdentity(userId: $userId, guestToken: $guestToken);

        RecentlyViewedProduct::query()->updateOrCreate(
            attributes: [...$identity, 'product_id' => $productId],
            values: ['viewed_at' => now()],
        );

        $keepIds = RecentlyViewedProduct::query()
            ->where($identity)
            ->orderByDesc('viewed_at')
            ->orderByDesc('id')
            ->limit(self::MAX_ENTRIES)
            ->pluck('id');

        RecentlyViewedProduct::query()
            ->where($identity)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    /**
     * Historial de la identidad, más nuevo primero.
     *
     * Solo devuelve productos activos y visibles en la tienda dada,
     * con las relaciones que `StorefrontProductResource` necesita
     * eager-loaded (en particular `images`, para emitir la galería).
     * Sin identidad devuelve vacío — el caller decide si eso es error.
     *
     * @return Collection<int, Product>
     */
    public function list(
        ?int $userId,
        ?string $guestToken,
        int $storeId,
        ?int $excludeProductId = null,
        int $limit = 8,
    ): Collection {
        if ($this->hasNoIdentity(userId: $userId, guestToken: $guestToken)) {
            return new Collection;
        }

        $identity = $this->resolveIdentity(userId: $userId, guestToken: $guestToken);
        $limit = max(1, min($limit, self::MAX_ENTRIES));

        $entriesQuery = RecentlyViewedProduct::query()->where($identity);

        if ($excludeProductId !== null) {
            $entriesQuery->where(column: 'product_id', operator: '!=', value: $excludeProductId);
        }

        $entries = $entriesQuery
            ->orderByDesc('viewed_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        if ($entries->isEmpty()) {
            return new Collection;
        }

        // Mismo criterio de visibilidad que ProductController::index:
        // filtro por la pivot product_stores antes de tocar Product,
        // para no duplicar el JOIN del pivot en el query de productos.
        $visibleIds = DB::table('product_stores')
            ->where(column: 'store_id', operator: '=', value: $storeId)
            ->where(column: 'is_visible', operator: '=', value: true)
            ->whereIn('product_id', $entries->pluck('product_id'))
            ->pluck('product_id');

        if ($visibleIds->isEmpty()) {
            return new Collection;
        }

        $productsById = Product::query()
            ->with(['category', 'brand', 'images', 'variants', 'variants.size'])
            ->whereIn('id', $visibleIds)
            ->where(column: 'is_active', operator: '=', value: true)
            ->get()
            ->keyBy('id');

        // Se reordena según las entradas (newest-first) porque el
        // whereIn de productos no preserva el orden del historial.
        // map() devuelve Support\Collection: se re-envuelve en una
        // Eloquent Collection para honrar el return type.
        return new Collection(
            $entries
                ->map(fn (RecentlyViewedProduct $entry): ?Product => $productsById->get($entry->product_id))
                ->filter()
                ->values()
                ->all(),
        );
    }

    /**
     * Attributes de identidad para matchear/insertar filas.
     *
     * User manda sobre guest: si hay sesión, el guest_token se ignora
     * y la fila queda con `guest_token NULL`.
     *
     * @return array{user_id: int} | array{guest_token: string}
     *
     * @throws InvalidArgumentException cuando no hay ninguna identidad.
     */
    private function resolveIdentity(?int $userId, ?string $guestToken): array
    {
        if ($userId !== null) {
            return ['user_id' => $userId];
        }

        if (! $this->hasNoIdentity(userId: $userId, guestToken: $guestToken)) {
            /** @var string $guestToken */
            return ['guest_token' => $guestToken];
        }

        throw new InvalidArgumentException(
            'Se requiere user_id o guest_token para identificar la vista.',
        );
    }

    private function hasNoIdentity(?int $userId, ?string $guestToken): bool
    {
        return $userId === null && ($guestToken === null || $guestToken === '');
    }
}
