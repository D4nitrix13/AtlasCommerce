<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Assets;

use App\Services\Assets\AssetFilenameParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests del parseo `{role}-{WxH}` del árbol de assets.
 *
 * El regex vive acá y no duplicado: lo consumen el seeder (que persiste
 * `product_images.width`) y el backfill (que rellena las columnas que el
 * seeder histórico dejó vacías). Si las dos copias se divergieran, una
 * escribiría anchos desplazados sin que ninguna se quejara.
 */
#[CoversClass(AssetFilenameParser::class)]
final class AssetFilenameParserTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function parseableFilenames(): iterable
    {
        yield 'formato plano' => ['front-1600x1600', 'front', '1600'];
        yield 'formato indexado legacy' => ['01-front-1600x1600', 'front', '1600'];
        yield 'rol con guion' => ['back-headstock-1200x1200', 'back-headstock', '1200'];
        yield 'rol compuesto' => ['finish-peghead-800x800', 'finish-peghead', '800'];
        yield 'ancho chico' => ['knobs-400x400', 'knobs', '400'];
        yield 'indice de 2 digitos' => ['18-tailpiece-1600x1600', 'tailpiece', '1600'];
    }

    #[Test]
    #[DataProvider('parseableFilenames')]
    public function it_extracts_the_role_and_the_width(string $base, string $expectedRole, string $expectedWidth): void
    {
        $parsed = (new AssetFilenameParser)->parseRoleAndSize(base: $base);

        $this->assertNotNull($parsed);
        $this->assertSame($expectedRole, $parsed['role']);
        $this->assertSame($expectedWidth, $parsed['size_w']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unparseableFilenames(): iterable
    {
        yield 'sin tamaño' => ['front'];
        yield 'extensión suelta' => ['logo'];
        yield 'tamaño sin x' => ['front-1600'];
        yield 'mayúsculas' => ['Front-1600x1600'];
        yield 'sufijos extra' => ['front-1600x1600-v2'];
        yield 'vacío' => [''];
    }

    #[Test]
    #[DataProvider('unparseableFilenames')]
    public function it_returns_null_for_names_outside_the_convention(string $base): void
    {
        // `null` es "no se sabe el ancho", no "ancho 0": quien llama lo
        // traduce a NULL en la BD y el Resource no colapsa ese grupo.
        $this->assertNull((new AssetFilenameParser)->parseRoleAndSize(base: $base));
    }
}
