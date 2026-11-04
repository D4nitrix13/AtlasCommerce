<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Catalog;

use App\Models\Catalog\ProductImage;
use App\Services\Catalog\HoverImageBackfiller;
use App\Services\Catalog\ProductPhotoGrouper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests de `App\Services\Catalog\HoverImageBackfiller`.
 *
 * Cubre la traducción del vocabulario legacy del `alt_text` al flag
 * `is_hover`:
 *   - Cascada de roles `back` → `backbody` → `body` (en ese orden).
 *   - El rol sale del `alt_text`: último segmento tras " - vista ", en
 *     minúsculas.
 *   - La portada se elige por el `is_primary` del REPRESENTANTE (fila de
 *     mayor `width`), no de cualquier fila.
 *   - El ámbito de búsqueda es la variante de la portada: una foto de
 *     hover de OTRA variante no se marca.
 *   - La marca se pide a nivel de foto: se devuelven TODAS las filas.
 *
 * Sin DB: las filas se construyen en memoria, porque el servicio sólo
 * lee atributos del modelo.
 */
#[CoversClass(HoverImageBackfiller::class)]
#[Group('catalog')]
final class HoverImageBackfillerTest extends TestCase
{
    private HoverImageBackfiller $backfiller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->backfiller = new HoverImageBackfiller(grouper: new ProductPhotoGrouper);
    }

    /**
     * Fila en memoria, sin persistir. `forceFill` porque `id` no es
     * fillable y el desempate de `representative()` lo necesita.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function row(array $attributes): ProductImage
    {
        $image = new ProductImage;
        $image->forceFill($attributes);

        return $image;
    }

    /**
     * Portada sin rol, sin marcar primaria: la primera foto del producto.
     *
     * @return list<ProductImage>
     */
    private function cover(?int $variantId = null, int $sortOrder = 0): array
    {
        return [
            $this->row([
                'id' => 100 + $sortOrder,
                'variant_id' => $variantId,
                'sort_order' => $sortOrder,
                'width' => 1600,
                'alt_text' => 'Portada',
                'is_primary' => true,
            ]),
        ];
    }

    #[Test]
    public function it_returns_every_row_of_the_first_back_photo(): void
    {
        $backRows = [
            $this->row(['id' => 21, 'variant_id' => null, 'sort_order' => 1, 'width' => 800, 'alt_text' => 'Guitarra - vista back']),
            $this->row(['id' => 22, 'variant_id' => null, 'sort_order' => 1, 'width' => 1600, 'alt_text' => 'Guitarra - vista back']),
        ];

        $rows = $this->backfiller->hoverRowsFor(images: [
            ...$this->cover(),
            ...$backRows,
        ]);

        $this->assertSame(expected: $backRows, actual: $rows);
    }

    #[Test]
    public function it_prefers_the_back_role_over_backbody_and_body(): void
    {
        $backRows = [
            $this->row(['id' => 30, 'variant_id' => null, 'sort_order' => 3, 'width' => 1600, 'alt_text' => 'Guitarra - vista back']),
        ];

        $rows = $this->backfiller->hoverRowsFor(images: [
            ...$this->cover(),
            $this->row(['id' => 31, 'variant_id' => null, 'sort_order' => 1, 'width' => 1600, 'alt_text' => 'Guitarra - vista body']),
            $this->row(['id' => 32, 'variant_id' => null, 'sort_order' => 2, 'width' => 1600, 'alt_text' => 'Guitarra - vista backbody']),
            ...$backRows,
        ]);

        $this->assertSame(expected: $backRows, actual: $rows);
    }

    #[Test]
    public function it_falls_back_to_backbody_when_no_photo_is_back(): void
    {
        $backbodyRows = [
            $this->row(['id' => 40, 'variant_id' => null, 'sort_order' => 1, 'width' => 1600, 'alt_text' => 'Guitarra - vista backbody']),
        ];

        $rows = $this->backfiller->hoverRowsFor(images: [
            ...$this->cover(),
            $this->row(['id' => 41, 'variant_id' => null, 'sort_order' => 2, 'width' => 1600, 'alt_text' => 'Guitarra - vista body']),
            ...$backbodyRows,
        ]);

        $this->assertSame(expected: $backbodyRows, actual: $rows);
    }

    #[Test]
    public function it_falls_back_to_body_when_only_the_body_role_is_present(): void
    {
        $bodyRows = [
            $this->row(['id' => 50, 'variant_id' => null, 'sort_order' => 1, 'width' => 1600, 'alt_text' => 'Guitarra - vista body']),
        ];

        $rows = $this->backfiller->hoverRowsFor(images: [
            ...$this->cover(),
            ...$bodyRows,
        ]);

        $this->assertSame(expected: $bodyRows, actual: $rows);
    }

    #[Test]
    public function it_returns_no_rows_when_no_photo_has_a_back_role(): void
    {
        $rows = $this->backfiller->hoverRowsFor(images: [
            ...$this->cover(),
            $this->row(['id' => 60, 'variant_id' => null, 'sort_order' => 1, 'width' => 1600, 'alt_text' => 'Guitarra - vista headstock']),
        ]);

        $this->assertSame(expected: [], actual: $rows);
    }

    #[Test]
    public function it_returns_no_rows_for_a_product_without_photos(): void
    {
        $this->assertSame(expected: [], actual: $this->backfiller->hoverRowsFor(images: []));
    }

    #[Test]
    public function it_scopes_the_search_to_the_cover_variant(): void
    {
        // La back de la variante ajena NO entra: mostrarla sería cambiar
        // el acabado que la card anuncia.
        $rows = $this->backfiller->hoverRowsFor(images: [
            ...$this->cover(variantId: 7),
            $this->row(['id' => 70, 'variant_id' => 8, 'sort_order' => 1, 'width' => 1600, 'alt_text' => 'Guitarra - vista back']),
        ]);

        $this->assertSame(expected: [], actual: $rows);
    }

    #[Test]
    public function it_prefers_a_back_photo_of_the_cover_variant_over_an_earlier_back_of_another_variant(): void
    {
        $sameVariantBack = [
            $this->row(['id' => 80, 'variant_id' => 7, 'sort_order' => 2, 'width' => 1600, 'alt_text' => 'Guitarra - vista back']),
        ];

        $rows = $this->backfiller->hoverRowsFor(images: [
            ...$this->cover(variantId: 7),
            $this->row(['id' => 81, 'variant_id' => 8, 'sort_order' => 1, 'width' => 1600, 'alt_text' => 'Guitarra - vista back']),
            ...$sameVariantBack,
        ]);

        $this->assertSame(expected: $sameVariantBack, actual: $rows);
    }

    #[Test]
    public function it_chooses_the_cover_by_the_primary_flag_of_the_representative_row(): void
    {
        // La primaria vive sólo en la fila de 400 de la foto 1: el
        // representante (1600) no la tiene, así que la portada es la
        // foto 2 — y el ámbito de búsqueda pasa a ser su variante.
        $rows = $this->backfiller->hoverRowsFor(images: [
            $this->row(['id' => 90, 'variant_id' => 7, 'sort_order' => 0, 'width' => 400, 'alt_text' => 'Frente', 'is_primary' => true]),
            $this->row(['id' => 91, 'variant_id' => 7, 'sort_order' => 0, 'width' => 1600, 'alt_text' => 'Frente']),
            $this->row(['id' => 92, 'variant_id' => 8, 'sort_order' => 1, 'width' => 1600, 'alt_text' => 'Portada', 'is_primary' => true]),
            $this->row(['id' => 93, 'variant_id' => 8, 'sort_order' => 2, 'width' => 1600, 'alt_text' => 'Guitarra - vista back']),
        ]);

        $this->assertSame(
            expected: [93],
            actual: array_map(static fn (ProductImage $image): int => (int) $image->id, $rows),
        );
    }

    #[Test]
    public function it_extracts_the_role_from_the_alt_text_after_the_marker(): void
    {
        $role = $this->backfiller->roleOf(rows: [
            $this->row(['id' => 95, 'variant_id' => null, 'sort_order' => 0, 'width' => 1600, 'alt_text' => 'Fender Player - vista Back']),
        ]);

        $this->assertSame(expected: 'back', actual: $role);
    }

    #[Test]
    public function it_returns_a_null_role_when_the_alt_text_has_no_marker(): void
    {
        $role = $this->backfiller->roleOf(rows: [
            $this->row(['id' => 96, 'variant_id' => null, 'sort_order' => 0, 'width' => 1600, 'alt_text' => 'Fender Player']),
        ]);

        $this->assertNull($role);
    }

    #[Test]
    public function it_takes_the_last_marker_when_the_alt_text_has_several(): void
    {
        $role = $this->backfiller->roleOf(rows: [
            $this->row(['id' => 97, 'variant_id' => null, 'sort_order' => 0, 'width' => 1600, 'alt_text' => 'Fender - vista back - vista body']),
        ]);

        $this->assertSame(expected: 'body', actual: $role);
    }
}
