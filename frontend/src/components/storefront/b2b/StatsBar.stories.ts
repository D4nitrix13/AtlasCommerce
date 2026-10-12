import type { Meta, StoryObj } from '@storybook/astro';
import StatsBar from './StatsBar.astro';

/**
 * StatsBar — banda B2B con cifras destacadas (Panda Estampados).
 *
 * Réplica del patrón Bonfire "stats hero" (número grande + label +
 * icono). Tenant-aware vía `--primary`; empty-safe si el array llega
 * vacío el componente no renderiza nada.
 */
const meta: Meta<typeof StatsBar> = {
  title: 'Storefront/B2B/StatsBar',
  component: StatsBar,
  tags: ['autodocs'],
  argTypes: {
    heading: { control: 'text' },
    stats: { control: 'object' },
  },
  args: {
    heading: 'Por qué estampan con nosotros',
    stats: [
      { value: '500+', label: 'Empresas atendidas', iconKey: 'building-office' },
      { value: '10.000+', label: 'Estampados producidos', iconKey: 'cube' },
      { value: '24 h', label: 'Tiempo de respuesta', iconKey: 'bolt' },
      { value: '98%', label: 'Pedidos a tiempo', iconKey: 'shield-check' },
    ],
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const Empty: Story = {
  args: {
    stats: [],
  },
};

export const Panda: Story = {
  args: {
    heading: 'Por qué estampan con nosotros',
    stats: [
      { value: '500+', label: 'Empresas atendidas', iconKey: 'building-office' },
      { value: '10.000+', label: 'Estampados producidos', iconKey: 'cube' },
      { value: '24 h', label: 'Tiempo de respuesta', iconKey: 'bolt' },
      { value: '98%', label: 'Pedidos a tiempo', iconKey: 'shield-check' },
    ],
  },
};
