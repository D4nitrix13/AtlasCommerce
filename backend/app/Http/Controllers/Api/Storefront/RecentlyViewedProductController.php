<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\Storefront\StorefrontProductResource;
use App\Models\Catalog\Product;
use App\Services\Storefront\RecentlyViewedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Vistos recientemente" de la PDP — persistidos en DB por identidad.
 *
 * Público (mismo grupo `store.domain` que el resto del storefront).
 * La identidad es el usuario autenticado si lo hay; si no, el
 * `guest_token` que manda el frontend en el request.
 *
 * Rutas:
 *   - POST /storefront/recently-viewed  registra la visita (204)
 *   - GET  /storefront/recently-viewed  historial newest-first
 */
class RecentlyViewedProductController extends Controller
{
    /**
     * POST /storefront/recently-viewed
     *
     * Body: `{ product_id: int, guest_token?: string }`. Valida que el
     * producto exista y esté visible en la tienda actual (404 si no,
     * igual que `ProductController::show`). Responde 204 sin payload.
     *
     * Sin sesión y sin guest_token no hay identidad para persistir →
     * 422 (mismo canal de errores que la validación de Laravel).
     */
    public function store(Request $request, RecentlyViewedService $service): Response
    {
        $store = $request->attributes->get('store');

        $validated = $request->validate(rules: [
            'product_id' => ['required', 'integer'],
            'guest_token' => ['nullable', 'string', 'min:1', 'max:64'],
        ]);
        $productId = (int) $validated['product_id'];

        // Mismo criterio que ProductController::show: se resuelve la
        // visibilidad contra la pivot product_stores antes de tocar
        // Product. Producto inexistente y producto oculto son el mismo
        // 404 (no se filtra existencia).
        $isVisible = DB::table('product_stores')
            ->where(column: 'store_id', operator: '=', value: $store->id)
            ->where(column: 'is_visible', operator: '=', value: true)
            ->where(column: 'product_id', operator: '=', value: $productId)
            ->exists();

        if (! $isVisible) {
            abort(code: 404);
        }

        [$userId, $guestToken] = $this->resolveIdentity(
            request: $request,
            guestToken: $validated['guest_token'] ?? null,
            requireIdentity: true,
        );

        $service->record(userId: $userId, guestToken: $guestToken, productId: $productId);

        return response()->noContent();
    }

    /**
     * GET /storefront/recently-viewed
     *
     * Query params:
     *   - guest_token: string opcional (identidad del visitante).
     *   - exclude:     string opcional — slug o id del producto actual
     *                  de la PDP, para no recomendárselo a sí mismo.
     *   - limit:       int 1..MAX_ENTRIES (default 8).
     *
     * Response: `{ data: [StorefrontProductResource, ...] }` newest
     * primero. Sin identidad → `data: []` (no es error).
     */
    public function index(Request $request, RecentlyViewedService $service): JsonResponse
    {
        $store = $request->attributes->get('store');

        $validated = $request->validate(rules: [
            'guest_token' => ['nullable', 'string', 'min:1', 'max:64'],
            'exclude' => ['nullable', 'string', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.RecentlyViewedService::MAX_ENTRIES],
        ]);

        [$userId, $guestToken] = $this->resolveIdentity(
            request: $request,
            guestToken: $validated['guest_token'] ?? null,
            requireIdentity: false,
        );

        $products = $service->list(
            userId: $userId,
            guestToken: $guestToken,
            storeId: $store->id,
            excludeProductId: $this->resolveExcludeProductId(exclude: $validated['exclude'] ?? null),
            limit: (int) ($validated['limit'] ?? 8),
        );

        return response()->json(data: [
            'data' => StorefrontProductResource::collection($products)->resolve(),
        ]);
    }

    /**
     * Identidad del request: el usuario autenticado manda; si no hay,
     * el guest_token del body/query. Nunca ambos.
     *
     * Con `$requireIdentity` (POST) la ausencia de identidad es un 422;
     * sin él (GET) se devuelve `[null, null]` y el servicio lista vacío.
     *
     * @return array{0: int|null, 1: string|null}
     *
     * @throws ValidationException cuando se requiere identidad y no hay.
     */
    private function resolveIdentity(Request $request, ?string $guestToken, bool $requireIdentity): array
    {
        $userId = $request->user()?->id;

        if ($userId !== null) {
            return [$userId, null];
        }

        if ($guestToken === null && $requireIdentity) {
            throw ValidationException::withMessages([
                'guest_token' => 'El guest_token es requerido cuando no hay sesión activa.',
            ]);
        }

        return [null, $guestToken];
    }

    /**
     * Resuelve `exclude=<slugOrId>` a un product_id. Los valores
     * puramente numéricos se interpretan como id; el resto como slug.
     * Si no resuelve, no se excluye nada (el historial sigue igual).
     */
    private function resolveExcludeProductId(?string $exclude): ?int
    {
        if ($exclude === null || $exclude === '') {
            return null;
        }

        if (ctype_digit($exclude)) {
            return (int) $exclude;
        }

        return Product::query()
            ->where(column: 'slug', operator: '=', value: $exclude)
            ->value('id');
    }
}
