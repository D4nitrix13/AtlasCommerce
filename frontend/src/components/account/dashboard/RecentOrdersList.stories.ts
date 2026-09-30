import type { Meta, StoryObj } from '@storybook/astro';
import RecentOrdersList from './RecentOrdersList.astro';
import type { RecentOrderMock } from '../../../data/account-mock';

/**
 * RecentOrdersList — Mini lista de últimos N pedidos del cliente.
 *
 * Maneja estados loading/empty/error explícitamente. Status pill vía
 * Badge primitive.
 */
const meta: Meta<typeof RecentOrdersList> = {
  title: 'Account/Dashboard/RecentOrdersList',
  component: RecentOrdersList,
  tags: ['autodocs'],
  argTypes: {
    orders: { control: 'object' },
    heading: { control: 'text' },
    viewAllLabel: { control: 'text' },
    viewAllHref: { control: 'text' },
    loading: { control: 'boolean' },
    error: { control: 'boolean' },
    loadingLabel: { control: 'text' },
    errorLabel: { control: 'text' },
    emptyTitle: { control: 'text' },
    emptyHint: { control: 'text' },
  },
  args: {
    heading: 'Pedidos recientes',
    viewAllLabel: 'Ver todos',
    viewAllHref: '/account/orders',
    loadingLabel: 'Cargando pedidos…',
    errorLabel: 'No se pudieron cargar los pedidos.',
    emptyTitle: 'Sin pedidos aún',
    emptyHint: 'Cuando hagas tu primera compra aparecerá aquí.',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

const sampleOrders: RecentOrderMock[] = [
  { id: '12345', date: '2026-09-25', status: 'shipped', total: 4500, items: 3 },
  { id: '12340', date: '2026-09-18', status: 'delivered', total: 12000, items: 7 },
  { id: '12338', date: '2026-09-10', status: 'delivered', total: 3400, items: 2 },
  { id: '12335', date: '2026-08-30', status: 'cancelled', total: 0, items: 1 },
  { id: '12330', date: '2026-08-22', status: 'delivered', total: 8600, items: 4 },
];

const orderIdLabel = (id: string): string => `Pedido #${id}`;
const itemsCountLabel = (count: number): string => `${count} artículo${count === 1 ? '' : 's'}`;
const statusLabel = (status: string): string => {
  const map: Record<string, string> = {
    pending: 'Pendiente',
    processing: 'Preparando',
    shipped: 'Enviado',
    delivered: 'Entregado',
    cancelled: 'Cancelado',
  };
  return map[status] ?? status;
};

export const Default: Story = {
  args: {
    orders: sampleOrders,
    orderIdLabel,
    itemsCountLabel,
    viewDetailLabel: 'Ver detalle →',
    statusLabel: statusLabel as never,
  },
};

export const Loading: Story = {
  args: {
    orders: [],
    loading: true,
    orderIdLabel,
    itemsCountLabel,
    viewDetailLabel: 'Ver detalle →',
    statusLabel: statusLabel as never,
  },
};

export const Error: Story = {
  args: {
    orders: [],
    error: true,
    orderIdLabel,
    itemsCountLabel,
    viewDetailLabel: 'Ver detalle →',
    statusLabel: statusLabel as never,
  },
};

export const Empty: Story = {
  args: {
    orders: [],
    orderIdLabel,
    itemsCountLabel,
    viewDetailLabel: 'Ver detalle →',
    statusLabel: statusLabel as never,
  },
};

export const SingleOrder: Story = {
  args: {
    orders: [sampleOrders[0]],
    orderIdLabel,
    itemsCountLabel,
    viewDetailLabel: 'Ver detalle →',
    statusLabel: statusLabel as never,
  },
};

export const WithoutViewAllLink: Story = {
  args: {
    orders: sampleOrders,
    viewAllHref: undefined,
    orderIdLabel,
    itemsCountLabel,
    viewDetailLabel: 'Ver detalle →',
    statusLabel: statusLabel as never,
  },
};