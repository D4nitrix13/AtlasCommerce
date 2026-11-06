<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Auth Language Lines
    |--------------------------------------------------------------------------
    |
    | Mensajes de auth emitidos por los controllers (via `trans()`), para
    | que sigan el locale del request. El wording en `es` conserva los
    | mensajes que ya mostraba el producto.
    |
    */

    'login_throttle' => 'Demasiados intentos. Probá de nuevo en :seconds segundos.',
    'invalid_credentials' => 'Las credenciales no son válidas.',
    'account_disabled' => 'La cuenta está desactivada.',
    'no_store_access' => 'No tienes acceso a esta tienda.',
    'register_store_inactive' => 'No se puede registrar clientes: la tienda no está activa.',
    'totp_challenge_invalid' => 'Challenge inválido o expirado.',
    'totp_code_invalid' => 'Código inválido.',

];
