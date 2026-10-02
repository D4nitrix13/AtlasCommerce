import type { Meta, StoryObj } from '@storybook/astro';
import CartItem from './CartItem.astro';
import type { CartItemData } from './CartItem.astro';
import type { CartItemLabels } from './CartItem.astro';

/**
 * CartItem — fila de un producto dentro del carrito.
 *
 * Layout: imagen + título + controles de cantidad + precio unitario +
 * subtotal de fila + botón eliminar.
 */
const meta: Meta<typeof CartItem> = {
  title: 'Storefront/CartItem',
  component: CartItem,
  tags: ['autodocs'],
  argTypes: {
    item: { control: 'object' },
    locale: { control: 'text' },
    labels: { control: 'object' },
  },
  args: {
    locale: 'es',
    labels: {
      remove: 'Eliminar',
      decrement: 'Disminuir',
      increment: 'Aumentar',
      qty: 'Cant.',
    } as CartItemLabels,
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

const placeholderImage =
  'data:image/svg+xml;utf8,' +
  encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400"><rect width="400" height="400" fill="#f1f5f9"/><circle cx="200" cy="160" r="60" fill="#cbd5e1"/><path d="M120 320 Q200 220 280 320 Z" fill="#cbd5e1"/></svg>`
  );

const baseItem: CartItemData = {
  id: 1,
  slug: 'saber-alter-figma',
  name: 'Saber Alter Figma',
  imageUrl: placeholderImage,
  priceCents: 14500,
  currency: 'USD',
  quantity: 1,
  maxQuantity: 10,
};

export const Default: Story = {
  args: { item: { ...baseItem } },
};

export const MultipleQuantity: Story = {
  args: {
    item: { ...baseItem, quantity: 3 },
  },
};

export const MaxQuantity: Story = {
  args: {
    item: { ...baseItem, quantity: 10, maxQuantity: 10 },
  },
};

export const MinQuantity: Story = {
  args: {
    item: { ...baseItem, quantity: 1 },
  },
};

export const DifferentCurrency: Story = {
  args: {
    item: { ...baseItem, name: 'Manga Chainsaw Man Tomo 1', priceCents: 1900, currency: 'JPY' },
  },
};

export const LongName: Story = {
  args: {
    item: {
      ...baseItem,
      name: 'Evangelion Unit-01 1/6 — Edición limitada con luz LED y piezas articuladas premium',
    },
  },
};

export const ManyItems: Story = {
  args: {
    item: { ...baseItem, quantity: 5, priceCents: 3500, name: 'Peluche Totoro mediano' },
  },
};
