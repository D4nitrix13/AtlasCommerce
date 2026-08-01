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