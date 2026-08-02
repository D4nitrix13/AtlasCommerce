/**
 * Types del dominio Auth.
 *
 * Reflejan la shape de `UserResource` y la respuesta de `/auth/login`
 * en Laravel.
 */

export type UserRole = {
  id: number;
  slug: string;
  name: string;
};

export type User = {
  id: number;
  email: string;
  full_name: string;
  role: UserRole | null;
  role_id: number | null;
  /**
   * IDs de tiendas a las que el usuario tiene acceso via `user_stores`.
   * Un admin multi-tienda tendrá varios IDs. Un admin de una sola tienda
   * tendrá un array de un elemento.
   */
  store_ids: number[];
  is_active: boolean;
  /**
   * Slugs efectivos. Vacío cuando `role.slug === 'admin'` (bypass).
   */
  permissions: string[];
};

export type LoginResponse = {
  token: string;
  user: User;
};

export type LoginPayload = {
  email: string;
  password: string;
  device_name?: string;
};

export type ValidationErrors = Record<string, string[]>;