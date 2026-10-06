# Componentes de arquitectura en `Laravel`

Este documento explica cuándo usar **Controllers**,
**Models**, **Services**, **Notifications**,
**Commands**, **Tests** y otros componentes comunes dentro
del backend `Laravel`.

El objetivo es mantener una arquitectura clara,
mantenible y profesional, evitando que los controladores
acumulen demasiada lógica o que responsabilidades
distintas se mezclen en una misma clase.

---

## 1. Idea principal

En `Laravel`, cada tipo de archivo tiene una
responsabilidad concreta.

La pregunta base es:

> ¿Qué responsabilidad tiene este código?

Según la respuesta, se decide dónde debe vivir.

- Exponer una ruta HTTP para el frontend → Controller
- Representar una tabla o entidad de base de datos → Model
- Ejecutar lógica de negocio reutilizable → Service
- Enviar avisos al usuario por correo u otro canal →
  Notification
- Ejecutar una tarea desde terminal → Command
- Validar una funcionalidad automáticamente → Test
- Transformar respuestas JSON → Resource
- Validar requests complejos → Form Request
- Ejecutar trabajo pesado en segundo plano → Job
- Crear o modificar tablas → Migration
- Insertar datos iniciales o de prueba → Seeder / Factory

---

## 2. Controller

### ¿Cuándo debe ser un Controller?

Debe ser un **Controller** cuando el código responde a
una solicitud HTTP.

Ejemplos:

- `GET /api/auth/2fa`
- `POST /api/auth/2fa/email/send`
- `POST /api/auth/2fa/email/verify`
- `POST /api/auth/2fa/disable`

Un controlador debe encargarse principalmente de:

1. Recibir la solicitud.
2. Validar datos básicos.
3. Llamar servicios.
4. Retornar una respuesta HTTP o JSON.

Un controlador no debería contener lógica pesada de
negocio.

### Controller — Ejemplo de creación con Artisan

```bash
php artisan make:controller Api/Auth/TwoFactorAuthController
php artisan make:controller Api/Auth/RecoveryCodeController
```

Esto crea archivos como:

```txt
app/Http/Controllers/Api/Auth/TwoFactorAuthController.php
app/Http/Controllers/Api/Auth/RecoveryCodeController.php
```

### Controller — Regla práctica

Un Controller responde esta pregunta:

> ¿Qué endpoint necesita consumir el frontend?

---

## 3. Model

### ¿Cuándo debe ser un Model?

Debe ser un **Model** cuando existe una entidad persistente
en base de datos.

Ejemplos del módulo 2FA:

- `User`
- `TwoFactorSetting`
- `TwoFactorMethod`
- `OtpChallenge`
- `RecoveryCode`
- `PasskeyCredential`

Un modelo debe representar:

- una tabla;
- sus atributos asignables;
- sus casts;
- sus relaciones;
- pequeños métodos relacionados directamente con el estado
  del registro.

### Model — Ejemplo de creación con Artisan

```bash
php artisan make:model Auth/TwoFactorSetting
php artisan make:model Auth/TwoFactorMethod
php artisan make:model Auth/OtpChallenge
php artisan make:model Auth/RecoveryCode
php artisan make:model Auth/PasskeyCredential
```

Esto crea archivos como:

```txt
app/Models/Auth/TwoFactorSetting.php
app/Models/Auth/OtpChallenge.php
app/Models/Auth/RecoveryCode.php
```

### Model — Ejemplo correcto de lógica en un Model

```php
public function isExpired(): bool
{
    return $this->expires_at->isPast();
}
```

Este método sí pertenece al modelo porque depende
directamente del estado del registro.

### Model — Regla práctica

Un Model responde esta pregunta:

> ¿Qué entidad de base de datos representa esto?

---

## 4. Service

### ¿Cuándo debe ser un Service?

Debe ser un **Service** cuando existe lógica de negocio
importante, reutilizable o demasiado compleja para vivir
dentro de un Controller.

Esta es una de las preguntas más importantes en una
arquitectura `Laravel` profesional:

> ¿Cuándo debe ser un servicio?

Debe ser un servicio cuando la lógica:

1. Tiene varias reglas de negocio.
2. Se reutiliza desde varios lugares.
3. Coordina varios modelos.
4. Tiene validaciones internas.
5. Maneja seguridad.
6. Maneja cálculos importantes.
7. Maneja procesos con varios pasos.
8. Haría que el Controller se vuelva largo o difícil de
   leer.

