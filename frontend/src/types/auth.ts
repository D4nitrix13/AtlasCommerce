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

/**
 * Métodos de recuperación disponibles para un usuario.
 *
 * El backend (`POST /auth/forgot-password`) devuelve esta lista para
 * que el frontend muestre solo los métodos que el user realmente puede
 * usar:
 *
 *  - `'email'` — siempre disponible si la cuenta existe; el backend
 *    envía un OTP al email.
 *  - `'recovery_codes'` — disponible si el user tiene al menos un
 *    recovery code sin usar.
 *  - `'totp'` — disponible si el user tiene 2FA habilitada.
 */
export type AvailableMethod = 'email' | 'recovery_codes' | 'totp';

/**
 * Respuesta de `POST /auth/forgot-password` cuando se llama desde el
 * flow wizard. El backend declara qué métodos tiene disponibles el user
 * para que el wizard muestre solo los que aplican.
 */
export interface ForgotPasswordResponse {
  message: string;
  expires_minutes: number;
  /**
   * Métodos que el user puede usar para verificar su identidad. Si la
   * cuenta no existe (respuesta genérica por seguridad), el array está
   * vacío. El wizard debe mostrar todos los métodos disponibles como
   * opciones; el user elige cuál usar.
   */
  available_methods: AvailableMethod[];
  /**
   * Solo presente en dev (`APP_ENV !== 'production'`). El código OTP
   * para que el dev pueda probar el flow sin SMTP.
   */
  code?: string;
  dev?: boolean;
}

/**
 * Respuesta de `POST /auth/recover-via-recovery-code` y
 * `POST /auth/recover-via-totp`. El `challenge_token` se envía en el
 * siguiente paso a `/auth/reset-password` para completar el cambio de
 * password sin volver a pedir el segundo factor.
 */
export interface RecoveryCodeChallengeResponse {
  challenge_token: number;
  expires_at: string;
  expires_minutes: number;
}

/**
 * Payload para `POST /auth/reset-password`.
 *
 * Dos modos:
 *  - `code` (default): código OTP/recovery code directo.
 *  - `challenge_token`: token emitido por recover-via-recovery-code /
 *    recover-via-totp. NO debe acompañarse de `code`.
 */
export interface ResetPasswordRequest {
  email: string;
  method?: 'email' | 'recovery_codes';
  code?: string;
  challenge_token?: number;
  password: string;
  password_confirmation: string;
  device_name?: string;
}

/**
 * Pasos del wizard `ForgotPasswordWizard`.
 *
 *  - `'email'`   — ingreso de email + ver métodos disponibles.
 *  - `'method'`  — selección del método (cuando hay >1 disponible).
 *  - `'code'`    — ingreso del código (recovery o TOTP) según método.
 *  - `'password'`— ingreso del nuevo password ×2 + submit.
 */
export type ForgotPasswordWizardStep = 'email' | 'method' | 'code' | 'password';
