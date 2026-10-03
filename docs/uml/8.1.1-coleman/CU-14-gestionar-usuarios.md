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
