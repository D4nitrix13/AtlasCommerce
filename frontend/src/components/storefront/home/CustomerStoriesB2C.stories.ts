import type { Meta, StoryObj } from '@storybook/astro';
import CustomerStoriesB2C from './CustomerStoriesB2C.astro';

/**
 * CustomerStoriesB2C — testimonios B2C con quote, identidad del cliente
 * y métrica destacada. Tenant-aware vía `--primary`; empty-safe si
 * stories está vacío no renderiza la sección.
 *
 * Mock data: fans / clientes recurrentes, no corporativo. Las métricas
 * son ratings (4.9/5), recomendaciones (98%) y volumen de compras
 * (200+), consistentes con la audiencia B2C de Kitsune / MM-Boutique
 * / Morales Store.
 */
const meta: Meta<typeof CustomerStoriesB2C> = {
  title: 'Storefront/Home/CustomerStoriesB2C',
  component: CustomerStoriesB2C,
  tags: ['autodocs'],
  argTypes: {
    heading: { control: 'text' },
    subheading: { control: 'text' },
    stories: { control: 'object' },
  },
  args: {
    heading: 'Lo que dicen nuestros clientes',
    subheading: 'Historias reales de fans, coleccionistas y clientes recurrentes',
    stories: [
      {
        quote:
          'Encontré la figura de Saber que buscaba hace años. Llegó perfectamente empacada y con todos los accesorios originales.',
        customerName: 'Marcos R.',
        customerRole: 'Coleccionista otaku',
        metricValue: '200+',
        metricLabel: 'compras en la tienda',
        avatarIconKey: 'user',
      },
      {
        quote:
          'Las camisetas son de calidad increíble. Compré 5 para mi familia en eventos de la comunidad y todos quedaron encantados.',
        customerName: 'Ana L.',
        customerRole: 'Cliente recurrente',
        metricValue: '98%',
        metricLabel: 'recomienda la tienda',
        avatarIconKey: 'users',
      },
      {
        quote:
          'Tuve un problema con un envío y el servicio al cliente me lo resolvió en horas. Eso vale más que el producto mismo.',
        customerName: 'Diego M.',
        customerRole: 'Cliente',
        metricValue: '24h',
        metricLabel: 'tiempo de respuesta',
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
          'Encontré la figura de Saber que buscaba hace años. Llegó perfectamente empacada y con todos los accesorios originales.',
        customerName: 'Marcos R.',
        customerRole: 'Coleccionista otaku',
        metricValue: '200+',
        metricLabel: 'compras en la tienda',
        avatarIconKey: 'user',
      },
    ],
  },
};
