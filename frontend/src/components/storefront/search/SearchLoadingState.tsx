import type { ReactElement } from 'react';
interface Props {
  message: string;
}

/**
 * Estado loading — se muestra mientras el debounce espera o el backend
 * responde. No incluye spinner animado por ahora; sólo un texto claro.
 * Si el patrón se repite en otras páginas, vale la pena extraer un
 * componente `LoadingState` compartido.
 */
export default function SearchLoadingState({ message }: Props): ReactElement {
  return (
    <p className="rounded-lg border border-slate-200 bg-slate-50 px-4 py-8 text-center text-slate-500">
      {message}
    </p>
  );
}
