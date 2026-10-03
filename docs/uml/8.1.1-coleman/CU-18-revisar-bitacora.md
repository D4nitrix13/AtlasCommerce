### CU-18 — Revisar bitácora de auditoría  (HU-18)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-18 |
| **Actores involved** | Administrador |
| **Precondiciones** | El administrador está autenticado con permisos de sistema. Existen registros de auditoría en el sistema. |
| **Postcondiciones** | El administrador visualiza los registros. Si exporta, se genera un archivo con el log filtrado. |
| **Descripción / Propósito** | Permite al administrador revisar la bitácora de auditoría y los logs de la plataforma, con el fin de monitorear la actividad del sistema. |
| **Flujo principal (básico)** | 1. Administrador accede al módulo de bitácora. 2. Administrador aplica filtros (período, usuario, módulo, acción, tienda). 3. El sistema muestra los registros de `BitacoraAuditoria` que coinciden con los filtros. 4. Administrador selecciona un registro para ver el detalle (datos anteriores y nuevos). 5. Administrador exporta el log filtrado a archivo. 6. El sistema genera el archivo y permite su descarga. |
| **Flujo alternativo / Excepciones** | **A1 — Sin registros:** si no hay registros para los filtros aplicados, se muestra mensaje "Sin registros". **A2 — Registros críticos:** si hay registros con acciones críticas (ej: borrado), el sistema alerta al administrador. |
| **Reglas de negocio** | RB-AUDIT-01: La bitácora es inalterable (solo inserción). RB-AUDIT-02: Los registros mantienen IP de origen, usuario, tienda, módulo, entidad y timestamp. **[RECOMENDADO] RB-AUDIT-03:** Retención mínima de la bitácora: ≥ 5 años. |
| **Requerimientos relacionados** | RF-SYS (1-2) |
| **Prioridad** | Media |
