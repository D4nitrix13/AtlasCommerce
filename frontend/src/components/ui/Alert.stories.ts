import type { Meta, StoryObj } from '@storybook/astro';
import Alert from './Alert.astro';

/**
 * Alert — primitive UI.
 *
 * Caja semántica para mensajes contextuales (info / success / warning /
 * danger). Soporta dismissible y slot `title`.
 */
const meta: Meta<typeof Alert> = {
  title: 'UI/Alert',
  component: Alert,
  tags: ['autodocs'],
  argTypes: {
    variant: {
      control: { type: 'inline-radio' },
      options: ['info', 'success', 'warning', 'danger'],
    },
    title: { control: 'text' },
    dismissible: { control: 'boolean' },
  },
  args: {
    variant: 'info',
    title: 'Aviso',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  args: {
    variant: 'info',
    slot: 'Estamos mejorando la experiencia de pago. Algunas funciones podrían tardar más de lo habitual.',
  },
};

export const Info: Story = {
  args: {
    variant: 'info',
    slot: 'Hemos actualizado nuestros términos de servicio.',
  },
};

export const Success: Story = {
  args: {
    variant: 'success',
    slot: 'Cambios guardados correctamente.',
  },
};

export const Warning: Story = {
  args: {
    variant: 'warning',
    slot: 'Tu sesión expirará en 5 minutos.',
  },
};

export const Danger: Story = {
  args: {
    variant: 'danger',
    slot: 'No se pudo procesar el pago. Intenta con otro método.',
  },
};

export const WithTitle: Story = {
  args: {
    variant: 'danger',
    title: 'Error al guardar',
    slot: 'Revisa los campos marcados e inténtalo nuevamente.',
  },
};

export const Dismissible: Story = {
  args: {
    variant: 'success',
    title: 'Pedido confirmado',
    slot: 'Tu pedido #12345 ha sido recibido.',
    dismissible: true,
  },
};

export const AllVariants: Story = {
  render: () => `
    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
      <div role="status" class="flex items-start gap-3 rounded-lg border p-4 text-sm border-blue-200 bg-blue-50 text-blue-900">
        <span aria-hidden="true" class="mt-0.5 shrink-0 text-blue-500">ⓘ</span>
        <div class="flex-1">Información general al usuario.</div>
      </div>
      <div role="status" class="flex items-start gap-3 rounded-lg border p-4 text-sm border-emerald-200 bg-emerald-50 text-emerald-900">
        <span aria-hidden="true" class="mt-0.5 shrink-0 text-emerald-500">✓</span>
        <div class="flex-1">Operación completada con éxito.</div>
      </div>
      <div role="alert" class="flex items-start gap-3 rounded-lg border p-4 text-sm border-amber-200 bg-amber-50 text-amber-900">
        <span aria-hidden="true" class="mt-0.5 shrink-0 text-amber-500">⚠</span>
        <div class="flex-1">Atención: revisa esta advertencia.</div>
      </div>
      <div role="alert" class="flex items-start gap-3 rounded-lg border p-4 text-sm border-red-200 bg-red-50 text-red-900">
        <span aria-hidden="true" class="mt-0.5 shrink-0 text-red-500">✕</span>
        <div class="flex-1">Error crítico. Acción requerida.</div>
      </div>
    </div>
  `,
};
