<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Models\Carts\Cart;
use App\Models\Carts\CartItem;
use App\Models\Catalog\Product;
use App\Models\Customers\CustomerAddress;
use App\Models\Payments\PaymentMethod;
use App\Models\Sales\Invoice;
use App\Models\Sales\Order;
use App\Models\Settings\ExchangeRate;
use App\Services\Stores\StoreContextService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Servicio de checkout.
 *
 * Convierte un `Cart` activo en:
 *   1. `Order` con sus `OrderItem`s (snapshot de nombre/SKU/precio)
 *   2. `Invoice` con sus `InvoiceItem`s
 *   3. Marca el carrito como `converted`
 *
 * Todo ocurre dentro de una transacción de DB. Si cualquier paso falla,
 * se hace rollback completo.
 *
 * Reglas operativas:
 * - El carrito debe estar `active` y pertenecer al cliente autenticado.
 * - El carrito debe pertenecer a la tienda activa en
 *   `StoreContextService` (multi-tenancy).
 * - El `exchange_rate_snapshot` se congela desde la moneda del carrito
 *   a la moneda base (si difieren). Para MVP el snapshot es 1.0 si la
 *   moneda del carrito == primaria de la tienda.
 *
 * Decisiones MVP (no production-ready):
 * - Impuestos = 0% (configurar `taxes` está fuera de scope).
 * - Shipping = 0 (se calcula en un paso posterior; ver `PHASE 5` futuro).
 * - No aplica cupones; queda para v2.
 */
final class CheckoutService
{
    public function __construct(
        private readonly StoreContextService $storeContext,
    ) {}

    /**
     * Ejecuta el checkout para el carrito dado.
     *
     * @param  Cart  $cart  Carrito activo del cliente. Debe estar
     *                      `active`, tener items y pertenecer a la tienda
     *                      resuelta por el contexto actual.
     * @param  CustomerAddress  $shippingAddress  Dirección de envío del
     *                                            cliente. Pertenencia verificada por el caller.
     * @param  PaymentMethod  $paymentMethod  Método de pago elegido.
     * @param  string|null  $notes  Notas opcionales del cliente.
     */
    public function checkout(
        Cart $cart,
        CustomerAddress $shippingAddress,
        PaymentMethod $paymentMethod,
        ?string $notes = null,
    ): Order {
        $store = $this->storeContext->getStore();
        $storeId = $store?->id ?? (int) $cart->store_id;
        $storeCurrencyId = (int) ($store?->currency_id ?? $cart->currency_id);

        // Snapshot del tipo de cambio al momento de la venta. Para MVP
        // lo tomamos como 1.0 si la moneda del carrito es la primaria;
        // si difiere, leemos el último exchange rate registrado.
        $exchangeRate = $this->resolveExchangeRate(
            fromCurrencyId: $storeCurrencyId,
            toCurrencyId: $storeCurrencyId,
        );

        return DB::transaction(function () use (
            $cart,
            $shippingAddress,
            $notes,
            $exchangeRate,
            $storeId,
            $store,
        ): Order {
            $cart->loadMissing('items.product', 'items.productVariant');

            $items = $cart->items;
            if ($items->isEmpty()) {
                throw new RuntimeException(
                    'El carrito está vacío. Agrega productos antes de hacer checkout.'
                );
            }

            $subtotalCents = (int) $items->sum('subtotal_cents');

            // Tax: tasa por tienda aplicada al subtotal. El snapshot queda
            // implícito al persistir tax_cents en la orden (no recalculamos
            // si la tasa del store cambia después).
            $taxCents = $this->calculateTaxCents(
                subtotalCents: $subtotalCents,
                taxRate: (float) ($store?->tax_rate ?? 0),
            );

            $totalCents = $subtotalCents + $taxCents;

            // Snapshot a order_items.
            $orderItemsPayload = $items->map(function (CartItem $item) {
                /** @var Product $product */
                $product = $item->product;

                return [
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'custom_design_id' => $item->custom_design_id,
                    'quantity' => $item->quantity,
                    'unit_price_cents' => $item->unit_price_cents,
                    'discount_cents' => (int) ($item->discount_cents ?? 0),
                    'subtotal_cents' => $item->subtotal_cents,
                    'product_name_snapshot' => $product?->name,
                    'product_sku_snapshot' => $item->productVariant?->sku ?? $product?->sku,
                ];
            })->all();

            $order = Order::create([
                'customer_id' => $cart->customer_id,
                'shipping_address_id' => $shippingAddress->id,
                'store_id' => $storeId,
                'currency_id' => $cart->currency_id,
                'sales_channel_id' => null,
                'user_creator_id' => null,
                'order_number' => $this->generateOrderNumber(),
                'subtotal_cents' => $subtotalCents,
                'discount_cents' => 0,
                'tax_cents' => $taxCents,
                'shipping_cents' => 0,
                'total_cents' => $totalCents,
                'exchange_rate_snapshot' => $exchangeRate,
                'total_in_base_currency_cents' => (int) round(
                    $subtotalCents * (float) $exchangeRate
                ),
                'status' => 'pending',
                'notes' => $notes,
                'ordered_at' => now(),
            ]);

            foreach ($orderItemsPayload as $payload) {
                $order->items()->create($payload);
            }

            // Una invoice por orden (auto-creada en checkout).
            // El monto se registra en cents con el mismo currency del cart.
            $invoice = $order->invoices()->create([
                'customer_id' => $cart->customer_id,
                'store_id' => $storeId,
                'currency_id' => $cart->currency_id,
                'invoice_number' => $this->generateInvoiceNumber(),
                'subtotal_cents' => $subtotalCents,
                'discount_cents' => 0,
                'tax_cents' => $taxCents,
                'total_cents' => $totalCents,
                'exchange_rate_snapshot' => $exchangeRate,
                'payment_status' => 'pending',
                'invoice_status' => 'issued',
                'issued_at' => now(),
            ]);

            // Replicar items en la invoice con snapshot del nombre.
            foreach ($orderItemsPayload as $payload) {
                $invoice->items()->create([
                    'product_id' => $payload['product_id'],
                    'product_variant_id' => $payload['product_variant_id'],
                    'quantity' => $payload['quantity'],
                    'unit_price_cents' => $payload['unit_price_cents'],
                    'subtotal_cents' => $payload['subtotal_cents'],
                    'product_name_snapshot' => $payload['product_name_snapshot'],
                    'product_sku_snapshot' => $payload['product_sku_snapshot'],
                ]);
            }

            // El pago NO se crea aquí — el cliente paga via /api/payments
            // o vía flujo externo (Stripe checkout). La invoice queda
            // con payment_status='pending' hasta que el primer pago la
            // liquide.

            $cart->update([
                'status' => 'converted',
                'expires_at' => null,
            ]);

            return $order->load(['items', 'invoices.items']);
        });
    }

