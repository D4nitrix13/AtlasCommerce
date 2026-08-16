/**
 * Types del dominio Auth.
 *
 * Reflejan la shape de `UserResource` y la respuesta de `/auth/login`
 * en Laravel.
 */

export interface UserRole {
  id: number;
  slug: string;
  name: string;
}

export interface User {
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
}

export interface LoginResponse {
  token: string;
  user: User;
}

/**
 * Miembro success del discriminated union `LoginResult`.
 *
 * `LoginResponse` se mantiene como el shape RAW que viene del backend
 * (`{ token, user }`); este wrapper agrega el discriminador `kind`
 * para que el caller pueda narrowing sin ambigu\u00fcedad.
 */
export interface LoginSuccess {
  kind: 'success';
  token: string;
  user: User;
}

/**
 * Backend challenge para 2FA (POST /auth/login → HTTP 200 sin token).
 *
 * Solo soporta TOTP en el MVP. El challenge_token es el id numérico
 * de `OtpChallenge` que el caller (TwoFactorChallengeForm) re-envía
 * a /auth/2fa/verify-totp-login junto con el código de 6 dígitos.
 */
export interface TwoFactorChallenge {
  kind: '2fa_required';
  challengeId: number;
  method: 'totp';
  expiresAt: string;
}

/**
 * Discriminated union que `auth.login()` puede devolver.
 *
 *   - kind: 'success'         → LoginSuccess (token + user)
 *   - kind: '2fa_required'    → TwoFactorChallenge
 *
 * El caller DEBE hacer narrowing con `if (result.kind === 'success')`.
 */
export type LoginResult = LoginSuccess | TwoFactorChallenge;

export interface LoginPayload {
  email: string;
  password: string;
  device_name?: string;
  /**
   * Checkbox "Recuérdame" del LoginForm. Backend usa esto para setear
   * `remember_token` en `personal_access_tokens` (Sanctum).
   */
  remember?: boolean;
}

/**
 * Payload para POST /auth/2fa/verify-totp-login.
 *
 * El backend recibe `challenge_token` (snake) y `code` (string de 6
 * dígitos). Mapeamos en el caller para no contaminar el código de UI.
 */
export interface TwoFactorVerifyPayload {
  challengeId: number;
  code: string;
}

export type ValidationErrors = Record<string, string[]>;
