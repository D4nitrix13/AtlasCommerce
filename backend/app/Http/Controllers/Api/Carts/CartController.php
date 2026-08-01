<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Carts;

use App\Http\Controllers\Controller;
use App\Http\Resources\Carts\CartResource;
use App\Models\Carts\Cart;
use App\Models\Customers\Customer;
use App\Models\Settings\Currency;
use App\Services\Stores\StoreContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints del carrito de compras para clientes autenticados.
 *
 * Reglas:
 * - El cliente se resuelve desde `auth()->user()->customer`.
 * - El carrito siempre pertenece a la tienda resuelta por
 *   `StoreContextService` (multi-tenancy: el global scope
 *   `BelongsToStore` filtra queries; el controller asigna
 *   `store_id` explícito al crear).
 * - El currency del carrito se asigna desde la tienda en `store`.
 */
class CartController extends Controller
{
    public function __construct(
        private readonly StoreContextService $storeContext,
    ) {}

    /**
     * Lista los carritos activos del cliente autenticado en la tienda actual.
     *
     * Útil cuando un cliente tiene carritos abandonados en varias
     * sesiones. Devuelve una colección vacía si no hay.
     */
    public function index(Request $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        $carts = Cart::with(['items', 'currency'])
            ->where('customer_id', $customer->id)
            ->active()
            ->notExpired()
            ->orderByDesc('updated_at')
            ->get();

        return CartResource::collection($carts)->response();
    }

    /**
     * Devuelve el carrito especificado (debe ser del cliente autenticado).
     */
    public function show(Request $request, Cart $cart): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        $this->assertCartOwnership($cart, $customer);

        $cart->load(['items.product', 'items.productVariant', 'currency']);

        return (new CartResource($cart))->response();
    }

    /**
     * Crea (o retorna) un carrito activo del cliente en la tienda actual.
     *
     * Idempotente: si el cliente ya tiene un carrito activo en esta
     * tienda, lo devuelve sin crear duplicados. Útil para que el
     * storefront llame a este endpoint cuando el usuario entra.
     */
    public function store(Request $request): JsonResponse
    {
        $store = $this->storeContext->getStore();
        if ($store === null) {
            return response()->json(
                ['message' => 'No hay tienda activa en el contexto.'],
                422,
            );
        }

        $customer = $this->resolveCustomer($request);

        $existing = Cart::with(['items', 'currency'])
            ->where('customer_id', $customer->id)
            ->active()
            ->notExpired()
            ->latest('updated_at')
            ->first();

        if ($existing !== null) {
            return (new CartResource($existing))->response();
        }

        $cart = Cart::create([
            'customer_id' => $customer->id,
            'store_id' => $store->id,
            'currency_id' => $store->currency_id,
            'status' => 'active',
            'expires_at' => now()->addDays(7),
        ]);

        $cart->load(['items', 'currency']);

        return (new CartResource($cart))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Marca el carrito como abandonado sin eliminarlo.
     *
     * El carrito queda soft-deleted via el campo `status='abandoned'`.
     * El cliente puede reactivarlo abriendo uno nuevo.
     */
    public function update(Request $request, Cart $cart): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        $this->assertCartOwnership($cart, $customer);

        $validated = $request->validate([
            'status' => ['sometimes', 'in:active,abandoned'],
        ]);

        $cart->update($validated);

        return (new CartResource($cart->fresh()->load(['items', 'currency'])))->response();
    }

    /**
     * Elimina el carrito (soft delete via Laravel).
     */
    public function destroy(Request $request, Cart $cart): JsonResponse
    {
        $customer = $this->resolveCustomer($request);
        $this->assertCartOwnership($cart, $customer);

        $cart->delete();

        return response()->json(null, 204);
    }

    /**
     * Resuelve el `Customer` vinculado al usuario autenticado.
     *
     * Lanza 404 si el usuario no tiene perfil de Customer (caso
     * legítimo: el admin no debería poder usar carrito).
     */
    private function resolveCustomer(Request $request): Customer
    {
        $user = $request->user();
        abort_if($user === null, 401, 'Autenticación requerida.');

        $customer = Customer::where('user_id', $user->id)->first();
        abort_if($customer === null, 404, 'No existe perfil de cliente para este usuario.');

        return $customer;
    }

    /**
     * Verifica que el carrito pertenece al cliente autenticado y
     * está en la tienda activa. El global scope ya filtra por tienda,
     * pero la verificación de `customer_id` se hace explícita.
     */
    private function assertCartOwnership(Cart $cart, Customer $customer): void
    {
        abort_if(
            $cart->customer_id !== $customer->id,
            403,
            'El carrito no pertenece al cliente autenticado.',
        );
    }
}
