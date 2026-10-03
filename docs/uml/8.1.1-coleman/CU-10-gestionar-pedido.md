### CU-10 — Gestionar pedido  (HU-10)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-10 |
| **Actores involved** | Operador de tienda |
| **Precondiciones** | El operador está autenticado y tiene permisos sobre la tienda del pedido. Existe un pedido registrado con pago confirmado. |
| **Postcondiciones** | El `Pedido` cambia al estado siguiente según la acción. Se actualiza el inventario y se registra el envío con guía. El cliente recibe notificación. |
| **Descripción / Propósito** | Permite al operador gestionar el ciclo de vida de un pedido: confirmar pago, preparar, enviar y registrar la guía de envío. |
| **Flujo principal (básico)** | 1. Operador accede al listado de pedidos pendientes. 2. Operador selecciona un pedido. 3. El sistema valida el estado del pago del pedido. 4. Operador confirma el pago y prepara el envío. 5. Operador marca como enviado e ingresa el número de guía. 6. El sistema descuenta el stock y registra el movimiento. 7. El sistema notifica al cliente el envío del despacho. |
| **Flujo alternativo / Excepciones** | **A1 — Pago no recibido:** si el pago no está aprobado, el operador no puede enviar. **A2 — Cancelar pedido:** el operador puede cancelar el pedido antes del envío, registrando motivo de cambio y motivo. **A3 — Productos personalizados:** si el pedido contiene productos personalizados, el flujo incluye "En producción" con tiempo estimado. |
| **Reglas de negocio** | RB-SALES-06: El stock se descuenta al marcar como enviado. RB-SALES-07: La cancelación es posible solo antes del envío. RB-SALES-08: El operador registra la guía de envío obligatoria para registrar el envío. |
| **Requerimientos relacionados** | RF-SALES (1-17), RF-SHIP (1-8) |
| **Prioridad** | Alta |
