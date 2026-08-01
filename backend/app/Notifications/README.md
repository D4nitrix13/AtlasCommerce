# Notifications en Laravel

Este directorio contiene las **Notifications** del backend Laravel.

Las Notifications se usan cuando el sistema necesita **avisarle algo a un usuario** por uno o varios canales, por ejemplo correo electrónico, base de datos, broadcast, SMS u otros canales configurados.

En este proyecto se usan principalmente para correos relacionados con seguridad y autenticación.

---

## 1. ¿Qué es una Notification?

Una **Notification** es una clase encargada de preparar y enviar un mensaje a un usuario.

Una Notification responde esta pregunta:

> ¿Cómo aviso esto al usuario?

No debe contener reglas complejas de negocio. Su responsabilidad principal es definir:

1. Qué canal se usará.
2. Qué asunto tendrá el mensaje.
3. Qué datos se enviarán.
4. Qué vista o plantilla se renderizará.
5. Cómo se verá el mensaje final.

---

## 2. ¿Cuándo debe ser una Notification?

Debe crearse una Notification cuando el sistema necesita comunicarle algo a un usuario.

Ejemplos:

- enviar un código OTP;
- avisar cambio de contraseña;
- enviar confirmación de compra;
- notificar que un pedido fue enviado;
- avisar que una factura venció;
- enviar alertas de seguridad;
- enviar confirmación de pago;
- notificar recuperación de contraseña.

La pregunta práctica es:

> ¿El objetivo principal de esta clase es avisarle algo al usuario?

Si la respuesta es sí, probablemente debe ser una Notification.

---

## 3. ¿Cuándo no debe ser una Notification?

No debe ser una Notification cuando la clase necesita ejecutar lógica fuerte del negocio.

Una Notification no debería:

1. Generar códigos OTP.
2. Validar intentos.
3. Verificar expiraciones.
4. Crear reglas de seguridad.
5. Calcular totales.
6. Procesar pagos.
7. Activar o desactivar 2FA.
8. Modificar varios modelos con lógica compleja.
9. Decidir reglas principales del negocio.

Ese tipo de lógica debe vivir en un **Service**.

---

## 4. Notification vs Service

Una Notification comunica.

Un Service ejecuta reglas del negocio.

Ejemplo del módulo 2FA:

```text
EmailOtpService
   ↓
EmailOtpNotification
```

`EmailOtpService` se encarga de:

- generar el OTP;
- hashear el OTP;
- guardar el reto en base de datos;
- validar expiración;
- validar intentos;
- marcar el código como usado.

`EmailOtpNotification` se encarga de:

- preparar el correo;
- definir el asunto;
- pasar datos a la vista Blade;
- enviar el mensaje por el canal `mail`.

La Notification no debe generar ni validar el OTP.

---

## 5. Notification vs Controller

Un Controller recibe una solicitud HTTP.

Una Notification envía un mensaje al usuario.

Ejemplo:

```text
POST /api/auth/2fa/email/send
   ↓
TwoFactorAuthController
   ↓
EmailOtpService
   ↓
EmailOtpNotification
```

El Controller recibe la petición.
El Service ejecuta la lógica.
La Notification envía el correo.

---

## 6. Notification vs Command

Un Command ejecuta una tarea desde consola usando `php artisan`.

Una Notification construye y envía un mensaje.

Ejemplo:

```text
php artisan mail:preview-otp
   ↓
MailPreviewOtpCommand
   ↓
EmailOtpNotification
   ↓
Mailpit
```

El Command permite previsualizar el correo OTP desde terminal.
La Notification define cómo se construye ese correo.

---

## 7. Estructura actual

La estructura actual del directorio es:

```text
app/Notifications
└── Auth
    └── EmailOtpNotification.php
```

La carpeta `Auth` agrupa notificaciones relacionadas con autenticación y seguridad.

---

## 8. EmailOtpNotification

Archivo:

```text
app/Notifications/Auth/EmailOtpNotification.php
```

Esta Notification envía un código OTP por correo electrónico como parte del flujo de verificación en dos pasos.

Responsabilidades principales:

1. Definir que el canal de entrega será `mail`.
2. Construir el correo con `MailMessage`.
3. Definir el asunto del correo.
4. Cargar la vista Blade personalizada.
5. Pasar a la vista los datos necesarios:
    - nombre de la aplicación;
    - código OTP;
    - minutos de expiración;
    - nombre del usuario;
    - correo de soporte;
    - logo;
    - dirección;
    - enlaces sociales;
    - iconos de redes sociales.

