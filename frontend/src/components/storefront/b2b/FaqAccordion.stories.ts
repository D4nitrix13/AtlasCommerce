import type { Meta, StoryObj } from '@storybook/astro';
import FaqAccordion from './FaqAccordion.astro';

/**
 * FaqAccordion — accordion accesible de preguntas frecuentes B2B
 * Panda. Implementado con <details>/<summary> nativos (cero JS) y
 * por eso no requiere hidratación. Tenant-aware vía `--primary` para
 * el borde del item abierto; empty-safe si items está vacío.
 */
const meta: Meta<typeof FaqAccordion> = {
  title: 'Storefront/B2B/FaqAccordion',
  component: FaqAccordion,
  tags: ['autodocs'],
  argTypes: {
    heading: { control: 'text' },
    items: { control: 'object' },
  },
  args: {
    heading: 'Preguntas frecuentes',
    items: [
      {
        question: '¿Cuál es el pedido mínimo?',
        answer:
          'El MOQ es de 10 unidades por diseño. Para pedidos mayores a 100 unidades accedés a descuentos por volumen automáticamente.',
      },
      {
        question: '¿Cómo funciona el pago a 30 días (NET 30)?',
        answer:
          'NET 30 está disponible para clientes con historial de crédito aprobado. Solicitá evaluación al iniciar tu segunda orden.',
      },
      {
        question: '¿Pueden estampar mi propio diseño o necesito uno creado?',
        answer:
          'Aceptamos diseños en formato vectorial (SVG, AI, PDF) o bitmap de alta resolución (mínimo 300 dpi). Si necesitás diseño desde cero, tenemos equipo creativo con tarifas competitivas.',
      },
      {
        question: '¿Cuánto tarda un pedido?',
        answer:
          'Pedidos estándar: 5-7 días hábiles después de aprobar muestra. Pedidos urgentes (suscripción premium): 48-72 horas con cargo adicional.',
      },
      {
        question: '¿Envían a todo el país?',
        answer:
          'Sí, despachamos a cualquier departamento de Nicaragua y Centroamérica. Para pedidos corporativos con múltiples sucursales usamos courier dedicado con tracking personalizado.',
      },
    ],
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const Empty: Story = {
  args: {
    items: [],
  },
};

export const SingleQuestion: Story = {
  args: {
    items: [
      {
        question: '¿Cuál es el pedido mínimo?',
        answer:
          'El MOQ es de 10 unidades por diseño. Para pedidos mayores a 100 unidades accedés a descuentos por volumen automáticamente.',
      },
    ],
  },
};
