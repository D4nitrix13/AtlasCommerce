/**
 * ProductFeaturedArticle — bloque de artículo/video destacado estilo Gibson.
 *
 * Layout: imagen 16/9 + overlay oscuro + eyebrow + título + CTA.
 */
import type { Meta, StoryObj } from '@storybook/astro';
import ProductFeaturedArticle from './ProductFeaturedArticle.astro';
import type { ProductFeaturedArticleProps } from './ProductFeaturedArticle.types';

const meta: Meta<typeof ProductFeaturedArticle> = {
  title: 'Storefront/Products/ProductFeaturedArticle',
  component: ProductFeaturedArticle,
  tags: ['autodocs'],
};

export default meta;
type Story = StoryObj<typeof meta>;

const PLACEHOLDER_IMAGE =
  'data:image/svg+xml;utf8,' +
  encodeURIComponent(
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1600 900"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#0f172a"/><stop offset="100%" stop-color="#475569"/></linearGradient></defs><rect width="1600" height="900" fill="url(#g)"/><circle cx="800" cy="450" r="180" fill="#cbd5e1"/></svg>`
  );

const DEFAULT_ARGS: ProductFeaturedArticleProps = {
  eyebrow: 'Gibson Gear Guide',
  title: 'Gibson Les Paul SUPREME Series — Full Demo & Overview',
  imageUrl: PLACEHOLDER_IMAGE,
  href: '#',
  ctaLabel: 'Leer artículo',
};

export const Default: Story = { args: { ...DEFAULT_ARGS } };

export const WithoutCta: Story = {
  args: { ...DEFAULT_ARGS, ctaLabel: '' },
};

export const EnglishLocale: Story = {
  args: {
    ...DEFAULT_ARGS,
    eyebrow: 'Gear Guide',
    title: 'Les Paul SUPREME — Demo',
    ctaLabel: 'Read article',
  },
};