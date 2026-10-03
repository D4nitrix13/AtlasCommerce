# 8.1.1 — Plantillas de Coleman (AtlasCommerce)

**Las 18 plantillas de Coleman, una por cada caso de uso del sistema.**

**Trazabilidad:** cada plantilla corresponde a una historia de usuario (HU-01 a HU-18) y a los requerimientos funcionales asociados.

---

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

---

### CU-02 — Registrarse en el sistema  (HU-02)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-02 |
| **Actores involved** | Cliente externo / Sistema de email |
| **Precondiciones** | El cliente no tiene sesión activa. El correo electrónico no está registrado previamente en `usuarios.correo`. |
| **Postcondiciones** | Se crea un registro `Usuario` con `correoVerificadoEn` poblado. Si activó 2FA, existe una `CredencialSegundoFactor` asociada. |
| **Descripción / Propósito** | Permite a un cliente crear una cuenta en el sistema para poder comprar, hacer seguimiento de pedidos y acceder a funcionalidades personalizadas. |
| **Flujo principal (básico)** | 1. Cliente accede al formulario de registro desde el header del sitio. 2. Completa correo y contraseña con nombre en el formulario. 3. Sistema valida formato, uniqueness de correo, estado de la contraseña (mín. 8 caracteres, mayúsculas, números). 4. Confirma y almacena la contraseña (hash). 5. El sistema confirma el alta del correo y la activación de la cuenta. |
| **Flujo alternativo / Excepciones** | **A1 — Correo ya registrado:** el sistema muestra error "El correo ya está registrado". **A2 — Token expirado:** si el enlace de verificación vence (`[RECOMENDADO]` 24h), permite reenviar. **A3 — Activar 2FA:** opcionalmente, post-registro el cliente activa 2FA vía email OTP o TOTP. |
| **Reglas de negocio** | RB-AUTH-01: La contraseña debe cumplir política mínima (mín. 8 caracteres, mayúscula, número). **RB-AUTH-02:** Correo debe ser único en el sistema. **[RECOMENDADO] RB-AUTH-03:** Token de correo expira en ≥ 24h. |
| **Requerimientos relacionados** | RF-AUTH (1-16) |
| **Prioridad** | Alta |

---

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

---

### CU-04 — Realizar checkout paso a paso  (HU-04)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-04 |
| **Actores involved** | Cliente externo |
| **Precondiciones** | El cliente tiene un `Carrito` con al menos 1 producto y con estado `ACTIVO`. El cliente está autenticado o se le autentica durante el flujo. |
| **Postcondiciones** | Se crea un `Pedido` en estado `PENDIENTE` con sus `LineaPedido`, `direccionEnvio` y `metodoPago` seleccionados. El `Carrito` cambia a estado `CONVERTIDO`. |
| **Descripción / Propósito** | Convierte un carrito activo en un pedido confirmado, recopilando los datos requeridos por el cliente para el envío y el pago. |
| **Flujo principal (básico)** | 1. Cliente confirma el checkout del carrito. 2. El sistema valida el stock disponible de cada ítem. 3. El sistema muestra el paso 1: dirección de envío (cliente selecciona o crea una). 4. El cliente selecciona la opción de envío y completa el resumen. 5. El sistema valida el stock de cada producto. 6. El sistema crea un `Pedido` en estado `PENDIENTE_PAGO` con sus `LineaPedido`, `direccionEnvio`, `metodoPago`. 7. El `Carrito` cambia a estado `CONVERTIDO`. 8. El cliente confirma el pedido y es redirigido al flujo de pago. |
| **Flujo alternativo / Excepciones** | **A1 — Stock insuficiente:** el sistema notifica al cliente de los productos sin stock y permite ajustar. **A2 — Sin dirección:** si el cliente no tiene direcciones registradas, debe crear una. **A3 — Aplicar cupón:** opcionalmente, cliente ingresa un cupón válido para descuento. |
| **Reglas de negocio** | RB-SALES-01: El stock se valida al momento del checkout, no se descuenta hasta que el pago se confirme. RB-SALES-02: La dirección de envío debe pertenecer al cliente autenticado. **[RECOMENDADO] RB-SALES-03:** El pedido conserva el ID de la tienda del carrito original. |
| **Requerimientos relacionados** | RF-SALES (1-17), RF-SHIP (1-8) |
| **Prioridad** | Alta |

---

