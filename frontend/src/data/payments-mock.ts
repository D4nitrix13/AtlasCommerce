/**
 * Mock data para el módulo de Payments admin.
 *
 * Tres dominios:
 *  - paymentMethods[] : métodos de pago configurados (5 items: tarjeta Visa/MC,
 *                       transferencia, OXXO cash, wallet)
 *  - installmentOptions[] : configuración de cuotas sin/con interés (5 items)
 *  - recentPayments[] : pagos recientes para el dashboard (8 items)
 *
 * Mismo patrón que `inventory-mock.ts` / `marketing-mock.ts`: tipos
 * exportados + arrays congelados con `as const`. Cuando el backend
 * exponga `GET /api/v1/admin/payments/*`, los componentes hacen
 * `Astro.fetch()` con fallback a este mock.
 *
 * PaymentMethodType agrupa las 5 mecánicas más comunes del e-commerce
 * regional (LATAM):
 *   card      → tarjeta de crédito/débito (Visa/MC/Amex)
 *   transfer  → transferencia bancaria / SPEI
 *   cash      → pago en efectivo (OXXO, Rapipago, etc.)
 *   wallet    → wallet digital (Mercado Pago, Yappy, etc.)
 *   crypto    → stablecoins (USDC, USDT)
 *
 * **AGENTS §11**: este archivo provee data no-vacía para que los
 * componentes puedan renderizar success; los componentes manejan
 * empty/error/loading en SSR.
 */

export type PaymentMethodType = 'card' | 'transfer' | 'cash' | 'wallet' | 'crypto';

export type PaymentStatus = 'authorized' | 'captured' | 'refunded' | 'failed';

export interface PaymentMethod {
  readonly id: string;
  readonly code: string;
  readonly name: string;
  readonly type: PaymentMethodType;
  readonly feePercent: number;
  readonly feeFixedCents: number;
  readonly processedCount: number;
  readonly processedAmountCents: number;
  readonly isActive: boolean;
}

export interface InstallmentOption {
  readonly id: string;
  readonly installments: number;
  readonly surchargePercent: number;
  readonly minAmountCents: number;
  readonly maxAmountCents: number | null;
  readonly isActive: boolean;
}

export interface RecentPayment {
  readonly id: string;
  readonly orderNumber: string;
  readonly methodCode: string;
  readonly methodName: string;
  readonly amountCents: number;
  readonly status: PaymentStatus;
  readonly createdAt: string;
}

export const paymentMethods: readonly PaymentMethod[] = [
  {
    id: 'pm-001',
    code: 'CARD_VISA',
    name: 'Visa / Mastercard',
    type: 'card',
    feePercent: 2.9,
    feeFixedCents: 30,
    processedCount: 1248,
    processedAmountCents: 38_400_00,
    isActive: true,
  },
  {
    id: 'pm-002',
    code: 'CARD_AMEX',
    name: 'American Express',
    type: 'card',
    feePercent: 3.5,
    feeFixedCents: 50,
    processedCount: 142,
    processedAmountCents: 4_200_00,
    isActive: true,
  },
  {
    id: 'pm-003',
    code: 'TRANSFER_SPEI',
    name: 'Transferencia SPEI',
    type: 'transfer',
    feePercent: 0,
    feeFixedCents: 0,
    processedCount: 312,
    processedAmountCents: 12_800_00,
    isActive: true,
  },
  {
    id: 'pm-004',
    code: 'CASH_OXXO',
    name: 'OXXO (efectivo)',
    type: 'cash',
    feePercent: 3.0,
    feeFixedCents: 25,
    processedCount: 86,
    processedAmountCents: 2_100_00,
    isActive: true,
  },
  {
    id: 'pm-005',
    code: 'WALLET_YAPPY',
    name: 'Yappy (wallet)',
    type: 'wallet',
    feePercent: 1.8,
    feeFixedCents: 15,
    processedCount: 28,
    processedAmountCents: 580_00,
    isActive: false,
  },
];

export const installmentOptions: readonly InstallmentOption[] = [
  {
    id: 'ins-001',
    installments: 3,
    surchargePercent: 0,
    minAmountCents: 50_00,
    maxAmountCents: null,
    isActive: true,
  },
  {
    id: 'ins-002',
    installments: 6,
    surchargePercent: 0,
    minAmountCents: 100_00,
    maxAmountCents: null,
    isActive: true,
  },
  {
    id: 'ins-003',
    installments: 12,
    surchargePercent: 8.5,
    minAmountCents: 200_00,
    maxAmountCents: null,
    isActive: true,
  },
  {
    id: 'ins-004',
    installments: 18,
    surchargePercent: 14.2,
    minAmountCents: 500_00,
    maxAmountCents: null,
    isActive: true,
  },
  {
    id: 'ins-005',
    installments: 24,
    surchargePercent: 19.9,
    minAmountCents: 800_00,
    maxAmountCents: 5_000_00,
    isActive: false,
  },
];

export const recentPayments: readonly RecentPayment[] = [
  {
    id: 'pay-001',
    orderNumber: '#ORD-1042',
    methodCode: 'CARD_VISA',
    methodName: 'Visa',
    amountCents: 12_500_00,
    status: 'captured',
    createdAt: '2026-10-03T08:42:00Z',
  },
  {
    id: 'pay-002',
    orderNumber: '#ORD-1041',
    methodCode: 'TRANSFER_SPEI',
    methodName: 'SPEI',
    amountCents: 8_300_00,
    status: 'captured',
    createdAt: '2026-10-03T07:18:00Z',
  },
  {
    id: 'pay-003',
    orderNumber: '#ORD-1040',
    methodCode: 'CASH_OXXO',
    methodName: 'OXXO',
    amountCents: 1_650_00,
    status: 'authorized',
    createdAt: '2026-10-03T05:54:00Z',
  },
  {
    id: 'pay-004',
    orderNumber: '#ORD-1039',
    methodCode: 'CARD_AMEX',
    methodName: 'Amex',
    amountCents: 22_400_00,
    status: 'captured',
    createdAt: '2026-10-02T22:10:00Z',
  },
  {
    id: 'pay-005',
    orderNumber: '#ORD-1038',
    methodCode: 'CARD_VISA',
    methodName: 'Visa',
    amountCents: 4_800_00,
    status: 'refunded',
    createdAt: '2026-10-02T19:33:00Z',
  },
  {
    id: 'pay-006',
    orderNumber: '#ORD-1037',
    methodCode: 'CARD_VISA',
    methodName: 'Visa',
    amountCents: 2_100_00,
    status: 'failed',
    createdAt: '2026-10-02T16:08:00Z',
  },
  {
    id: 'pay-007',
    orderNumber: '#ORD-1036',
    methodCode: 'WALLET_YAPPY',
    methodName: 'Yappy',
    amountCents: 3_200_00,
    status: 'captured',
    createdAt: '2026-10-02T11:45:00Z',
  },
  {
    id: 'pay-008',
    orderNumber: '#ORD-1035',
    methodCode: 'TRANSFER_SPEI',
    methodName: 'SPEI',
    amountCents: 18_900_00,
    status: 'captured',
    createdAt: '2026-10-02T09:20:00Z',
  },
];
