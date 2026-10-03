### CU-04 — Realizar checkout paso a paso  (HU-04)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-04 |
| **Actores involved** | Cliente externo |
| **Precondiciones** | El cliente tiene un `Carrito` con al menos 1 producto y con estado `ACTIVO`. El cliente está autenticado o se le autentica durante el flujo. |
| **Postcondiciones** | Se crea un `Pedido` en estado `PENDIENTE` con sus `LineaPedido`, `direccionEnvio` y `metodoPago` seleccionados. El `Carrito` cambia a estado `CONVERTIDO`. |
| **Descripción / Propósito** | Convierte un carrito activo en un pedido confirmado, recopilando los datos requeridos por el cliente para el envío y el pago. |
| **Flujo principal (básico)** | 1. Cliente confirma el checkout del carrito. 2. El sistema valida el stock disponible de cada ítem. 3. El sistema muestra el paso 1: dirección de envío (cliente selecciona o crea una). 4. El cliente selecciona la opción de envío y completa el resumen. 5. El sistema valida el stock de cada producto. 6. El sistema crea un `Pedido` en estado `PENDIENTE_PAGO` con sus `LineaPedido`, `direccionEnvio`, `metodoPago`. 7. El `Carrito` cambia a estado `CONVERTIDO`. 8. El cliente confirma el pedido y es redirigido al flujo de pago. |
| **Flujo alternativo / Excepciones** | **A1 — Stock insuficiente:** el sistema notifica al cliente de los productos sin stock y permite ajustar. **A2 — Sin dirección:** si el cliente no tiene direcciones registradas, debe crear una. **A3 — Aplicar cupón:** opcionalmente, cliente ingresa un cupón válido para descuento. |
| **Reglas de negocio** | RB-SALES-01: El stock se valida al momento del checkout, no se descuenta hasta que el pago se confirme. RB-SALES-02: La dirección de envío debe pertenecer al cliente autenticado. **[RECOMENDADO] RB-SALES-03:** El pedido conserva el ID de la tienda del carrito original. |
| **Requerimientos relacionados** | RF-SALES (1-17), RF-SHIP (1-8) |
| **Prioridad** | Alta |
