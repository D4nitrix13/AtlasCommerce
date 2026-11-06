import type { ReactElement } from 'react';
import { GOOGLE_ICON_PATHS, SOCIAL_ICON_PATHS } from './social-icon-paths';

interface IconProps {
  /**
   * Tamaño del SVG en píxeles. Por defecto 18 para mantener consistencia
   * con los SVGs inline que existían antes en StorefrontLoginForm y
   * RegisterForm.
   */
  size?: number;
  /**
   * Clases Tailwind adicionales para el SVG (e.g. `size-5`, `text-white`).
   * Aplicado al elemento raiz.
   */
  className?: string;
}

/**
 * Icon de Google (g-brand multicolor) extraído a un componente reutilizable.
 *
 * Antes: el SVG estaba inline-duplicado en `LoginForm`, `StorefrontLoginForm`
 * y `RegisterForm`. Ahora `LoginForm` admin NO usa social buttons (Task
 * 2.3) pero storefront + signup sí. Esta extracción elimina la
 * duplicación para los dos consumidores restantes.
 */
export function GoogleIcon({ size = 18, className }: IconProps): ReactElement {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
      className={className}
      dangerouslySetInnerHTML={{ __html: GOOGLE_ICON_PATHS.join('') }}
    />
  );
}

/**
 * Icon de Facebook (brand azul). El glifo viene del módulo compartido
 * (mismo path que el footer); el azul lo pone este componente via fill
 * en el `<svg>`, que el path hereda por ser fill-less.
 */
export function FacebookIcon({ size = 18, className }: IconProps): ReactElement {
  return (
    <svg
      width={size}
      height={size}
      viewBox="0 0 24 24"
      xmlns="http://www.w3.org/2000/svg"
      aria-hidden="true"
      className={className}
      fill="#1877F2"
      dangerouslySetInnerHTML={{ __html: SOCIAL_ICON_PATHS.facebook }}
    />
  );
}
