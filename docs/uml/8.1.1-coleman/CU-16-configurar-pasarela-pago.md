### CU-16 — Configurar pasarela de pago  (HU-16)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-16 |
| **Actores involved** | Administrador / Pasarela de pago externa |
| **Precondiciones** | El administrador está autenticado con permisos de configuración. La tienda existe. |
| **Postcondiciones** | La `PasarelaGenerica` queda configurada con credenciales cifradas, modo (PRUEBA/PRODUCCION) y monedas soportadas. La pasarela responde a las solicitudes de prueba. |
| **Descripción / Propósito** | Permite al administrador configurar la pasarela de pago por tienda: credenciales, monedas, webhook y modo (prueba o producción). |
| **Flujo principal (básico)** | 1. Administrador accede al módulo de configuración de pasarela. 2. Administrador selecciona la tienda y la pasarela. 3. Administrador ingresa las credenciales (API key, secret). 4. Administrador selecciona el modo (PRUEBA o PRODUCCION) y las monedas. 5. Administrador guarda la configuración. 6. El sistema cifra las credenciales y las almacena. 7. El sistema prueba la conexión con la pasarela usando las credenciales. 8. Si la prueba es exitosa, el sistema confirma la configuración. |
| **Flujo alternativo / Excepciones** | **A1 — Credenciales inválidas:** si las credenciales no son válidas, el sistema muestra error y no guarda la configuración. **A2 — Pasarela no responde:** si la pasarela no responde en `[RECOMENDADO]` ≥ 10s, el sistema cancela la prueba. |
| **Reglas de negocio** | RB-PAY-04: Las credenciales se almacenan cifradas. RB-PAY-05: El cambio de PRUEBA a PRODUCCION requiere confirmación por seguridad. RB-PAY-06: La pasarela solo se activa tras una prueba exitosa. |
| **Requerimientos relacionados** | RF-PAY (1-10) |
| **Prioridad** | Alta |