### CU-05 — Pagar pedido en línea  (HU-05)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-05 |
| **Actores involved** | Cliente externo / Pasarela de pago externa |
| **Precondiciones** | Existe un `Pedido` en estado `PENDIENTE`. El cliente tiene un método de pago válido configurado en la tienda. |
| **Postcondiciones** | Se crea un `Pago` con estado `COMPLETADO` (o `FALLIDO` según el caso). El `Pedido` cambia a estado `PROCESANDO`. El inventario se descuenta y se registra el movimiento. |
| **Descripción / Propósito** | Procesa el pago del pedido a través de la pasarela configurada, completando el ciclo de compra. |
| **Flujo principal (básico)** | 1. Cliente confirma el pago del pedido. 2. El sistema valida el método de pago seleccionado. 3. El sistema envía la solicitud a la pasarela de pago configurada. 4. La pasarela procesa el pago y devuelve el resultado. 5. Caso resultado positivo: el sistema actualiza el `Pago` a `COMPLETADO`. 6. El sistema cambia el `Pedido` a `PROCESANDO`. 7. El sistema descuenta el stock y registra el movimiento de inventario. 8. El sistema envía la confirmación al cliente vía email. |
| **Flujo alternativo / Excepciones** | **A1 — Pasarela no responde:** si la pasarela no responde en `[RECOMENDADO]` ≥ 30s, el sistema cancela el flujo y permite reintentar. **A2 — Pago rechazado:** si la pasarela rechaza el pago, el sistema notifica al cliente y permite cambiar de método o reintentar. **A3 — Pago duplicado:** si se detecta el mismo `referenciaPasarela` dentro del flujo, se considera el primer resultado como válido. |
| **Reglas de negocio** | RB-PAY-01: El webhook de la pasarela es la fuente de verdad del estado del pago. RB-PAY-02: El estado `PROCESANDO` se confirma solo tras recibir el webhook positivo. RB-PAY-03: Solo se descuenta el stock tras la confirmación del pago, no en el checkout. |
| **Requerimientos relacionados** | RF-PAY (1-10) |
| **Prioridad** | Alta |

---

### CU-06 — Consultar estado de pedido  (HU-06)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-06 |
| **Actores involved** | Cliente externo |
| **Precondiciones** | El cliente está autenticado. Tiene al menos un pedido registrado en el sistema. |
| **Postcondiciones** | El cliente visualiza el estado actual del pedido. No se modifica el estado del pedido. |
| **Descripción / Propósito** | Permite al cliente visualizar el estado de sus pedidos y descargar la factura en PDF. |
| **Flujo principal (básico)** | 1. Cliente accede al módulo de pedidos del cliente. 2. El sistema muestra el listado de pedidos del cliente con estado actual. 3. El cliente selecciona un pedido. 4. El sistema muestra el detalle del pedido (líneas, pago, envío, factura). 5. El cliente consulta el estado del envío y la guía si existe. |
| **Flujo alternativo / Excepciones** | **A1 — Descargar factura:** si existe `Factura` para el pedido, el cliente puede descargar el PDF. **A2 — Sin pedidos:** si el cliente no tiene pedidos, se muestra mensaje "Aún no tienes pedidos". |
| **Reglas de negocio** | RB-SALES-04: Solo el cliente dueño del pedido puede ver su detalle. RB-SALES-05: La factura se genera tras la confirmación del pago. |
| **Requerimientos relacionados** | RF-SALES (1-17) |
| **Prioridad** | Media |

---

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

---

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

---

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

---

### CU-10 — Gestionar pedido  (HU-10)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-10 |
| **Actores involved** | Operador de tienda |
| **Precondiciones** | El operador está autenticado y tiene permisos sobre la tienda del pedido. Existe un pedido registrado con pago confirmado. |
| **Postcondiciones** | El `Pedido` cambia al estado siguiente según la acción. Se actualiza el inventario y se registra el envío con guía. El cliente recibe notificación. |
| **Descripción / Propósito** | Permite al operador gestionar el ciclo de vida de un pedido: confirmar pago, preparar, enviar y registrar la guía de envío. |
| **Flujo principal (básico)** | 1. Operador accede al listado de pedidos pendientes. 2. Operador selecciona un pedido. 3. El sistema valida el estado del pago del pedido. 4. Operador confirma el pago y prepara el envío. 5. Operador marca como enviado e ingresa el número de guía. 6. El sistema descuenta el stock y registra el movimiento. 7. El sistema notifica al cliente el envío del despacho. |
| **Flujo alternativo / Excepciones** | **A1 — Pago no recibido:** si el pago no está aprobado, el operador no puede enviar. **A2 — Cancelar pedido:** el operador puede cancelar el pedido antes del envío, registrando motivo de cambio y motivo. **A3 — Productos personalizados:** si el pedido contiene productos personalizados, el flujo incluye "En producción" con tiempo estimado. |
| **Reglas de negocio** | RB-SALES-06: El stock se descuenta al marcar como enviado. RB-SALES-07: La cancelación es posible solo antes del envío. RB-SALES-08: El operador registra la guía de envío obligatoria para registrar el envío. |
| **Requerimientos relacionados** | RF-SALES (1-17), RF-SHIP (1-8) |
| **Prioridad** | Alta |

---

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

---

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

---

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

---

