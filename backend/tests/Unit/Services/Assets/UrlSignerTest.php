<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Assets;

use App\Services\Assets\UrlSigner;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests de `App\Services\Assets\UrlSigner`.
 *
 * Cubre:
 *   - `sign()`: determinismo, formato del query string, TTL custom,
 *     TTL ausente (fallback al default).
 *   - `verify()`: happy path, expiración, tampering, secret
 *     incorrecto, hex inválido, firma vacía, expires ≤ 0.
 *   - Patrón timing-safe (`hash_equals`) — documentado en PHPDoc del
 *     método, no es testeable behaviour-económicamente.
 *
 * La clase es pura (sin I/O, sin facade), así que los tests no
 * requieren DB ni container — solo instanciación y asserciones.
 * Para tests de expiración se manipula el clock via `time()` mock
 * implícito: el delta entre `sign()` y `verify()` es ~0 segundos, así
 * que el test de expiración se hace con `$expires = time() - 1`
 * (pasado) en lugar de mockear el clock.
 */
#[CoversClass(UrlSigner::class)]
#[Group('assets')]
final class UrlSignerTest extends TestCase
{
    private const SECRET = 'unit-test-secret-do-not-use-in-prod';

    private const PATH = 'tenants/panda-estampados/branding/logo.webp';

