<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Test;

use App\Http\Controllers\Controller;
use App\Http\Concerns\CursorPaginates;
use App\Http\Resources\System\NotificationResource;
use App\Models\System\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller dummy para tests del trait CursorPaginates.
 *
 * Existe solo para que los tests de Feature puedan instanciarlo y
 * ejercitar el trait sin tener que mockear Controller.
 */
class CursorPaginatesTestController extends Controller
{
    use CursorPaginates;

    public function index(Request $request): JsonResponse
    {
        $query = Notification::query()->orderBy('id');

        return $this->cursorPaginatedResponse(
            request: $request,
            query: $query,
            resourceClass: NotificationResource::class,
        );
    }
}