---

### Ejemplos de lógica que debe ir en Services

En un ecommerce:

- calcular el total de una orden;
- aplicar cupones;
- validar stock;
- procesar pagos;
- generar facturas;
- actualizar inventario;
- calcular impuestos;
- generar reportes;
- validar estados de pedido.

En autenticación:

- generar OTP;
- validar OTP;
- controlar intentos;
- validar expiración;
- generar recovery codes;
- hashear códigos;
- activar o desactivar 2FA.

---

### Service — Ejemplo del proyecto

En este proyecto existen servicios como:

```txt
app/Services/Auth/EmailOtpService.php
app/Services/Auth/RecoveryCodeService.php
```

Estos archivos no se crearon con un comando oficial de
`Laravel`, porque `Laravel` no trae un comando
`make:service` por defecto.

Se crean manualmente:

```bash
mkdir -p app/Services/Auth
nvim app/Services/Auth/EmailOtpService.php
nvim app/Services/Auth/RecoveryCodeService.php
```

---

### ¿Por qué EmailOtpService debe ser un Service?

Porque no solo envía un correo. Su responsabilidad
incluye varias reglas:

1. Leer configuración del OTP.
2. Generar un código seguro.
3. Hashear el código.
4. Guardar el reto en base de datos.
5. Registrar IP y user-agent.
6. Enviar la notificación.
7. Validar el código ingresado.
8. Controlar expiración.
9. Controlar intentos máximos.
10. Marcar el código como usado.

Esa lógica no debería estar directamente dentro del
Controller.

---

### ¿Por qué RecoveryCodeService debe ser un Service?

Porque maneja reglas de seguridad:

1. Generar códigos de recuperación.
2. Evitar caracteres ambiguos.
3. Hashear cada código.
4. Eliminar códigos anteriores al regenerar.
5. Retornar los códigos en texto plano solo una vez.
6. Validar un código ingresado.
7. Marcar códigos como usados.

Esta lógica es parte del dominio de seguridad del
sistema, por eso pertenece a un Service.

---

### Service — Regla práctica

Un Service responde esta pregunta:

> ¿Cómo se ejecuta esta regla del negocio?

---

## 5. Notification

### ¿Cuándo debe ser una Notification?

Debe ser una **Notification** cuando el sistema necesita
avisarle algo a un usuario.

Ejemplos:

- enviar código OTP;
- avisar cambio de contraseña;
- enviar confirmación de compra;
- notificar pedido enviado;
- avisar factura vencida;
- enviar alerta de seguridad.

### Notification — Ejemplo de creación con Artisan

```bash
php artisan make:notification Auth/EmailOtpNotification
```

Esto crea:

```txt
app/Notifications/Auth/EmailOtpNotification.php
```

### Responsabilidad correcta

Una Notification debe encargarse de:

1. Definir el canal de envío.
2. Preparar el correo.
3. Definir asunto.
4. Renderizar una vista.
5. Enviar el mensaje al usuario.

### Qué no debe hacer una Notification

Una Notification no debería:

- generar el OTP;
- validar intentos;
- guardar registros complejos;
- activar 2FA;
- contener reglas de negocio.

Eso pertenece al Service.

### Notification — Regla práctica

Una Notification responde esta pregunta:

> ¿Cómo aviso esto al usuario?

---

## 6. Command

### ¿Cuándo debe ser un Command?

Debe ser un **Command** cuando se necesita ejecutar una
tarea desde la terminal usando `php artisan`.

Ejemplos:

- previsualizar un correo;
- limpiar registros vencidos;
- generar reportes;
- sincronizar datos;
- crear datos de prueba;
- ejecutar tareas administrativas;
- recalcular estadísticas.

### Command — Ejemplo de creación con Artisan

```bash
php artisan make:command MailPreviewOtpCommand --command=mail:preview-otp
```

Esto crea:

```txt
app/Console/Commands/MailPreviewOtpCommand.php
```

Luego se ejecuta así:

```bash
php artisan mail:preview-otp
```

### Command — Ejemplo del proyecto

El comando `mail:preview-otp` sirve para enviar un
correo OTP de prueba a Mailpit y revisar el diseño
visual del email.

