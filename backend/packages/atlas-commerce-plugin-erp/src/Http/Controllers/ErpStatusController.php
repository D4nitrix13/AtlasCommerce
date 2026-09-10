<?php

declare(strict_types=1);

namespace D4nitrix13\AtlasCommercePluginErp\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

final class ErpStatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'plugin' => config('erp.name'),
            'feature' => config('erp.feature'),
            'status' => 'installed',
        ]);
    }
}
