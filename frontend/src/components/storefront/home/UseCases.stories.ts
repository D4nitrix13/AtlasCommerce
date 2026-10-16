import type { Meta, StoryObj } from '@storybook/astro';
import UseCases from './UseCases.astro';

/**
 * UseCases — grilla de verticales / "personas" de la home B2C.
 *
 * Cards circulares con icono + label. Cada card linkea al vertical
 * que representa (categoría, landing, etc.). Tenant-aware vía `--primary`.
 */
const meta: Meta<typeof UseCases> = {
  title: 'Storefront/UseCases',
  component: UseCases,
  tags: ['autodocs'],
  argTypes: {
    heading: { control: 'text' },
    subheading: { control: 'text' },
    cases: { control: 'object' },
  },
  args: {
    heading: 'Para quien tú quieras',
    subheading: 'Elegí el vertical que mejor te represente',
    cases: [
      { iconKey: 'user', label: 'Individuos', href: '/products?audience=individual' },
      { iconKey: 'shopping-cart', label: 'Negocios', href: '/products?audience=business' },
      { iconKey: 'building-office', label: 'Empresas', href: '/products?audience=enterprise' },
      { iconKey: 'star', label: 'Equipos', href: '/products?audience=teams' },
    ],
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const Empty: Story = {
  args: {
    cases: [],
  },
};

export const WithoutSubheading: Story = {
  args: {
    subheading: undefined,
  },
};

export const Panda: Story = {
  args: {
    heading: '¿Para quién estás diseñando?',
    subheading: 'Encontrá el vertical que mejor describe tu proyecto',
    cases: [
      { iconKey: 'user', label: 'Individuos', href: '/products?vertical=individual' },
      { iconKey: 'shopping-cart', label: 'Eventos', href: '/products?vertical=events' },
      { iconKey: 'building-office', label: 'Empresas', href: '/products?vertical=business' },
      { iconKey: 'star', label: 'Equipos', href: '/products?vertical=teams' },
    ],
  },
};

export const Kitsune: Story = {
  args: {
    heading: 'Explorá por nicho',
    subheading: 'Figures, anime, manga y cosplay en un solo lugar',
    cases: [
      { iconKey: 'star', label: 'Figures', href: '/products?vertical=figures' },
      { iconKey: 'eye', label: 'Anime', href: '/products?vertical=anime' },
      { iconKey: 'folder', label: 'Manga', href: '/products?vertical=manga' },
      { iconKey: 'tag', label: 'Cosplay', href: '/products?vertical=cosplay' },
    ],
  },
};

export const MmBoutique: Story = {
  args: {
    heading: 'Para cada ocasión',
    subheading: 'Lo que necesitás, cuando lo necesitás',
    cases: [
      { iconKey: 'shopping-cart', label: 'Eventos', href: '/products?vertical=events' },
      { iconKey: 'star', label: 'Diario', href: '/products?vertical=daily' },
      { iconKey: 'folder', label: 'Trabajo', href: '/products?vertical=work' },
      { iconKey: 'tag', label: 'Accesorios', href: '/products?vertical=accessories' },
    ],
  },
};

export const MoralesStore: Story = {
  args: {
    heading: 'Para toda la familia',
    subheading: 'Encontrá tu sección en segundos',
    cases: [
      { iconKey: 'user', label: 'Mujer', href: '/products?vertical=woman' },
      { iconKey: 'users', label: 'Hombre', href: '/products?vertical=men' },
      { iconKey: 'shield-check', label: 'Niños', href: '/products?vertical=kids' },
      { iconKey: 'tag', label: 'Accesorios', href: '/products?vertical=accessories' },
    ],
  },
};
