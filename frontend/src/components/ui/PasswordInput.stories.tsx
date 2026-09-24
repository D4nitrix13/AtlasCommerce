import type { Meta, StoryObj } from '@storybook/react';
import { useState } from 'react';
import { PasswordInput } from './PasswordInput';

/**
 * PasswordInput — Input de password con toggle show/hide.
 *
 * Componente controlado (caller maneja value/onChange). Reutilizado
 * por LoginForm, ResetPasswordForm, RegisterForm, etc. para eliminar
 * duplicación visual.
 *
 * Accesibilidad: el botón toggle tiene aria-label que el caller debe
 * localizar via i18n. La prop `autoComplete` permite hint al
 * navegador (default 'new-password').
 */
const meta: Meta<typeof PasswordInput> = {
  title: 'UI/PasswordInput',
  component: PasswordInput,
  tags: ['autodocs'],
  argTypes: {
    showLabel: { control: 'text', description: 'aria-label del boton toggle cuando oculta' },
    hideLabel: { control: 'text', description: 'aria-label del boton toggle cuando muestra' },
    autoComplete: { control: 'text', description: 'HTML autocomplete hint' },
    required: { control: 'boolean' },
    minLength: { control: { type: 'number', min: 0, max: 128 } },
    placeholder: { control: 'text' },
  },
  args: {
    autoComplete: 'new-password',
    required: true,
    minLength: 8,
    placeholder: 'Minimo 8 caracteres',
  },
  decorators: [
    (Story) => (
      <div style={{ maxWidth: '400px', padding: '1rem' }}>
        <Story />
      </div>
    ),
  ],
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  render: (args) => {
    const [value, setValue] = useState('');
    return <PasswordInput {...args} id="password" value={value} onChange={setValue} />;
  },
};

export const LoginForm: Story = {
  args: {
    autoComplete: 'current-password',
    required: true,
    placeholder: 'Tu contrasena',
  },
  render: (args) => {
    const [value, setValue] = useState('hunter2');
    return <PasswordInput {...args} id="password" value={value} onChange={setValue} />;
  },
};

export const WithMinLength: Story = {
  args: {
    minLength: 12,
    placeholder: 'Minimo 12 caracteres',
  },
  render: (args) => {
    const [value, setValue] = useState('short');
    return <PasswordInput {...args} id="password" value={value} onChange={setValue} />;
  },
};
