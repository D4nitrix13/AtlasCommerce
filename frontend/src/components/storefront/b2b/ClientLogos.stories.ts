import type { Meta, StoryObj } from '@storybook/astro';
import ClientLogos from './ClientLogos.astro';

/**
 * ClientLogos — grilla B2B "trusted by" con labels de rubro
 * (restaurante, escuela, evento, ONG) en lugar de logos reales.
 * Tenant-aware vía `--primary`; empty-safe si logos está vacío no
 * renderiza la sección.
 */
const meta: Meta<typeof ClientLogos> = {
  title: 'Storefront/B2B/ClientLogos',
  component: ClientLogos,
  tags: ['autodocs'],
  argTypes: {
    heading: { control: 'text' },
    subheading: { control: 'text' },
    logos: { control: 'object' },
  },
  args: {
    heading: 'Quiénes estampan con Panda',
    subheading:
      'Empresas, escuelas, ONGs, restaurantes y equipos deportivos en todo el país',
    logos: [
      { iconKey: 'building-office', label: 'Restaurante La Esquina' },
      { iconKey: 'users', label: 'Escuela Pública Central' },
      { iconKey: 'star', label: 'Festival Verano' },
      { iconKey: 'gift', label: 'ONG Manos Solidarias' },
      { iconKey: 'shopping-cart', label: 'Club Deportivo Norte' },
      { iconKey: 'tag', label: 'Hotel Boutique Sur' },
    ],
  },
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {};

export const Empty: Story = {
  args: {
    logos: [],
  },
};

export const WithLinks: Story = {
  args: {
    heading: 'Verticales que atendemos',
    subheading: 'Cada vertical con descuento por volumen a medida',
    logos: [
      { iconKey: 'building-office', label: 'Restaurante La Esquina', href: '/products?vertical=restaurantes' },
      { iconKey: 'users', label: 'Escuela Pública Central', href: '/products?vertical=escuelas' },
      { iconKey: 'star', label: 'Festival Verano', href: '/products?vertical=eventos' },
      { iconKey: 'gift', label: 'ONG Manos Solidarias', href: '/products?vertical=ongs' },
      { iconKey: 'shopping-cart', label: 'Club Deportivo Norte', href: '/products?vertical=deportes' },
      { iconKey: 'tag', label: 'Hotel Boutique Sur', href: '/products?vertical=hoteles' },
    ],
  },
};
