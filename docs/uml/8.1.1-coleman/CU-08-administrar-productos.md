### CU-08 — Administrar productos (CRUD)  (HU-08)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-08 |
| **Actores involved** | Operador de tienda |
| **Precondiciones** | El operador está autenticado y tiene permisos sobre la tienda activa. |
| **Postcondiciones** | Se crea/actualiza/elimina un `Producto` (o `VarianteProducto`, `ImagenProducto`, `Atributo`) en el sistema. Se actualiza la publicación `ProductoTienda` correspondiente. |
| **Descripción / Propósito** | Permite al operador gestionar el catálogo de productos de la tienda: crear, editar, listar y eliminar productos con sus variantes, imágenes, precios y descripciones. |
| **Flujo principal (básico)** | 1. Operador accede al módulo de catálogo del panel administrativo. 2. Operador selecciona "Nuevo producto" o edita uno existente. 3. Operador completa el formulario (nombre, descripción, marca, categoría, precio, imágenes). 4. Operador agrega variantes (talla, color, etc.). 5. Operador configura la publicación en la tienda (precio público, descripción pública). 6. Operador guarda. 7. El sistema crea/actualiza el producto y sus entidades asociadas. |
| **Flujo alternativo / Excepciones** | **A1 — Producto con personalización:** si el producto es personalizable, el operador configura las opciones de personalización y las áreas de impresión. **A2 — Desactivar producto:** el operador puede desactivar el producto sin eliminarlo (queda oculto del catálogo público). |
| **Reglas de negocio** | RB-CAT-04: Solo el operador asignado a la tienda puede modificar productos en ella. RB-CAT-05: El SKU base es único en el sistema. RB-CAT-06: El `producto.precioBase` es obligatorio pero no se muestra al cliente; lo que se muestra es `productoTienda.precioPublico`. |
| **Requerimientos relacionados** | RF-CAT (1-18), RF-CUSTOM (1-21) |
| **Prioridad** | Alta |
