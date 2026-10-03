### CU-02 — Registrarse en el sistema  (HU-02)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-02 |
| **Actores involved** | Cliente externo / Sistema de email |
| **Precondiciones** | El cliente no tiene sesión activa. El correo electrónico no está registrado previamente en `usuarios.correo`. |
| **Postcondiciones** | Se crea un registro `Usuario` con `correoVerificadoEn` poblado. Si activó 2FA, existe una `CredencialSegundoFactor` asociada. |
| **Descripción / Propósito** | Permite a un cliente crear una cuenta en el sistema para poder comprar, hacer seguimiento de pedidos y acceder a funcionalidades personalizadas. |
| **Flujo principal (básico)** | 1. Cliente accede al formulario de registro desde el header del sitio. 2. Completa correo y contraseña con nombre en el formulario. 3. Sistema valida formato, uniqueness de correo, estado de la contraseña (mín. 8 caracteres, mayúsculas, números). 4. Confirma y almacena la contraseña (hash). 5. El sistema confirma el alta del correo y la activación de la cuenta. |
| **Flujo alternativo / Excepciones** | **A1 — Correo ya registrado:** el sistema muestra error "El correo ya está registrado". **A2 — Token expirado:** si el enlace de verificación vence (`[RECOMENDADO]` 24h), permite reenviar. **A3 — Activar 2FA:** opcionalmente, post-registro el cliente activa 2FA vía email OTP o TOTP. |
| **Reglas de negocio** | RB-AUTH-01: La contraseña debe cumplir política mínima (mín. 8 caracteres, mayúscula, número). **RB-AUTH-02:** Correo debe ser único en el sistema. **[RECOMENDADO] RB-AUTH-03:** Token de correo expira en ≥ 24h. |
| **Requerimientos relacionados** | RF-AUTH (1-16) |
| **Prioridad** | Alta |
