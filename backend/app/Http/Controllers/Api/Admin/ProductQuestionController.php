<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\Admin\ProductQuestionAnswerRequest;
use App\Http\Resources\Support\ProductQuestionResource;
use App\Models\Auth\User as UserModel;
use App\Models\Support\ProductQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin: moderación y respuesta de Q&A de producto (RF-SUP-162).
 */
class ProductQuestionController extends Controller
{
    /**
     * GET /admin/questions
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProductQuestion::query()
            ->whereIn('store_id', $this->accessibleStoreIds());

        if ($request->has('product_id')) {
            $query->where(column: 'product_id', operator: '=', value: (int) $request->query('product_id'));
        }

        // Por defecto: preguntas SIN respuesta (pending).
        if ($request->boolean('pending_only')) {
            $query->whereNull('answer');
        }

        $paginator = $query->orderByDesc('asked_at')
            ->paginate(perPage: (int) $request->query('per_page', 20));

        return response()->json(data: [
            'data' => ProductQuestionResource::collection(resource: $paginator->getCollection())->resolve(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /admin/questions/{question}
     */
    public function show(ProductQuestion $question): JsonResponse
    {
        $this->assertQuestionInTenantScope(question: $question);

        return response()->json(data: [
            'data' => (new ProductQuestionResource(resource: $question))->resolve(),
        ]);
    }

    /**
     * PUT /admin/questions/{question}/answer
     */
    public function answer(ProductQuestionAnswerRequest $request, ProductQuestion $question): JsonResponse
    {
        $this->assertQuestionInTenantScope(question: $question);

        $question->update([
            'answer' => $request->string('answer')->toString(),
            'answered_at' => now(),
        ]);

        return response()->json(data: [
            'data' => (new ProductQuestionResource(resource: $question))->resolve(),
        ]);
    }

    /**
     * DELETE /admin/questions/{question}
     */
    public function destroy(ProductQuestion $question): JsonResponse
    {
        $this->assertQuestionInTenantScope(question: $question);

        $question->delete();

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
     * Helper: aborta si la pregunta pertenece a una tienda no accesible.
     */
    private function assertQuestionInTenantScope(ProductQuestion $question): void
    {
        if ($question->store_id === null) {
            return;
        }

        if (! in_array((int) $question->store_id, $this->accessibleStoreIds(), strict: true)) {
            abort(code: 404, message: 'Pregunta no encontrada en tu tenant.');
        }
    }
}