---

## 9. ¿Cómo se creó?

Esta Notification se creó con Artisan:

```bash
php artisan make:notification Auth/EmailOtpNotification
```

Ese comando genera el archivo:

```text
app/Notifications/Auth/EmailOtpNotification.php
```

Laravel interpreta `Auth/EmailOtpNotification` como una subcarpeta dentro de `app/Notifications`.

---

## 10. Canal de envío

La Notification usa el canal `mail`:

```php
public function via(object $notifiable): array
{
    return ['mail'];
}
```

Esto significa que Laravel enviará el mensaje como correo electrónico.

Para que funcione, el modelo destinatario debe poder recibir notificaciones. Normalmente el modelo `User` usa el trait:

```php
use Illuminate\Notifications\Notifiable;
```

---

## 11. Método toMail

El método `toMail()` construye la representación del correo.

Ejemplo conceptual:

```php
public function toMail(object $notifiable): MailMessage
{
    $appName = (string) config(key: 'app.name');

    return (new MailMessage)
        ->subject(subject: "Código de seguridad - {$appName}")
        ->view(
            view: 'emails.auth.email-otp',
            data: [
                'appName' => $appName,
                'code' => $this->code,
                'expiresMinutes' => $this->expiresMinutes,
            ],
        );
}
```

En este proyecto se usa una vista Blade personalizada para tener mayor control visual sobre el diseño del correo.

---

## 12. Vista Blade del correo

La Notification renderiza esta vista:

```text
resources/views/emails/auth/email-otp.blade.php
```

Esa vista utiliza un layout reutilizable:

```text
resources/views/components/email/layout.blade.php
```

Esto permite separar:

- la lógica de envío del correo;
- el diseño visual del correo;
- el branding de la aplicación;
- los datos dinámicos del OTP.

---

## 13. Configuración de branding

La Notification no debe hardcodear rutas de imágenes, nombres ni redes sociales.

Debe leer esos valores desde configuración:

```text
config/brand.php
```

Ejemplos de valores configurables:

```env
BRAND_LOGO_PATH=images/brand/app-logo.png
BRAND_SUPPORT_EMAIL=support@example.test
BRAND_FACEBOOK_URL=https://facebook.com/tu-pagina
BRAND_INSTAGRAM_URL=https://instagram.com/tu-pagina
BRAND_FACEBOOK_ICON_PATH=images/social/facebook.png
BRAND_INSTAGRAM_ICON_PATH=images/social/instagram.png
```

Esto permite cambiar logo, redes sociales o correo de soporte sin tocar la clase PHP.

---

## 14. APP_URL e imágenes en correos

Para que las imágenes se vean correctamente en Mailpit o en un cliente de correo, `APP_URL` debe apuntar al servidor donde Laravel sirve los archivos públicos.

Ejemplo para desarrollo local:

```env
APP_URL=http://127.0.0.1:8000
```

Las imágenes deben existir dentro de `public/`.

Ejemplo:

```text
public/images/brand/app-logo.png
public/images/social/facebook.png
public/images/social/instagram.png
```

Si `APP_URL` está mal, el correo puede llegar, pero el logo y los iconos no se mostrarán.

---

## 15. Previsualización con Mailpit

Para revisar el diseño visual del correo OTP se usa Mailpit.

Configuración típica en `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="no-reply@example.test"
MAIL_FROM_NAME="${APP_NAME}"
```

Mailpit se abre en:

```text
http://127.0.0.1:8025
```

Para enviar un correo OTP de prueba se puede usar el comando:

```bash
php artisan mail:preview-otp
```

Ese comando no reemplaza los tests. Solo sirve para revisar el diseño visual del correo.

---

## 16. Tests y Notifications

En los tests automatizados normalmente no se deben enviar correos reales.

Para eso Laravel permite usar:

```php
Notification::fake();
```

Esto evita que el correo llegue a Mailpit o a un SMTP real, pero permite comprobar que la Notification fue enviada.

Ejemplo conceptual:

```php
Notification::fake();

$user->notify(
    new EmailOtpNotification(
        code: '123456',
        expiresMinutes: 10,
    ),
);

Notification::assertSentTo(
    $user,
    EmailOtpNotification::class,
);
```

Esto es correcto para pruebas automáticas.

Para ver el diseño del correo se debe usar Mailpit y el comando de previsualización.

---

## 17. Parámetros nombrados

En este proyecto se prefiere usar parámetros nombrados cuando sea seguro hacerlo.

