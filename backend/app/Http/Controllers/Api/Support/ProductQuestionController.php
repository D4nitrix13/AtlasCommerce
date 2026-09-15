<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Support;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\ProductQuestionStoreRequest;
use App\Http\Resources\Support\ProductQuestionResource;
use App\Models\Catalog\Product;
use App\Models\Customers\Customer;
use App\Models\Support\ProductQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Q&A de producto — endpoints del cliente y públicos (RF-SUP-162).
 *
 * Rutas:
 *   - POST   /products/{product}/questions    cliente autenticado pregunta
 *   - GET    /products/{product}/questions    público, lista respondidas
 *   - GET    /account/questions               cliente lista SUS preguntas
 *
 * Reglas:
 *  - Cualquier cliente puede preguntar (no se requiere compra previa).
 *  - Las preguntas son visibles públicamente SOLO cuando tienen answer.
 *  - Admin responde vía /admin/questions/{q}/answer (en admin controller).
 */
class ProductQuestionController extends Controller
{
    /**
     * POST /products/{product}/questions
     */
    public function store(ProductQuestionStoreRequest $request, Product $product): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $question = ProductQuestion::query()->create([
            'product_id' => $product->id,
            'customer_id' => $customer->id,
            'store_id' => $customer->stores()->first()?->id ?? $product->store_id,
            'question' => $request->string('question')->toString(),
            'asked_at' => now(),
        ]);

        return response()->json(data: [
            'data' => (new ProductQuestionResource(resource: $question))->resolve(),
        ], status: Response::HTTP_CREATED);
    }

    /**
     * GET /products/{product}/questions
     *
     * Público. Lista solo preguntas respondidas.
     */
    public function indexForProduct(Request $request, Product $product): JsonResponse
    {
        $rows = DB::table('product_questions')
            ->where(column: 'product_id', operator: '=', value: $product->id)
            ->whereNotNull('answer')
            ->whereNull('deleted_at')
            ->orderByDesc('answered_at')
            ->limit(50)
            ->get();

        return response()->json(data: [
            'data' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'product_id' => $r->product_id,
                'customer_id' => $r->customer_id,
                'store_id' => $r->store_id,
                'question' => $r->question,
                'answer' => $r->answer,
                'asked_at' => $r->asked_at,
                'answered_at' => $r->answered_at,
            ])->all(),
        ]);
    }

    /**
     * GET /account/questions
     */
    public function indexForCustomer(Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $rows = DB::table('product_questions')
            ->where(column: 'customer_id', operator: '=', value: $customer->id)
            ->whereNull('deleted_at')
            ->orderByDesc('asked_at')
            ->get();

        return response()->json(data: [
            'data' => $rows->map(fn ($r) => [
                'id' => $r->id,
                'product_id' => $r->product_id,
                'customer_id' => $r->customer_id,
                'store_id' => $r->store_id,
                'question' => $r->question,
                'answer' => $r->answer,
                'asked_at' => $r->asked_at,
                'answered_at' => $r->answered_at,
            ])->all(),
        ]);
    }

    /**
     * Helper: resuelve el Customer asociado al User autenticado.
     */
    private function resolveCustomer(Request $request): Customer
    {
        $user = $request->user();
        abort_if($user === null, 401, 'Autenticación requerida.');

        $customer = Customer::query()->where('user_id', $user->id)->first();
        abort_if($customer === null, 404, 'No existe perfil de cliente para este usuario.');

        return $customer;
    }
}
