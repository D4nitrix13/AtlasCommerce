### CU-15 — Configurar tienda  (HU-15)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-15 |
| **Actores involved** | Administrador |
| **Precondiciones** | El administrador está autenticado con permisos de configuración. La tienda existe y está activa. |
| **Postcondiciones** | La `Tienda` queda con los nuevos parámetros de tema, métodos de pago, envío, impuestos y dominio. |
| **Descripción / Propósito** | Permite al administrador configurar por tienda el tema visual, métodos de pago habilitados, empresas de envío, impuestos y dominios personalizados. |
| **Flujo principal (básico)** | 1. Administrador accede al módulo de tiendas. 2. Administrador selecciona una tienda. 3. Administrador edita los parámetros: tema visual (colores, logo), métodos de pago habilitados, zonas de envío, impuestos por defecto. 4. Administrador guarda la configuración. 5. El sistema valida la configuración y la aplica. 6. El sistema notifica al operador de la tienda de los cambios. |
| **Flujo alternativo / Excepciones** | **A1 — Configurar dominio:** opcionalmente, el administrador agrega dominios personalizados a la tienda. **A2 — Modo mantenimiento:** el administrador puede activar el modo mantenimiento de la tienda (oculta el storefront). |
| **Reglas de negocio** | RB-STOR-01: Los cambios en tema visual se reflejan en el storefront sin deploy. RB-STOR-02: El modo mantenimiento oculta el storefront pero no afecta el panel administrativo. **[RECOMENDADO] RB-STOR-03:** Los cambios en métodos de pago requieren confirmación por seguridad. |
| **Requerimientos relacionados** | RF-STOR (1-9) |
| **Prioridad** | Alta |
