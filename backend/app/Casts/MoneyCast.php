<?php

declare(strict_types=1);

namespace App\Casts;

use App\Models\Settings\Currency;
use App\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Cast que conecta una columna entera (centavos) con un valor
 * {@see Money} en lectura y el conteo de centavos en escritura.
 *
 * La escala (cantidad de decimales) se infiere de la moneda asociada
 * al modelo, si el modelo expone una relación `currency`. Si el modelo
 * no tiene moneda (configuración global pendiente), se usa 2 por
 * defecto.
 *
 * @implements CastsAttributes<Money, int>
 */
final class MoneyCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $arguments
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        $currency = $this->resolveCurrency($model);

        return new Money(
            cents: (int) $value,
            scale: $currency?->decimal_places ?? 2,
            currency: $currency,
        );
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Money) {
            return $value->cents;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) || is_float($value)) {
            return Money::fromDecimal($value, scale: 2)->cents;
        }

        return null;
    }

    private function resolveCurrency(Model $model): ?Currency
    {
        if (method_exists($model, 'currency')) {
            $relation = $model->currency();

            return $relation instanceof Relation
                ? $relation->getResults()
                : null;
        }

        return null;
    }
}
