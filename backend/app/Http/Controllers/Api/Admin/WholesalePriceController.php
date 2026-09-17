<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Concerns\CursorPaginates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\WholesalePriceStoreRequest;
use App\Http\Requests\Admin\WholesalePriceUpdateRequest;
use App\Http\Resources\Catalog\AdminWholesalePriceResource;
use App\Models\Auth\User as UserModel;
use App\Models\Catalog\WholesalePrice;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD admin de precios mayoristas (RF-CAT-029).
 *
 * Permite configurar los tiers de descuento por volumen para cada
 * (producto, tienda, cantidad mínima). El servicio de lectura
 * (`WholesalePricingService`) ya existe — este controller es la cara
 * administrativa para mantener los tiers.
 *
 * Reglas:
 *  - Permiso `catalog.manage` (admin slug bypasea).
 *  - Aislamiento por tenant: el listado y los show sólo exponen
 *    tiers de tiendas accesibles del admin.
 *  - `store_id` en create debe ser accesible al admin.
 *  - `product_id`, `store_id` y `min_qty` son inmutables en update:
 *    cambiarlos rompe la unicidad y la integridad del pricing.
 *  - La combinación `(product_id, store_id, min_qty)` es única
 *    (constraint DB + validación 422 explícita).
 */
class WholesalePriceController extends Controller
{
    use CursorPaginates;
    /**
     * GET /admin/wholesale-prices
     *
     * Query params:
     *  - page, per_page (default 20, max 100).
     *  - product_id: filtro por producto.
     *  - store_id: filtro por tienda (debe ser accesible al admin).
     *  - min_qty: filtro por cantidad mínima exacta.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $accessibleStoreIds = $this->accessibleStoreIds();

        $query = WholesalePrice::query()
            ->whereIn(column: 'store_id', values: $accessibleStoreIds);

        if ($request->has('product_id')) {
            $query->where(column: 'product_id', operator: '=', value: (int) $request->query('product_id'));
        }

        if ($request->has('store_id')) {
            $storeId = (int) $request->query('store_id');
            if (! in_array($storeId, $accessibleStoreIds, strict: true)) {
                abort(code: 403, message: 'No tenés acceso a esa tienda.');
            }
            $query->where(column: 'store_id', operator: '=', value: $storeId);
        }

        if ($request->has('min_qty')) {
            $query->where(column: 'min_qty', operator: '=', value: (int) $request->query('min_qty'));
        }

        $paginator = $query
            ->orderBy('product_id')
            ->orderBy('store_id')
            ->orderBy('min_qty')
            ->cursorPaginate($perPage);

        return $this->cursorPaginatedResponse(
            request: $request, query: $query, resourceClass: AdminWholesalePriceResource::class,
        );
    }

    /**
     * GET /admin/wholesale-prices/{wholesalePrice}
     */
    public function show(WholesalePrice $wholesalePrice): JsonResponse
    {
        $this->assertPriceInTenantScope(wholesalePrice: $wholesalePrice);

        $wholesalePrice->load(relations: ['store.currency']);

        return response()->json(data: [
            'data' => (new AdminWholesalePriceResource(resource: $wholesalePrice))->resolve(),
        ]);
    }

    /**
     * POST /admin/wholesale-prices
     *
     * Crea un tier mayorista. La combinación (product_id, store_id, min_qty)
     * debe ser única — si ya existe, 422 con mensaje claro.
     */
    public function store(WholesalePriceStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        $this->assertStoresAccessible(storeIds: [$data['store_id']]);

        try {
            $price = DB::transaction(function () use ($data): WholesalePrice {
                return WholesalePrice::query()->create(attributes: [
                    'product_id' => $data['product_id'],
                    'store_id' => $data['store_id'],
                    'min_qty' => $data['min_qty'],
                    'price_cents' => $data['price_cents'],
                ]);
            });
        } catch (QueryException $e) {
            // 23505 = unique_violation en PostgreSQL — combinación
            // (product_id, store_id, min_qty) ya existe.
            if ((string) $e->getCode() === '23505') {
                return response()->json(
                    data: ['message' => 'Ya existe un tier mayorista para ese producto/tienda/cantidad.'],
                    status: Response::HTTP_UNPROCESSABLE_ENTITY,
                );
            }
            throw $e;
        }

        $price->load(relations: ['store.currency']);

        return response()->json(data: [
            'data' => (new AdminWholesalePriceResource(resource: $price))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * PUT /admin/wholesale-prices/{wholesalePrice}
     *
     * Solo se permite cambiar `price_cents` — la identidad del tier
     * (product_id + store_id + min_qty) es inmutable. Para "mover" un
     * tier hay que borrar + crear.
     */
    public function update(
        WholesalePriceUpdateRequest $request,
        WholesalePrice $wholesalePrice,
    ): JsonResponse {
        $this->assertPriceInTenantScope(wholesalePrice: $wholesalePrice);

        $wholesalePrice = DB::transaction(function () use ($request, $wholesalePrice): WholesalePrice {
            $wholesalePrice->update(attributes: [
                'price_cents' => $request->integer(key: 'price_cents'),
            ]);

            return $wholesalePrice;
        });

        $wholesalePrice->load(relations: ['store.currency']);

        return response()->json(data: [
            'data' => (new AdminWholesalePriceResource(resource: $wholesalePrice))->resolve(),
        ]);
    }

    /**
     * DELETE /admin/wholesale-prices/{wholesalePrice}
     */
    public function destroy(WholesalePrice $wholesalePrice): JsonResponse
    {
        $this->assertPriceInTenantScope(wholesalePrice: $wholesalePrice);

        $wholesalePrice->delete();

        return response()->json(data: null, status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Helper: IDs de tiendas accesibles por el admin actual.
     *
     * @return array<int, int>
     */
    private function accessibleStoreIds(): array
    {
        $user = auth()->user();
        if (! $user instanceof UserModel) {
            abort(code: 401);
        }

        return $user->stores()->pluck('stores.id')->all();
    }

    /**
     * Helper: aborta si el tier no pertenece a una tienda accesible.
     */
    private function assertPriceInTenantScope(WholesalePrice $wholesalePrice): void
    {
        $accessible = $this->accessibleStoreIds();

        if (! in_array((int) $wholesalePrice->store_id, $accessible, strict: true)) {
            abort(code: 404, message: 'Precio mayorista no encontrado en tu tenant.');
        }
    }

    /**
     * Helper: valida que todos los store_ids pertenezcan a las tiendas
     * accesibles del admin.
     *
     * @param  array<int, int>  $storeIds
     */
    private function assertStoresAccessible(array $storeIds): void
    {
        $accessible = $this->accessibleStoreIds();

        $invalid = array_values(array_diff($storeIds, $accessible));
        if ($invalid !== []) {
            abort(
                code: 403,
                message: 'No podés asociar precios mayoristas a tiendas que no administrás: '
                    .implode(', ', $invalid),
            );
        }
    }
}
