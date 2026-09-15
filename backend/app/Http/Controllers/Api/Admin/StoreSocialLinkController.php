<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stores\Admin\StoreSocialLinkStoreRequest;
use App\Http\Requests\Stores\Admin\StoreSocialLinkUpdateRequest;
use App\Http\Resources\Stores\StoreSocialLinkResource;
use App\Models\Auth\User as UserModel;
use App\Models\Stores\Store;
use App\Models\Stores\StoreSocialLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD admin de redes sociales por tienda (RF-STOR-133 — sólo social links).
 *
 * Reglas:
 *  - Aislamiento por tenant (store debe ser accesible al admin).
 *  - platform enum (instagram, facebook, tiktok, whatsapp, youtube,
 *    x, linkedin, other).
 *
 * Pendiente: SalesChannel (RF-STOR-133 parte 2 — comisiones + stock rules).
 */
class StoreSocialLinkController extends Controller
{
    /**
     * GET /admin/stores/{store}/social-links
     */
    public function index(Request $request, Store $store): JsonResponse
    {
        $this->assertStoreInTenantScope($store);

        $links = $store->socialLinks()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json(data: [
            'data' => StoreSocialLinkResource::collection(resource: $links)->resolve(),
        ]);
    }

    /**
     * POST /admin/stores/{store}/social-links
     */
    public function store(StoreSocialLinkStoreRequest $request, Store $store): JsonResponse
    {
        $this->assertStoreInTenantScope($store);

        $link = $store->socialLinks()->create($request->validated());

        return response()->json(data: [
            'data' => (new StoreSocialLinkResource(resource: $link))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * PUT /admin/stores/{store}/social-links/{link}
     */
    public function update(
        StoreSocialLinkUpdateRequest $request,
        Store $store,
        StoreSocialLink $link,
    ): JsonResponse {
        $this->assertStoreInTenantScope($store);
        $this->assertLinkInStore($store, $link);

        $link->update($request->validated());

        return response()->json(data: [
            'data' => (new StoreSocialLinkResource(resource: $link))->resolve(),
        ]);
    }

    /**
     * DELETE /admin/stores/{store}/social-links/{link}
     */
    public function destroy(Store $store, StoreSocialLink $link): JsonResponse
    {
        $this->assertStoreInTenantScope($store);
        $this->assertLinkInStore($store, $link);

        $link->delete();

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
     * Helper: aborta si la tienda no es accesible al admin.
     */
    private function assertStoreInTenantScope(Store $store): void
    {
        if (! in_array((int) $store->id, $this->accessibleStoreIds(), strict: true)) {
            abort(code: 404, message: 'Tienda no encontrada en tu tenant.');
        }
    }

    /**
     * Helper: aborta si el link no pertenece a la tienda (evita edit cross-store).
     */
    private function assertLinkInStore(Store $store, StoreSocialLink $link): void
    {
        abort_if(
            $link->store_id !== $store->id,
            404,
            'Red social no encontrada en esta tienda.',
        );
    }
}
