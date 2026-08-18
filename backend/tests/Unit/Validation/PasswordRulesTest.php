<?php

declare(strict_types=1);

namespace Tests\Unit\Validation;

use App\Validation\PasswordRules;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Unit tests para PasswordRules — password complexity rules compartidas.
 *
 * Cubre:
 *  - Valid password pasa todas las rules
 *  - Length inválido (< min) falla
 *  - Falta mayúscula / minúscula / número / símbolo falla
 *  - Confirmation mismatch falla
 *  - required:true vs required:false se comportan distinto
 *  - confirmed:false desactiva la regla de confirmación
 *
 * Helper: `rules()` envuelve las rules en `['password' => [...]]` para
 * que `Validator::make` las aplique al campo correcto. Esto refleja el
 * uso real en FormRequest::rules() donde se invoca como:
 *   'password' => PasswordRules::complexity(),
 */
#[Group('validation')]
#[Group('auth')]
#[Group('password')]
final class PasswordRulesTest extends TestCase
{
    /**
     * Envolver las rules de PasswordRules en un mapa para Validator.
     *
     * @param  array<int, string>  $rules
     * @return array<string, array<int, string>>
     */
    private static function rules(array $rules): array
    {
        return ['password' => $rules];
    }

    #[Test]
    public function valid_password_with_complexity_passes(): void
    {
        $validator = Validator::make(
            data: [
                'password' => 'MySecure-Pass-2026',
                'password_confirmation' => 'MySecure-Pass-2026',
            ],
            rules: self::rules(PasswordRules::complexity()),
        );

        $this->assertFalse($validator->fails(), 'Password válido debe pasar todas las rules.');
    }

    #[Test]
    public function password_shorter_than_minimum_fails(): void
    {
        // 11 chars (justo debajo del mínimo 12).
        $validator = Validator::make(
            data: [
                'password' => 'Abc-12345-X',
                'password_confirmation' => 'Abc-12345-X',
            ],
            rules: self::rules(PasswordRules::complexity()),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    #[Test]
    public function password_without_uppercase_fails(): void
    {
        $validator = Validator::make(
            data: [
                'password' => 'mysecure-pass-2026',
                'password_confirmation' => 'mysecure-pass-2026',
            ],
            rules: self::rules(PasswordRules::complexity()),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    #[Test]
    public function password_without_lowercase_fails(): void
    {
        $validator = Validator::make(
            data: [
                'password' => 'MYSECURE-PASS-2026',
                'password_confirmation' => 'MYSECURE-PASS-2026',
            ],
            rules: self::rules(PasswordRules::complexity()),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    #[Test]
    public function password_without_number_fails(): void
    {
        $validator = Validator::make(
            data: [
                'password' => 'MySecure-Pass-Word',
                'password_confirmation' => 'MySecure-Pass-Word',
            ],
            rules: self::rules(PasswordRules::complexity()),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    #[Test]
    public function password_without_symbol_fails(): void
    {
        $validator = Validator::make(
            data: [
                'password' => 'MySecurePass2026',
                'password_confirmation' => 'MySecurePass2026',
            ],
            rules: self::rules(PasswordRules::complexity()),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    #[Test]
    public function password_confirmation_mismatch_fails(): void
    {
        $validator = Validator::make(
            data: [
                'password' => 'MySecure-Pass-2026',
                'password_confirmation' => 'Different-Pass-2026',
            ],
            rules: self::rules(PasswordRules::complexity()),
        );

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('password', $validator->errors()->toArray());
    }

    #[Test]
    public function optional_password_can_be_absent_when_required_is_false(): void
    {
        $validator = Validator::make(
            data: [
                // Sin password ni password_confirmation.
            ],
            rules: self::rules(PasswordRules::complexity(required: false)),
        );

        $this->assertFalse($validator->fails(), 'Con required:false, password ausente debe pasar.');
    }

    #[Test]
    public function confirmed_false_disables_confirmation_requirement(): void
    {
        $validator = Validator::make(
            data: [
                'password' => 'MySecure-Pass-2026',
                // Sin password_confirmation.
            ],
            rules: self::rules(PasswordRules::complexity(confirmed: false)),
        );

        $this->assertFalse($validator->fails(), 'Con confirmed:false, no se exige confirmation.');
    }
}
