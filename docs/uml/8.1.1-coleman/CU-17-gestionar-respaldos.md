### CU-17 — Gestionar respaldos  (HU-17)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-17 |
| **Actores involved** | Administrador |
| **Precondiciones** | El administrador está autenticado con permisos de sistema. Existe un respaldo reciente disponible. |
| **Postcondiciones** | Se crea/ejecuta un `Respaldo`. Si se ejecuta una restauración, los datos vuelven al estado del respaldo seleccionado. |
| **Descripción / Propósito** | Permite al administrador configurar los respaldos automáticos y ejecutar restauraciones del sistema. |
| **Flujo principal (básico)** | 1. Administrador accede al módulo de respaldos. 2. Administrador configura la frecuencia (DIARIO/SEMANAL/MENSUAL) y la retención. 3. Administrador selecciona "Ejecutar respaldo ahora". 4. El sistema genera el respaldo (COMPLETO o INCREMENTAL) y lo cifra. 5. El sistema almacena el respaldo en la ubicación configurada. 6. El sistema notifica al administrador el estado del respaldo. |
| **Flujo alternativo / Excepciones** | **A1 — Restaurar desde respaldo:** el administrador selecciona un respaldo y ejecuta la restauración. **A2 — Respaldo fallido:** si el respaldo falla, el sistema notifica al administrador con los detalles del error. |
| **Reglas de negocio** | RB-SYS-01: Los respaldos se cifran con AES-256. **[RECOMENDADO] RB-SYS-02:** Política de retención 7-4-12 (7 diarios, 4 semanales, 12 mensuales). **[RECOMENDADO] RB-SYS-03:** La restauración requiere confirmación y respaldo del estado actual antes de proceder. |
| **Requerimientos relacionados** | RF-SYS (1-2) |
| **Prioridad** | Media |
