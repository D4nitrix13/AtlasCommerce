### CU-09 — Gestionar stock e inventario  (HU-09)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-09 |
| **Actores involved** | Operador de tienda |
| **Precondiciones** | El operador está autenticado y tiene permisos sobre la tienda activa. Existen productos y almacenes registrados. |
| **Postcondiciones** | Se actualiza `RegistroInventario.stockActual` y se registra un `MovimientoInventario` con la trazabilidad del cambio. |
| **Descripción / Propósito** | Permite al operador registrar y actualizar el stock por producto, variante y almacén, con trazabilidad completa de movimientos. |
| **Flujo principal (básico)** | 1. Operador accede al módulo de inventario del panel. 2. Operador selecciona un producto (y opcionalmente variante) y un almacén. 3. Operador registra el movimiento (entrada, salida, ajuste) con cantidad, motivo y referencia. 4. El sistema valida el stock y crea el `MovimientoInventario`. 5. El sistema actualiza el `RegistroInventario.stockActual`. 6. El sistema registra el movimiento para trazabilidad. |
| **Flujo alternativo / Excepciones** | **A1 — Stock bajo mínimo:** si el stock resultante queda por debajo del `stockMinimo`, el sistema alerta al operador. **A2 — Ajuste sin comprobante:** los ajustes sin comprobante requieren aprobación del supervisor. |
| **Reglas de negocio** | RB-INV-01: Cada movimiento de stock genera un `MovimientoInventario` con tipo, cantidad, motivo, referencia y usuario. RB-INV-02: El stock es global entre las marcas, no por tienda. RB-INV-03: La trazabilidad es inalterable (auditoría completa). |
| **Requerimientos relacionados** | RF-INV (1-7) |
| **Prioridad** | Alta |