    /**
     * Resuelve el tipo de cambio entre dos monedas en una fecha.
     *
     * Si ambas monedas son iguales, retorna 1.0. Si difieren, busca el
     * último `ExchangeRate` registrado.
     */
    private function resolveExchangeRate(int $fromCurrencyId, int $toCurrencyId): float
    {
        if ($fromCurrencyId === $toCurrencyId) {
            return 1.0;
        }

        $rate = ExchangeRate::query()
            ->where('from_currency_id', $fromCurrencyId)
            ->where('to_currency_id', $toCurrencyId)
            ->latest('rate_date')
            ->first();

        return $rate ? (float) $rate->value : 1.0;
    }

    /**
     * Calcula los centavos de impuesto sobre el subtotal.
     *
     * `tax_rate` es porcentaje (0..100). Para $12.34 con tasa 15%:
     *   1234 * 0.15 = 185.1 → 185 cents (banker's rounding evita sesgo).
     *
     * Si la tasa es 0 o negativa, retorna 0.
     */
    private function calculateTaxCents(int $subtotalCents, float $taxRate): int
    {
        if ($taxRate <= 0) {
            return 0;
        }

        return (int) round($subtotalCents * $taxRate / 100, 0, PHP_ROUND_HALF_EVEN);
    }

    /**
     * Genera un número de orden único formato `ORD-YYYYMMDD-XXXXXX`.
     *
     * Usa `random_int` para el sufijo (no `uniqid` porque puede chocar
     * en concurrencia). Bajo carga real debería usarse un sequence.
     */
    private function generateOrderNumber(): string
    {
        $date = now()->format('Ymd');

        do {
            $suffix = strtoupper(base_convert((string) random_int(0, 36 ** 6 - 1), 10, 36));
            $number = sprintf('ORD-%s-%s', $date, $suffix);
        } while (Order::withTrashed()->where('order_number', $number)->exists());

        return $number;
    }

    /**
     * Genera un número de invoice `INV-YYYYMMDD-XXXXXX`.
     */
    private function generateInvoiceNumber(): string
    {
        $date = now()->format('Ymd');

        do {
            $suffix = strtoupper(base_convert((string) random_int(0, 36 ** 6 - 1), 10, 36));
            $number = sprintf('INV-%s-%s', $date, $suffix);
        } while (Invoice::withTrashed()
            ->where('invoice_number', $number)->exists());

        return $number;
    }
}
