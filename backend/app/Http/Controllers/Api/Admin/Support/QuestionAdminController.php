<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\Admin\Api\QuestionAnswerRequest;
use App\Http\Requests\Support\Admin\Api\QuestionStoreRequest;
use App\Http\Requests\Support\Admin\Api\QuestionUpdateRequest;
use App\Http\Resources\Admin\Support\QuestionResource;
use App\Models\Auth\User as UserModel;
use App\Models\Support\ProductQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CRUD admin de Q&A de productos bajo `/admin/support/questions`.
 *
 * Acciones extra: `answer` (responde una pregunta pendiente) y bulk via
 * Resource CRUD. Esta vista no existía antes — RF-SUP-162.
 */
class QuestionAdminController extends Controller
{
    /**
     * GET /admin/support/questions
     */
    public function index(Request $request): JsonResponse
    {
        $query = ProductQuestion::query()
            ->with('product')
            ->whereIn('store_id', $this->accessibleStoreIds());

        if ($request->boolean('pending')) {
            $query->pending();
        }

        if ($productId = $request->query('product_id')) {
            $query->where(column: 'product_id', operator: '=', value: (int) $productId);
        }

        $questions = $query->orderByDesc('asked_at')->limit(200)->get();

        return response()->json(data: [
            'data' => QuestionResource::collection(resource: $questions)->resolve(),
        ]);
    }

    /**
     * GET /admin/support/questions/{question}
     */
    public function show(ProductQuestion $question): JsonResponse
    {
        $this->assertQuestionInTenantScope(question: $question);

        $question->load('product');

        return response()->json(data: [
            'data' => (new QuestionResource(resource: $question))->resolve(),
        ]);
    }

    /**
     * POST /admin/support/questions
     */
    public function store(QuestionStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['store_id'] = $data['store_id'] ?? $this->accessibleStoreIds()[0] ?? null;
        $data['asked_at'] = now();

        $question = ProductQuestion::query()->create(attributes: $data);
        $question->load('product');

        return response()->json(data: [
            'data' => (new QuestionResource(resource: $question))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * PUT /admin/support/questions/{question}
     */
    public function update(QuestionUpdateRequest $request, ProductQuestion $question): JsonResponse
    {
        $this->assertQuestionInTenantScope(question: $question);

        $question->update(attributes: $request->validated());
        $question->load('product');

        return response()->json(data: [
            'data' => (new QuestionResource(resource: $question))->resolve(),
        ]);
    }

    /**
     * POST /admin/support/questions/{question}/answer
     */
    public function answer(QuestionAnswerRequest $request, ProductQuestion $question): JsonResponse
    {
        $this->assertQuestionInTenantScope(question: $question);

        $question->update([
            'answer' => (string) $request->input('answer'),
            'answered_at' => now(),
        ]);
        $question->load('product');

        return response()->json(data: [
            'data' => (new QuestionResource(resource: $question))->resolve(),
        ]);
    }

    /**
     * DELETE /admin/support/questions/{question}
     */
    public function destroy(ProductQuestion $question): JsonResponse
    {
        $this->assertQuestionInTenantScope(question: $question);

        $question->delete();

        return response()->json(data: null, status: Response::HTTP_NO_CONTENT);
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
