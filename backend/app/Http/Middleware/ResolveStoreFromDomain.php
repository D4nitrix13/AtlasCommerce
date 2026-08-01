<?php

namespace App\Http\Middleware;

use App\Models\Stores\StoreDomain;
use App\Services\Stores\StoreContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveStoreFromDomain
{
    public function __construct(
        private readonly StoreContextService $storeContext,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        $domain = StoreDomain::where('domain', $host)
            ->where('is_active', true)
            ->with('store')
            ->first();

        if (! $domain?->store?->is_active) {
            return response()->json([
                'message' => 'Store not found or inactive.',
            ], 404);
        }

        $this->storeContext->setStore($domain->store);

        $request->attributes->set('store', $domain->store);

        return $next($request);
    }
}
