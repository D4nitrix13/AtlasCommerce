<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Agrega un CHECK constraint a `users.preferred_recovery_method`
 * restringiendo los valores válidos a 'email' | '2fa' | 'recovery_codes'.
 *
 * Defensa en profundidad: las validations a nivel app (Form Requests,
 * casts en el modelo) ya garantizan que solo se persisten valores
 * válidos. Este constraint es un segundo layer a nivel DB para
 * rechazar inserts/updates directos por SQL, migraciones manuales o
 * seeds que olviden validar.
 *
 * Si en el futuro se agrega un nuevo método (ej. 'passkey', 'webauthn'),
 * hay que actualizar este CHECK y la lista de validations en
 * ForgotPasswordRequest / ResetPasswordRequest.
 *
 * Idempotente: usa DROP CONSTRAINT IF EXISTS antes del ADD para
 * permitir rollback + re-migrate sin errores.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(query: <<<'SQL'
            ALTER TABLE users
            DROP CONSTRAINT IF EXISTS users_preferred_recovery_method_check
        SQL);

        DB::statement(query: <<<'SQL'
            ALTER TABLE users
            ADD CONSTRAINT users_preferred_recovery_method_check
            CHECK (preferred_recovery_method IN ('email', '2fa', 'recovery_codes'))
        SQL);
    }

    public function down(): void
    {
        DB::statement(query: <<<'SQL'
            ALTER TABLE users
            DROP CONSTRAINT IF EXISTS users_preferred_recovery_method_check
        SQL);
    }
};
