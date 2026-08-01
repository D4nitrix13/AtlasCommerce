# Services en Laravel

Este directorio contiene los **Services** del backend Laravel.

Los Services se utilizan para separar la lógica de negocio de los Controllers, Models, Notifications y Commands. Su objetivo principal es mantener el código más limpio, reutilizable, fácil de probar y fácil de mantener.

---

## 1. ¿Qué es un Service?

Un **Service** es una clase encargada de ejecutar una operación de negocio.

En otras palabras, un Service responde esta pregunta:

> ¿Cómo se ejecuta esta regla del negocio?

Un Service no representa una tabla directamente, no recibe una ruta HTTP por sí mismo y no debería encargarse de renderizar vistas. Su responsabilidad es coordinar reglas, modelos, validaciones internas y procesos que tienen varios pasos.

---

## 2. ¿Cuándo debe ser un Service?

Debe crearse un Service cuando la lógica cumple una o varias de estas condiciones:

1. Tiene varios pasos.
2. Usa más de un modelo.
3. Se reutiliza desde varios lugares.
4. Tiene reglas de negocio importantes.
5. Tiene lógica de seguridad.
6. Tiene cálculos o validaciones internas.
7. Hace que el Controller se vuelva demasiado largo.
8. Necesita ser probado de forma aislada.
9. Coordina acciones como crear, validar, actualizar, notificar o registrar información.
10. No pertenece naturalmente a un único Model.

La pregunta práctica es:

> ¿Esta lógica hace que el Controller piense demasiado?

Si la respuesta es sí, probablemente debe vivir en un Service.

---

## 3. Qué problema resuelve un Service

Sin Services, los Controllers suelen terminar con demasiadas responsabilidades.

Ejemplo de mala separación:

```php
public function verify(Request $request)
{
    // Validar request.
    // Buscar OTP activo.
    // Verificar expiración.
    // Validar intentos.
    // Comparar hash.
    // Activar 2FA.
    // Marcar OTP como usado.
    // Devolver respuesta JSON.
}
```

Ese Controller está haciendo demasiado.

Una mejor separación sería:

```php
public function verify(Request $request, EmailOtpService $emailOtpService)
{
    $verified = $emailOtpService->verify(
        user: $request->user(),
        code: $request->string(key: 'code')->toString(),
    );

    // El Controller solo decide la respuesta HTTP.
}
```

El Controller recibe la solicitud y responde.
El Service ejecuta la lógica importante.

---

## 4. Services actuales

La estructura actual es:

```text
app/Services
└── Auth
    ├── EmailOtpService.php
    └── RecoveryCodeService.php
```

Estos servicios pertenecen al módulo de autenticación porque manejan lógica relacionada con 2FA.

---

## 5. EmailOtpService

Archivo:

```text
app/Services/Auth/EmailOtpService.php
```

Este Service se encarga de manejar el ciclo de vida de los códigos OTP enviados por correo electrónico.

Responsabilidades principales:

1. Leer la configuración del OTP.
2. Generar un código numérico seguro.
3. Guardar el código como hash.
4. Registrar IP y user-agent.
5. Enviar el código por medio de una Notification.
6. Validar el código ingresado por el usuario.
7. Verificar expiración.
8. Verificar intentos disponibles.
9. Marcar el código como usado después de una verificación exitosa.

Este Service existe porque la lógica de OTP no es simplemente “enviar un correo”. También incluye reglas de seguridad, persistencia, expiración e intentos máximos.

---

## 6. RecoveryCodeService

Archivo:

```text
app/Services/Auth/RecoveryCodeService.php
```

Este Service se encarga de manejar los códigos de recuperación de 2FA.

Responsabilidades principales:

1. Generar códigos de recuperación.
2. Usar un formato legible para el usuario.
3. Evitar caracteres ambiguos.
4. Guardar los códigos como hash.
5. Eliminar códigos anteriores al regenerar.
6. Retornar los códigos en texto plano solo una vez.
7. Verificar códigos ingresados por el usuario.
8. Marcar códigos como usados para impedir reutilización.

