import type { Meta, StoryObj } from '@storybook/astro';
import ProcessSteps from './ProcessSteps.astro';

/**
 * ProcessSteps — stepper horizontal de 4 pasos (Cotiza → Aprueba →
 * Produce → Entrega) para la home B2B Panda. Tenant-aware vía
 * `--primary`; empty-safe si steps está vacío no renderiza nada.
 */
const meta: Meta<typeof ProcessSteps> = {
  title: 'Storefront/B2B/ProcessSteps',
  component: ProcessSteps,
  tags: ['autodocs'],
  argTypes: {
    heading: { control: 'text' },
    subheading: { control: 'text' },
    steps: { control: 'object' },
  },
  args: {
    heading: 'Cómo funciona',
    subheading: 'De la cotización inicial hasta la entrega, en cuatro pasos',
    steps: [
      {
        iconKey: 'document-text',
        title: 'Cotizá',
        description:
          'Envíanos tu diseño o idea y te respondemos en menos de 24 horas con cotización detallada.',
      },
      {
        iconKey: 'eye',
        title: 'Aprobá',
        description:
          'Ajustamos el diseño, elegís técnica de estampado y confirmás muestra digital.',
      },
      {
        iconKey: 'cube',
        title: 'Producimos',
        description:
          'Estampamos en tu plazo. Línea de producción dedicada para clientes B2B con capacidad mensual alta.',
      },
      {
        iconKey: 'truck',
        title: 'Entregamos',
        description:
          'Despachamos a tu dirección o sucursales. Tracking incluido para pedidos superiores a 50 unidades.',
      },
    ],
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const Empty: Story = {
  args: {
    steps: [],
  },
};

export const WithoutSubheading: Story = {
  args: {
    subheading: undefined,
  },
};
