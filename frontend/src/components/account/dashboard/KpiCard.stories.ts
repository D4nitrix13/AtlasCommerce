/**
 * Storybook stories para `KpiCard` (F5.5).
 *
 * Cubre los 3 tonos del helper (`positive`/`neutral`/`warning`) +
 * empty value (`—`) para validar degradación graceful cuando un
 * endpoint devuelve null.
 *
 * `KpiGrid` es un wrapper trivial (grid de N×KpiCard) y no merece story
 * propia. Sus variantes se cubren renderizando manualmente desde
 * Storybook la combinación de estas 4 cards.
 */
import type { Meta, StoryObj } from '@storybook/astro';
import KpiCard from './KpiCard.astro';
import type { DashboardKpi } from '../../../types/dashboard-kpis';

const MOCK_T: Record<string, string> = {
  'account.dashboard.kpi.ordersTotal': 'Pedidos totales',
  'account.dashboard.kpi.processing': 'En producción',
  'account.dashboard.kpi.rfqApproval': 'Tasa de aprobación RFQ',
  'account.dashboard.kpi.pendingBalance': 'Saldo pendiente',
};

const KPI_ORDERS: DashboardKpi = {
  labelKey: 'account.dashboard.kpi.ordersTotal',
  value: '24',
  delta: '+6 este mes',
  tone: 'positive',
  hint: null,
};

const KPI_PROCESSING: DashboardKpi = {
  labelKey: 'account.dashboard.kpi.processing',
  value: '2',
  delta: null,
  tone: 'neutral',
  hint: 'Llega el 24 oct',
};

const KPI_RFQ: DashboardKpi = {
  labelKey: 'account.dashboard.kpi.rfqApproval',
  value: '87%',
  delta: '+4% vs. Q3',
  tone: 'positive',
  hint: null,
};

const KPI_PENDING: DashboardKpi = {
  labelKey: 'account.dashboard.kpi.pendingBalance',
  value: '$1,240',
  delta: null,
  tone: 'warning',
  hint: '1 factura por vencer',
};

const KPI_EMPTY: DashboardKpi = {
  labelKey: 'account.dashboard.kpi.ordersTotal',
  value: '—',
  delta: null,
  tone: 'neutral',
  hint: null,
};

const meta: Meta<typeof KpiCard> = {
  title: 'Account/Dashboard/KpiCard',
  component: KpiCard,
  tags: ['autodocs'],
  args: {
    kpi: KPI_ORDERS,
    t: MOCK_T as never,
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const WithHint: Story = {
  args: { kpi: KPI_PROCESSING },
};

export const Warning: Story = {
  args: { kpi: KPI_PENDING },
};

export const Empty: Story = {
  args: { kpi: KPI_EMPTY },
};
