<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Garantiza que `users.last_login_at` exista.
     *
     * La migración original `2026_06_22_032005_create_users_table.php`
     * ya incluye la columna (línea 46), pero esta migración queda
     * como defensa contra escenarios donde el baseline se aplicó sin
     * esa columna (instalaciones legacy). El guard `hasColumn` la hace
     * idempotente — la re-corrida no falla.
     *
     * Decisión de diseño (decision #207 del SDD): NO persistimos un
     * valor por columna rotante tipo `provider_id` directamente en
     * `users`; el binding vive en `social_accounts` y acá solo
     * anotamos el último login exitoso del user.
     */
    public function up(): void
    {
        if (! Schema::hasColumn(table: 'users', column: 'last_login_at')) {
            Schema::table(table: 'users', callback: function (Blueprint $table): void {
                $table->timestamp(column: 'last_login_at')
                    ->nullable()
                    ->after(column: 'password_changed_at');
            });
        }
    }

    /**
     * Revierte la migración.
     *
     * Si la columna la creó esta migración (no estaba en la
     * original), la dropeamos. Si la creó la migración original,
     * no la tocamos para no romper el rollback de instalaciones
     * previas.
     */
    public function down(): void
    {
        // Sin flag de "yo la creé" — la columna ya existía antes de este
        // PR. No la dropeamos para no romper migraciones previas que
        // dependen del dato. Si en el futuro queremos reversibilidad
        // estricta, agregamos una columna sentinel o movemos el
        // tracking a `social_accounts.last_login_at` (R10 follow-up).
    }
};