Ejemplo recomendado:

```php
$appName = (string) config(key: 'app.name');
```

Ejemplo recomendado:

```php
return (new MailMessage)
    ->subject(subject: "Código de seguridad - {$appName}")
    ->view(
        view: 'emails.auth.email-otp',
        data: [
            'appName' => $appName,
        ],
    );
```

También es válido usar parámetros nombrados al crear la Notification:

```php
new EmailOtpNotification(
    code: $code,
    expiresMinutes: $expiresMinutes,
);
```

Sin embargo, no se deben usar parámetros nombrados de forma ciega en APIs internas de PHP o Facades con métodos mágicos donde puedan causar errores.

---

## 18. Reglas para escribir Notifications

Una Notification debe:

1. Tener un nombre claro.
2. Estar agrupada por dominio.
3. Definir sus canales en `via()`.
4. Mantener el contenido del mensaje claro.
5. Usar vistas Blade si el diseño es personalizado.
6. Leer valores configurables desde `config/`.
7. Evitar lógica de negocio compleja.
8. No hardcodear el nombre del proyecto.
9. Ser fácil de probar con `Notification::fake()`.
10. Mantener separación entre comunicación y lógica del negocio.

---

## 19. Nombres recomendados

Buenos nombres:

```text
EmailOtpNotification
PasswordChangedNotification
PasswordResetNotification
OrderConfirmedNotification
InvoicePaidNotification
PaymentFailedNotification
ShipmentDispatchedNotification
SecurityAlertNotification
```

Nombres débiles:

```text
MailNotification
GeneralNotification
UserNotification
MessageNotification
NotifyUser
```

El nombre debe expresar claramente qué se está notificando.

---

## 20. Cuándo crear una subcarpeta

Conviene crear subcarpetas cuando hay varias notificaciones por dominio.

Ejemplos:

```text
app/Notifications/Auth
app/Notifications/Orders
app/Notifications/Payments
app/Notifications/Invoices
app/Notifications/Security
```

Esto mantiene el proyecto organizado y evita que todas las notificaciones queden mezcladas en un solo directorio.

---

## 21. Flujo recomendado

Flujo correcto para Email OTP:

```text
TwoFactorAuthController
   ↓
EmailOtpService
   ↓
EmailOtpNotification
   ↓
resources/views/emails/auth/email-otp.blade.php
   ↓
Mailpit / SMTP real
```

El Controller no arma el correo.
El Service no diseña el correo.
La Notification no genera el OTP.
La vista Blade no ejecuta lógica de seguridad.

Cada pieza tiene una responsabilidad clara.

---

## 22. Errores comunes

### Error 1: poner lógica de negocio en la Notification

Mala práctica:

```php
// Generar OTP, guardarlo en BD y validar intentos dentro de la Notification.
```

Mejor:

```text
EmailOtpService genera y guarda el OTP.
EmailOtpNotification solo envía el correo.
```

---

### Error 2: hardcodear datos de marca

Mala práctica:

```php
'logoUrl' => 'http://127.0.0.1:8000/images/logo.png'
```

Mejor:

```php
'logoUrl' => asset(
    path: (string) config(
        key: 'brand.logo_path',
        default: 'images/brand/app-logo.png',
    ),
)
```

---

### Error 3: probar diseño con tests automatizados

Los tests deben validar comportamiento, no diseño visual.

Para lógica:

```bash
php artisan test --filter=EmailOtpTwoFactorTest --env=testing
```

Para diseño:

```bash
php artisan mail:preview-otp
```

---

## 23. Resumen rápido

| Pregunta                                        | Respuesta                       |
| ----------------------------------------------- | ------------------------------- |
| ¿Necesito avisar algo al usuario?               | Notification                    |
| ¿Necesito enviar un correo con diseño?          | Notification + Blade View       |
| ¿Necesito generar o validar datos?              | Service                         |
| ¿Necesito recibir una petición HTTP?            | Controller                      |
| ¿Necesito ejecutar algo desde terminal?         | Command                         |
| ¿Necesito probar que se envió una notificación? | Test con `Notification::fake()` |

---

## 24. Conclusión

Las Notifications permiten mantener separada la comunicación con el usuario.

En este proyecto, `EmailOtpNotification` existe porque el sistema necesita enviar un código OTP por correo como parte del flujo 2FA.

La lógica de seguridad pertenece a los Services.
La presentación del correo pertenece a Blade.
La Notification conecta ambos mundos y define cómo se entrega el mensaje.
