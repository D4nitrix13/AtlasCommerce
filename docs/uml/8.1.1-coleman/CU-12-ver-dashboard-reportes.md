### CU-12 — Ver dashboard y reportes  (HU-12)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-12 |
| **Actores involved** | Supervisor |
| **Precondiciones** | El supervisor está autenticado y tiene permisos sobre la tienda activa. Existen datos transaccionales (ventas, inventario, clientes) en el sistema. |
| **Postcondiciones** | El supervisor visualiza los indicadores. Si exporta, se genera un archivo Excel con los datos. |
| **Descripción / Propósito** | Permite al supervisor visualizar los indicadores de ventas, inventario y clientes, y exportar los reportes a Excel. |
| **Flujo principal (básico)** | 1. Supervisor accede al módulo de reportes. 2. El sistema muestra el dashboard con los indicadores clave (ventas por período, top productos, stock bajo mínimo, clientes nuevos). 3. Supervisor aplica filtros (período, tienda, categoría). 4. El sistema recalcula y muestra los indicadores filtrados. 5. Supervisor selecciona un reporte específico para ver el detalle. 6. El sistema genera el reporte en pantalla. 7. Supervisor exporta el reporte a Excel. 8. El sistema genera el archivo Excel y permite su descarga. |
| **Flujo alternativo / Excepciones** | **A1 — Sin datos:** si no hay datos para el período, se muestra mensaje "Sin datos en este período". |
| **Reglas de negocio** | RB-RPT-01: El supervisor solo ve datos de su tienda asignada. RB-RPT-02: Los datos se filtran por `tiendaId` y por rango de fechas. **[RECOMENDADO] RB-RPT-03:** Los reportes se generan con cache (`[RECOMENDADO]` TTL 5min) para optimizar rendimiento. |
| **Requerimientos relacionados** | RF-RPT (1-2) |
| **Prioridad** | Media |
