<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Console\Commands\SyncLeftHandedFlagCommand;
use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Catalog\Product;
use App\Models\Settings\Currency;
use App\Services\Catalog\VariantHandednessResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tests del comando `catalog:sync-left-handed-flag` (Oct 2026).
 *
 * El desacuerdo que el comando viene a cerrar: 18 productos del catálogo
 * tienen "left handed" en el nombre y `products.is_left_handed = false`.
 * El PDP dice "zurdo" (lee la variante, ya backfilleada) y el producto
 * dice "diestro" (lee el flag) en cualquier parte que lo use.
 *
 * Cubre:
 *   - Default = dry-run: reporta la lista y NO escribe.
 *   - `--write`: pone `is_left_handed = true` en los candidatos.
 *   - Un producto ya marcado `true` no se toca (no se revierte).
 *   - Un producto cuyo texto NO delata mano izquierda no se toca.
 *   - Idempotencia: la segunda corrida con `--write` reporta 0.
 *   - `--product=` acota a un solo producto.
 */
#[CoversClass(SyncLeftHandedFlagCommand::class)]
#[CoversClass(VariantHandednessResolver::class)]
#[Group('console')]
#[Group('catalog')]
final class SyncLeftHandedFlagCommandTest extends TestCase
{
    use RefreshDatabase;

    private Product $nameSaysLeftHanded;

    private Product $alreadyFlagged;

    private Product $rightHanded;

    protected function setUp(): void
    {
        parent::setUp();

        $currency = Currency::query()->create(attributes: [
            'code' => 'USD',
            'name' => 'US Dollar',
            'symbol' => '$',
            'is_active' => true,
            'is_primary' => true,
        ]);
        $brand = Brand::query()->create(attributes: [
            'name' => 'Epiphone',
            'slug' => 'epiphone',
            'is_active' => true,
        ]);
        $category = Category::query()->create(attributes: [
            'name' => 'Guitars',
            'slug' => 'guitars',
            'is_active' => true,
        ]);

        $makeProduct = function (string $slug, string $name, bool $isLeftHanded) use ($brand, $category, $currency): Product {
            return Product::query()->create(attributes: [
                'name' => $name,
                'slug' => $slug,
                'sku' => strtoupper(str_replace('-', '', $slug)),
                'description' => 'Test product',
                'category_id' => $category->id,
                'brand_id' => $brand->id,
                'currency_id' => $currency->id,
                'base_price_cents' => 100000,
                'is_active' => true,
                'is_left_handed' => $isLeftHanded,
            ]);
        };

        // El caso del brief: el nombre dice zurdo, el flag quedó en false.
        $this->nameSaysLeftHanded = $makeProduct(
            slug: 'test-1960-les-paul-iced-tea-burst-left-handed',
            name: 'Test 1960 Les Paul Standard Reissue Iced Tea Burst Left Handed (Epiphone)',
            isLeftHanded: false,
        );

        // Ya sincronizado: el comando no lo debe revertir.
        $this->alreadyFlagged = $makeProduct(
            slug: 'test-sg-tribute-cherry-burst-left-handed',
            name: 'Test Sg Tribute Plus Cherry Burst Left Handed (Epiphone)',
            isLeftHanded: true,
        );

        // Producto corriente: ni el nombre ni el flag dicen nada.
        $this->rightHanded = $makeProduct(
            slug: 'test-j-45-standard',
            name: 'Test J-45 Standard (Gibson)',
            isLeftHanded: false,
        );
    }

    #[Test]
    public function it_reports_but_does_not_write_by_default(): void
    {
        $this->artisan(command: 'catalog:sync-left-handed-flag')
            ->expectsOutputToContain('DRY-RUN')
            ->assertSuccessful();

        $this->assertFalse(
            (bool) $this->nameSaysLeftHanded->fresh()?->is_left_handed,
            'Sin `--write` el comando no puede escribir nada.',
        );
    }

    #[Test]
    public function it_flags_the_product_whose_name_says_left_handed_when_write_is_passed(): void
    {
        $this->artisan(command: 'catalog:sync-left-handed-flag', parameters: ['--write' => true])
            ->assertSuccessful();

        $this->assertTrue((bool) $this->nameSaysLeftHanded->fresh()?->is_left_handed);
    }

    #[Test]
    public function it_never_reverts_a_product_that_is_already_flagged(): void
    {
        $this->artisan(command: 'catalog:sync-left-handed-flag', parameters: ['--write' => true])
            ->assertSuccessful();

        $this->assertTrue(
            (bool) $this->alreadyFlagged->fresh()?->is_left_handed,
            'Un flag ya puesto a mano es un dato existente, no uno faltante.',
        );
    }

    #[Test]
    public function it_leaves_products_whose_name_does_not_say_left_handed_alone(): void
    {
        $this->artisan(command: 'catalog:sync-left-handed-flag', parameters: ['--write' => true])
            ->assertSuccessful();

        $this->assertFalse((bool) $this->rightHanded->fresh()?->is_left_handed);
    }

    #[Test]
    public function it_is_idempotent(): void
    {
        $this->artisan(command: 'catalog:sync-left-handed-flag', parameters: ['--write' => true])->run();

        // La segunda corrida no encuentra candidatos: el WHERE ya exige
        // `is_left_handed = false`, así que lo sincronizado queda fuera
        // del scan y no se cuenta dos veces.
        $this->artisan(command: 'catalog:sync-left-handed-flag', parameters: ['--write' => true])
            ->expectsOutputToContain('A sincronizar (nombre dice zurdo, flag dice false): 0')
            ->assertSuccessful();

        $this->assertTrue((bool) $this->nameSaysLeftHanded->fresh()?->is_left_handed);
        $this->assertTrue((bool) $this->alreadyFlagged->fresh()?->is_left_handed);
        $this->assertFalse((bool) $this->rightHanded->fresh()?->is_left_handed);
    }

    #[Test]
    public function it_scopes_to_a_single_product_with_the_product_option(): void
    {
        $this->artisan(command: 'catalog:sync-left-handed-flag', parameters: [
            '--write' => true,
            '--product' => $this->nameSaysLeftHanded->slug,
        ])->assertSuccessful();

        $this->assertTrue((bool) $this->nameSaysLeftHanded->fresh()?->is_left_handed);
    }
}
