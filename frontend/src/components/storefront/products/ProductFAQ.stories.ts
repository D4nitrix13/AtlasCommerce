/**
 * ProductFAQ — sección de FAQs del PDP, wrapper sobre FaqAccordion.
 */
import type { Meta, StoryObj } from '@storybook/astro';
import ProductFAQ from './ProductFAQ.astro';
import type { ProductFAQItem } from './ProductFAQ.types';

const SAMPLE_FAQS: ProductFAQItem[] = [
  {
    question: '¿La Les Paul Supreme incluye estuche?',
    answer:
      'Sí, todas las Les Paul Supreme se entregan con un estuche rígido Gibson original con llave, foam interior removible y compartimento para accesorios.',
  },
  {
    question: '¿Cuál es la diferencia entre Burstbucker Pro y Burstbucker Pro Plus?',
    answer:
      'El Burstbucker Pro (mástil) tiene una salida balanceada con medios cálidos; el Burstbucker Pro Plus (puente) tiene mayor salida y agudos cortantes para solos. Ambos comparten el cover nickel-silver y son hand-wired en Nashville.',
  },
  {
    question: '¿Se puede dividir la bobina con los controles push/pull?',
    answer:
      'Sí, los push/pull del volumen y tono activan coil-tap, out-of-phase y Pure Bypass switching según el modelo. Las posiciones están documentadas en el manual que viene con la guitarra.',
  },
];

const meta: Meta<typeof ProductFAQ> = {
  title: 'Storefront/Products/ProductFAQ',
  component: ProductFAQ,
  tags: ['autodocs'],
};

export default meta;
type Story = StoryObj<typeof meta>;

export const Default: Story = {
  args: {
    faqs: SAMPLE_FAQS,
    labels: { heading: 'Preguntas frecuentes' },
  },
};

export const SingleQuestion: Story = {
  args: {
    faqs: [SAMPLE_FAQS[0]!],
    labels: { heading: 'Preguntas frecuentes' },
  },
};

export const Empty: Story = {
  args: {
    faqs: [],
    labels: { heading: 'Preguntas frecuentes' },
  },
};