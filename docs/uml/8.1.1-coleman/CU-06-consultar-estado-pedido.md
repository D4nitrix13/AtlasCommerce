### CU-06 — Consultar estado de pedido  (HU-06)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-06 |
| **Actores involved** | Cliente externo |
| **Precondiciones** | El cliente está autenticado. Tiene al menos un pedido registrado en el sistema. |
| **Postcondiciones** | El cliente visualiza el estado actual del pedido. No se modifica el estado del pedido. |
| **Descripción / Propósito** | Permite al cliente visualizar el estado de sus pedidos y descargar la factura en PDF. |
| **Flujo principal (básico)** | 1. Cliente accede al módulo de pedidos del cliente. 2. El sistema muestra el listado de pedidos del cliente con estado actual. 3. El cliente selecciona un pedido. 4. El sistema muestra el detalle del pedido (líneas, pago, envío, factura). 5. El cliente consulta el estado del envío y la guía si existe. |
| **Flujo alternativo / Excepciones** | **A1 — Descargar factura:** si existe `Factura` para el pedido, el cliente puede descargar el PDF. **A2 — Sin pedidos:** si el cliente no tiene pedidos, se muestra mensaje "Aún no tienes pedidos". |
| **Reglas de negocio** | RB-SALES-04: Solo el cliente dueño del pedido puede ver su detalle. RB-SALES-05: La factura se genera tras la confirmación del pago. |
| **Requerimientos relacionados** | RF-SALES (1-17) |
| **Prioridad** | Media |
