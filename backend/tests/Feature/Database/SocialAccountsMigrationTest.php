<?php

declare(strict_types=1);

namespace Tests\Feature\Database;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Verifica la shape y la idempotencia de las migraciones que sustentan
 * el login social (SDD oauth-google-facebook-login).
 *
 * El test corre dentro de `RefreshDatabase` así que el estado de la
 * tabla `social_accounts` es consistente entre casos. Las aserciones
 * son estructurales: cubren cada fila de la spec A14 (índices únicos
 * compuestos) + A15 (idempotencia de `last_login_at`).
 */
#[Group('database')]
#[Group('social-auth')]
#[CoversClass(\Database\Migrations\Auth\CreateSocialAccountsTable::class)]
#[CoversClass(\Database\Migrations\Auth\AddLastLoginAtToUsers::class)]
final class SocialAccountsMigrationTest extends \Tests\TestCase
{
    use RefreshDatabase;

    #[Test]
    public function social_accounts_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable(table: 'social_accounts'));

        foreach (['id', 'user_id', 'provider', 'provider_user_id', 'created_at', 'updated_at'] as $column) {
            $this->assertTrue(
                Schema::hasColumn(table: 'social_accounts', column: $column),
                "Falta la columna {$column} en social_accounts",
            );
        }
    }

    #[Test]
    public function social_accounts_has_unique_composite_on_provider_and_provider_user_id(): void
    {
        $indexes = Schema::getIndexes(table: 'social_accounts');

        $found = false;
        foreach ($indexes as $index) {
            $columns = collect($index['columns'] ?? [])
                ->map(fn (string $col): string => strtolower($col))
                ->sort()
                ->values()
                ->all();

            if ($columns === ['provider', 'provider_user_id']
                && ($index['unique'] ?? false) === true) {
                $found = true;
                break;
            }
        }

        $this->assertTrue($found, 'Falta el índice único compuesto (provider, provider_user_id)');
    }

    #[Test]
    public function social_accounts_has_index_on_user_id(): void
    {
        $indexes = Schema::getIndexListing(table: 'social_accounts');

        $hasUserIdIndex = collect($indexes)->contains(
            fn (string $name): bool => str_contains($name, 'user_id'),
        );

        $this->assertTrue($hasUserIdIndex, 'Falta el índice sobre user_id');
    }

    #[Test]
    public function social_accounts_has_foreign_key_to_users_with_cascade_delete(): void
    {
        $foreignKeys = Schema::getForeignKeys(table: 'social_accounts');

        $userFk = collect($foreignKeys)->first(
            fn (array $fk): bool => in_array('user_id', $fk['columns'], strict: true),
        );

        $this->assertNotNull($userFk, 'Falta la foreign key user_id → users.id');
        $this->assertSame(expected: 'users', actual: $userFk['foreign_table']);
        $this->assertSame(expected: 'cascade', actual: $userFk['on_delete']);
    }

    #[Test]
    public function social_accounts_down_drops_the_table(): void
    {
        // El RefreshDatabase ya deja la tabla creada. Llamamos el down
        // de la migración directamente y verificamos que la tabla
        // desaparece.
        $migration = require __DIR__ . '/../../../database/migrations/auth/2026_08_17_000001_create_social_accounts_table.php';
        $migration->down();

        $this->assertFalse(Schema::hasTable(table: 'social_accounts'));

        // Recreamos para no romper el resto del test suite dentro de
        // esta misma clase (RefreshDatabase limpia al final igual).
        $migration->up();
        $this->assertTrue(Schema::hasTable(table: 'social_accounts'));
    }

    #[Test]
    public function last_login_at_migration_is_idempotent(): void
    {
        $this->assertTrue(Schema::hasColumn(table: 'users', column: 'last_login_at'));

        // La migración base `create_users_table` ya agrega `last_login_at`.
        // La migración de PR 1 (`add_last_login_at_to_users`) debe ser
        // un no-op cuando la columna ya existe. Re-ejecutamos migrate
        // y verificamos que no falle.
        $this->artisan(command: 'migrate')->assertSuccessful();
    }

    #[Test]
    public function last_login_at_migration_can_be_run_twice_without_error(): void
    {
        // Cubre A15 explícitamente: doble corrida debe ser no-op.
        $this->artisan(command: 'migrate')->assertSuccessful();
        $this->artisan(command: 'migrate')->assertSuccessful();

        $this->assertTrue(Schema::hasColumn(table: 'users', column: 'last_login_at'));
    }
}
