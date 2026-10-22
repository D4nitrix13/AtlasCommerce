<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Assets;

use App\Services\Assets\AssetFilenameParser;
use App\Services\Assets\CatalogAssetsSeeder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Tests del parseo `{role}-{WxH}` del árbol de assets.
 *
 * El parseo vive en un servicio y no duplicado: lo consumen el seeder
 * (que persiste `product_images.width` y `sort_order`), el backfill de
 * imágenes y el auditor de galerías. Si las copias se divergieran, una
 * escribiría anchos desplazados sin que ninguna se quejara.
 *
 * La regresión central: el regex anterior era
 * `^(?:[0-9]{2}-)?(?P<role>[a-z\-]+)-(?P<size_w>[0-9]+)x[0-9]+$`, con un
 * grupo de rol que sólo aceptaba letras y guiones. Los nombres reales
 * del catálogo no son sólo el rol: el pipeline deja el slug pegado
 * (`gibson-mod-collection-es-335-...-front-1600x1600.webp`), así que los
 * slugs con dígitos devolvían `null` —el archivo se descartaba en
 * silencio— y los que no tenían dígitos devolvían el slug entero como
 * "rol", que el seeder persistía con `sort_order = 99`.
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
        yield 'ancho distinto del alto' => ['body-1200x900', 'body', '1200'];
        yield 'indice legacy + rol compuesto' => ['06-back-headstock-1200x1200', 'back-headstock', '1200'];
        yield 'indice legacy + rol con guion' => ['17-finish-top-800x800', 'finish-top', '800'];
        yield 'slug con digitos antes del rol' => [
            '1962-es-335-reissue-sixties-cherry-headstock-1600x1600',
            'headstock',
            '1600',
        ];
        yield 'slug largo repetido antes del rol' => [
            'gibson-mod-collection-es-335-smooth-sepia-smooth-sepia-front-1600x1600',
            'front',
            '1600',
        ];
        yield 'slug largo repetido, rol back' => [
            'gibson-mod-collection-les-paul-junior-tropicalia-tropicalia-back-1600x1600',
            'back',
            '1600',
        ];
        yield 'rol repetido en el slug' => ['front-front-1600x1600', 'front', '1600'];
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
     * Los nombres reales de los productos Gibson que fallaban, tal cual
     * están en disco, con el resultado exacto que se observó en el
     * catálogo.
     *
     * @return iterable<string, array{string, string, int|null}>
     */
    public static function realGibsonFilenames(): iterable
    {
        // Antes: NULL — el slug tiene dígitos y el grupo de rol los excluía.
        yield 'slug con modelo + ES-335' => [
            'gibson-mod-collection-es-335-smooth-sepia-smooth-sepia-front-1600x1600',
            'front',
            1600,
        ];
        yield 'slug con año + ES-335' => [
            '1962-es-335-reissue-sixties-cherry-headstock-1600x1600',
            'headstock',
            1600,
        ];
        // Antes: el slug entero como "rol" → `sort_order = 99` y la foto
        // de atrás terminaba sirviendo de portada.
        yield 'slug largo del que sólo sobrevivía back' => [
            'gibson-mod-collection-les-paul-junior-tropicalia-tropicalia-back-1600x1600',
            'back',
            1600,
        ];
        // Antes: NULL.
        yield 'nombre sin ningún rol conocido' => [
            'gibson-mod-collection-es-335-smooth-sepia-smooth-sepia-garage-1600x1600',
            null,
            null,
        ];
    }

    #[Test]
    #[DataProvider('realGibsonFilenames')]
    public function it_resolves_the_real_catalog_filenames(string $base, ?string $expectedRole, ?int $expectedWidth): void
    {
        $parsed = (new AssetFilenameParser)->parseRoleAndSize(base: $base);

        if ($expectedRole === null) {
            $this->assertNull(
                $parsed,
                'Un nombre cuyo último segmento no es un rol del vocabulario se rechaza en vez de inventarse un rol.',
            );

            return;
        }

        $this->assertNotNull($parsed);
        $this->assertSame($expectedRole, $parsed['role']);
        $this->assertSame($expectedWidth, (int) $parsed['size_w']);
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
        // Forma correcta pero el último segmento no es un rol: es un dato
        // pendiente, no un rol inventado.
        yield 'ultimo segmento desconocido' => ['logo-1600x1600'];
        yield 'slug desconocido' => ['la-voz-mod-collection-garage-1600x1600'];
        yield 'finish sin sub-rol' => ['finish-1600x1600'];
        yield 'stem vacio' => ['-1600x1600'];
    }

    #[Test]
    #[DataProvider('unparseableFilenames')]
    public function it_returns_null_for_names_outside_the_convention(string $base): void
    {
        // `null` es "no se sabe el ancho", no "ancho 0": quien llama lo
        // traduce a NULL en la BD y el Resource no colapsa ese grupo.
        $this->assertNull((new AssetFilenameParser)->parseRoleAndSize(base: $base));
    }

    /**
     * Todo el vocabulario ordenado tiene que seguir siendo parseable: es
     * el contrato entre el nombre del archivo y `ROLE_ORDER`, y si el
     * seeder mañana agrega un rol sin tocar el parser, este test lo
     * delata.
     *
     * @return iterable<string, array{string}>
     */
    public static function knownRoles(): iterable
    {
        foreach (array_keys(CatalogAssetsSeeder::ROLE_ORDER) as $role) {
            yield $role => [$role];
        }
    }

    #[Test]
    #[DataProvider('knownRoles')]
    public function it_accepts_every_role_of_the_gallery_vocabulary(string $role): void
    {
        $parsed = (new AssetFilenameParser)->parseRoleAndSize(base: "{$role}-1600x1600");

        $this->assertNotNull($parsed, "El rol '{$role}' está en ROLE_ORDER y debe parsearse.");
        $this->assertSame($role, $parsed['role']);
        $this->assertSame('1600', $parsed['size_w']);
    }

    #[Test]
    public function it_never_returns_a_role_outside_the_vocabulary_for_a_padded_name(): void
    {
        // Un nombre puede traer cualquier cantidad de segmentos delante
        // del rol: lo único que se acepta es un sufijo que sea un rol
        // conocido, nunca un segmento suelto.
        $base = 'la-voz-mod-collection-1962-es-335-junior-algo-1600x1600';

        $parsed = (new AssetFilenameParser)->parseRoleAndSize(base: $base);

        $this->assertNull($parsed, 'Ningún sufijo de ese nombre pertenece al vocabulario.');
    }
}