    private UrlSigner $signer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->signer = new UrlSigner(secret: self::SECRET);
    }

    // -----------------------------------------------------------------------
    // sign()
    // -----------------------------------------------------------------------

    #[Test]
    public function it_produces_deterministic_signature_for_same_path_and_expires(): void
    {
        // El contrato determinístico: misma triplet
        // `(path, expires, secret)` → misma firma. Lo verificamos
        // dando vuelta: firmamos con sign() y comparamos contra un
        // `hash_hmac` re-computado a mano. Si difieren, el formato del
        // payload canónico cambió.
        $signed = $this->signer->sign(path: self::PATH, ttl: 600);

        ['expires' => $expires, 'sig' => $signature] = $this->parseQuery(query: $signed);

        $recomputed = hash_hmac(
            algo: 'sha256',
            data: self::PATH.'|'.$expires,
            key: self::SECRET,
        );

        $this->assertSame(expected: $recomputed, actual: $signature);
    }

    #[Test]
    public function it_includes_expires_in_query_string(): void
    {
        $signed = $this->signer->sign(path: self::PATH);

        $this->assertMatchesRegularExpression('/^expires=\d+&sig=[a-f0-9]{64}$/', $signed);
    }

    #[Test]
    public function it_respects_custom_ttl(): void
    {
        $before = time();
        $signed = $this->signer->sign(path: self::PATH, ttl: 600);
        $after = time();

        ['expires' => $expires] = $this->parseQuery(query: $signed);

        // El expires debe caer en [before+600, after+600].
        $this->assertGreaterThanOrEqual($before + 600, $expires);
        $this->assertLessThanOrEqual($after + 600, $expires);
    }

    #[Test]
    public function it_defaults_to_configured_ttl_when_no_arg(): void
    {
        $signer = new UrlSigner(secret: self::SECRET, defaultTtl: 42);

        $before = time();
        $signed = $signer->sign(path: self::PATH);
        $after = time();

        ['expires' => $expires] = $this->parseQuery(query: $signed);

        $this->assertGreaterThanOrEqual($before + 42, $expires);
        $this->assertLessThanOrEqual($after + 42, $expires);
    }

    #[Test]
    public function it_treats_zero_or_negative_ttl_as_default(): void
    {
        // TTL <= 0 debe fallback al $defaultTtl (300). El rationale es
        // que un caller que pasa 0 probablemente olvida el arg, no
        // quiere 0 (que sería inmediatamente expirado).
        $before = time();
        $signed = $this->signer->sign(path: self::PATH, ttl: 0);
        $after = time();

        ['expires' => $expires] = $this->parseQuery(query: $signed);

        $this->assertGreaterThanOrEqual($before + 300, $expires);
        $this->assertLessThanOrEqual($after + 300, $expires);
    }

    // -----------------------------------------------------------------------
    // verify()
    // -----------------------------------------------------------------------

    #[Test]
    public function it_accepts_valid_signature(): void
    {
        $signed = $this->signer->sign(path: self::PATH, ttl: 600);

        ['expires' => $expires, 'sig' => $signature] = $this->parseQuery(query: $signed);

        $this->assertTrue(
            $this->signer->verify(path: self::PATH, expires: $expires, signature: $signature),
        );
    }

    #[Test]
    public function it_rejects_expired_signature(): void
    {
        // expires = time() - 1 → ya pasó. verify() devuelve false.
        $expires = (string) (time() - 1);
        $expectedSig = hash_hmac(
            algo: 'sha256',
            data: self::PATH.'|'.$expires,
            key: self::SECRET,
        );

        $this->assertFalse(
            $this->signer->verify(path: self::PATH, expires: $expires, signature: $expectedSig),
        );
    }

    #[Test]
    public function it_rejects_signature_for_expires_at_exact_now(): void
    {
        // El contrato es `expires > time()`, no `expires >= time()`.
        // expires == time() debe rechazarse (ventana ya expirada).
        $expires = (string) time();
        $expectedSig = hash_hmac(
            algo: 'sha256',
            data: self::PATH.'|'.$expires,
            key: self::SECRET,
        );

        $this->assertFalse(
            $this->signer->verify(path: self::PATH, expires: $expires, signature: $expectedSig),
        );
    }

    #[Test]
    public function it_rejects_tampered_signature(): void
    {
        $signed = $this->signer->sign(path: self::PATH, ttl: 600);

        ['expires' => $expires, 'sig' => $signature] = $this->parseQuery(query: $signed);

        // Flip el último byte de la signature.
        $flipped = substr(string: $signature, offset: 0, length: -1).dechex((int) (hexdec(substr(string: $signature, offset: -1)) ^ 0xF));

        $this->assertNotSame($signature, $flipped);
        $this->assertFalse(
            $this->signer->verify(path: self::PATH, expires: $expires, signature: $flipped),
        );
    }

    #[Test]
    public function it_rejects_signature_with_wrong_secret(): void
    {
        // Firmamos con secret X pero verificamos con signer que tiene secret Y.
        $signerX = new UrlSigner(secret: 'secret-X');
        $signed = $signerX->sign(path: self::PATH, ttl: 600);

        ['expires' => $expires, 'sig' => $signature] = $this->parseQuery(query: $signed);

        $signerY = new UrlSigner(secret: 'secret-Y');
        $this->assertFalse(
            $signerY->verify(path: self::PATH, expires: $expires, signature: $signature),
        );
    }

    #[Test]
    public function it_rejects_signature_with_invalid_hex(): void
    {
        $signed = $this->signer->sign(path: self::PATH, ttl: 600);

        ['expires' => $expires] = $this->parseQuery(query: $signed);

        // 'g' no es hex válido.
        $this->assertFalse(
            $this->signer->verify(path: self::PATH, expires: $expires, signature: str_repeat(string: 'g', times: 64)),
        );
    }

    #[Test]
    public function it_rejects_empty_signature(): void
    {
        $expires = (string) (time() + 600);

        $this->assertFalse(
            $this->signer->verify(path: self::PATH, expires: $expires, signature: ''),
        );
    }

    #[Test]
    public function it_rejects_zero_or_negative_expires(): void
    {
        // Sin firma válida: solo verificar el pre-check de expires.
        foreach (['0', '-1', 'abc', ''] as $bad) {
            $this->assertFalse(
                $this->signer->verify(path: self::PATH, expires: $bad, signature: str_repeat(string: 'a', times: 64)),
                "Expected reject for expires='{$bad}'",
            );
        }
    }

    #[Test]
    public function it_rejects_signature_for_different_path(): void
    {
        // Mismo signer, mismo secret, pero firmamos un path y
        // verificamos contra otro. La firma no matchea porque el
        // payload canónico (`path|expires`) difiere.
        $signed = $this->signer->sign(path: self::PATH, ttl: 600);

        ['expires' => $expires, 'sig' => $signature] = $this->parseQuery(query: $signed);

        $otherPath = 'tenants/kitsune/branding/logo.webp';

        $this->assertFalse(
            $this->signer->verify(path: $otherPath, expires: $expires, signature: $signature),
        );
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Parsear el query string `expires=NUM&sig=HEX` retornado por
     * `sign()` para inspeccionar cada componente. Falla loudly si
     * el formato no es canónico.
     *
     * @return array{expires: string, sig: string}
     */
    private function parseQuery(string $query): array
    {
        $parts = [];
        parse_str(string: $query, result: $parts);

        $this->assertArrayHasKey(key: 'expires', array: $parts);
        $this->assertArrayHasKey(key: 'sig', array: $parts);
        $this->assertIsString($parts['expires']);
        $this->assertIsString($parts['sig']);

        return ['expires' => $parts['expires'], 'sig' => $parts['sig']];
    }
}
