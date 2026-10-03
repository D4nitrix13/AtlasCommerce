### CU-07 — Gestionar lista de deseos  (HU-07)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-07 |
| **Actores involved** | Cliente externo |
| **Precondiciones** | El cliente está autenticado. Existen productos visibles en el catálogo. |
| **Postcondiciones** | Se crea/actualiza una `ListaDeseos` con sus `ItemListaDeseos`. Si activó notificaciones, queda suscrito a promociones de los ítems. |
| **Descripción / Propósito** | Permite al cliente guardar productos de interés para comprarlos después, y opcionalmente recibir notificaciones de promociones. |
| **Flujo principal (básico)** | 1. Cliente navega en el catálogo y selecciona un producto. 2. El cliente agrega el producto a su lista de deseos. 3. El sistema valida la lista y crea el `ItemListaDeseos`. 4. El cliente accede al grupo "Mis listas" y visualiza sus listas. 5. El cliente confirma y sale del inventario. |
| **Flujo alternativo / Excepciones** | **A1 — Notificaciones:** el cliente puede activar la suscripción a notificaciones de promoción del producto. **A2 — Compartir lista:** opcionalmente, el cliente puede compartir la lista con visibilidad `PUBLICA` o `COMPARTIDA_CON_ENLACE`. |
| **Reglas de negocio** | RB-MKT-01: Las listas de deseos son por tienda y por cliente. **[RECOMENDADO] RB-MKT-02:** Las notificaciones de promoción se envían vía email según `[RECOMENDADO]` ≥ 1 vez por semana. |
| **Requerimientos relacionados** | RF-MKT (1-2) |
| **Prioridad** | Baja |
