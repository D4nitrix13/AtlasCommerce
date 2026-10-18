<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Concerns\CursorPaginates;
use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\Admin\CouponStoreRequest;
use App\Http\Requests\Marketing\Admin\CouponUpdateRequest;
use App\Http\Resources\Marketing\AdminCouponResource;
use App\Models\Auth\User as UserModel;
use App\Models\Marketing\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD admin de cupones (RF-MKT-060).
 *
 * Reglas:
 *  - Aislamiento por tenant: el listado y los show sólo exponen
 *    cupones accesibles al admin (cupones globales `store_id = null`
 *    se ven en todos los tenants; cupones con `store_id` sólo en
 *    su tienda).
 *  - `code` se normaliza a UPPERCASE en store (ver FormRequest).
 *  - `code` es inmutable en update para preservar trazabilidad de
 *    CouponUsage ya registrados.
 *
 * Permiso: `marketing.manage` (admin slug bypasea — pendiente definir
 * si va al staff).
 */
class CouponController extends Controller
{
    use CursorPaginates;

    /**
     * GET /admin/coupons
     *
     * Query params:
     *  - page, per_page (default 20, max 100).
     *  - q: filtro ILIKE por code.
     *  - is_active: bool.
     *  - discount_type: percentage | fixed.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->query('per_page', 20);
        $perPage = max(1, min($perPage, 100));

        $accessibleStoreIds = $this->accessibleStoreIds();

        $query = Coupon::query()
            ->where(function ($q) use ($accessibleStoreIds): void {
                $q->whereNull('store_id')
                    ->orWhereIn('store_id', $accessibleStoreIds);
            });

        if ($search = trim((string) $request->query('q', ''))) {
            $needle = '%'.$search.'%';
            $query->where(column: 'code', operator: 'ILIKE', value: $needle);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('discount_type')) {
            $query->where(column: 'discount_type', operator: '=', value: $request->query('discount_type'));
        }

        $paginator = $query
            ->orderBy('code')
            ->cursorPaginate($perPage);

        return $this->cursorPaginatedResponse(
            request: $request, query: $query, resourceClass: AdminCouponResource::class,
        );
    }

    /**
     * GET /admin/coupons/{coupon}
     */
    public function show(Coupon $coupon): JsonResponse
    {
        $this->assertCouponInTenantScope(coupon: $coupon);

        $coupon->load(relations: ['store.currency']);

        return response()->json(data: [
            'data' => (new AdminCouponResource(resource: $coupon))->resolve(),
        ]);
    }

    /**
     * POST /admin/coupons
     */
    public function store(CouponStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (array_key_exists('store_id', $data) && $data['store_id'] !== null) {
            $this->assertStoresAccessible(storeIds: [(int) $data['store_id']]);
        }

        $coupon = Coupon::query()->create(attributes: $data);

        $coupon->load(relations: ['store.currency']);

        return response()->json(data: [
            'data' => (new AdminCouponResource(resource: $coupon))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * PUT /admin/coupons/{coupon}
     */
    public function update(CouponUpdateRequest $request, Coupon $coupon): JsonResponse
    {
        $this->assertCouponInTenantScope(coupon: $coupon);

        $coupon->update(attributes: $request->validated());
        $coupon->load(relations: ['store.currency']);

        return response()->json(data: [
            'data' => (new AdminCouponResource(resource: $coupon))->resolve(),
        ]);
    }

    /**
     * DELETE /admin/coupons/{coupon}
     *
     * Soft-delete: preserva el historial de CouponUsage (auditoría).
     */
    public function destroy(Coupon $coupon): JsonResponse
    {
        $this->assertCouponInTenantScope(coupon: $coupon);

        $coupon->delete();

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
     * Helper: aborta si el cupón pertenece a una tienda no accesible.
     *
     * Cupones globales (`store_id = null`) son visibles para todos los
     * admins — no bloqueamos.
     */
    private function assertCouponInTenantScope(Coupon $coupon): void
    {
        if ($coupon->store_id === null) {
            return;
        }

        $accessible = $this->accessibleStoreIds();

        if (! in_array((int) $coupon->store_id, $accessible, strict: true)) {
            abort(code: 404, message: 'Cupón no encontrado en tu tenant.');
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
                message: 'No podés asociar cupones a tiendas que no administrás: '.implode(', ', $invalid),
            );
        }
    }
}
