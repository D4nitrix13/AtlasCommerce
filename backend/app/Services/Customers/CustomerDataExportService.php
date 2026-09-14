<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Carts\Cart;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerAddress;
use App\Models\Customization\CustomDesign;
use App\Models\Marketing\CouponUsage;
use App\Models\Payments\Payment;
use App\Models\Returns\Refund;
use App\Models\Returns\ReturnRequest;
use App\Models\Sales\Invoice;
use App\Models\Sales\Order;
use App\Models\Support\ProductQuestion;
use App\Models\Support\ProductReview;
use App\Models\Support\SupportTicket;
use Carbon\Carbon;

/**
 * Recolector de datos personales del cliente para exportación GDPR.
 *
 * RF-CUST-042 — Exportar datos del cliente (GDPR art. 15, derecho de acceso).
 *
 * Genera un payload JSON-safe con TODA la información identificable
 * vinculada al cliente en esta instancia. La estructura es estable
 * (no rompe el contrato del cliente aunque cambien los esquemas):
 *
 *  - metadata: timestamp + version del schema de export
 *  - profile: datos del Customer + dirección del User asociado
 *  - addresses: listado de CustomerAddress
 *  - orders: pedidos + sus items + status history
 *  - invoices: facturas + items
 *  - payments: pagos realizados
 *  - refunds: reembolsos emitidos
 *  - return_requests: devoluciones + sus items
 *  - carts: carritos (incluso abandonados, para diagnóstico de UX)
 *  - wishlists: listas de deseos
 *  - product_reviews: reseñas escritas
 *  - product_questions: preguntas en Q&A
 *  - support_tickets: tickets de soporte + mensajes
 *  - custom_designs: diseños personalizados subidos
 *  - coupon_usages: cupones canjeados (sin PII, sólo contexto)
 *
 * Decisiones:
 *  - El payload es eager-loaded por tabla para evitar N+1. Si la query
 *    tarda más de 2s, loguear para futura optimización con chunk.
 *  - No se exportan: tokens Sanctum (son infraestructura, no datos),
 *    password_reset_tokens (contienen hashes sensibles), sesiones.
 *  - No se filtra por tenant: el cliente SOLO ve los datos de la
 *    instancia donde está autenticado (multi-instance, RLS aisla).
 *
 * @phpstan-type ExportPayload array{
 *   metadata: array{exported_at: string, schema_version: string, customer_id: int, anonymized: bool},
 *   profile: array<string, mixed>,
 *   addresses: list<array<string, mixed>>,
 *   orders: list<array<string, mixed>>,
 *   invoices: list<array<string, mixed>>,
 *   payments: list<array<string, mixed>>,
 *   refunds: list<array<string, mixed>>,
 *   return_requests: list<array<string, mixed>>,
 *   carts: list<array<string, mixed>>,
 *   wishlists: list<array<string, mixed>>,
 *   product_reviews: list<array<string, mixed>>,
 *   product_questions: list<array<string, mixed>>,
 *   support_tickets: list<array<string, mixed>>,
 *   custom_designs: list<array<string, mixed>>,
 *   coupon_usages: list<array<string, mixed>>
 * }
 */
final class CustomerDataExportService
{
    /**
     * Schema version. Incrementar si cambia la estructura del payload
     * de forma incompatible con clientes que ya parsean versiones anteriores.
     */
    public const SCHEMA_VERSION = '1.0.0';

