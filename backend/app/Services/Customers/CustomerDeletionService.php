<?php

declare(strict_types=1);

namespace App\Services\Customers;

use App\Models\Auth\User as UserModel;
use App\Models\Customers\Customer;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Borrado / anonimización de cuenta de cliente conforme a GDPR.
 *
 * RF-CUST-042 — Exportar y borrar datos del cliente (GDPR art. 17,
 * derecho al olvido; Ley 896 de Protección de Datos Personales Nicaragua).
 *
 * Flujo:
 *  1. Cliente pide borrado → requestDeletion() genera un token seguro,
 *     guarda su SHA-256 + expiración (24h) y devuelve el token en claro
 *     al cliente (es la ÚNICA vez que se ve el token).
 *  2. Cliente confirma con token + re-auth de password → confirmDeletion()
 *     valida token + password, y dentro de una transacción DB:
 *       a) Anonimiza PII del Customer (email/name/phone/identity).
 *       b) Anonimiza PII del User vinculado (si existe).
 *       c) Soft-delete del Customer.
 *       d) Marca `anonymized_at`.
 *       e) Revoca todos los Sanctum tokens del User.
 *
 * Decisiones de diseño:
 *  - Token en claro SOLO se devuelve en `requestDeletion()`. En BD
 *    se guarda SHA-256: si la BD se ve comprometida, no se pueden
 *    regenerar tokens.
 *  - El token expira (default 24h) para limitar la ventana de ataque.
 *  - Re-auth con password es OBLIGATORIO en la confirmación, no
 *    opcional. Si el atacante sólo robó el token, no puede borrar
 *    la cuenta sin la password.
 *  - Los pedidos/facturas NO se borran (Ley 896 + normativa DGI
 *    exige retención fiscal de facturas durante 5 años). Lo único
 *    que cambia es la PII del Customer que les da contexto. En este
 *    MVP se documenta la decisión; la anonimización histórica de
 *    facturas queda fuera del alcance (backlog futuro).
 *  - La operación es transaccional. Si algo falla, NO se aplica
 *    parcialmente el borrado.
 *  - Los tokens Sanctum del User se revocan: la sesión muere con la
 *    cuenta. Cualquier intento de uso posterior devolverá 401.
 */
final class CustomerDeletionService
{
    /**
     * Ventana de expiración del token de confirmación. Configurable
     * vía constructor si fuera necesario por tests.
     */
    public function __construct(
        private readonly int $tokenTtlHours = 24,
        private readonly int $tokenByteLength = 32,
    ) {}

    /**
     * Genera un token de confirmación y lo persiste hasado.
     *
     * Idempotente: si ya hay un token activo, se reemplaza (rotación
     * natural que invalida el anterior).
     *
     * Devuelve el token en claro. El cliente debe presentarlo en la
     * llamada a confirmDeletion() junto con su password.
     */
    public function requestDeletion(Customer $customer): string
    {
        $plainToken = $this->generateToken();
        $hash = $this->hashToken(token: $plainToken);
        $expiresAt = Carbon::now()->addHours(hours: $this->tokenTtlHours);

        $customer->forceFill(attributes: [
            'deletion_requested_at' => Carbon::now(),
            'deletion_token_hash' => $hash,
            'deletion_token_expires_at' => $expiresAt,
        ])->save();

        return $plainToken;
    }

    /**
     * Confirma el borrado verificando token + password y anonimizando.
     *
     * @throws RuntimeException Si el token expiró, no coincide, o la
     *                          password es incorrecta.
     */
    public function confirmDeletion(Customer $customer, string $plainToken, string $password): void
    {
        $this->assertTokenIsValid(customer: $customer, plainToken: $plainToken);
        $this->assertPasswordIsValid(customer: $customer, password: $password);

        DB::transaction(callback: function () use ($customer): void {
            $this->anonymize(customer: $customer);
            $this->anonymizeUserIfLinked(customer: $customer);
            $this->revokeUserTokens(customer: $customer);
        });
    }

    /**
     * Cancela una solicitud pendiente de borrado (borrando token + flag).
     *
     * Útil si el cliente se arrepiente antes de confirmar.
     */
    public function cancelDeletion(Customer $customer): void
    {
        if ($customer->deletion_token_hash === null) {
            return;
        }

        $customer->forceFill(attributes: [
            'deletion_token_hash' => null,
            'deletion_token_expires_at' => null,
        ])->save();
    }

