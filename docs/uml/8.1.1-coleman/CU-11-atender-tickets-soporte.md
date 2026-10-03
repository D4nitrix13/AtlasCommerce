### CU-11 — Atender tickets de soporte  (HU-11)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-11 |
| **Actores involved** | Operador de tienda / Cliente externo |
| **Precondiciones** | Existe al menos un ticket de soporte registrado por un cliente. El operador está autenticado y tiene permisos. |
| **Postcondiciones** | El `TicketSoporte` cambia al estado siguiente según la acción. Se registran los `MensajeTicket` correspondientes. El cliente recibe notificación. |
| **Descripción / Propósito** | Permite al operador atender los tickets de soporte abiertos por los clientes: consultas, reclamos y devoluciones. |
| **Flujo principal (básico)** | 1. Operador accede al módulo de soporte. 2. Operador visualiza los tickets abiertos (filtrados por tienda). 3. Operador selecciona un ticket. 4. Operador lee el historial del ticket y responde al cliente. 5. El sistema registra el `MensajeTicket` y actualiza el estado del ticket. 6. Operador marca el ticket como cerrado si está resuelto. 7. El sistema notifica al cliente vía email. |
| **Flujo alternativo / Excepciones** | **A1 — Esperando cliente:** si el operador necesita más información, cambia el estado a `ESPERANDO_CLIENTE`. **A2 — Escalar a devolución:** si el ticket es de tipo `DEVOLUCION`, el operador puede iniciar el flujo de devolución desde el ticket. |
| **Reglas de negocio** | RB-SUP-01: El ticket mantiene referencia al pedido si lo usaron en su creación. RB-SUP-02: El operador solo ve tickets de su tienda asignada. **[RECOMENDADO] RB-SUP-03:** SLA de primera respuesta por prioridad (URGENTE ≤ 2h, ALTA ≤ 8h, MEDIA ≤ 24h, BAJA ≤ 72h). |
| **Requerimientos relacionados** | RF-SUP (1-3), RF-RET (1-4) |
| **Prioridad** | Media |
