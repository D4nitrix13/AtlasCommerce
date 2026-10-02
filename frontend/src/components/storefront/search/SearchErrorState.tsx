import type { ReactElement } from 'react';
interface Props {
  message: string;
}

/**
 * Estado de error — fondo rojo, mensaje del backend o fallback genérico.
 * `role="alert"` para que screen readers lo anuncien.
 */
export default function SearchErrorState({ message }: Props): ReactElement {
  return (
    <p
      role="alert"
      className="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"
    >
      {message}
    </p>
  );
}
