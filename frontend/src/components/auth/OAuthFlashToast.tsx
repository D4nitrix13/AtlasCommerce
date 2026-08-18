// @vitest-environment jsdom
import { type ReactElement, useEffect, useState } from 'react';
import { useTranslations } from '../../i18n';

/**
 * Toast que muestra errores OAuth desde el callback del provider.
 *
 * El backend (PR 2b) hace `redirect()->to('/admin/auth/login?flash=oauth_xxx')`
 * en cada `OAuthException`. Este componente lee `?flash=` en mount,
 * muestra un toast con el i18n key `auth.login.error.{code}` y luego
 * strippea el query param vía `history.replaceState` (no reload).
 *
 * DRY: compartido por admin login + account login (design Q #5).
 * Cualquier página que pueda recibir redirect de OAuth lo monta
 * con `<OAuthFlashToast client:load />`.
 */
export default function OAuthFlashToast(): ReactElement {
  const { t } = useTranslations();
  const [code, setCode] = useState<string | null>(null);

  useEffect(() => {
    if (typeof window === 'undefined') return;

    const params = new URLSearchParams(window.location.search);
    const flashCode = params.get('flash');
    if (flashCode === null || flashCode === '') return;

    setCode(flashCode);

    // Strip the query param without navigating.
    params.delete('flash');
    const remaining = params.toString();
    const cleanUrl = `${window.location.pathname}${remaining !== '' ? `?${remaining}` : ''}`;
    window.history.replaceState({}, '', cleanUrl);
  }, []);

  if (code === null) {
    return <></>;
  }

  // El backend emite códigos estables (oauth_email_conflict, etc.).
  // Si la traducción específica no existe (futuro code no agregado),
  // caemos al generic fallback. Detectamos el miss comparando
  // con el key (i18n.t() devuelve el key cuando falta).
  const i18nKey = `auth.login.error.${code}`;
  const specificMessage = t(i18nKey as Parameters<typeof t>[0]);
  const message = specificMessage === i18nKey ? t('auth.login.error.generic') : specificMessage;

  return (
    <div
      role="alert"
      data-testid="oauth-flash-toast"
      data-flash-code={code}
      className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800"
    >
      {message}
    </div>
  );
}
