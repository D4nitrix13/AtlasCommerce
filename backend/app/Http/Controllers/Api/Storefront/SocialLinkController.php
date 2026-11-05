<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Storefront;

use App\Http\Controllers\Controller;
use App\Http\Resources\Storefront\StorefrontSocialLinkResource;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Endpoint público de redes sociales del storefront (RF-STOR-133).
 *
 * GET /api/v1/storefront/social-links
 *
 * Devuelve los enlaces activos (`is_active`) de la tienda resuelta por
 * dominio, ordenados por `sort_order` asc y luego `id` asc. El footer
 * del storefront consume este endpoint para renderizar las redes.
 *
 * Sin autenticación: ruta storefront pública. El scope por tienda sale
 * de la relación `socialLinks()` del store del contexto — nunca se
 * consultan links de otra tienda.
 *
 * Si no hay tienda en el contexto, responde 503 con message (misma
 * convención que `StorefrontAuthController`): el middleware
 * `store.domain` corta antes con 404 cuando el dominio no resuelve,
 * pero el controller mantiene la defensa si el contexto queda vacío.
 */
class SocialLinkController extends Controller
{
    public function __construct(
        private readonly StoreContextService $storeContext,
    ) {}

    /**
     * Lista los links sociales activos de la tienda actual.
     */
    public function index(): JsonResponse
    {
        $store = $this->storeContext->getStore();

        if ($store === null) {
            return response()->json(
                data: ['message' => 'No se pueden mostrar las redes sociales: la tienda no está activa.'],
                status: 503,
            );
        }

        $links = $store->socialLinks()
            ->where(column: 'is_active', operator: '=', value: true)
            ->orderBy(column: 'sort_order')
            ->orderBy(column: 'id')
            ->get();

        return response()->json(data: [
            'data' => StorefrontSocialLinkResource::collection(resource: $links)->resolve(),
        ], status: Response::HTTP_OK);
    }
}