No reemplaza los tests.

Su objetivo es facilitar el desarrollo visual del correo
sin tener que ejecutar todo el flujo real de
autenticación.

### Command — Regla práctica

Un Command responde esta pregunta:

> ¿Qué tarea quiero ejecutar desde consola?

---

## 7. Notification vs Service vs Command

Estos tres componentes suelen confundirse.

### Service

Contiene la lógica de negocio.

Ejemplo:

```txt
Generar OTP, hashearlo, guardarlo y verificarlo.
```

### Notification (comparación)

Comunica algo al usuario.

Ejemplo:

```txt
Enviar el código OTP por correo.
```

### Command (comparación)

Ejecuta algo desde terminal.

Ejemplo:

```txt
Enviar un OTP de prueba para revisar el diseño en Mailpit.
```

---

## 8. Test

### ¿Cuándo debe ser un Test?

Debe ser un **Test** cuando se quiere asegurar que una
funcionalidad sigue funcionando después de cambios
futuros.

Ejemplos:

- verificar que el usuario puede activar 2FA;
- verificar que un OTP inválido devuelve error;
- verificar que los recovery codes se generan;
- verificar que se puede desactivar 2FA.

### Test — Ejemplo de creación con Artisan

```bash
php artisan make:test Auth/EmailOtpTwoFactorTest
```

Esto crea:

```txt
tests/Feature/Auth/EmailOtpTwoFactorTest.php
```

Se ejecuta con:

```bash
php artisan test --filter=EmailOtpTwoFactorTest --env=testing
```

### Test — Regla práctica

Un Test responde esta pregunta:

> ¿Cómo sé que esta funcionalidad no se rompió?

---

## 9. Migration

### ¿Cuándo debe ser una Migration?

Debe ser una **Migration** cuando se necesita crear o
modificar estructura de base de datos.

Ejemplos:

- crear una tabla;
- agregar una columna;
- crear un índice;
- crear claves foráneas;
- modificar tipos de datos.

### Migration — Ejemplo de creación con Artisan

```bash
php artisan make:migration create_two_factor_settings_table --path=database/migrations/auth
php artisan make:migration create_two_factor_methods_table --path=database/migrations/auth
php artisan make:migration create_otp_challenges_table --path=database/migrations/auth
php artisan make:migration create_recovery_codes_table --path=database/migrations/auth
php artisan make:migration create_passkey_credentials_table --path=database/migrations/auth
```

Esto crea migraciones dentro de:

```txt
database/migrations/auth/
```

### Migration — Regla práctica

Una Migration responde esta pregunta:

> ¿Qué estructura necesita la base de datos?

---

## 10. Blade View

### ¿Cuándo debe ser una vista Blade?

Debe ser una vista Blade cuando se necesita renderizar
HTML desde `Laravel`.

Ejemplos:

- correo OTP;
- correo de recuperación de contraseña;
- factura en HTML;
- plantilla de notificación;
- componente reutilizable para emails.

### Blade View — Ejemplo de creación manual

Las vistas Blade normalmente se crean manualmente:

```bash
mkdir -p resources/views/components/email
mkdir -p resources/views/emails/auth

nvim resources/views/components/email/layout.blade.php
nvim resources/views/emails/auth/email-otp.blade.php
```

### Blade View — Regla práctica

Una vista Blade responde esta pregunta:

> ¿Qué HTML debe renderizar `Laravel`?

---

## 11. Config

### ¿Cuándo crear un archivo en config?

Debe crearse un archivo en `config/` cuando hay valores
configurables que no deberían estar hardcodeados en el
código.

Ejemplos:

- duración del OTP;
- cantidad de intentos;
- cantidad de recovery codes;
- rutas de logos;
- correo de soporte;
- URLs de redes sociales.

### Config — Ejemplo de creación manual

```bash
nvim config/two_factor.php
nvim config/brand.php
```

### Config — Regla práctica

Un archivo de configuración responde esta pregunta:

> ¿Qué valores pueden cambiar según ambiente o proyecto?

---

## 12. Resumen de comandos utilizados

### API y Sanctum

```bash
php artisan install:api
```

### Migraciones

