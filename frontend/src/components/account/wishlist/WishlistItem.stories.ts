import type { Meta, StoryObj } from '@storybook/astro';
import WishlistItem from './WishlistItem.astro';

/**
 * WishlistItem — Card de "colección curada" (design-refs §6.2, ref Mejuri).
 *
 * Diferenciador: NO es un ProductCard genérico. Tiene contexto
 * personal (cuándo se agregó, nota opcional) y acciones contextuales.
 */
const meta: Meta<typeof WishlistItem> = {
  title: 'Account/Wishlist/WishlistItem',
  component: WishlistItem,
  tags: ['autodocs'],
  argTypes: {
    productHref: { control: 'text' },
    productName: { control: 'text' },
    imageUrl: { control: 'text' },
    priceCents: { control: { type: 'number', min: 0, step: 100 } },
    currencyCode: { control: 'text' },
    addedDaysAgo: { control: { type: 'number', min: 0, max: 365 } },
    personalNote: { control: 'text' },
    stock: { control: { type: 'number', min: 0, max: 99 } },
    notifyEnabled: { control: 'boolean' },
    addedDaysAgoLabel: { control: 'text' },
    addedYesterdayLabel: { control: 'text' },
    addedTodayLabel: { control: 'text' },
    personalNoteLabel: { control: 'text' },
    outOfStockLabel: { control: 'text' },
    onlyLeftLabel: { control: 'text' },
    moveToBagLabel: { control: 'text' },
    removeLabel: { control: 'text' },
    notifyLabel: { control: 'text' },
  },
  args: {
    productHref: '/es/products/saber-alter-figma',
    productName: 'Saber Alter Figma',
    priceCents: 14500,
    currencyCode: 'USD',
    addedDaysAgo: 14,
    stock: 5,
    notifyEnabled: true,
    addedDaysAgoLabel: 'Guardada hace {days} días',
    addedYesterdayLabel: 'Guardada ayer',
    addedTodayLabel: 'Guardada hoy',
    personalNoteLabel: 'Nota personal',
    outOfStockLabel: 'Agotado',
    onlyLeftLabel: '¡Solo quedan {count}!',
    moveToBagLabel: 'Mover al carrito',
    removeLabel: 'Eliminar',
    notifyLabel: 'Notificarme',
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

const placeholderImage =
  'data:image/svg+xml;utf8,' +
  encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 400 400"><rect width="400" height="400" fill="#f1f5f9"/><circle cx="200" cy="160" r="60" fill="#cbd5e1"/><path d="M120 320 Q200 220 280 320 Z" fill="#cbd5e1"/></svg>`
  );

export const Default: Story = {
  args: {
    imageUrl: placeholderImage,
  },
};

export const WithPersonalNote: Story = {
  args: {
    imageUrl: placeholderImage,
    personalNote: 'Para mi cumpleaños — no abrir antes del 15.',
  },
};

export const LowStock: Story = {
  args: {
    imageUrl: placeholderImage,
    productName: 'Nendoroid Zero Two',
    stock: 2,
    addedDaysAgo: 3,
  },
};

export const OutOfStock: Story = {
  args: {
    imageUrl: placeholderImage,
    productName: 'Evangelion Unit-01',
    stock: 0,
    notifyEnabled: true,
  },
};

export const OutOfStockNoNotify: Story = {
  args: {
    imageUrl: placeholderImage,
    productName: 'Figura descatalogada',
    stock: 0,
    notifyEnabled: false,
  },
};

export const AddedToday: Story = {
  args: {
    imageUrl: placeholderImage,
    productName: 'Peluche Totoro mediano',
    addedDaysAgo: 0,
  },
};

export const AddedYesterday: Story = {
  args: {
    imageUrl: placeholderImage,
    productName: 'Hoodie Jujutsu Kaisen',
    addedDaysAgo: 1,
  },
};

export const AddedLongAgo: Story = {
  args: {
    imageUrl: placeholderImage,
    productName: 'Manga Spy x Family Tomo 1',
    addedDaysAgo: 90,
  },
};
