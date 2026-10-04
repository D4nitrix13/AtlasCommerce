/**
 * Mock data para gestión de usuarios internos (RF-AUTH-009).
 *
 * `adminUsers[]` — 12 admins/operadores del tenant activo con:
 *  - id, first/last_name, email, full_name
 *  - role (id + slug + name) — referencia directa al role mock para
 *    que la tabla pueda mostrar el nombre sin join extra
 *  - status (active | suspended) — badge visual directo
 *  - last_login_at — relativo para la columna "Último acceso"
 *  - avatar_color — clave CSS-safe (slate/sky/amber/...) usada por el
 *    avatar inicial en la tabla
 *
 * `roleOptions[]` — subset de roles disponibles para el filtro y el
 * select de creación. Es un subset (no la lista completa) porque la
 * página de usuarios NO debe poder asignar roles administrativos
 * sensibles (ej: `admin`) sin una pantalla de elevación de permisos.
 *
 * Cuando el backend exponga `/api/v1/admin/users`, los componentes
 * hacen `apiFetch` con fallback a este mock (mismo approach que
 * `CustomersAdmin.tsx`).
 *
 * **AGENTS §11** (UI states): este archivo provee data no-vacía para
 * que el componente renderice success; el componente igual maneja
 * empty/error/loading.
 */

export type UserStatus = 'active' | 'suspended';

export interface AdminUser {
  readonly id: number;
  readonly first_name: string;
  readonly last_name: string;
  readonly full_name: string;
  readonly email: string;
  readonly avatar_color: string;
  readonly role_id: number;
  readonly role_name: string;
  readonly role_slug: string;
  readonly status: UserStatus;
  readonly last_login_at: string | null;
  readonly created_at: string;
}

export interface RoleOption {
  readonly id: number;
  readonly name: string;
  readonly slug: string;
}