```bash
php artisan make:migration create_two_factor_settings_table --path=database/migrations/auth
php artisan make:migration create_two_factor_methods_table --path=database/migrations/auth
php artisan make:migration create_otp_challenges_table --path=database/migrations/auth
php artisan make:migration create_recovery_codes_table --path=database/migrations/auth
php artisan make:migration create_passkey_credentials_table --path=database/migrations/auth
```

### Modelos

```bash
php artisan make:model Auth/TwoFactorSetting
php artisan make:model Auth/TwoFactorMethod
php artisan make:model Auth/OtpChallenge
php artisan make:model Auth/RecoveryCode
php artisan make:model Auth/PasskeyCredential
```

### Controladores

```bash
php artisan make:controller Api/Auth/TwoFactorAuthController
php artisan make:controller Api/Auth/RecoveryCodeController
```

### Notification

```bash
php artisan make:notification Auth/EmailOtpNotification
```

### Command (resumen)

```bash
php artisan make:command MailPreviewOtpCommand --command=mail:preview-otp
```

### Test

```bash
php artisan make:test Auth/EmailOtpTwoFactorTest
```

### Services

`Laravel` no trae un comando oficial `make:service` por
defecto. Por eso los servicios se crearon manualmente:

```bash
mkdir -p app/Services/Auth
nvim app/Services/Auth/EmailOtpService.php
nvim app/Services/Auth/RecoveryCodeService.php
```

### Configuración

```bash
nvim config/two_factor.php
nvim config/brand.php
```

### Vistas Blade

```bash
mkdir -p resources/views/components/email
mkdir -p resources/views/emails/auth

nvim resources/views/components/email/layout.blade.php
nvim resources/views/emails/auth/email-otp.blade.php
```

---

## 13. Tabla de decisión rápida

- ¿El frontend necesita llamar una URL? → Controller
- ¿Esto representa una tabla? → Model
- ¿Esto crea o cambia tablas? → Migration
- ¿Hay lógica de negocio compleja? → Service
- ¿Hay que avisar algo al usuario? → Notification
- ¿Hay que ejecutarlo desde terminal? → Command
- ¿Hay que probar que no se rompa? → Test
- ¿Hay que devolver JSON con estructura limpia? → Resource
- ¿Hay validaciones grandes de entrada? → Form Request
- ¿Hay HTML reutilizable o correo? → Blade View
- ¿Hay valores configurables? → Config

---

## 14. Regla senior

La regla más importante es:

> El Controller no debe pensar demasiado.

Un Controller profesional debe ser pequeño.

La lógica importante debe vivir en Services.

Las Notifications deben encargarse de comunicar.

Los Commands deben ejecutar tareas desde consola.

Los Models deben representar datos, relaciones y reglas
pequeñas del registro.

Los Tests deben proteger el comportamiento del sistema.

---

## 15. Aplicación al módulo 2FA

El flujo principal queda así:

```txt
Frontend
   ↓
Route
   ↓
TwoFactorAuthController
   ↓
EmailOtpService
   ↓
OtpChallenge / TwoFactorSetting / TwoFactorMethod
   ↓
EmailOtpNotification
   ↓
Mailpit o correo real
```

El flujo para recovery codes:

```txt
Frontend
   ↓
Route
   ↓
RecoveryCodeController
   ↓
RecoveryCodeService
   ↓
RecoveryCode Model
   ↓
Respuesta JSON con códigos mostrados una sola vez
```

El flujo para previsualizar el correo:

```txt
Terminal
   ↓
php artisan mail:preview-otp
   ↓
MailPreviewOtpCommand
   ↓
EmailOtpNotification
   ↓
Mailpit
```

El flujo de pruebas:

```txt
php artisan test --filter=EmailOtpTwoFactorTest --env=testing
   ↓
EmailOtpTwoFactorTest
   ↓
Rutas API
   ↓
Controllers
   ↓
Services
   ↓
Base de datos de testing
```

---

## 16. Conclusión

Un sistema `Laravel` profesional no consiste en poner
todo en controladores.

La arquitectura debe separar responsabilidades:

- los Controllers exponen endpoints;
- los Services ejecutan reglas de negocio;
- los Models representan datos;
- las Notifications comunican eventos al usuario;
- los Commands permiten tareas de consola;
- los Tests aseguran que el comportamiento siga
  funcionando.

Esta separación hace que el proyecto sea más fácil de
mantener, probar, documentar y extender.
