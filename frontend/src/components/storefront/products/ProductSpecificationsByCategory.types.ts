/**
 * Tipos compartidos para ProductSpecificationsByCategory — extraídos
 * para que las stories puedan importar el shape sin duplicar la unión
 * de iconos o shapes en el archivo `.astro`.
 */
export interface ProductSpecificationsByCategoryItem {
  label: string;
  value: string;
}

export interface ProductSpecificationsByCategoryGroup {
  category: string;
  items: readonly ProductSpecificationsByCategoryItem[];
}