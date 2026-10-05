/**
 * Tipos compartidos para el dashboard de cuenta.
 *
 * F4.2 — Cleanup post F1.1 + F2.2: las constantes `CURRENT_USER` y
 * `RECENT_ORDERS_MOCK` se ELIMINARON (ya no se usan — el dashboard
 * ahora consume `/api/v1/auth/me` y `/api/v1/orders` via el helper
 * `lib/server/account.ts`). El type `RecentOrderMock` se mantiene
 * porque el helper lo expone como return type.
 *
 * El resto del archivo (sesiones, recovery codes, notif prefs,
 * deletion, etc.) sigue mockeado hasta que las pages correspondientes
 * (/account/security, /account/notifications, /account/gdpr) tengan
 * endpoints reales. Ver `odd/tasks/dashboards-redesign-and-auth.md` F4.2
 * para el scope del cleanup.
 *
 * Convenciones que vienen del backend:
 *   - `total` se expresa en CENTAVOS (entero). Lo que ve el usuario
 *     se formatea con `formatCentsAsDecimal` de `lib/formatters.ts`.
 *   - `status` matchea exactamente las variants del Badge primitive
 *     (`pending | processing | shipped | delivered | cancelled`).
 *
 * **SOLO para vista**. NO tomar decisiones de negocio con esto.
 */

export type OrderStatus = 'pending' | 'processing' | 'shipped' | 'delivered' | 'cancelled';

export interface RecentOrderMock {
  id: string;
  date: string; // ISO 8601
  status: OrderStatus;
  total: number; // cents
  items: number;
}

// ──────────────────────────────────────────────────────────────────────────────
// Slice 2 — Páginas account/security, /notifications, /gdpr y /2fa.
//
// Los mocks siguientes reemplazan los endpoints reales hasta que
// `/api/account/security/*`, `/api/account/notifications`,
// `/api/account/data-export` y `/v1/auth/2fa/*` estén listos. Misma
// convención que el resto del archivo: SOLO para vista.
// ──────────────────────────────────────────────────────────────────────────────

/**
 * Sesión activa del cliente en un dispositivo concreto.
 *
 * `isCurrent` marca la sesión del navegador que está renderizando la
 * página (no se puede revocar a sí misma desde la UI).
 */
export interface ActiveSessionMock {
  id: string;
  device: string;
  browser: string;
  os: string;
  location: string;
  ip: string;
  lastActiveAt: string; // ISO 8601
  isCurrent: boolean;
}

export const ACTIVE_SESSIONS_MOCK: ActiveSessionMock[] = [
  {
    id: 'sess_current',
    device: 'MacBook Pro 14"',
    browser: 'Chrome 142',
    os: 'macOS 15.1',
    location: 'Managua, NI',
    ip: '186.1.45.78',
    lastActiveAt: '2026-10-03T14:22:00Z',
    isCurrent: true,
  },
  {
    id: 'sess_iphone',
    device: 'iPhone 15',
    browser: 'Safari 18',
    os: 'iOS 18.2',
    location: 'Managua, NI',
    ip: '186.1.45.78',
    lastActiveAt: '2026-10-03T09:11:00Z',
    isCurrent: false,
  },
  {
    id: 'sess_work',
    device: 'PC Escritorio',
    browser: 'Firefox 132',
    os: 'Windows 11',
    location: 'San José, CR',
    ip: '190.113.27.5',
    lastActiveAt: '2026-09-29T16:48:00Z',
    isCurrent: false,
  },
];

export function getActiveSessions(): ActiveSessionMock[] {
  return ACTIVE_SESSIONS_MOCK;
}

/**
 * Códigos de recovery del cliente.
 *
 * En producción el backend nunca debe devolver los códigos en claro al
 * frontend post-setup (solo el count + remaining). Para esta vista mock,
 * exponemos un set estático. Cuando exista el endpoint real, el cliente
 * verá solo el contador + botón regenerate.
 */
export interface RecoveryCodesMock {
  remaining: number;
  total: number;
  // Solo presente en el setup inicial o post-regenerate (temporalmente).
  plainCodes: string[] | null;
}

