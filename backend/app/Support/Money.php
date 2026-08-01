<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Settings\Currency;
use InvalidArgumentException;

/**
 * Representación inmutable de una cantidad monetaria en centavos.
 *
 * La columna de la base de datos siempre almacena enteros en centavos
 * (la unidad atómica). Esta clase permite operar y formatear sin
 * perder precisión y sin exponerse a errores de punto flotante.
 *
 * Las conversiones desde/hacia decimal externo se hacen con un
 * `scale` (cantidad de decimales) provisto por la moneda asociada.
 *
 * @property-read int $cents
 * @property-read int $scale
 * @property-read Currency|null $currency
 */
final readonly class Money
{
    public function __construct(
        public int $cents,
        public int $scale = 2,
        public ?Currency $currency = null,
    ) {
        if ($scale < 0 || $scale > 6) {
            throw new InvalidArgumentException(
                'El scale debe estar entre 0 y 6.',
            );
        }
        if ($this->cents < PHP_INT_MIN || $this->cents > PHP_INT_MAX) {
            throw new InvalidArgumentException(
                'El valor en centavos excede el rango de un entero.',
            );
        }
    }

    /**
     * Construye un Money a partir de una cantidad en decimal (string o float).
     *
     * Ej.: `Money::fromDecimal('12.34', 2)` → `Money(1234)`.
     */
    public static function fromDecimal(string|float|int $decimal, int $scale = 2, ?Currency $currency = null): self
    {
        $sign = $decimal < 0 ? -1 : 1;
        $abs = abs((float) $decimal);

        $cents = (int) round($abs * (10 ** $scale));

        return new self(cents: $sign * $cents, scale: $scale, currency: $currency);
    }

    /**
     * Construye un Money desde centavos (la forma canónica).
     */
    public static function fromCents(int $cents, ?Currency $currency = null): self
    {
        return new self(cents: $cents, scale: $currency?->decimal_places ?? 2, currency: $currency);
    }

    /**
     * Devuelve el equivalente decimal como string (útil para serializar).
     *
     * Ej.: `(1234)->toDecimal(2)` → `"12.34"`. Con `scale = 0` devuelve
     * el entero como string sin separador decimal.
     */
    public function toDecimal(): string
    {
        $divisor = 10 ** $this->scale;
        $whole = intdiv($this->cents, $divisor);
        $absCents = $this->cents < 0 ? -$this->cents : $this->cents;
        $fraction = $absCents % $divisor;

        if ($this->scale === 0) {
            return (string) ($this->cents < 0 ? -$whole : $whole);
        }

        return sprintf('%d.%0'.$this->scale.'d', $whole, $fraction);
    }

    /**
     * Suma respetando la misma escala.
     */
    public function add(self $other): self
    {
        $this->assertSameScale($other);

        return new self(
            cents: $this->cents + $other->cents,
            scale: $this->scale,
            currency: $this->currency ?? $other->currency,
        );
    }

    /**
     * Resta respetando la misma escala.
     */
    public function subtract(self $other): self
    {
        $this->assertSameScale($other);

        return new self(
            cents: $this->cents - $other->cents,
            scale: $this->scale,
            currency: $this->currency ?? $other->currency,
        );
    }

    /**
     * Multiplica por una cantidad entera (ej.: cantidad de unidades).
     */
    public function multiplyBy(int $factor): self
    {
        return new self(
            cents: $this->cents * $factor,
            scale: $this->scale,
            currency: $this->currency,
        );
    }

    /**
     * Distribuye el monto en N partes, sin generar pérdida por redondeo.
     *
     * El último elemento absorbe los centavos sobrantes para que la suma
     * de las partes sea exactamente igual al monto original.
     *
     * @return array<int, self>
     */
    public function split(int $parts): array
    {
        if ($parts <= 0) {
            throw new InvalidArgumentException('La cantidad de partes debe ser mayor a 0.');
        }

        $base = intdiv($this->cents, $parts);
        $remainder = $this->cents - ($base * $parts);

        $result = [];
        for ($i = 0; $i < $parts; $i++) {
            $cents = $base + ($i === $parts - 1 ? $remainder : 0);

            $result[] = new self(
                cents: $cents,
                scale: $this->scale,
                currency: $this->currency,
            );
        }

        return $result;
    }

    /**
     * Formato humano. Si la moneda está asociada y tiene símbolo,
     * lo usa; de lo contrario devuelve el decimal crudo.
     */
    public function format(?string $locale = null): string
    {
        $decimal = $this->toDecimal();
        $symbol = $this->currency?->symbol ?? '';
        $code = $this->currency?->code;

        if ($symbol === '' && $code === null) {
            return $decimal;
        }

        $amount = number_format(
            num: (float) $decimal,
            decimals: $this->scale,
            decimal_separator: '.',
            thousands_separator: ',',
        );

        return trim($symbol.' '.$amount.' '.$code);
    }

    public function isZero(): bool
    {
        return $this->cents === 0;
    }

    public function isPositive(): bool
    {
        return $this->cents > 0;
    }

    public function isNegative(): bool
    {
        return $this->cents < 0;
    }

    private function assertSameScale(self $other): void
    {
        if ($this->scale !== $other->scale) {
            throw new InvalidArgumentException(sprintf(
                'No se pueden operar Money con escalas distintas (%d vs %d).',
                $this->scale,
                $other->scale,
            ));
        }
    }
}
