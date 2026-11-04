<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Catalog;

use App\Models\Catalog\ProductImage;
use App\Services\Catalog\ProductPhotoGrouper;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests de `App\Services\Catalog\ProductPhotoGrouper`.
 *
 * Cubre la identidad de una FOTO — la terna `(variant_id, sort_order)`:
 *   - Agrupamiento: filas que comparten la terna son la misma foto, y
 *     basta con que difiera un componente para que sean fotos distintas.
 *   - `variant_id` NULL agrupa bajo el pseudo-escopo "unscoped".
 *   - Orden de salida: primera aparición (orden de galería).
 *   - `representative()`: la fila de mayor `width`, con empate por `id`
 *     menor, y fallback a la primera fila cuando ninguna tiene `width`.
 *   - `hasKnownWidths()`.
 *
 * Sin DB: las filas se construyen en memoria, porque el servicio sólo
 * lee atributos del modelo.
 */
#[CoversClass(ProductPhotoGrouper::class)]
#[Group('catalog')]
final class ProductPhotoGrouperTest extends TestCase
{
    private ProductPhotoGrouper $grouper;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grouper = new ProductPhotoGrouper;
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
     * @return list<int|null>
     */
    private function groupSizes(array $groups): array
    {
        return array_map(static fn (array $group): int => count($group['rows']), $groups);
    }

    #[Test]
    public function it_groups_rows_sharing_variant_and_sort_order_into_one_photo(): void
    {
        $groups = $this->grouper->group(images: [
            $this->row(['id' => 1, 'variant_id' => 7, 'sort_order' => 3, 'width' => 400]),
            $this->row(['id' => 2, 'variant_id' => 7, 'sort_order' => 3, 'width' => 1600]),
        ]);

        $this->assertCount(expectedCount: 1, haystack: $groups);
    }

    #[Test]
    public function it_keeps_photos_apart_when_only_the_variant_differs(): void
    {
        $groups = $this->grouper->group(images: [
            $this->row(['id' => 1, 'variant_id' => 7, 'sort_order' => 0, 'width' => 1600]),
            $this->row(['id' => 2, 'variant_id' => 8, 'sort_order' => 0, 'width' => 1600]),
        ]);

        $this->assertCount(expectedCount: 2, haystack: $groups);
    }

    #[Test]
    public function it_keeps_photos_apart_when_only_the_sort_order_differs(): void
    {
        $groups = $this->grouper->group(images: [
            $this->row(['id' => 1, 'variant_id' => 7, 'sort_order' => 0, 'width' => 1600]),
            $this->row(['id' => 2, 'variant_id' => 7, 'sort_order' => 1, 'width' => 1600]),
        ]);

        $this->assertCount(expectedCount: 2, haystack: $groups);
    }

    #[Test]
    public function it_groups_null_variant_rows_as_a_single_unscoped_photo(): void
    {
        $groups = $this->grouper->group(images: [
            $this->row(['id' => 1, 'variant_id' => null, 'sort_order' => 0, 'width' => 400]),
            $this->row(['id' => 2, 'variant_id' => null, 'sort_order' => 0, 'width' => 1600]),
        ]);

        $this->assertCount(expectedCount: 1, haystack: $groups);
        $this->assertNull($groups[0]['variant_id']);
    }

    #[Test]
    public function it_orders_groups_by_first_appearance_in_the_input(): void
    {
        $groups = $this->grouper->group(images: [
            $this->row(['id' => 1, 'variant_id' => 7, 'sort_order' => 1, 'width' => 1600]),
            $this->row(['id' => 2, 'variant_id' => 8, 'sort_order' => 0, 'width' => 1600]),
            $this->row(['id' => 3, 'variant_id' => 7, 'sort_order' => 1, 'width' => 800]),
        ]);

        $this->assertSame(
            expected: ['7#1', '8#0'],
            actual: array_map(
                static fn (array $group): string => ProductPhotoGrouper::keyOf(image: $group['rows'][0]),
                $groups,
            ),
        );
    }

    #[Test]
    public function it_picks_the_widest_row_as_representative(): void
    {
        $widest = $this->row(['id' => 2, 'variant_id' => null, 'sort_order' => 0, 'width' => 1600]);

        $representative = $this->grouper->representative(rows: [
            $this->row(['id' => 1, 'variant_id' => null, 'sort_order' => 0, 'width' => 400]),
            $widest,
            $this->row(['id' => 3, 'variant_id' => null, 'sort_order' => 0, 'width' => 800]),
        ]);

        $this->assertSame(expected: $widest, actual: $representative);
    }

    #[Test]
    public function it_breaks_a_width_tie_by_the_lowest_id(): void
    {
        $lowest = $this->row(['id' => 4, 'variant_id' => null, 'sort_order' => 0, 'width' => 800]);

        $representative = $this->grouper->representative(rows: [
            $this->row(['id' => 9, 'variant_id' => null, 'sort_order' => 0, 'width' => 800]),
            $lowest,
        ]);

        $this->assertSame(expected: $lowest, actual: $representative);
    }

    #[Test]
    public function it_falls_back_to_the_first_row_when_no_row_has_width(): void
    {
        $first = $this->row(['id' => 1, 'variant_id' => null, 'sort_order' => 0, 'width' => null]);

        $representative = $this->grouper->representative(rows: [
            $first,
            $this->row(['id' => 2, 'variant_id' => null, 'sort_order' => 0, 'width' => null]),
        ]);

        $this->assertSame(expected: $first, actual: $representative);
    }

    #[Test]
    public function it_reports_unknown_widths_when_no_row_has_one(): void
    {
        $known = $this->grouper->hasKnownWidths(rows: [
            $this->row(['id' => 1, 'variant_id' => null, 'sort_order' => 0, 'width' => null]),
            $this->row(['id' => 2, 'variant_id' => null, 'sort_order' => 0, 'width' => null]),
        ]);

        $this->assertFalse($known);
    }

    #[Test]
    public function it_reports_known_widths_when_any_row_has_one(): void
    {
        $known = $this->grouper->hasKnownWidths(rows: [
            $this->row(['id' => 1, 'variant_id' => null, 'sort_order' => 0, 'width' => null]),
            $this->row(['id' => 2, 'variant_id' => null, 'sort_order' => 0, 'width' => 400]),
        ]);

        $this->assertTrue($known);
    }
}
