<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Mensajes de validación para el locale `es` (locale primario del
    | storefront, con registro voseo en los mensajes `custom`). Cubren las
    | reglas que usa la app (required, string, email, min, max, same,
    | unique, integer, in, size y `Password::min()->max()`). Los mensajes
    | de `min`/`max`/`size` van anidados por tipo porque el validator
    | resuelve `validation.{regla}.{tipo}` sin fallback al plano.
    |
    */

    'email' => 'El campo :attribute debe ser una dirección de correo válida.',
    'in' => 'El campo :attribute es inválido.',
    'integer' => 'El campo :attribute debe ser un número entero.',
    'max' => [
        'numeric' => 'El campo :attribute no debe ser mayor que :max.',
        'string' => 'El campo :attribute no debe ser mayor que :max caracteres.',
    ],
    'min' => [
        'numeric' => 'El campo :attribute debe ser al menos :min.',
        'string' => 'El campo :attribute debe tener al menos :min caracteres.',
    ],
    'password' => [
        'letters' => 'El campo :attribute debe contener al menos una letra.',
        'mixed' => 'El campo :attribute debe contener al menos una letra mayúscula y una minúscula.',
        'numbers' => 'El campo :attribute debe contener al menos un número.',
        'symbols' => 'El campo :attribute debe contener al menos un símbolo.',
        'uncompromised' => 'El campo :attribute apareció en una filtración de datos. Elegí otro valor.',
    ],
    'required' => 'El campo :attribute es obligatorio.',
    'same' => 'El campo :attribute y :other deben coincidir.',
    'size' => [
        'numeric' => 'El campo :attribute debe ser :size.',
        'string' => 'El campo :attribute debe ser de :size caracteres.',
    ],
    'string' => 'El campo :attribute debe ser un texto.',
    'unique' => 'El campo :attribute ya está en uso.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Mensajes por atributo y regla ("atributo.regla"): reemplazan los
    | genéricos para los mensajes que el producto quiere en voseo.
    |
    */

    'custom' => [
        'email' => [
            'required' => 'Ingresá tu correo electrónico.',
            'unique' => 'Ya existe una cuenta con este correo.',
        ],
        'password' => [
            'required' => 'Ingresá una contraseña.',
        ],
        'password_confirmation' => [
            'same' => 'Las contraseñas no coinciden.',
        ],
        'customer_type' => [
            'in' => 'El tipo de cliente debe ser retail, wholesale o vip.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |

    | Nombres "display" de los atributos para interpolar en los mensajes.
    |
    */

    'attributes' => [
        'email' => 'correo electrónico',
        'password' => 'contraseña',
        'password_confirmation' => 'confirmación de contraseña',
        'first_name' => 'nombre',
        'last_name' => 'apellido',
        'phone' => 'teléfono',
        'customer_type' => 'tipo de cliente',
        'code' => 'código',
        'challenge_token' => 'token de desafío',
        'device_name' => 'nombre del dispositivo',
        'count' => 'cantidad',
    ],

];