Este Service existe porque los recovery codes tienen reglas de seguridad propias y no deberían manejarse directamente dentro de un Controller.

---

## 7. ¿Los Services se crearon con php artisan?

Laravel no incluye por defecto un comando oficial como:

```bash
php artisan make:service
```

Por eso, los Services normalmente se crean manualmente.

En este proyecto, la carpeta y los archivos se pueden crear así:

```bash
mkdir -p app/Services/Auth

nvim app/Services/Auth/EmailOtpService.php
nvim app/Services/Auth/RecoveryCodeService.php
```

Como este README está dentro de `app/Services`, desde esta carpeta la creación sería:

```bash
mkdir -p Auth

nvim Auth/EmailOtpService.php
nvim Auth/RecoveryCodeService.php
```

También se podría crear un comando personalizado `make:service`, pero no es necesario para este proyecto.

---

## 8. Diferencia entre Service, Controller, Model, Notification y Command

| Componente   | Responsabilidad                                 |
| ------------ | ----------------------------------------------- |
| Controller   | Recibe solicitudes HTTP y devuelve respuestas   |
| Model        | Representa una tabla o entidad de base de datos |
| Service      | Ejecuta lógica de negocio                       |
| Notification | Envía avisos al usuario                         |
| Command      | Ejecuta tareas desde terminal                   |
| Test         | Comprueba que una funcionalidad no se rompió    |

---

## 9. Service vs Controller

Un **Controller** responde:

> ¿Qué endpoint necesita consumir el frontend?

Un **Service** responde:

> ¿Cómo se ejecuta la lógica de negocio detrás de ese endpoint?

Ejemplo:

```text
POST /api/auth/2fa/email/verify
```

El Controller recibe el código desde la API.

El Service verifica si el código es válido, si no expiró, si tiene intentos disponibles y si debe marcarse como usado.

---

## 10. Service vs Model

Un **Model** representa datos.

Un **Service** coordina procesos.

Ejemplo correcto en un Model:

```php
public function isExpired(): bool
{
    return $this->expires_at->isPast();
}
```

Este método sí puede vivir en el Model porque depende directamente del estado del registro.

Ejemplo correcto en un Service:

```php
public function verify(User $user, string $code): bool
{
    // Buscar reto activo.
    // Validar expiración.
    // Validar intentos.
    // Comparar hash.
    // Marcar como usado.
}
```

Esta lógica debe vivir en un Service porque coordina varias reglas y operaciones.

---

## 11. Service vs Notification

Una **Notification** comunica algo al usuario.

Un **Service** decide cuándo, por qué y con qué datos se debe comunicar.

Ejemplo:

```text
EmailOtpService
   ↓
EmailOtpNotification
```

`EmailOtpService` genera, guarda y controla el OTP.
`EmailOtpNotification` solo construye y envía el correo.

La Notification no debería generar códigos, validar intentos ni activar 2FA.

---

## 12. Service vs Command

Un **Command** sirve para ejecutar algo desde terminal con `php artisan`.

Un **Service** contiene la lógica que puede usar ese Command.

Ejemplo:

```text
php artisan mail:preview-otp
```

Ese comando sirve para previsualizar un correo OTP en Mailpit.

Pero si una tarea de consola necesita lógica importante, el Command debería llamar a un Service en lugar de contener toda la lógica internamente.

---

## 13. Ejemplo de flujo con Service

Flujo de Email OTP:

```text
Frontend
   ↓
Route
   ↓
TwoFactorAuthController
   ↓
EmailOtpService
   ↓
OtpChallenge
   ↓
EmailOtpNotification
   ↓
Correo / Mailpit
```

El Controller expone la API.
El Service ejecuta la lógica.
El Model guarda el reto OTP.
La Notification envía el correo.

---

## 14. Reglas para escribir Services

Un Service debe:

1. Tener un nombre claro.
2. Vivir en un namespace relacionado con su dominio.
3. Tener métodos públicos orientados a acciones del negocio.
4. Recibir dependencias de forma clara.
5. Evitar respuestas HTTP directas.
6. Evitar lógica de presentación.
7. Usar Models, Notifications u otros Services cuando sea necesario.
8. Ser fácil de probar.
9. Tener comentarios solo cuando la lógica no sea obvia.
10. No hardcodear valores configurables.

