/**
 * Tipos compartidos para ProductFAQ — extraídos para que las
 * stories y futuros consumers (admin) puedan importar el shape sin
 * duplicar la unión de tipos en el archivo `.astro`.
 *
 * El shape reusa el contrato `question` + `answer` de `FaqAccordion`
 * para que la PDP pueda pasar las FAQs directamente al componente
 * genérico sin mapping intermedio.
 */
export interface ProductFAQItem {
  /** Pregunta del acordeón (heading del summary). */
  question: string;
  /** Respuesta del acordeón (cuerpo del detail). */
  answer: string;
}

/**
 * Labels de UI (i18n) consumidos por el ProductFAQ. Vivir en el
 * shape del componente (no como prop suelta) permite pasar el bundle
 * de traducciones completo cuando se cargan traducciones dinámicas.
 */
export interface ProductFAQLabels {
  /** Heading visible de la sección (ej. "Preguntas frecuentes"). */
  heading: string;
}