import type { Meta, StoryObj } from '@storybook/astro';
import CustomerStories from './CustomerStories.astro';

/**
 * CustomerStories — testimonios B2B con quote, identidad del cliente y
 * métrica destacada. Tenant-aware vía `--primary`; empty-safe si
 * stories está vacío no renderiza la sección.
 */
const meta: Meta<typeof CustomerStories> = {
  title: 'Storefront/B2B/CustomerStories',
  component: CustomerStories,
  tags: ['autodocs'],
  argTypes: {
    heading: { control: 'text' },
    subheading: { control: 'text' },
    stories: { control: 'object' },
  },
  args: {
    heading: 'Lo que dicen nuestros clientes',
    subheading: 'Historias reales de estampado B2B con números verificables',
    stories: [
      {
        quote:
          'Necesitábamos 300 playeras en 5 días para el evento corporativo. Panda entregó 2 días antes y con calidad excelente.',
        customerName: 'María González',
        customerRole: 'Coordinadora de Eventos, Restaurante La Esquina',
        metricValue: '300 unidades',
        metricLabel: 'Entregadas en 3 días',
        avatarIconKey: 'user',
      },
      {
        quote:
          'Los uniformes del colegio se ven impecables. Hicimos 4 pedidos en el año con descuentos por volumen automáticos.',
        customerName: 'Carlos Mendoza',
        customerRole: 'Director Administrativo, Escuela Pública Central',
        metricValue: '1.200 unidades',
        metricLabel: 'Uniformes por ciclo escolar',
        avatarIconKey: 'users',
      },
      {
        quote:
          'Para la temporada recibimos playeras, hoodies y tazas. Una sola cotización consolidada, todo entregado a tiempo.',
        customerName: 'Andrea Ruiz',
        customerRole: 'Coordinadora, Club Deportivo Norte',
        metricValue: '98%',
        metricLabel: 'Pedidos entregados a tiempo',
        avatarIconKey: 'star',
      },
    ],
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const Empty: Story = {
  args: {
    stories: [],
  },
};

export const SingleStory: Story = {
  args: {
    stories: [
      {
        quote:
          'Necesitábamos 300 playeras en 5 días para el evento corporativo. Panda entregó 2 días antes y con calidad excelente.',
        customerName: 'María González',
        customerRole: 'Coordinadora de Eventos, Restaurante La Esquina',
        metricValue: '300 unidades',
        metricLabel: 'Entregadas en 3 días',
        avatarIconKey: 'user',
      },
    ],
  },
};
