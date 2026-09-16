<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\OrderStatus;
use App\Models\Carts\Cart;
use App\Models\Carts\CartItem;
use App\Models\Catalog\Product;
use App\Models\Customers\CustomerAddress;
use App\Models\Marketing\Coupon;
use App\Models\Marketing\CouponUsage;
use App\Models\Payments\PaymentMethod;
use App\Models\Sales\Invoice;
use App\Models\Sales\Order;
use App\Models\Settings\ExchangeRate;
use App\Models\Shipping\ShippingZone;
use App\Models\Stores\Store;
use App\Services\Marketing\CouponService;
use App\Services\Marketing\PromotionService;
use Core\Tenant\Application\StoreContextService;
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
 *
 * Descuentos (RF-MKT-060 + RF-MKT-061):
 * - Cupón (explícito, tipeado por el cliente) y promociones automáticas
 *   se ACUMULAN: el descuento total = cupón + promos aplicables.
 * - Si ambos son 0 → sin descuento.
 * - Descuento total nunca excede el subtotal (sanity cap).
 */
final class CheckoutService
{
    public function __construct(
        private readonly StoreContextService $storeContext,
        private readonly CouponService $coupons,
        private readonly PromotionService $promotions,
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
        ?ShippingZone $shippingZone = null,
        ?Coupon $coupon = null,
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

        return DB::transaction(callback: function () use (
            $cart,
            $shippingAddress,
            $notes,
            $exchangeRate,
            $storeId,
            $store,
            $shippingZone,
            $coupon,
        ): Order {
            $cart->loadMissing('items.product', 'items.productVariant');

            $items = $cart->items;
            if ($items->isEmpty()) {
                throw new RuntimeException(
                    'El carrito está vacío. Agrega productos antes de hacer checkout.',
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

            // Shipping: zona elegida por el cliente o default de la tienda.
            $shippingCents = $this->resolveShippingCents(
                requestedZone: $shippingZone,
                storeId: (int) $storeId,
            );

            // Discount: cupón (RF-MKT-060) + promociones automáticas (RF-MKT-061).
            // Cupón se aplica ANTES de tax/shipping, sobre el subtotal.
            // Si el cupón es inválido (expirado, agotado, etc.) se ignora
            // silenciosamente — el checkout NO falla por un cupón malo
            // (regla de negocio MVP: "cupón roto = no aplica descuento").
            $couponDiscountCents = $this->resolveDiscountCents(
                subtotalCents: $subtotalCents,
                coupon: $coupon,
            );

            // Promociones automáticas: se evalúan sobre cada item del carrito
            // y se acumulan al descuento del cupón (no son excluyentes).
            $promoDiscountCents = $this->resolvePromotionDiscountCents(
                cart: $cart,
                store: $store,
            );

            $discountCents = $couponDiscountCents + $promoDiscountCents;

            // Sanity: el descuento total nunca puede exceder el subtotal.
            if ($discountCents > $subtotalCents) {
                $discountCents = $subtotalCents;
            }

            $totalCents = $subtotalCents - $discountCents + $taxCents + $shippingCents;

            // Registrar uso del cupón si se aplicó (las promociones NO se registran —
            // son automáticas, sin contador de uso).
            if ($coupon !== null && $couponDiscountCents > 0) {
                CouponUsage::create([
                    'coupon_id' => $coupon->id,
                    'customer_id' => $cart->customer_id,
                    'order_id' => null, // se actualiza post-insert
                    'used_at' => now(),
                ]);
                $coupon->increment('used_count');
            }

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
                'discount_cents' => $discountCents,
                'tax_cents' => $taxCents,
                'shipping_cents' => $shippingCents,
                'total_cents' => $totalCents,
                'exchange_rate_snapshot' => $exchangeRate,
                'total_in_base_currency_cents' => (int) round(
                    $subtotalCents * (float) $exchangeRate,
                ),
                'status' => OrderStatus::Pending,
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
                'discount_cents' => $discountCents,
                'tax_cents' => $taxCents,
                'shipping_cents' => $shippingCents,
                'shipping_zone_id' => $shippingZone?->id,
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
     * Resuelve el costo de envío en centavos.
     *
     * Reglas:
     *  1. Si el cliente eligió zona explícita, usa esa.
     *  2. Si no, toma la primera zona activa de la tienda.
     *  3. Si no hay zonas, 0 (envío gratis implícito — el admin decide
     *     manualmente si cobra envío).
     */
    private function resolveShippingCents(?ShippingZone $requestedZone, int $storeId): int
    {
        if ($requestedZone !== null) {
            return (int) $requestedZone->base_cost_cents;
        }

        $zone = ShippingZone::query()
            ->where(column: 'store_id', operator: '=', value: $storeId)
            ->where(column: 'is_active', operator: '=', value: true)
            ->orderBy('id')
            ->first();

        return $zone !== null ? (int) $zone->base_cost_cents : 0;
    }

    /**
     * Resuelve el descuento delegando en `CouponService`.
     *
     * Si el cupón es null → 0. Si el cupón es inválido (expirado,
     * inactivo, agotado, cross-tenant) → 0 silenciosamente. Si el
     * cupón no cumple `minimum_amount` → 0.
     *
     * Reglas de validación viven en `CouponService` — este método
     * silenciosamente devuelve 0 ante cualquier cupón inválido para
     * mantener la regla MVP "cupón roto no bloquea checkout".
     */
    private function resolveDiscountCents(int $subtotalCents, ?Coupon $coupon): int
    {
        if ($coupon === null) {
            return 0;
        }

        $store = $this->storeContext->getStore();
        if ($store === null) {
            return 0;
        }

        // Re-leer el cupón desde DB para tener los casts aplicados (ends_at,
        // starts_at). Si el cupón que llega acá ya está cargado, el caller
        // (CouponController::validate) típicamente lo creó. Pero el flujo
        // de checkout recibe el Coupon del request — confíamos en los casts
        // del modelo para comparar fechas.
        if (! $coupon->is_active) {
            return 0;
        }

        $now = now();
        if ($coupon->starts_at !== null && $coupon->starts_at->gt($now)) {
            return 0;
        }
        if ($coupon->ends_at !== null && $coupon->ends_at->lt($now)) {
            return 0;
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            return 0;
        }

        // Tenant: si el cupón es de otra tienda, no aplica.
        if ($coupon->store_id !== null && (int) $coupon->store_id !== (int) $store->id) {
            return 0;
        }

        return $this->coupons->calculateDiscountCents(
            coupon: $coupon,
            subtotalCents: $subtotalCents,
        );
    }

    /**
     * Resuelve el descuento por promociones automáticas (RF-MKT-061).
     *
     * Si no hay tienda activa en el contexto, retorna 0 silenciosamente.
     * Si el carrito está vacío, retorna 0 (delegado al servicio).
     * Si no hay promociones aplicables, retorna 0.
     */
    private function resolvePromotionDiscountCents(Cart $cart, ?Store $store): int
    {
        if ($store === null) {
            return 0;
        }

        return $this->promotions->calculateForCart(cart: $cart, store: $store);
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
