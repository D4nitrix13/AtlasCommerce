<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Validation messages for the `en` locale. They cover the rules the app
    | uses (required, string, email, min, max, same, unique, integer, in,
    | size and `Password::min()->max()`). `min`/`max`/`size` messages are
    | nested per type because the validator resolves
    | `validation.{rule}.{type}` without falling back to the flat key.
    |
    */

    'email' => 'The :attribute field must be a valid email address.',
    'in' => 'The selected :attribute is invalid.',
    'integer' => 'The :attribute field must be an integer.',
    'max' => [
        'numeric' => 'The :attribute field must not be greater than :max.',
        'string' => 'The :attribute field must not be greater than :max characters.',
    ],
    'min' => [
        'numeric' => 'The :attribute field must be at least :min.',
        'string' => 'The :attribute field must be at least :min characters.',
    ],
    'password' => [
        'letters' => 'The :attribute field must contain at least one letter.',
        'mixed' => 'The :attribute field must contain at least one uppercase and one lowercase letter.',
        'numbers' => 'The :attribute field must contain at least one number.',
        'symbols' => 'The :attribute field must contain at least one symbol.',
        'uncompromised' => 'The given :attribute has appeared in a data leak. Please choose a different :attribute.',
    ],
    'required' => 'The :attribute field is required.',
    'same' => 'The :attribute field must match :other.',
    'size' => [
        'numeric' => 'The :attribute field must be :size.',
        'string' => 'The :attribute field must be :size characters.',
    ],
    'string' => 'The :attribute field must be a string.',
    'unique' => 'The :attribute has already been taken.',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Per-attribute, per-rule messages ("attribute.rule") that replace the
    | generic ones with product-friendly wording.
    |
    */

    'custom' => [
        'email' => [
            'required' => 'Enter your email address.',
            'unique' => 'An account with this email already exists.',
        ],
        'password' => [
            'required' => 'Enter a password.',
        ],
        'password_confirmation' => [
            'same' => 'The passwords do not match.',
        ],
        'customer_type' => [
            'in' => 'The customer type must be retail, wholesale or vip.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | Display names for the attributes interpolated into messages.
    |
    */

    'attributes' => [
        'email' => 'email',
        'password' => 'password',
        'password_confirmation' => 'password confirmation',
        'first_name' => 'first name',
        'last_name' => 'last name',
        'phone' => 'phone',
        'customer_type' => 'customer type',
        'code' => 'code',
        'challenge_token' => 'challenge token',
        'device_name' => 'device name',
        'count' => 'count',
    ],

];