---

## 15. Nombres recomendados para Services

Buenos nombres:

```text
EmailOtpService
RecoveryCodeService
OrderTotalService
InventoryMovementService
PaymentProcessingService
InvoiceGenerationService
CouponApplicationService
```

Nombres débiles:

```text
HelperService
GeneralService
AuthService
DataService
ManagerService
```

Un Service debe tener una responsabilidad clara. Si el nombre es demasiado genérico, probablemente la clase está mezclando demasiadas cosas.

---

## 16. Parámetros nombrados

En este proyecto se prefiere usar parámetros nombrados cuando sea seguro hacerlo.

Ejemplo:

```php
config(key: 'two_factor.email_otp.digits', default: 6);
```

Ejemplo:

```php
Hash::make(value: $code);
```

Ejemplo:

```php
random_int(min: $min, max: $max);
```

Sin embargo, no se deben usar parámetros nombrados en lugares donde Laravel o PHP puedan fallar por compatibilidad, especialmente en Facades con métodos mágicos o APIs internas.

Evitar en:

```php
Route::middleware(middleware: 'auth:sanctum');
```

Usar mejor:

```php
Route::middleware('auth:sanctum');
```

También evitar en:

```php
new ReflectionProperty(
    objectOrClass: $object,
    property: $property,
);
```

Usar mejor:

```php
new ReflectionProperty($object, $property);
```

---

## 17. Cuándo no crear un Service

No hace falta crear un Service cuando:

1. La lógica es mínima.
2. Solo se lee un modelo y se devuelve una respuesta.
3. La operación no se reutiliza.
4. El Controller sigue siendo claro.
5. La lógica pertenece claramente a un Model.
6. Es solo transformación de JSON, donde conviene usar un Resource.
7. Es solo validación de entrada, donde conviene usar un Form Request.

Ejemplo donde quizá no hace falta Service:

```php
public function index()
{
    return UserResource::collection(User::query()->paginate());
}
```

Ahí la lógica es simple.

---

## 18. Cuándo sí crear un Service

Sí conviene crear un Service cuando la lógica se parece a esto:

```text
1. Validar estado actual.
2. Consultar varios registros.
3. Crear un registro.
4. Actualizar otro registro.
5. Enviar una notificación.
6. Registrar auditoría.
7. Retornar un resultado.
```

Ese tipo de flujo ya no debería vivir completo en un Controller.

---

## 19. Regla senior

La regla principal es:

> El Controller no debe pensar demasiado.

Si una acción tiene reglas importantes, debe delegarse a un Service.

Un buen Controller coordina la entrada y la salida.
Un buen Service contiene la lógica del negocio.
Un buen Model representa los datos y relaciones.
Una buena Notification comunica al usuario.
Un buen Command ejecuta tareas desde consola.

---

## 20. Resumen rápido

| Pregunta                                | Respuesta    |
| --------------------------------------- | ------------ |
| ¿Esto recibe una petición HTTP?         | Controller   |
| ¿Esto representa una tabla?             | Model        |
| ¿Esto contiene lógica de negocio?       | Service      |
| ¿Esto envía un aviso al usuario?        | Notification |
| ¿Esto se ejecuta con php artisan?       | Command      |
| ¿Esto valida que algo no se rompa?      | Test         |
| ¿Esto crea estructura de base de datos? | Migration    |
| ¿Esto renderiza HTML?                   | Blade View   |
| ¿Esto transforma JSON?                  | Resource     |
| ¿Esto valida entrada compleja?          | Form Request |

---

## 21. Conclusión

Los Services ayudan a que el backend sea más profesional y mantenible.

En este proyecto, `EmailOtpService` y `RecoveryCodeService` existen porque manejan lógica de seguridad que no debería vivir en Controllers ni Notifications.

La separación correcta permite que el sistema sea más fácil de probar, extender, documentar y mantener.
