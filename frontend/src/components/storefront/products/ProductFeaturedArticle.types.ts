/**
 * Props de ProductFeaturedArticle.
 *
 * Mantenido en archivo separado para que las stories y otros
 * consumidores puedan importar el shape sin tocar el .astro.
 */
export interface ProductFeaturedArticleProps {
  /** Etiqueta eyebrow encima del título (ej. "Gibson Gear Guide"). */
  eyebrow: string;
  /** Título overlay sobre la imagen. */
  title: string;
  /** URL de la imagen (puede ser data URI). */
  imageUrl: string;
  /** Alt accesible — cae a `title` si no se provee. */
  imageAlt?: string;
  /** Destino del enlace. */
  href: string;
  /** Label del CTA (ej. "Leer artículo"). */
  ctaLabel?: string;
}