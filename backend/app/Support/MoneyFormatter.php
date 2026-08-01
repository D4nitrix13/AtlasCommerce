<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Settings\Currency;

/**
 * Helper compartido para formatear montos en respuestas API.
 *
 * Mantiene un único punto de decisión sobre cómo presentar plata al
 * cliente (`cents` interno vs `decimal` legible). Usado por Resources
 * y por formatters de Exception para mensajes de validación.
 */
final class MoneyFormatter
{
    /**
     * Formato por defecto: `"19.99"` (decimal puro).
     *
     * Si la currency tiene `symbol` y `code`, devuelve
     * `"C$ 19.99 NIO"` con espacios.
     */
    public static function format(int $cents, ?Currency $currency = null, ?int $scale = null): string
    {
        $scale ??= $currency?->decimal_places ?? 2;
        $divisor = 10 ** $scale;
        $whole = intdiv($cents, $divisor);
        $fraction = abs($cents % $divisor);

        $decimal = $scale === 0
            ? (string) $whole
            : sprintf('%d.%0'.$scale.'d', $whole, $fraction);

        if ($currency === null) {
            return $decimal;
        }

        $parts = array_filter([$currency->symbol, $decimal, $currency->code]);

        return implode(' ', $parts);
    }

    /**
     * Payload JSON-ready: devuelve array con `cents` (int) y `formatted`
     * (string). El cliente puede usar uno u otro según su UI.
     *
     * @return array{cents: int, formatted: string, currency_code: string|null}
     */
    public static function payload(int $cents, ?Currency $currency = null): array
    {
        return [
            'cents' => $cents,
            'formatted' => self::format($cents, $currency),
            'currency_code' => $currency?->code,
        ];
    }
}