### CU-14 — Gestionar usuarios  (HU-14)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-14 |
| **Actores involved** | Administrador |
| **Precondiciones** | El administrador está autenticado con permisos de gestión de usuarios. Existen tiendas y roles registrados. |
| **Postcondiciones** | Se crea/actualiza/elimina un `Usuario` y su asignación `UsuarioTienda` con `Rol` específico. |
| **Descripción / Propósito** | Permite al administrador gestionar los usuarios del sistema, asignarles roles y las tiendas que pueden administrar. |
| **Flujo principal (básico)** | 1. Administrador accede al módulo de gestión de usuarios. 2. Administrador selecciona "Nuevo usuario" o edita uno existente. 3. Administrador completa correo, contraseña, cargo, rol. 4. Administrador selecciona la tienda y el rol del usuario en esa tienda. 5. Administrador guarda el usuario. 6. El sistema crea el `Usuario` y la asignación `UsuarioTienda` con su `Rol`. 7. El sistema envía el correo de activación al usuario. |
| **Flujo alternativo / Excepciones** | **A1 — Múltiples tiendas:** el administrador puede asignar al usuario a múltiples tiendas con roles distintos en cada una. **A2 — Desactivar usuario:** el administrador puede desactivar al usuario sin eliminarlo (mantiene la auditoría). |
| **Reglas de negocio** | RB-AUTH-04: Un usuario puede tener dos distintos roles en dos distintas tiendas. RB-AUTH-05: El rol define los permisos del usuario en esa tienda. RB-AUTH-06: Eliminar un usuario no borra su auditoría. |
| **Requerimientos relacionados** | RF-AUTH (1-16) |
| **Prioridad** | Alta |

---

### CU-15 — Configurar tienda  (HU-15)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-15 |
| **Actores involved** | Administrador |
| **Precondiciones** | El administrador está autenticado con permisos de configuración. La tienda existe y está activa. |
| **Postcondiciones** | La `Tienda` queda con los nuevos parámetros de tema, métodos de pago, envío, impuestos y dominio. |
| **Descripción / Propósito** | Permite al administrador configurar por tienda el tema visual, métodos de pago habilitados, empresas de envío, impuestos y dominios personalizados. |
| **Flujo principal (básico)** | 1. Administrador accede al módulo de tiendas. 2. Administrador selecciona una tienda. 3. Administrador edita los parámetros: tema visual (colores, logo), métodos de pago habilitados, zonas de envío, impuestos por defecto. 4. Administrador guarda la configuración. 5. El sistema valida la configuración y la aplica. 6. El sistema notifica al operador de la tienda de los cambios. |
| **Flujo alternativo / Excepciones** | **A1 — Configurar dominio:** opcionalmente, el administrador agrega dominios personalizados a la tienda. **A2 — Modo mantenimiento:** el administrador puede activar el modo mantenimiento de la tienda (oculta el storefront). |
| **Reglas de negocio** | RB-STOR-01: Los cambios en tema visual se reflejan en el storefront sin deploy. RB-STOR-02: El modo mantenimiento oculta el storefront pero no afecta el panel administrativo. **[RECOMENDADO] RB-STOR-03:** Los cambios en métodos de pago requieren confirmación por seguridad. |
| **Requerimientos relacionados** | RF-STOR (1-9) |
| **Prioridad** | Alta |

---

### CU-16 — Configurar pasarela de pago  (HU-16)

| Campo | Contenido |
|---|---|
| **ID / Código** | CU-16 |
| **Actores involved** | Administrador / Pasarela de pago externa |
| **Precondiciones** | El administrador está autenticado con permisos de configuración. La tienda existe. |
| **Postcondiciones** | La `PasarelaGenerica` queda configurada con credenciales cifradas, modo (PRUEBA/PRODUCCION) y monedas soportadas. La pasarela responde a las solicitudes de prueba. |
| **Descripción / Propósito** | Permite al administrador configurar la pasarela de pago por tienda: credenciales, monedas, webhook y modo (prueba o producción). |
| **Flujo principal (básico)** | 1. Administrador accede al módulo de configuración de pasarela. 2. Administrador selecciona la tienda y la pasarela. 3. Administrador ingresa las credenciales (API key, secret). 4. Administrador selecciona el modo (PRUEBA o PRODUCCION) y las monedas. 5. Administrador guarda la configuración. 6. El sistema cifra las credenciales y las almacena. 7. El sistema prueba la conexión con la pasarela usando las credenciales. 8. Si la prueba es exitosa, el sistema confirma la configuración. |
| **Flujo alternativo / Excepciones** | **A1 — Credenciales inválidas:** si las credenciales no son válidas, el sistema muestra error y no guarda la configuración. **A2 — Pasarela no responde:** si la pasarela no responde en `[RECOMENDADO]` ≥ 10s, el sistema cancela la prueba. |
| **Reglas de negocio** | RB-PAY-04: Las credenciales se almacenan cifradas. RB-PAY-05: El cambio de PRUEBA a PRODUCCION requiere confirmación por seguridad. RB-PAY-06: La pasarela solo se activa tras una prueba exitosa. |
| **Requerimientos relacionados** | RF-PAY (1-10) |
| **Prioridad** | Alta |

---

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

---

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

---

