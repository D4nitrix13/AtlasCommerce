<?php

declare(strict_types=1);

namespace Tests\Unit\Concerns;

use App\Models\Catalog\Brand;
use App\Models\Catalog\Category;
use App\Models\Concerns\HasSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[CoversClass(HasSlug::class)]
#[Group('concerns')]
final class HasSlugTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function slug_is_generated_from_name_when_missing(): void
    {
        $brand = new Brand(['name' => 'Hello World']);

        // Fuerza el evento saving manualmente.
        $brand->save();

        $this->assertSame('hello-world', $brand->slug);
    }

    #[Test]
    public function explicit_slug_is_respected(): void
    {
        $brand = new Brand([
            'name' => 'Hello',
            'slug' => 'custom-slug',
        ]);

        $brand->save();

        $this->assertSame('custom-slug', $brand->slug);
    }

    #[Test]
    public function generate_unique_slug_appends_suffix_on_collision(): void
    {
        Category::create(['name' => 'Shoes', 'slug' => 'shoes']);

        $second = new Category(['name' => 'Shoes']);

        $slug = $second->generateUniqueSlug();

        $this->assertSame('shoes-2', $slug);
    }
}
