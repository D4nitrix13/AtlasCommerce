<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sales\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoint admin que lista orders recientes del tenant actual.
 *
 * Diseñado para alimentar el widget `RecentOrdersTable` del dashboard
 * admin (frontend/src/components/admin/dashboard/RecentOrdersTable.astro).
 *
 * Diferencia con `OrderController::index` (endpoint customer-scoped):
 *   - **NO** filtra por customer_id (es admin, no cliente).
 *   - Scope de tenant via `BelongsToStore` global scope + middleware
 *     `store.domain` que el grupo `admin` ya enrutado.
 *   - Devuelve un shape custom optimizado para la tabla (no el
 *     OrderResource completo).
 *
 * Limite: `?limit=N`, default 10, max 50 (defensa contra listing
 * excesivo en dashboard).
 */
class RecentOrdersController extends Controller
{
    private const DEFAULT_LIMIT = 10;

    private const MAX_LIMIT = 50;

    /**
     * Human-readable labels para OrderStatus.
     *
     * El enum OrderStatus es plain (cases 'pending'|'processing'|'completed'|'cancelled')
     * — no tiene metodo `label()` propio. Si en sesion futura se agrega
     * un ->label() method, este array se reemplaza por llamadas al enum.
     */
    private const STATUS_LABELS = [
        'pending' => 'Pendiente',
        'processing' => 'Procesando',
        'completed' => 'Completado',
        'cancelled' => 'Cancelado',
    ];

    public function index(Request $request): JsonResponse
    {
        $limit = min(
            (int) $request->query(key: 'limit', default: self::DEFAULT_LIMIT),
            self::MAX_LIMIT,
        );

        $orders = Order::query()
            ->with([
                'customer.user',
                'items',
                'currency',
            ])
            ->orderByDesc('ordered_at')
            ->orderByDesc('id')
            ->limit(value: $limit)
            ->get();

        return response()->json(data: [
            'data' => $orders->map(fn (Order $order) => [
                'id' => $order->id,
                'order_number' => '#ORD-'.$order->id,
                'customer_name' => trim(
                    ($order->customer?->first_name ?? '').' '.($order->customer?->last_name ?? ''),
                ) ?: ($order->customer?->user?->name ?? '—'),
                'product_summary' => $this->buildProductSummary(order: $order),
                'status_label' => self::STATUS_LABELS[$order->status?->value ?? ''] ?? $order->status?->value,
                'status_color' => $this->statusColorClass(status: $order->status),
                'amount_label' => $this->formatAmount(order: $order),
            ])->all(),
        ]);
    }

    /**
     * Resume los items del order en una sola linea legible.
     *
     * Formato: 'Primer item name (qty X), +N mas' cuando hay mas de 1.
     */
    private function buildProductSummary(Order $order): string
    {
        $items = $order->items;
        if ($items === null || $items->isEmpty()) {
            return 'Sin items';
        }

        $first = $items->first();
        $name = $first?->product?->name ?? $first?->name ?? 'Item';
        $qty = (int) ($first?->quantity ?? 0);
        $extra = $items->count() - 1;

        return $extra > 0
            ? "{$name} (qty {$qty}) +{$extra} más"
            : "{$name} (qty {$qty})";
    }

    /**
     * Mapea OrderStatus a clase Tailwind de color (mismas clases que
     * el frontend mock data para consistencia visual).
     */
    private function statusColorClass(mixed $status): string
    {
        return match ($status?->value ?? $status) {
            'pending' => 'bg-amber-100 text-amber-700',
            'processing' => 'bg-sky-100 text-sky-700',
            'completed' => 'bg-emerald-100 text-emerald-700',
            'shipped' => 'bg-sky-100 text-sky-700',
            'delivered' => 'bg-violet-100 text-violet-700',
            'cancelled' => 'bg-slate-100 text-slate-700',
            default => 'bg-slate-100 text-slate-700',
        };
    }

    /**
     * Formatea el total del order con la currency del tenant.
     */
    private function formatAmount(Order $order): string
    {
        $currency = $order->currency?->code ?? 'USD';
        $symbol = match ($currency) {
            'MXN' => '$',
            'USD' => '$',
            'EUR' => '€',
            default => $currency.' ',
        };

        $amount = number_format(num: $order->total_cents / 100, decimals: 2, thousands_separator: ',', decimal_separator: '.');

        return "{$symbol}{$amount}";
    }
}
