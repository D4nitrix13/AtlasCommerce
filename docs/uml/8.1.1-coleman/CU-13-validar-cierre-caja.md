### CU-13 — Validar cierre de caja diario  (HU-13)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-13 |
| **Actores involved** | Supervisor |
| **Precondiciones** | El supervisor está autenticado. Existen pagos del día en la tienda activa con estado `COMPLETADO` o `FALLIDO`. |
| **Postcondiciones** | Se crea un `CierreCaja` en estado `VALIDADO` o `RECHAZADO`. Si está validado, el cierre queda cerrado para el día. |
| **Descripción / Propósito** | Permite al supervisor validar los cierres de caja diarios y las conciliaciones de pago de la tienda. |
| **Flujo principal (básico)** | 1. Supervisor accede al módulo de cierres de caja. 2. El sistema calcula el monto esperado del día (separado por efectivo y pasarela) sumando los pagos del día por tienda. 3. El sistema muestra el cierre calculado y el monto reportado por el operador. 4. Supervisor compara ambos montos. 5. Si coinciden, supervisor valida el cierre. 6. El sistema crea el `CierreCaja` en estado `VALIDADO`. 7. El sistema bloquea el cierre del día. |
| **Flujo alternativo / Excepciones** | **A1 — Diferencia:** si hay diferencia entre el monto esperado y el reportado, el supervisor rechaza el cierre, registra observaciones y solicita ajuste. **A2 — Sin pagos:** si no hay pagos en el día, se muestra cierre en ceros. |
| **Reglas de negocio** | RB-SALES-09: Un `CierreCaja` validado no puede modificarse. RB-SALES-10: La conciliación se hace por separado entre efectivo contraentrega y pagos por pasarela. **[RECOMENDADO] RB-SALES-11:** El cierre se genera automáticamente a las 23:59 hora local de Nicaragua. |
| **Requerimientos relacionados** | RF-SALES (1-17) |
| **Prioridad** | Media |