    /**
     * Recolecta toda la información personal del cliente y la devuelve
     * como un array listo para serializar a JSON.
     *
     * @return ExportPayload
     */
    public function export(Customer $customer): array
    {
        $customerId = (int) $customer->id;

        return [
            'metadata' => [
                'exported_at' => Carbon::now()->toIso8601String(),
                'schema_version' => self::SCHEMA_VERSION,
                'customer_id' => $customerId,
                'anonymized' => $customer->isAnonymized(),
            ],
            'profile' => $this->profileSection(customer: $customer),
            'addresses' => $this->addressSection(customerId: $customerId),
            'orders' => $this->orderSection(customerId: $customerId),
            'invoices' => $this->invoiceSection(customerId: $customerId),
            'payments' => $this->paymentSection(customerId: $customerId),
            'refunds' => $this->refundSection(customerId: $customerId),
            'return_requests' => $this->returnRequestSection(customerId: $customerId),
            'carts' => $this->cartSection(customerId: $customerId),
            'wishlists' => $this->wishlistSection(customerId: $customerId),
            'product_reviews' => $this->productReviewSection(customerId: $customerId),
            'product_questions' => $this->productQuestionSection(customerId: $customerId),
            'support_tickets' => $this->supportTicketSection(customerId: $customerId),
            'custom_designs' => $this->customDesignSection(customerId: $customerId),
            'coupon_usages' => $this->couponUsageSection(customerId: $customerId),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function profileSection(Customer $customer): array
    {
        $payload = $customer->only([
            'id',
            'user_id',
            'customer_type',
            'identity_document',
            'first_name',
            'last_name',
            'email',
            'phone',
            'is_active',
            'registered_at',
            'anonymized_at',
            'deletion_requested_at',
        ]);

        return [
            'customer' => $payload,
            'user' => $customer->user === null
                ? null
                : $customer->user->only(['id', 'name', 'email', 'created_at']),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function addressSection(int $customerId): array
    {
        return CustomerAddress::query()
            ->where(column: 'customer_id', operator: '=', value: $customerId)
            ->get()
            ->map(fn (CustomerAddress $a): array => $a->only([
                'id',
                'address_type',
                'country',
                'department',
                'municipality',
                'detailed_address',
                'reference',
                'is_primary',
                'created_at',
            ]))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function orderSection(int $customerId): array
    {
        return Order::query()
            ->where(column: 'customer_id', operator: '=', value: $customerId)
            ->with(relations: ['items', 'statusHistory'])
            ->orderBy(column: 'ordered_at', direction: 'desc')
            ->get()
            ->map(fn (Order $o): array => [
                ...$o->only([
                    'id',
                    'order_number',
                    'store_id',
                    'status',
                    'subtotal_cents',
                    'discount_cents',
                    'tax_cents',
                    'shipping_cents',
                    'total_cents',
                    'currency_id',
                    'ordered_at',
                    'notes',
                ]),
                'items' => $o->items->map(fn ($i): array => $i->only([
                    'id',
                    'product_variant_id',
                    'quantity',
                    'unit_price_cents',
                    'subtotal_cents',
                ]))->all(),
                'status_history' => $o->statusHistory->map(fn ($h): array => $h->only([
                    'id',
                    'from_status',
                    'to_status',
                    'changed_at',
                    'notes',
                ]))->all(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function invoiceSection(int $customerId): array
    {
        return Invoice::query()
            ->where(column: 'customer_id', operator: '=', value: $customerId)
            ->with(relations: ['items'])
            ->orderBy(column: 'issued_at', direction: 'desc')
            ->get()
            ->map(fn (Invoice $i): array => [
                ...$i->only([
                    'id',
                    'invoice_number',
                    'order_id',
                    'store_id',
                    'status',
                    'subtotal_cents',
                    'tax_cents',
                    'shipping_cents',
                    'total_cents',
                    'refunded_cents',
                    'currency_id',
                    'issued_at',
                ]),
                'items' => $i->items->map(fn ($it): array => $it->only([
                    'id',
                    'product_variant_id',
                    'quantity',
                    'unit_price_cents',
                    'subtotal_cents',
                ]))->all(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function paymentSection(int $customerId): array
    {
        return Payment::query()
            ->where(column: 'customer_id', operator: '=', value: $customerId)
            ->orderBy(column: 'created_at', direction: 'desc')
            ->get()
            ->map(fn (Payment $p): array => $p->only([
                'id',
                'invoice_id',
                'payment_method_id',
                'status',
                'amount_cents',
                'currency_id',
                'installments',
                'created_at',
                'confirmed_at',
            ]))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function refundSection(int $customerId): array
    {
        return Refund::query()
            ->whereHas(
                relation: 'returnRequest',
                callback: fn ($q) => $q->where(column: 'customer_id', operator: '=', value: $customerId),
            )
            ->orderBy(column: 'created_at', direction: 'desc')
            ->get()
            ->map(fn (Refund $r): array => $r->only([
                'id',
                'return_request_id',
                'status',
                'amount_cents',
                'currency_id',
                'reason',
                'created_at',
                'processed_at',
            ]))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function returnRequestSection(int $customerId): array
    {
        return ReturnRequest::query()
            ->where(column: 'customer_id', operator: '=', value: $customerId)
            ->with(relations: ['items'])
            ->orderBy(column: 'created_at', direction: 'desc')
            ->get()
            ->map(fn (ReturnRequest $r): array => [
                ...$r->only([
                    'id',
                    'order_id',
                    'status',
                    'reason',
                    'created_at',
                    'resolved_at',
                ]),
                'items' => $r->items->map(fn ($i): array => $i->only([
                    'id',
                    'order_item_id',
                    'quantity',
                    'condition',
                ]))->all(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cartSection(int $customerId): array
    {
        return Cart::query()
            ->where(column: 'customer_id', operator: '=', value: $customerId)
            ->with(relations: ['items'])
            ->orderBy(column: 'updated_at', direction: 'desc')
            ->get()
            ->map(fn (Cart $c): array => [
                ...$c->only(['id', 'store_id', 'status', 'created_at', 'updated_at']),
                'items' => $c->items->map(fn ($i): array => $i->only([
                    'id',
                    'product_variant_id',
                    'quantity',
                    'custom_design_id',
                ]))->all(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function wishlistSection(int $customerId): array
    {
        return \App\Models\Carts\Wishlist::query()
            ->where(column: 'customer_id', operator: '=', value: $customerId)
            ->with(relations: ['items'])
            ->get()
            ->map(fn ($w): array => [
                ...$w->only(['id', 'name', 'created_at']),
                'items' => $w->items->map(fn ($i): array => $i->only([
                    'id',
                    'product_variant_id',
                    'added_at',
                ]))->all(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function productReviewSection(int $customerId): array
    {
        return ProductReview::query()
            ->where(column: 'customer_id', operator: '=', value: $customerId)
            ->orderBy(column: 'created_at', direction: 'desc')
            ->get()
            ->map(fn (ProductReview $r): array => $r->only([
                'id',
                'product_id',
                'rating',
                'title',
                'comment',
                'status',
                'created_at',
            ]))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function productQuestionSection(int $customerId): array
    {
        return ProductQuestion::query()
            ->where(column: 'customer_id', operator: '=', value: $customerId)
            ->orderBy(column: 'created_at', direction: 'desc')
            ->get()
            ->map(fn (ProductQuestion $q): array => $q->only([
                'id',
                'product_id',
                'question',
                'answer',
                'status',
                'created_at',
                'answered_at',
            ]))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function supportTicketSection(int $customerId): array
    {
        return SupportTicket::query()
            ->where(column: 'customer_id', operator: '=', value: $customerId)
            ->with(relations: ['messages'])
            ->orderBy(column: 'created_at', direction: 'desc')
            ->get()
            ->map(fn (SupportTicket $t): array => [
                ...$t->only([
                    'id',
                    'subject',
                    'status',
                    'priority',
                    'created_at',
                    'closed_at',
                ]),
                'messages' => $t->messages->map(fn ($m): array => $m->only([
                    'id',
                    'author_type',
                    'author_id',
                    'body',
                    'created_at',
                ]))->all(),
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function customDesignSection(int $customerId): array
    {
        return CustomDesign::query()
            ->where(column: 'customer_id', operator: '=', value: $customerId)
            ->orderBy(column: 'created_at', direction: 'desc')
            ->get()
            ->map(fn (CustomDesign $d): array => $d->only([
                'id',
                'product_id',
                'print_area_id',
                'name',
                'status',
                'created_at',
            ]))
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function couponUsageSection(int $customerId): array
    {
        return CouponUsage::query()
            ->where(column: 'customer_id', operator: '=', value: $customerId)
            ->orderBy(column: 'created_at', direction: 'desc')
            ->get()
            ->map(fn (CouponUsage $c): array => $c->only([
                'id',
                'coupon_id',
                'order_id',
                'discount_cents',
                'created_at',
            ]))
            ->all();
    }
}
