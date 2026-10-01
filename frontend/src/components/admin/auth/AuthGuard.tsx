import { useEffect, type ReactElement } from 'react';
import { authStore, useAuth } from '../../../stores/auth';
import { useTranslations } from '../../../i18n';
import { isPublicAdminPath, stripLocaleFromPath, withLocale } from '../../../lib/path-helpers';

/**
 * Guard client-side para rutas admin.
 *
 * El token vive en localStorage (v\u00eda `stores/auth`), as\u00ed que este
 * guard corre en el cliente y redirige v\u00eda `window.location` si
 * el usuario no est\u00e1 autenticado.
 *
 * Locale awareness (sdd/critical-2fa-frontend-flow · CR2):
 *   - Astro i18n (`prefixDefaultLocale: true`) genera URLs
 *     `/<locale>/admin/...` para ambos locales.
 *   - El pathname del usuario conserva el locale al redirigir al
 *     login para que el language switcher no lo "pierda".
 *   - `PUBLIC_ADMIN_PATHS` cubre login + forgot + reset + reset-success
 *     + 2fa (ver `lib/path-helpers.ts`).
 */
export default function AuthGuard(): ReactElement | null {
  const { isAuthenticated, isHydrating } = useAuth();
  const { t } = useTranslations();

  useEffect(() => {
    void authStore.hydrate();
  }, []);

  useEffect(() => {
    if (typeof window === 'undefined') {
      return;
    }

    const { locale, barePath } = stripLocaleFromPath(window.location.pathname);

    if (isPublicAdminPath(barePath)) {
      return;
    }

    if (isHydrating) {
      return;
    }

    if (!isAuthenticated) {
      // Preservamos el pathname completo (incluido locale + query) en
      // `?redirect=`. El decode lo hace el LoginForm en el destino.
      const redirect = encodeURIComponent(window.location.pathname);
      const destination = `${withLocale('/account/login', locale)}?redirect=${redirect}`;
      window.location.replace(destination);
    }
  }, [isAuthenticated, isHydrating]);

  const { barePath } =
    typeof window === 'undefined'
      ? { barePath: '/' }
      : stripLocaleFromPath(window.location.pathname);

  if (isHydrating && !isPublicAdminPath(barePath)) {
    return (
      <div
        role="status"
        className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950 text-sm text-slate-400"
      >
        {t('auth.guard.redirecting')}
      </div>
    );
  }

  return null;
}