export const adminUsers: readonly AdminUser[] = [
  {
    id: 1,
    first_name: 'María',
    last_name: 'García',
    full_name: 'María García',
    email: 'maria.garcia@panda-estampados.com',
    avatar_color: 'sky',
    role_id: 1,
    role_name: 'Administrador',
    role_slug: 'admin',
    status: 'active',
    last_login_at: '2026-10-03T08:14:00Z',
    created_at: '2024-03-12T10:00:00Z',
  },
  {
    id: 2,
    first_name: 'Carlos',
    last_name: 'López',
    full_name: 'Carlos López',
    email: 'carlos.lopez@panda-estampados.com',
    avatar_color: 'amber',
    role_id: 2,
    role_name: 'Gerente de tienda',
    role_slug: 'store_manager',
    status: 'active',
    last_login_at: '2026-10-02T16:42:00Z',
    created_at: '2024-05-04T09:30:00Z',
  },
  {
    id: 3,
    first_name: 'Ana',
    last_name: 'Martínez',
    full_name: 'Ana Martínez',
    email: 'ana.martinez@panda-estampados.com',
    avatar_color: 'emerald',
    role_id: 3,
    role_name: 'Operador de ventas',
    role_slug: 'sales_operator',
    status: 'active',
    last_login_at: '2026-10-03T07:55:00Z',
    created_at: '2024-07-22T14:00:00Z',
  },
  {
    id: 4,
    first_name: 'Roberto',
    last_name: 'Sánchez',
    full_name: 'Roberto Sánchez',
    email: 'roberto.sanchez@panda-estampados.com',
    avatar_color: 'rose',
    role_id: 4,
    role_name: 'Operador de inventario',
    role_slug: 'inventory_operator',
    status: 'active',
    last_login_at: '2026-10-01T11:20:00Z',
    created_at: '2024-09-15T08:45:00Z',
  },
  {
    id: 5,
    first_name: 'Laura',
    last_name: 'Díaz',
    full_name: 'Laura Díaz',
    email: 'laura.diaz@panda-estampados.com',
    avatar_color: 'violet',
    role_id: 3,
    role_name: 'Operador de ventas',
    role_slug: 'sales_operator',
    status: 'suspended',
    last_login_at: '2026-08-19T09:10:00Z',
    created_at: '2025-01-08T12:00:00Z',
  },
  {
    id: 6,
    first_name: 'Pedro',
    last_name: 'Ramírez',
    full_name: 'Pedro Ramírez',
    email: 'pedro.ramirez@panda-estampados.com',
    avatar_color: 'sky',
    role_id: 5,
    role_name: 'Soporte al cliente',
    role_slug: 'support_agent',
    status: 'active',
    last_login_at: '2026-10-02T13:05:00Z',
    created_at: '2025-02-20T11:30:00Z',
  },
  {
    id: 7,
    first_name: 'Sofía',
    last_name: 'Hernández',
    full_name: 'Sofía Hernández',
    email: 'sofia.hernandez@panda-estampados.com',
    avatar_color: 'emerald',
    role_id: 6,
    role_name: 'Marketing',
    role_slug: 'marketing_editor',
    status: 'active',
    last_login_at: '2026-09-30T17:48:00Z',
    created_at: '2025-04-11T09:00:00Z',
  },
  {
    id: 8,
    first_name: 'Diego',
    last_name: 'Morales',
    full_name: 'Diego Morales',
    email: 'diego.morales@panda-estampados.com',
    avatar_color: 'amber',
    role_id: 2,
    role_name: 'Gerente de tienda',
    role_slug: 'store_manager',
    status: 'active',
    last_login_at: '2026-10-02T20:12:00Z',
    created_at: '2025-06-30T15:20:00Z',
  },
  {
    id: 9,
    first_name: 'Valeria',
    last_name: 'Castillo',
    full_name: 'Valeria Castillo',
    email: 'valeria.castillo@panda-estampados.com',
    avatar_color: 'rose',
    role_id: 3,
    role_name: 'Operador de ventas',
    role_slug: 'sales_operator',
    status: 'active',
    last_login_at: '2026-10-03T06:33:00Z',
    created_at: '2025-08-14T10:45:00Z',
  },
  {
    id: 10,
    first_name: 'Andrés',
    last_name: 'Vega',
    full_name: 'Andrés Vega',
    email: 'andres.vega@panda-estampados.com',
    avatar_color: 'violet',
    role_id: 4,
    role_name: 'Operador de inventario',
    role_slug: 'inventory_operator',
    status: 'active',
    last_login_at: null,
    created_at: '2025-09-02T14:00:00Z',
  },
  {
    id: 11,
    first_name: 'Camila',
    last_name: 'Reyes',
    full_name: 'Camila Reyes',
    email: 'camila.reyes@panda-estampados.com',
    avatar_color: 'sky',
    role_id: 5,
    role_name: 'Soporte al cliente',
    role_slug: 'support_agent',
    status: 'suspended',
    last_login_at: '2026-07-04T10:25:00Z',
    created_at: '2025-11-18T08:30:00Z',
  },
  {
    id: 12,
    first_name: 'Javier',
    last_name: 'Mendoza',
    full_name: 'Javier Mendoza',
    email: 'javier.mendoza@panda-estampados.com',
    avatar_color: 'emerald',
    role_id: 6,
    role_name: 'Marketing',
    role_slug: 'marketing_editor',
    status: 'active',
    last_login_at: '2026-10-02T19:00:00Z',
    created_at: '2026-01-25T11:15:00Z',
  },
] as const;

/**
 * Roles asignables desde la página de usuarios.
 *
 * Excluye `admin` (slug) intencionalmente — sólo un owner puede
 * promover a otro usuario a admin, lo cual vive en una pantalla
 * separada (no en esta página). La fuente de verdad para los roles
 * disponibles sigue siendo `/api/v1/admin/roles`.
 */
export const roleOptions: readonly RoleOption[] = [
  { id: 2, name: 'Gerente de tienda', slug: 'store_manager' },
  { id: 3, name: 'Operador de ventas', slug: 'sales_operator' },
  { id: 4, name: 'Operador de inventario', slug: 'inventory_operator' },
  { id: 5, name: 'Soporte al cliente', slug: 'support_agent' },
  { id: 6, name: 'Marketing', slug: 'marketing_editor' },
] as const;
