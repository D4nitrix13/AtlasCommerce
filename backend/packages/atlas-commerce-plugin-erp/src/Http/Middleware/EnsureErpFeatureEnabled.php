<?php

declare(strict_types=1);

namespace D4nitrix13\AtlasCommercePluginErp\Http\Middleware;

use Closure;
use Core\Tenant\Application\StoreContextService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureErpFeatureEnabled
{
    public function __construct(
        private readonly StoreContextService $storeContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $store = $this->storeContext->getStore();

        if ($store === null || ! $store->hasFeature('erp')) {
            return response()->json([
                'message' => 'Feature not enabled for this store.',
            ], status: 404);
        }

        return $next($request);
    }
}
