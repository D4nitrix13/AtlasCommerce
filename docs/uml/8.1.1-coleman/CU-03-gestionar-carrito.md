### CU-03 — Gestionar carrito de compras  (HU-03)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-03 |
| **Actores involved** | Cliente externo |
| **Precondiciones** | El cliente tiene una sesión iniciada o un carrito anónimo activo vinculado a la tienda actual. Existen productos visibles en el catálogo de la tienda. |
| **Postcondiciones** | El `Carrito` refleja las modificaciones del cliente. `ItemCarrito` con cantidades, productos y variantes actualizadas. Subtotal y total calculados. |
| **Descripción / Propósito** | Permite al cliente armar y mantener su selección de productos antes de iniciar el pago. |
| **Flujo principal (básico)** | 1. Cliente navega desde el catálogo y agrega un producto (con variante si corresponde) al carrito. 2. El sistema valida el carrito y crea un `ItemCarrito`. 3. El cliente modifica la cantidad del producto en el carrito. 4. El sistema recalcula el subtotal y total. 5. El cliente confirma la selección y procede al checkout. |
| **Flujo alternativo / Excepciones** | **A1 — Eliminar ítem:** cliente remueve el producto. **A3 — carrito vacío:** si el carrito queda sin cero o sin ítems, no se permite checkout. **[RECOMENDADO] A4 — carrito expira automáticamente** tras X días. |
| **Reglas de negocio** | RB-CART-01: Cada `ItemCarrito` pertenece a un único `Carrito` y a una única tienda. RB-CART-02: El precio unitario se congela al momento de agregar al carrito (`[RECOMENDADO]` válido por 15 días). RB-CART-03: No se permite mezclar dos distintos tipos de productos de dos distintas tiendas en un mismo carrito. |
| **Requerimientos relacionados** | RF-CART (1-9) |
| **Prioridad** | Alta |
