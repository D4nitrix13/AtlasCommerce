import type { Meta, StoryObj } from '@storybook/astro';
import MetricCard from './MetricCard.astro';

/**
 * MetricCard — Card de metrica para el dashboard admin.
 *
 * Usado en `frontend/src/pages/[locale]/admin/dashboard.astro` (varias
 * instancias). El shape visual es:
 *   ┌─────────────────────────┐
 *   │ Title          [icon-bg] │
 *   │ Big value               │
 *   │ ↗ +5% vs mes anterior   │
 *   │ ◠◠◠ sparkline          │
 *   └─────────────────────────┘
 *
 * Variantes por `accent`: emerald (default), sky, amber, rose.
 * Variantes por `trend`: up (default), down.
 */
const meta: Meta<typeof MetricCard> = {
  title: 'Admin/Dashboard/MetricCard',
  component: MetricCard,
  tags: ['autodocs'],
  argTypes: {
    title: { control: 'text', description: 'Label descriptivo de la metrica' },
    value: { control: 'text', description: 'Valor destacado (formateado por caller)' },
    change: { control: 'text', description: 'Delta vs periodo anterior (ej: "+12%")' },
    trend: {
      control: { type: 'select' },
      options: ['up', 'down'],
      description: 'Color y flecha de la delta',
    },
    icon: {
      control: { type: 'text' },
      description: 'Nombre del icono lucide',
    },
    accent: {
      control: { type: 'select' },
      options: ['emerald', 'sky', 'amber', 'rose'],
      description: 'Color del icono accent',
    },
  },
  args: {
    title: 'Ventas del mes',
    value: '$2,540',
    change: '+12%',
    trend: 'up',
    icon: 'chart-bar',
    accent: 'emerald',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const NegativeTrend: Story = {
  args: {
    title: 'Devoluciones',
    value: '$180',
    change: '-8%',
    trend: 'down',
    icon: 'package',
    accent: 'rose',
  },
};

export const AllAccents: Story = {
  render: () => (
    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '1rem' }}>
      <MetricCard title="Ventas" value="$2,540" change="+12%" trend="up" icon="chart-bar" accent="emerald" />
      <MetricCard title="Visitas" value="18,420" change="+4%" trend="up" icon="user" accent="sky" />
      <MetricCard title="Stock bajo" value="12 SKUs" change="+3" trend="up" icon="package" accent="amber" />
      <MetricCard title="Cancelaciones" value="$340" change="-15%" trend="down" icon="x" accent="rose" />
    </div>
  ),
};
