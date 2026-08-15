<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cambia la columna `secret` de `two_factor_settings` de `varchar(255)`
 * a `text`.
 *
 * El comentario original de la migration que creó la tabla estimaba
 * que 255 chars alcanzaban para el base32 de 32 bytes (52 chars) +
 * headers de Laravel Crypt. En la práctica, `Crypt::encryptString()`
 * produce un payload base64 con iv + hmac + JSON envelope que supera
 * los 255 chars para secrets generados con `Str::random()` (32 chars
 * base32 = 20 bytes = ~27 chars base64, pero el envelope de Crypt
 * añade ~200 chars de overhead). Resultado: tests unitarios que
 * persisten un secret recién generado explotan con
 * `SQLSTATE[22001]: value too long for type character varying(255)`.
 *
 * Cambio mínimo: ampliar la columna a `text`. No se pierden datos
 * (los rows existentes con secret < 255 chars siguen siendo válidos;
 * si la columna ya tenía un valor de >255 chars la migration previa
 * no podría haberla insertado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(table: 'two_factor_settings', callback: function (Blueprint $table): void {
            $table->text(column: 'secret')->change();
        });
    }

    public function down(): void
    {
        Schema::table(table: 'two_factor_settings', callback: function (Blueprint $table): void {
            $table->string(column: 'secret', length: 255)->change();
        });
    }
};
