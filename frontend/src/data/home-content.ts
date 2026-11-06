/**
 * Datos editoriales de la portada (`/[locale]/`).
 *
 * Stats, logos de clientes, testimonios, pasos de proceso, features de
 * materiales y FAQ viven acá como contenido editorial tipado. No son
 * mocks de catálogo: son textos de marketing que hoy no administra
 * ningún endpoint (`/api/storefront/b2b/home` todavía no existe).
 *
 * Los bloques con copy traducido se construyen contra el dictionary
 * (`Translations`) porque las keys residen en `i18n/locales/*`. Los
 * valores literales (quotes, métricas, rubros) están igual que cuando
 * vivían inline en `index.astro`.
 */
import type { Translations } from '../i18n/types';

export interface HomeStat {
  value: string;
  label: string;
  iconKey: string;
}

export interface HomeProcessStep {
  iconKey: string;
  title: string;
  description: string;
}

export interface HomeClientLogo {
  iconKey: string;
  label: string;
  href?: string;
}

export interface HomeCustomerStory {
  quote: string;
  customerName: string;
  customerRole: string;
  metricValue: string;
  metricLabel: string;
  avatarIconKey: string;
}

export interface HomeMaterialFeature {
  iconKey: string;
  label: string;
  description: string;
}

export interface HomeFaqItem {
  question: string;
  answer: string;
}

export interface HomeContent {
  b2bStats: HomeStat[];
  b2bProcessSteps: HomeProcessStep[];
  b2bClientLogos: HomeClientLogo[];
  b2bCustomerStories: HomeCustomerStory[];
  b2bMaterialsFeatures: HomeMaterialFeature[];
  b2bFaqItems: HomeFaqItem[];
  b2cCustomerStories: HomeCustomerStory[];
}

const b2bStats: HomeStat[] = [
  { value: '500+', label: 'Empresas atendidas', iconKey: 'building-office' },
  { value: '10.000+', label: 'Estampados producidos', iconKey: 'cube' },
  { value: '24 h', label: 'Tiempo de respuesta', iconKey: 'bolt' },
  { value: '98%', label: 'Pedidos a tiempo', iconKey: 'shield-check' },
];

const b2bClientLogos: HomeClientLogo[] = [
  { iconKey: 'building-office', label: 'Restaurante La Esquina' },
  { iconKey: 'users', label: 'Escuela Pública Central' },
  { iconKey: 'star', label: 'Festival Verano' },
  { iconKey: 'gift', label: 'ONG Manos Solidarias' },
  { iconKey: 'shopping-cart', label: 'Club Deportivo Norte' },
  { iconKey: 'tag', label: 'Hotel Boutique Sur' },
];

const b2bCustomerStories: HomeCustomerStory[] = [
  {
    quote:
      'Necesitábamos 300 playeras en 5 días para el evento corporativo. Panda entregó 2 días antes y con calidad excelente.',
    customerName: 'María González',
    customerRole: 'Coordinadora de Eventos, Restaurante La Esquina',
    metricValue: '300 unidades',
    metricLabel: 'Entregadas en 3 días',
    avatarIconKey: 'user',
  },
  {
    quote:
      'Los uniformes del colegio se ven impecables. Hicimos 4 pedidos en el año con descuentos por volumen automáticos.',
    customerName: 'Carlos Mendoza',
    customerRole: 'Director Administrativo, Escuela Pública Central',
    metricValue: '1.200 unidades',
    metricLabel: 'Uniformes por ciclo escolar',
    avatarIconKey: 'users',
  },
  {
    quote:
      'Para la temporada recibimos playeras, hoodies y tazas. Una sola cotización consolidada, todo entregado a tiempo.',
    customerName: 'Andrea Ruiz',
    customerRole: 'Coordinadora, Club Deportivo Norte',
    metricValue: '98%',
    metricLabel: 'Pedidos entregados a tiempo',
    avatarIconKey: 'star',
  },
];

