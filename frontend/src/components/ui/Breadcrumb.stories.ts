import type { Meta, StoryObj } from '@storybook/astro';
import Breadcrumb from './Breadcrumb.astro';

/**
 * Breadcrumb — primitive UI.
 *
 * Lista de migas semántica (nav + ol). Cada item es `{label, href?}`;
 * el último se renderiza como `<span aria-current="page">`. Separator
 * configurable (default "/").
 */
const meta: Meta<typeof Breadcrumb> = {
  title: 'UI/Breadcrumb',
  component: Breadcrumb,
  tags: ['autodocs'],
  argTypes: {
    items: { control: 'object' },
    separator: { control: 'text' },
    ariaLabel: { control: 'text' },
  },
  args: {
    items: [
      { label: 'Inicio', href: '/' },
      { label: 'Productos', href: '/products' },
      { label: 'Figures' },
    ],
    separator: '/',
    ariaLabel: 'Migas de pan',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const DeepPath: Story = {
  args: {
    items: [
      { label: 'Inicio', href: '/' },
      { label: 'Cuenta', href: '/account' },
      { label: 'Pedidos', href: '/account/orders' },
      { label: '#12345' },
    ],
  },
};

export const ChevronSeparator: Story = {
  args: {
    separator: '›',
    items: [
      { label: 'Inicio', href: '/' },
      { label: 'Categorías', href: '/categories' },
      { label: 'Manga' },
    ],
  },
};

export const NoLastLink: Story = {
  args: {
    items: [{ label: 'Tienda', href: '/' }, { label: 'Figures' }],
  },
};

export const CustomAriaLabel: Story = {
  args: {
    ariaLabel: 'Navegación de ruta',
    items: [{ label: 'Inicio', href: '/' }, { label: 'Wishlist' }],
  },
};