export const RECOVERY_CODES_MOCK: RecoveryCodesMock = {
  remaining: 8,
  total: 10,
  plainCodes: null,
};

export function getRecoveryCodes(): RecoveryCodesMock {
  return RECOVERY_CODES_MOCK;
}

/**
 * Preferencias de notificación del cliente.
 *
 * `security` siempre es `true` y NO se puede desactivar (lo decide el
 * backend por compliance). El frontend lo refleja visualmente como
 * toggle bloqueado.
 */
export type NotificationChannel = 'email' | 'sms' | 'push';
export type NotificationCategory = 'orders' | 'promotions' | 'newsletter' | 'security';

export interface NotificationPrefsMock {
  matrix: Record<NotificationCategory, Record<NotificationChannel, boolean>>;
  updatedAt: string;
}

export const NOTIFICATION_PREFS_MOCK: NotificationPrefsMock = {
  matrix: {
    orders: { email: true, sms: true, push: true },
    promotions: { email: false, sms: false, push: false },
    newsletter: { email: true, sms: false, push: false },
    security: { email: true, sms: true, push: true },
  },
  updatedAt: '2026-09-12T10:30:00Z',
};

export function getNotificationPrefs(): NotificationPrefsMock {
  return NOTIFICATION_PREFS_MOCK;
}

/**
 * Estado 2FA del cliente.
 *
 * `method` discrimina qué campos son relevantes. `recoveryCodesRemaining`
 * siempre se muestra, incluso con 2FA desactivado (el cliente puede haberlo
 * tenido activo antes).
 */
export type TwoFactorMethod = 'totp' | 'email' | 'none';

export interface TwoFactorStatusMock {
  enabled: boolean;
  method: TwoFactorMethod;
  enabledAt: string | null;
  lastUsedAt: string | null;
  recoveryCodesRemaining: number;
  recoveryCodesTotal: number;
}

export const TWO_FACTOR_STATUS_MOCK: TwoFactorStatusMock = {
  enabled: true,
  method: 'totp',
  enabledAt: '2026-08-15T12:00:00Z',
  lastUsedAt: '2026-10-02T18:42:00Z',
  recoveryCodesRemaining: 8,
  recoveryCodesTotal: 10,
};

export function getTwoFactorStatus(): TwoFactorStatusMock {
  return TWO_FACTOR_STATUS_MOCK;
}

/**
 * Lista de motivos por los que un cliente puede querer borrar su cuenta.
 * Las razones son strings simples; el selector las enumera en el form.
 */
export const ACCOUNT_DELETION_REASONS: readonly string[] = [
  'No lo uso',
  'Creé otra cuenta',
  'Problemas de privacidad',
  'Experiencia mala',
  'Otro',
] as const;

/**
 * Última fecha de export de datos (mock). `null` = nunca exportó.
 * El backend real debería trackear esto en `customer.last_export_at`.
 */
export const LAST_DATA_EXPORT_MOCK: string | null = '2026-07-22T16:05:00Z';

/**
 * Solicitud de eliminación pendiente (mock).
 *
 * Cuando `requestId` no es `null`, el cliente tiene un grace period de
 * 30 días para cancelar la eliminación. El endpoint real debería leer
 * esto de `customer.deletion_token_expires_at` en BD.
 *
 * En mock por defecto NO hay solicitud pendiente (cambiar a un objeto
 * poblado para probar el flujo "cancel deletion" en la UI).
 */
export interface DeletionRequestMock {
  requestId: string;
  requestedAt: string; // ISO 8601
  scheduledFor: string; // ISO 8601 (requestedAt + 30 días)
}

export const PENDING_DELETION_MOCK: DeletionRequestMock | null = null;

/**
 * Devuelve la solicitud de eliminación pendiente o `null` si no hay.
 * En el futuro esto se reemplaza por `GET /api/account/deletion-status`.
 */
export function getPendingDeletion(): DeletionRequestMock | null {
  return PENDING_DELETION_MOCK;
}