// Los use cases B2C son fans / clientes recurrentes, no corporativo,
// para que el tono del testimonials ring con la audiencia B2C.
const b2cCustomerStories: HomeCustomerStory[] = [
  {
    quote:
      'Encontré la figura de Saber que buscaba hace años. Llegó perfectamente empacada y con todos los accesorios originales.',
    customerName: 'Marcos R.',
    customerRole: 'Coleccionista otaku',
    metricValue: '200+',
    metricLabel: 'compras en la tienda',
    avatarIconKey: 'user',
  },
  {
    quote:
      'Las camisetas son de calidad increíble. Compré 5 para mi familia en eventos de la comunidad y todos quedaron encantados.',
    customerName: 'Ana L.',
    customerRole: 'Cliente recurrente',
    metricValue: '98%',
    metricLabel: 'recomienda la tienda',
    avatarIconKey: 'users',
  },
  {
    quote:
      'Tuve un problema con un envío y el servicio al cliente me lo resolvió en horas. Eso vale más que el producto mismo.',
    customerName: 'Diego M.',
    customerRole: 'Cliente',
    metricValue: '24h',
    metricLabel: 'tiempo de respuesta',
    avatarIconKey: 'star',
  },
];

function buildB2bProcessSteps(t: Translations): HomeProcessStep[] {
  return [
    {
      iconKey: 'document-text',
      title: t['storefront.b2b.home.process.step1.title'],
      description: t['storefront.b2b.home.process.step1.description'],
    },
    {
      iconKey: 'eye',
      title: t['storefront.b2b.home.process.step2.title'],
      description: t['storefront.b2b.home.process.step2.description'],
    },
    {
      iconKey: 'cube',
      title: t['storefront.b2b.home.process.step3.title'],
      description: t['storefront.b2b.home.process.step3.description'],
    },
    {
      iconKey: 'truck',
      title: t['storefront.b2b.home.process.step4.title'],
      description: t['storefront.b2b.home.process.step4.description'],
    },
  ];
}

function buildB2bMaterialsFeatures(t: Translations): HomeMaterialFeature[] {
  return [
    {
      iconKey: 'shield-check',
      label: t['storefront.b2b.home.materials.feature1.label'],
      description: t['storefront.b2b.home.materials.feature1.description'],
    },
    {
      iconKey: 'eye',
      label: t['storefront.b2b.home.materials.feature2.label'],
      description: t['storefront.b2b.home.materials.feature2.description'],
    },
    {
      iconKey: 'cog-6-tooth',
      label: t['storefront.b2b.home.materials.feature3.label'],
      description: t['storefront.b2b.home.materials.feature3.description'],
    },
  ];
}

function buildB2bFaqItems(t: Translations): HomeFaqItem[] {
  return [
    {
      question: t['storefront.b2b.home.faq.q1.question'],
      answer: t['storefront.b2b.home.faq.q1.answer'],
    },
    {
      question: t['storefront.b2b.home.faq.q2.question'],
      answer: t['storefront.b2b.home.faq.q2.answer'],
    },
    {
      question: t['storefront.b2b.home.faq.q3.question'],
      answer: t['storefront.b2b.home.faq.q3.answer'],
    },
    {
      question: t['storefront.b2b.home.faq.q4.question'],
      answer: t['storefront.b2b.home.faq.q4.answer'],
    },
    {
      question: t['storefront.b2b.home.faq.q5.question'],
      answer: t['storefront.b2b.home.faq.q5.answer'],
    },
  ];
}

export function getHomeContent(t: Translations): HomeContent {
  return {
    b2bStats,
    b2bProcessSteps: buildB2bProcessSteps(t),
    b2bClientLogos,
    b2bCustomerStories,
    b2bMaterialsFeatures: buildB2bMaterialsFeatures(t),
    b2bFaqItems: buildB2bFaqItems(t),
    b2cCustomerStories,
  };
}
