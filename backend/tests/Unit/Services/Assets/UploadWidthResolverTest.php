<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Assets;

use App\Services\Assets\UploadWidthResolver;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests de `App\Services\Assets\UploadWidthResolver`.
 *
 * Cubre el contrato de `assets.upload.widths`:
 *   - Default cuando la clave está ausente (comportamiento previo intacto).
 *   - Default declarado en `config/assets.php` = el de la constante.
 *   - Lista propia por array y por CSV crudo.
 *   - Rechazo con error explícito de: vacía, no ascendente, duplicados,
 *     no positiva y no entera.
 *   - Que el mensaje nombre la clave de config y el valor culpable.
 */
#[CoversClass(UploadWidthResolver::class)]
#[Group('assets')]
final class UploadWidthResolverTest extends TestCase
{
    private UploadWidthResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new UploadWidthResolver;
    }

    /**
     * @return array<string, mixed>
     */
    private function configured(mixed $value): void
    {
        config()->set(key: UploadWidthResolver::CONFIG_KEY, value: $value);
    }

    // -----------------------------------------------------------------------
    // Defaults
    // -----------------------------------------------------------------------

    #[Test]
    public function it_falls_back_to_the_default_widths_when_the_key_is_absent(): void
    {
        config()->offsetUnset(UploadWidthResolver::CONFIG_KEY);

        $this->assertSame(
            expected: [400, 800, 1200, 1600],
            actual: $this->resolver->resolve(),
        );
    }

    #[Test]
    public function the_config_file_ships_the_same_default_as_the_constant(): void
    {
        $fromConfig = (array) config(key: UploadWidthResolver::CONFIG_KEY);

        // Sin `config()->set`: lo que devuelve la app arrancada de verdad.
        $this->assertSame(expected: UploadWidthResolver::DEFAULT_WIDTHS, actual: $fromConfig);
    }

    // -----------------------------------------------------------------------
    // Listas válidas
    // -----------------------------------------------------------------------

    #[Test]
    public function it_accepts_a_custom_list_as_an_array(): void
    {
        $this->configured([300, 900]);

        $this->assertSame(expected: [300, 900], actual: $this->resolver->resolve());
    }

    #[Test]
    public function it_accepts_a_raw_csv_list(): void
    {
        // El path que recorre `ASSETS_UPLOAD_WIDTHS=250,500` sin pasar por
        // `config:cache`: el valor crudo llega como string.
        $this->configured('250,500');

        $this->assertSame(expected: [250, 500], actual: $this->resolver->resolve());
    }

    #[Test]
    public function it_accepts_numeric_strings_and_trims_their_whitespace(): void
    {
        $this->configured([' 320 ', '640']);

        $this->assertSame(expected: [320, 640], actual: $this->resolver->resolve());
    }

    // -----------------------------------------------------------------------
    // Listas inválidas — cada una con su propio diagnóstico
    // -----------------------------------------------------------------------

    #[Test]
    #[DataProvider('invalidLists')]
    public function it_rejects_an_invalid_list_instead_of_sanitizing_it(mixed $configured, string $expectedDetail): void
    {
        $this->configured($configured);

        // `expectExceptionMessage()` se SOBREESCRIBE en cada llamada, así
        // que el mensaje se verifica entero con dos `assertStringContainsString`.
        try {
            $this->resolver->resolve();
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString(
                needle: UploadWidthResolver::CONFIG_KEY,
                haystack: $exception->getMessage(),
            );
            $this->assertStringContainsString(needle: $expectedDetail, haystack: $exception->getMessage());

            return;
        }

        $this->fail('Una lista de anchos inválida no puede resolverse en silencio.');
    }

    /**
     * @return array<string, array{0: mixed, 1: string}>
     */
    public static function invalidLists(): array
    {
        return [
            'lista vacía' => [[], 'la lista está vacía'],
            'csv vacío' => ['', 'la lista está vacía'],
            'csv de comas' => [',,', 'la lista está vacía'],
            'no ascendente' => [[1600, 400], 'no está en orden ascendente'],
            'duplicados' => [[400, 400], 'anchos repetidos'],
            'cero' => [[0, 400], 'no es positivo'],
            'negativo' => [[-400, 800], 'no es positivo'],
            'texto' => [['400', 'largo'], 'no es un entero'],
            'float' => [[400.5], 'no es un entero'],
            'float entero' => [[400.0], 'no es un entero'],
            'csv con texto' => ['400,auto', 'no es un entero'],
            'csv con decimal' => ['400,4.5', 'no es un entero'],
            'no es una lista' => [400, 'no es una lista'],
        ];
    }

    #[Test]
    public function the_rejection_message_names_the_offending_value(): void
    {
        $this->configured([400, 'largo']);

        try {
            $this->resolver->resolve();
        } catch (InvalidArgumentException $exception) {
            // El token original sobrevive: 'largo', no un 0 coercionado.
            $this->assertStringContainsString(needle: "'largo'", haystack: $exception->getMessage());

            return;
        }

        $this->fail('Un token no entero tiene que rechazarse.');
    }

    // -----------------------------------------------------------------------
    // Parseo de la variable de entorno
    // -----------------------------------------------------------------------

    #[Test]
    public function it_parses_a_csv_into_integers(): void
    {
        $this->assertSame(
            expected: [400, 800, 1200, 1600],
            actual: UploadWidthResolver::fromEnvList(list: '400, 800,1200 , 1600'),
        );
    }

    #[Test]
    public function the_env_parse_keeps_non_integer_tokens_for_the_error_message(): void
    {
        $this->assertSame(
            expected: [400, 'auto'],
            actual: UploadWidthResolver::fromEnvList(list: '400,auto'),
        );
    }
}
