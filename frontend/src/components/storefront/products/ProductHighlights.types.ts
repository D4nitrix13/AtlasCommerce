/**
 * Tipos compartidos para ProductHighlights — extraídos para que las
 * stories puedan importar el shape sin tener que duplicar la unión de
 * iconos en el archivo `.astro`.
 *
 * Los keys de iconos están en ESPAÑOL (premio, destello, regla, escudo,
 * estrella, musica) para que el shape del mock sea autoexplicativo en
 * el codebase, que es es-MX por convención del proyecto.
 */
export interface ProductHighlightsItem {
  icon: 'premio' | 'destello' | 'regla' | 'escudo' | 'estrella' | 'musica';
  label: string;
}
