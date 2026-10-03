### CU-05 — Pagar pedido en línea  (HU-05)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-05 |
| **Actores involved** | Cliente externo / Pasarela de pago externa |
| **Precondiciones** | Existe un `Pedido` en estado `PENDIENTE`. El cliente tiene un método de pago válido configurado en la tienda. |
| **Postcondiciones** | Se crea un `Pago` con estado `COMPLETADO` (o `FALLIDO` según el caso). El `Pedido` cambia a estado `PROCESANDO`. El inventario se descuenta y se registra el movimiento. |
| **Descripción / Propósito** | Procesa el pago del pedido a través de la pasarela configurada, completando el ciclo de compra. |
| **Flujo principal (básico)** | 1. Cliente confirma el pago del pedido. 2. El sistema valida el método de pago seleccionado. 3. El sistema envía la solicitud a la pasarela de pago configurada. 4. La pasarela procesa el pago y devuelve el resultado. 5. Caso resultado positivo: el sistema actualiza el `Pago` a `COMPLETADO`. 6. El sistema cambia el `Pedido` a `PROCESANDO`. 7. El sistema descuenta el stock y registra el movimiento de inventario. 8. El sistema envía la confirmación al cliente vía email. |
| **Flujo alternativo / Excepciones** | **A1 — Pasarela no responde:** si la pasarela no responde en `[RECOMENDADO]` ≥ 30s, el sistema cancela el flujo y permite reintentar. **A2 — Pago rechazado:** si la pasarela rechaza el pago, el sistema notifica al cliente y permite cambiar de método o reintentar. **A3 — Pago duplicado:** si se detecta el mismo `referenciaPasarela` dentro del flujo, se considera el primer resultado como válido. |
| **Reglas de negocio** | RB-PAY-01: El webhook de la pasarela es la fuente de verdad del estado del pago. RB-PAY-02: El estado `PROCESANDO` se confirma solo tras recibir el webhook positivo. RB-PAY-03: Solo se descuenta el stock tras la confirmación del pago, no en el checkout. |
| **Requerimientos relacionados** | RF-PAY (1-10) |
| **Prioridad** | Alta |
