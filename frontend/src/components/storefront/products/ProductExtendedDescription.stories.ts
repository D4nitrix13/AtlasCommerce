/**
 * ProductExtendedDescription — bloque de descripción extendida del PDP.
 */
import type { Meta, StoryObj } from '@storybook/astro';
import ProductExtendedDescription from './ProductExtendedDescription.astro';
import type { ProductExtendedDescriptionItem } from './ProductExtendedDescription.types';

const SINGLE_BLOCK: ProductExtendedDescriptionItem[] = [
  {
    title: 'The Les Paul, llevada al extremo',
    body:
      'Gibson se enorgullece de presentar el regreso de la Les Paul Supreme, una guitarra que redefine los límites de la artesanía y el rendimiento sonoro. Esta edición exclusiva Blueberry Burst combina la silueta clásica de la Les Paul con materiales premium y un diseño meticuloso.',
  },
];

const MULTIPLE_BLOCKS: ProductExtendedDescriptionItem[] = [
  {
    title: 'The Les Paul, llevada al extremo',
    body:
      'Gibson se enorgullece de presentar el regreso de la Les Paul Supreme, una guitarra que redefine los límites de la artesanía y el rendimiento sonoro.\n\nEquipada con un par de humbuckers Burstbucker Pro y Burstbucker Pro Plus, ofrece una versatilidad tonal sin precedentes.',
  },
  {
    title: 'Historia de la línea Supreme',
    body:
      'La línea Supreme nació en 1999 como un escaparate de las custom shops de Gibson. Cada generación incorpora hardware premium, tops figurados y electrónica de alta gama.',
  },
  {
    title: 'Setup recomendado',
    body:
      'Para esta guitarra recomendamos un amplifier tube de 30W mínimo, pedales de overdrive con respuesta dinámica y un delay corto para solos melódicos.',
  },
];

const meta: Meta<typeof ProductExtendedDescription> = {
  title: 'Storefront/Products/ProductExtendedDescription',
  component: ProductExtendedDescription,
  tags: ['autodocs'],
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  args: {
    items: SINGLE_BLOCK,
    labels: { heading: 'Descripción extendida' },
  },
};

export const MultipleBlocks: Story = {
  args: {
    items: MULTIPLE_BLOCKS,
    labels: { heading: 'Descripción extendida' },
  },
};

export const Empty: Story = {
  args: {
    items: [],
    labels: { heading: 'Descripción extendida' },
  },
};