    /**
     * Anonimiza la PII del Customer en una transacción abierta por el caller.
     *
     * Valores colocados (placeholders no identificables):
     *  - email  → "deleted-{uuid}@anonymized.invalid"
     *  - first_name → "Deleted"
     *  - last_name  → "User"
     *  - phone  → null
     *  - identity_document → null
     *  - is_active → false
     *  - anonymized_at → now()
     *
     * Se preserva `id` y `user_id` para no romper las FKs existentes
     * (orders, invoices, etc. siguen apuntando al Customer anonimizado).
     */
    private function anonymize(Customer $customer): void
    {
        $anonymizedEmail = sprintf(
            'deleted-%s@anonymized.invalid',
            (string) Str::uuid(),
        );

        $customer->forceFill(attributes: [
            'first_name' => 'Deleted',
            'last_name' => 'User',
            'email' => $anonymizedEmail,
            'phone' => null,
            'identity_document' => null,
            'is_active' => false,
            'anonymized_at' => Carbon::now(),
            'deletion_token_hash' => null,
            'deletion_token_expires_at' => null,
        ])->save();

        // Soft-delete al final para que la fila siga accesible mientras
        // anonimizamos, por si otra parte de la app necesita leer su id.
        $customer->delete();
    }

    /**
     * Anonimiza el User vinculado si existe. Esto blinda el acceso
     * a la cuenta aunque el customer intente re-crearla con el
     * mismo email.
     */
    private function anonymizeUserIfLinked(Customer $customer): void
    {
        $user = $customer->user;

        if (! $user instanceof UserModel) {
            return;
        }

        $user->forceFill(attributes: [
            'name' => 'Deleted User',
            'email' => sprintf('deleted-%s@anonymized.invalid', (string) Str::uuid()),
            'password' => Hash::make(value: Str::random(length: 64)),
        ])->save();
    }

    /**
     * Revoca todos los Sanctum tokens del User vinculado para matar
     * cualquier sesión activa que pudiera tener.
     */
    private function revokeUserTokens(Customer $customer): void
    {
        $user = $customer->user;

        if (! $user instanceof UserModel) {
            return;
        }

        $user->tokens()->delete();
    }

    /**
     * Verifica el token contra el hash guardado y su expiración.
     *
     * @throws RuntimeException
     */
    private function assertTokenIsValid(Customer $customer, string $plainToken): void
    {
        if ($customer->deletion_token_hash === null || $customer->deletion_token_expires_at === null) {
            throw new RuntimeException(message: 'No hay solicitud de borrado activa.');
        }

        if ($customer->deletion_token_expires_at->isPast()) {
            throw new RuntimeException(message: 'El token de borrado ha expirado. Iniciá una nueva solicitud.');
        }

        $expectedHash = (string) $customer->deletion_token_hash;
        $actualHash = $this->hashToken(token: $plainToken);

        if (! hash_equals(known_string: $expectedHash, user_string: $actualHash)) {
            throw new RuntimeException(message: 'El token de borrado no es válido.');
        }
    }

    /**
     * Verifica la password del User vinculado contra el hash guardado.
     *
     * Re-auth obligatoria como salvaguarda adicional al token.
     *
     * @throws RuntimeException
     */
    private function assertPasswordIsValid(Customer $customer, string $password): void
    {
        $user = $customer->user;

        if (! $user instanceof UserModel) {
            // Customer sin User (compra como invitado): no podemos
            // re-autenticar. Permitimos continuar si el token es válido.
            // El riesgo es aceptable: robar el token ya requiere MITM
            // del response original.
            return;
        }

        if (! Hash::check(value: $password, hashedValue: (string) $user->getAuthPassword())) {
            throw new RuntimeException(message: 'La contraseña no es correcta.');
        }
    }

    /**
     * Genera un token URL-safe de $this->tokenByteLength bytes.
     */
    private function generateToken(): string
    {
        return Str::random(length: $this->tokenByteLength);
    }

    /**
     * Hashea el token con SHA-256. Suficiente para nuestro modelo de
     * amenaza (el token es aleatorio y de un solo uso, no necesitamos
     * bcrypt por coste computacional).
     */
    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
