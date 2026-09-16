# Emails Transaccionales i18n (RF-USA-044)

> **RNF-USA-044**: Los emails transaccionales (confirmación de orden,
> recuperación de password, etc.) se enviarán en el idioma del usuario.

## Estructura

```
backend/lang/
├── es/
│   └── emails.php    ← Traducciones español
└── en/
    └── emails.php    ← Traducciones inglés
```

Cada `emails.php` retorna un array jerárquico:

```php
return [
    'auth' => [
        'email_otp' => [
            'subject' => 'Tu código de acceso',
            'greeting' => 'Hola,',
            'intro' => 'Recibiste este correo porque...',
            'code_label' => 'Tu código de acceso es:',
            'expires' => 'Este código vence en :minutes minutos.',
            'ignore_notice' => 'Si no solicitaste este código...',
            'salutation' => '— El equipo de :appName',
        ],
        'password_reset_code' => [...],
    ],
];
```

## Helper `LocalizedMail`

`backend/app/Support/LocalizedMail.php` envuelve Laravel `MailMessage`
con localización automática:

```php
return LocalizedMail::for($user, 'auth.email_otp')
    ->withCode($code, 'code_label')
    ->expiresIn(10)
    ->toMailMessage(appName: 'AtlasCommerce');
```

### Locale resolution

```
User->locale (columna explícita en users)
  ↓ si null
App::getLocale() (runtime override)
  ↓ si vacío
'es' (fallback del proyecto)
```

El helper usa `App::setLocale()` para que `trans()` funcione correctamente.

### Parametrización

Las traducciones usan placeholders `:placeholder` que se reemplazan
en runtime:

| Placeholder | Significado |
| --- | --- |
| `:appName` | Nombre de la aplicación (AtlasCommerce, Panda, Kitsune, etc.) |
| `:minutes` | Minutos hasta expiración del código |
| `:code` | Código OTP/reset en sí |

## Notificaciones migradas

| Notificación | Translation key | Status |
| --- | --- | --- |
| `EmailOtpLoginNotification` | `auth.email_otp` | ✅ Migrado (PR #104) |
| `PasswordResetCodeNotification` | `auth.password_reset_code` | ✅ Migrado (PR #104) |

## Cómo agregar un nuevo email

1. Crear la key en `lang/es/emails.php` y `lang/en/emails.php`.
2. Crear el MailMessage via `LocalizedMail::for($user, 'auth.<key>')`.
3. Tests en `tests/Feature/Mail/`.

## Pendiente

- **Customer-facing emails**: confirmation de orden, shipping updates,
  invoice, etc. Todos deben seguir el mismo patrón al implementarse.
- **Plantillas Blade con `__()`**: las vistas Blade existentes (ej:
  `password-reset-code.blade.php`) tienen texto hardcoded. Convertir a
  `__()` directives en PRs siguientes.

## Referencias

- [Laravel Docs — Localization](https://laravel.com/docs/11.x/localization)
- [Laravel Docs — Notifications](https://laravel.com/docs/11.x/notifications)
- PR #104 — implementación inicial del helper + migraciones.
