import type { Meta, StoryObj } from '@storybook/astro';
import DashboardWelcomeBanner from './DashboardWelcomeBanner.astro';

/**
 * DashboardWelcomeBanner — Banner top del dashboard del cliente.
 *
 * Tres variants: kitsune (violeta), panda (esmeralda), neutral (slate).
 * El saludo se pasa resuelto desde el page (i18n) — el banner no decide.
 */
const meta: Meta<typeof DashboardWelcomeBanner> = {
  title: 'Account/Dashboard/DashboardWelcomeBanner',
  component: DashboardWelcomeBanner,
  tags: ['autodocs'],
  argTypes: {
    userName: { control: 'text' },
    greeting: { control: 'text' },
    subtitle: { control: 'text' },
    variant: {
      control: { type: 'inline-radio' },
      options: ['kitsune', 'panda', 'neutral'],
    },
  },
  args: {
    userName: 'Daniel Benjamin',
    greeting: 'Bienvenido de vuelta, Daniel Benjamin',
    subtitle: 'Tienes 2 pedidos en preparación y 1 por enviar.',
    variant: 'kitsune',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Kitsune: Story = {};

export const Panda: Story = {
  args: {
    userName: 'Luis',
    greeting: 'Bienvenido de vuelta, Luis',
    subtitle: '3 cotizaciones RFQ pendientes de tu aprobación.',
    variant: 'panda',
  },
};

export const Neutral: Story = {
  args: {
    userName: 'Ana',
    greeting: 'Hola, Ana',
    subtitle: 'Tu tienda, tus términos. ¿Qué necesitas hoy?',
    variant: 'neutral',
  },
};

export const JapaneseGreeting: Story = {
  args: {
    userName: 'Yuki',
    greeting: 'おかえりなさい, Yuki',
    subtitle: '新作フィギュアが入荷しました。チェックしてみてください。',
    variant: 'kitsune',
  },
};

export const LongSubtitle: Story = {
  args: {
    userName: 'María',
    greeting: 'Bienvenida de vuelta, María',
    subtitle: 'Resumen: 5 pedidos entregados · 1 en preparación · 1 ticket abierto · 2 notificaciones sin leer.',
    variant: 'kitsune',
  },
};