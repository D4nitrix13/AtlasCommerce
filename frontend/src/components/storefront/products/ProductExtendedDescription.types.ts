/**
 * Tipos compartidos para ProductExtendedDescription — extraídos para
 * que las stories y futuros consumers (admin) puedan importar el
 * shape sin duplicar la unión de tipos en el archivo `.astro`.
 *
 * El shape es `title` + `body` porque el bloque soporta múltiples
 * párrafos temáticos por producto (ej. "The Les Paul, llevada al
 * extremo" + bloque histórico).
 */
export interface ProductExtendedDescriptionItem {
  /** Heading visible del bloque. */
  title: string;
  /** Cuerpo del bloque (puede ser párrafo largo). */
  body: string;
}

/**
 * Labels de UI (i18n) consumidos por el componente.
 */
export interface ProductExtendedDescriptionLabels {
  /** Heading principal de la sección. */
  heading: string;
}