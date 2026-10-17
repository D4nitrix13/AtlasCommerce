<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Assets;

use App\Services\Assets\StorageKeyDeriver;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests de `App\Services\Assets\StorageKeyDeriver`.
 *
 * Cubre:
 *   - `derive()`: happy paths + 4 failure paths.
 *   - `validate()`: happy paths + 8 failure paths (rechazo de traversal,
 *     null bytes, uppercase slug, hash length incorrecto, etc).
 *   - `extractComponents()`: tenant/shared + invalid.
 *
 * La clase es pura (sin I/O, sin facade), así que los tests no
 * requieren DB ni storage fake — solo instanciación y asserciones.
 */
#[CoversClass(StorageKeyDeriver::class)]
#[Group('assets')]
final class StorageKeyDeriverTest extends TestCase
{
    private StorageKeyDeriver $deriver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->deriver = new StorageKeyDeriver;
    }

    // -----------------------------------------------------------------------
    // derive()
    // -----------------------------------------------------------------------

    #[Test]
    public function it_produces_tenant_path_with_12_char_content_hash(): void
    {
        $path = $this->deriver->derive(
            tenantSlug: 'panda-estampados',
            collection: 'branding',
            entity: 'logo',
            content: 'fake-webp-binary',
            extension: 'webp',
        );

        $this->assertMatchesRegularExpression(
            pattern: '#^tenants/panda-estampados/branding/logo/[a-f0-9]{12}\.webp$#',
            string: $path,
        );
    }

    #[Test]
    public function it_produces_deterministic_hash_for_same_content(): void
    {
        $a = $this->deriver->derive(
            tenantSlug: 'panda-estampados',
            collection: 'products',
            entity: 'camiseta-oversized',
            content: 'identical-binary-content',
            extension: 'webp',
        );

        $b = $this->deriver->derive(
            tenantSlug: 'panda-estampados',
            collection: 'products',
            entity: 'camiseta-oversized',
            content: 'identical-binary-content',
            extension: 'webp',
        );

        $this->assertSame($a, $b);
    }

    #[Test]
    public function it_produces_different_hash_for_different_content(): void
    {
        $a = $this->deriver->derive(
            tenantSlug: 'panda-estampados',
            collection: 'products',
            entity: 'camiseta-oversized',
            content: 'binary-content-A',
            extension: 'webp',
        );

        $b = $this->deriver->derive(
            tenantSlug: 'panda-estampados',
            collection: 'products',
            entity: 'camiseta-oversized',
            content: 'binary-content-B',
            extension: 'webp',
        );

        $this->assertNotSame($a, $b);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function supportedExtensions(): array
    {
        return [
            'webp' => ['webp'],
            'png' => ['png'],
            'jpg' => ['jpg'],
            'jpeg' => ['jpeg'],
            'svg' => ['svg'],
        ];
    }

    #[Test]
    #[DataProvider('supportedExtensions')]
    public function it_supports_all_documented_extensions(string $extension): void
    {
        $path = $this->deriver->derive(
            tenantSlug: 'panda-estampados',
            collection: 'branding',
            entity: 'logo',
            content: 'fake-content',
            extension: $extension,
        );

        $this->assertStringEndsWith(suffix: '.'.$extension, string: $path);
    }

    #[Test]
    public function it_rejects_invalid_extension(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->deriver->derive(
            tenantSlug: 'panda-estampados',
            collection: 'branding',
            entity: 'logo',
            content: 'fake-content',
            extension: 'gif',
        );
    }

    #[Test]
    public function it_rejects_empty_content(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->deriver->derive(
            tenantSlug: 'panda-estampados',
            collection: 'branding',
            entity: 'logo',
            content: '',
            extension: 'webp',
        );
    }

    #[Test]
    public function it_rejects_invalid_slug_chars(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->deriver->derive(
            tenantSlug: 'Panda_Estampados',
            collection: 'branding',
            entity: 'logo',
            content: 'fake-content',
            extension: 'webp',
        );
    }

    #[Test]
    public function it_rejects_invalid_collection(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->deriver->derive(
            tenantSlug: 'panda-estampados',
            collection: 'unknown',
            entity: 'logo',
            content: 'fake-content',
            extension: 'webp',
        );
    }

    // -----------------------------------------------------------------------
    // validate()
    // -----------------------------------------------------------------------

    #[Test]
    public function it_accepts_well_formed_tenant_path(): void
    {
        $path = 'tenants/panda-estampados/products/camiseta-oversized/abc123def456.webp';

        $this->assertTrue($this->deriver->validate(path: $path));
    }

    #[Test]
    public function it_accepts_well_formed_shared_path(): void
    {
        $path = 'shared/social-icons/abc123def456.webp';

        $this->assertTrue($this->deriver->validate(path: $path));
    }

    #[Test]
    public function it_rejects_path_traversal(): void
    {
        $path = 'tenants/panda-estampados/products/camiseta-oversized/../../../etc/passwd';

        $this->assertFalse($this->deriver->validate(path: $path));
    }

    #[Test]
    public function it_rejects_null_byte_injection(): void
    {
        $path = "tenants/panda-estampados/branding/logo\0abc123def456.webp";

        $this->assertFalse($this->deriver->validate(path: $path));
    }

    #[Test]
    public function it_rejects_uppercase_slug(): void
    {
        $path = 'tenants/Panda-Estampados/branding/logo/abc123def456.webp';

        $this->assertFalse($this->deriver->validate(path: $path));
    }

    #[Test]
    public function it_rejects_invalid_hash_length(): void
    {
        // 11 chars hex (uno menos que el HASH_LENGTH = 12)
        $path = 'tenants/panda-estampados/branding/logo/abc123def45.webp';

        $this->assertFalse($this->deriver->validate(path: $path));
    }

    #[Test]
    public function it_rejects_non_hex_hash_chars(): void
    {
        // 'g' no es hex
        $path = 'tenants/panda-estampados/branding/logo/abc123def45g.webp';

        $this->assertFalse($this->deriver->validate(path: $path));
    }

    #[Test]
    public function it_rejects_unsupported_extension(): void
    {
        $path = 'tenants/panda-estampados/branding/logo/abc123def456.gif';

        $this->assertFalse($this->deriver->validate(path: $path));
    }

    #[Test]
    public function it_rejects_empty_path(): void
    {
        $this->assertFalse($this->deriver->validate(path: ''));
    }

    #[Test]
    public function it_rejects_absolute_path_starting_with_slash(): void
    {
        $path = '/tenants/panda-estampados/branding/logo/abc123def456.webp';

        $this->assertFalse($this->deriver->validate(path: $path));
    }

    // -----------------------------------------------------------------------
    // extractComponents()
    // -----------------------------------------------------------------------

    #[Test]
    public function it_returns_tenant_components_for_tenant_path(): void
    {
        $components = $this->deriver->extractComponents(path: 'tenants/panda-estampados/products/camiseta-oversized/abc123def456.webp');

        $this->assertSame(
            expected: [
                'tenant_slug' => 'panda-estampados',
                'collection' => 'products',
                'entity' => 'camiseta-oversized',
                'hash' => 'abc123def456',
                'ext' => 'webp',
            ],
            actual: $components,
        );
    }

    #[Test]
    public function it_returns_shared_components_for_shared_path(): void
    {
        $components = $this->deriver->extractComponents(path: 'shared/social-icons/abc123def456.webp');

        $this->assertSame(
            expected: [
                'entity' => 'social-icons',
                'hash' => 'abc123def456',
                'ext' => 'webp',
            ],
            actual: $components,
        );
    }

    #[Test]
    public function it_returns_null_for_invalid_path(): void
    {
        $this->assertNull($this->deriver->extractComponents(path: 'tenants/panda-estampados/branding/logo.webp'));
    }
}
