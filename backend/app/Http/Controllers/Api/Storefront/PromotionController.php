<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\Marketing\StorefrontPromotionResource;
use App\Models\Marketing\Promotion;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoint público de promociones activas del storefront (RF-MKT-061).
 *
 * GET /api/storefront/promotions
 *
 * Devuelve las promociones vigentes (activas + dentro de la ventana
 * temporal) que aplican al tenant actual:
 *  - Promociones globales (`store_id IS NULL`).
 *  - Promociones específicas de la tienda activa (`store_id = $store->id`).
 *
 * El visitante usa este endpoint para mostrar badges en PDP y cart
 * con el descuento que aplicaría automáticamente al checkout.
 *
 * Sin autenticación: ruta storefront pública.
 */
class PromotionController extends Controller
{
    public function __construct(
        private readonly StoreContextService $storeContext,
    ) {}

    public function index(): JsonResponse
    {
        $store = $this->storeContext->getStore();

        $query = Promotion::query()->valid();

        $query->where(function ($q) use ($store): void {
            $q->whereNull('store_id');
            if ($store !== null) {
                $q->orWhere('store_id', $store->id);
            }
        });

        $promotions = $query
            ->orderBy('value', 'desc')
            ->limit(50)
            ->get();

        return response()->json(data: [
            'data' => StorefrontPromotionResource::collection(resource: $promotions)->resolve(),
        ], status: Response::HTTP_OK);
    }
}
