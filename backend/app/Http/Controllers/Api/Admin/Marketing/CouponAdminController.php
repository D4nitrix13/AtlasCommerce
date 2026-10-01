<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Marketing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\Admin\Api\CouponApplyRequest;
use App\Http\Requests\Marketing\Admin\Api\CouponStoreRequest;
use App\Http\Requests\Marketing\Admin\Api\CouponUpdateRequest;
use App\Http\Resources\Admin\Marketing\CouponResource;
use App\Models\Auth\User as UserModel;
use App\Models\Marketing\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD admin de cupones bajo la ruta `/admin/marketing/coupons`.
 *
 * Es el espejo del `Admin\CouponController` legacy (ruta `/admin/coupons`)
 * pero organizado bajo el namespace `Marketing/`. Ambos controllers
 * conviven; el nuevo es el canon para el panel admin F5.5+.
 */
class CouponAdminController extends Controller
{
    /**
     * GET /admin/marketing/coupons
     */
    public function index(Request $request): JsonResponse
    {
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

        $coupons = $query->orderBy('code')->limit(200)->get();

        return response()->json(data: [
            'data' => CouponResource::collection(resource: $coupons)->resolve(),
        ]);
    }

    /**
     * GET /admin/marketing/coupons/{coupon}
     */
    public function show(Coupon $coupon): JsonResponse
    {
        $this->assertCouponInTenantScope(coupon: $coupon);

        return response()->json(data: [
            'data' => (new CouponResource(resource: $coupon))->resolve(),
        ]);
    }

    /**
     * POST /admin/marketing/coupons
     */
    public function store(CouponStoreRequest $request): JsonResponse
    {
        $coupon = Coupon::query()->create(attributes: $request->validated());

        return response()->json(data: [
            'data' => (new CouponResource(resource: $coupon))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * PUT /admin/marketing/coupons/{coupon}
     */
    public function update(CouponUpdateRequest $request, Coupon $coupon): JsonResponse
    {
        $this->assertCouponInTenantScope(coupon: $coupon);

        $coupon->update(attributes: $request->validated());

        return response()->json(data: [
            'data' => (new CouponResource(resource: $coupon))->resolve(),
        ]);
    }

    /**
     * DELETE /admin/marketing/coupons/{coupon}
     */
    public function destroy(Coupon $coupon): JsonResponse
    {
        $this->assertCouponInTenantScope(coupon: $coupon);

        $coupon->delete();

        return response()->json(data: null, status: Response::HTTP_NO_CONTENT);
    }

    /**
     * POST /admin/marketing/coupons/{coupon}/apply — simula la aplicación.
     *
     * Devuelve el descuento que resultaría al aplicar el cupón a un
     * monto hipotético. No crea CouponUsage ni side effects.
     */
    public function apply(CouponApplyRequest $request, Coupon $coupon): JsonResponse
    {
        if (! $coupon->isValid()) {
            return response()->json(data: [
                'applies' => false,
                'reason' => 'El cupón no está vigente.',
            ], status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $amountCents = (int) $request->integer('amount_cents');
        $minimumAmountCents = (int) round((float) $coupon->minimum_amount * 100);

        if ($amountCents < $minimumAmountCents) {
            return response()->json(data: [
                'applies' => false,
                'reason' => 'Monto mínimo no alcanzado.',
                'minimum_amount_cents' => $minimumAmountCents,
            ], status: Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $discountCents = $coupon->discount_type === 'percentage'
            ? (int) round($amountCents * ((float) $coupon->value / 100))
            : (int) round((float) $coupon->value * 100);

        return response()->json(data: [
            'applies' => true,
            'discount_cents' => $discountCents,
            'final_cents' => max(0, $amountCents - $discountCents),
            'currency_code' => $request->input('currency_code'),
        ]);
    }

    /**
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
}
