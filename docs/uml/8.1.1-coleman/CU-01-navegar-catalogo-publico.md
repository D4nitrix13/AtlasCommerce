### CU-01 — Navegar catálogo público  (HU-01)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-01 |
| **Actores involved** | Cliente externo |
| **Precondiciones** | El cliente accede al storefront desde un dominio válido. La tienda correspondiente está activa (`tienda.activa=true`). Existen productos con publicación visible (`productoTienda.visible=true`). |
| **Postcondiciones** | El cliente visualiza el catálogo. No se altera el estado de ningún producto, variante ni publicación. |
| **Descripción / Propósito** | Permite al cliente explorar visualmente el catálogo público de la marca, con el objetivo de encontrar productos de su interés. |
| **Flujo principal (básico)** | 1. Cliente accede al storefront desde el dominio de la tienda. 2. El sistema identifica la tienda vía `StoreDomain → Store`. 3. Sistema muestra el catálogo paginado con `productoTienda.visible=true`. 4. El cliente aplica filtros opcionales (categoría, marca, rango de precio). 5. El sistema recalcula y muestra los resultados filtrados. 6. El cliente selecciona un producto y navega al detalle. |
| **Flujo alternativo / Excepciones** | **A1 — Sin resultados:** si no hay coincidencias con los filtros, se muestra mensaje "Sin resultados para tu búsqueda". **A2 — Tienda inactiva:** si `tienda.activa=false` o `mantenimiento=true`, se muestra página "Volveremos pronto". **A3 — Dominio no resuelto:** si el dominio no existe en `StoreDomain`, error 404. |
| **Reglas de negocio** | RB-CAT-01: Solo se muestran productos con `productoTienda.visible=true` y `tienda.activa=true`. RB-CAT-02: El precio mostrado es `productoTienda.precioPublico`, no `producto.precioBase`. RB-CAT-03: Los productos personalizados muestran el estado completo, "Desde producción y diseño habilitados". |
| **Requerimientos relacionados** | RF-CAT (1-18) |
| **Prioridad** | Alta |
