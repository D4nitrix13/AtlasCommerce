/**
 * ProductDetailTabs — tab strip del PDP (Descripción · Ficha técnica ·
 * Reseñas · Preguntas frecuentes · Envíos).
 */
import type { Meta, StoryObj } from '@storybook/astro';
import ProductDetailTabs from './ProductDetailTabs.astro';

const meta: Meta<typeof ProductDetailTabs> = {
  title: 'Storefront/Products/ProductDetailTabs',
  component: ProductDetailTabs,
  tags: ['autodocs'],
};

export default meta;
type Story = StoryObj<typeof meta>;

const LABELS = {
  legend: 'Información del producto',
  description: 'Descripción',
  specs: 'Ficha técnica',
  reviews: 'Reseñas',
  faq: 'Preguntas frecuentes',
  shipping: 'Envíos',
  emptySpecs: 'Este producto todavía no publica especificaciones técnicas.',
  specsHeading: 'Especificaciones',
  faqHeading: 'Preguntas frecuentes',
  highlightsHeading: 'Características destacadas',
  extendedHeading: 'Descripción extendida',
  featuredCta: 'Leer artículo',
  more: 'Leer más',
  less: 'Leer menos',
};

const REVIEWS_LABELS = {
  heading: 'Reseñas',
  average: 'Promedio',
  outOf: 'de 5',
  count: '18 reseñas',
  write: 'Escribir reseña',
  viewAll: 'Ver todas las reseñas',
  empty: 'Este producto todavía no tiene reseñas.',
  verified: 'Compra verificada',
  starAlt: 'estrella',
  loadMore: 'Cargar más reseñas',
  showLess: 'Ver menos',
  filterAll: 'Todas',
  filterByRating: 'Filtrar por',
  collapse: 'Colapsar reseñas',
  expand: 'Expandir reseñas',
};

const BOOLEAN_LABELS = { yes: 'Sí', no: 'No' };

const REVIEW = {
  id: 1,
  authorName: 'Ana G.',
  createdAt: '2026-01-15',
  rating: 5,
  title: 'Excelente sonido',
  body: 'La madera y el acabado son de otro nivel.',
  verified: true,
  likesCount: 3,
  likedByCurrentUser: false,
};

const BASE_ARGS = {
  labels: LABELS,
  description: '<p>Construida en Corona (California), con décadas de feedback de artistas.</p>',
  reviews: [REVIEW],
  reviewsLabels: REVIEWS_LABELS,
  viewAllHref: '/es/products/demo/reviews',
  writeHref: '/es/products/demo/reviews/new',
  loginHref: '/es/login',
  shippingInline: 'Envío gratis en pedidos superiores a $50',
  inclusionAccordions: [
    { label: 'Garantía de por vida', content: '<p>Cobertura contra defectos de fabricación.</p>' },
    { label: 'Estuche incluido', content: '<p>Estuche rígido premium.</p>' },
    { label: 'Ajuste incluido', content: '<p>Ajuste de cuerdas y entonación.</p>' },
  ],
  faqs: [{ question: '¿Hacen envíos internacionales?', answer: 'Sí, a toda la región.' }],
  faqHeading: 'Preguntas frecuentes',
  booleanLabels: BOOLEAN_LABELS,
};

export const Default: Story = {
  args: {
    ...BASE_ARGS,
    attributes: [
      { slug: 'material', label: 'Material', type: 'text', value: 'Aliso' },
      { slug: 'peso', label: 'Peso', type: 'number', unit: 'kg', valueNumber: 3.4 },
      { slug: 'estuche', label: 'Incluye estuche', type: 'boolean', valueBoolean: true },
    ],
  },
};

export const SpecsAgrupadas: Story = {
  args: {
    ...BASE_ARGS,
    groupedSpecs: [
      {
        category: 'Cuerpo',
        items: [
          { label: 'Madera', value: 'Aliso' },
          { label: 'Acabado', value: 'Gloss urethane' },
        ],
      },
    ],
  },
};

export const SinSpecs: Story = {
  args: {
    ...BASE_ARGS,
    attributes: [],
  },
};

export const SinReseñasNiFaq: Story = {
  args: {
    ...BASE_ARGS,
    reviews: [],
    faqs: [],
    attributes: [],
  },
};
