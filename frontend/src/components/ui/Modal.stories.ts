import type { Meta, StoryObj } from '@storybook/astro';
import Modal from './Modal.astro';

/**
 * Modal — primitive UI.
 *
 * Dialog modal construido sobre `<dialog>` nativo (HTMLDialogElement).
 * Soporta size (sm/md/lg/xl) y dismissible. El padre controla showModal().
 */
const meta: Meta<typeof Modal> = {
  title: 'UI/Modal',
  component: Modal,
  tags: ['autodocs'],
  argTypes: {
    id: { control: 'text' },
    title: { control: 'text' },
    open: { control: 'boolean', description: 'Abre el modal en SSR (raro)' },
    size: {
      control: { type: 'inline-radio' },
      options: ['sm', 'md', 'lg', 'xl'],
    },
    dismissible: { control: 'boolean' },
  },
  args: {
    id: 'demo-modal',
    title: 'Título del modal',
    size: 'md',
    dismissible: true,
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  args: {
    slot: 'Contenido del modal.',
  },
};

export const WithFooter: Story = {
  render: () => `
    <dialog id="demo-modal" aria-labelledby="demo-modal-title" class="w-full rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 max-w-md">
      <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-4">
        <h2 id="demo-modal-title" class="text-lg font-semibold text-slate-900">Confirmar eliminación</h2>
        <button type="button" aria-label="Cerrar" data-close-modal="demo-modal" class="shrink-0 rounded p-1 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400">
          <span aria-hidden="true">✕</span>
        </button>
      </div>
      <div class="px-6 py-4">
        <p class="text-sm text-slate-600">¿Estás seguro? Esta acción no se puede deshacer.</p>
      </div>
      <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-slate-50/50 px-6 py-3 rounded-b-2xl">
        <button type="button" data-close-modal="demo-modal" class="inline-flex items-center justify-center gap-2 rounded-lg font-medium transition bg-white text-slate-700 border border-slate-200 hover:bg-slate-50 h-10 px-4 text-sm">Cancelar</button>
        <button type="button" class="inline-flex items-center justify-center gap-2 rounded-lg font-medium transition bg-red-600 text-white hover:bg-red-700 h-10 px-4 text-sm">Eliminar</button>
      </div>
    </dialog>
  `,
};

export const SizeOptions: Story = {
  render: () => `
    <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
      <dialog open class="rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 max-w-sm">
        <div class="p-6"><p class="text-sm text-slate-600">sm · max-w-sm</p></div>
      </dialog>
      <dialog open class="rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 max-w-md">
        <div class="p-6"><p class="text-sm text-slate-600">md · max-w-md</p></div>
      </dialog>
      <dialog open class="rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 max-w-lg">
        <div class="p-6"><p class="text-sm text-slate-600">lg · max-w-lg</p></div>
      </dialog>
      <dialog open class="rounded-2xl bg-white p-0 shadow-2xl backdrop:bg-slate-900/50 max-w-2xl">
        <div class="p-6"><p class="text-sm text-slate-600">xl · max-w-2xl</p></div>
      </dialog>
    </div>
  `,
};

export const NotDismissible: Story = {
  args: {
    dismissible: false,
    title: 'Acción requerida',
    slot: 'Debes completar este paso antes de continuar.',
  },
};
