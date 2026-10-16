import { useEffect, type ReactElement } from 'react';
import { authStore, isAdminUser, useAuth } from '../../../stores/auth';
import { useTranslations } from '../../../i18n';
import { isPublicAdminPath, stripLocaleFromPath, withLocale } from '../../../lib/path-helpers';

/**
 * Guard client-side para rutas admin.
 *
 * El token vive en cookie httpOnly de Sanctum (CR2), así que este
 * guard corre en el cliente y redirige vía `window.location` si
 * el usuario no está autenticado.
 *
 * Locale awareness (sdd/critical-2fa-frontend-flow · CR2):
 *   - Astro i18n (`prefixDefaultLocale: true`) genera URLs
 *     `/<locale>/admin/...` para ambos locales.
 *   - El pathname del usuario conserva el locale al redirigir al
 *     login para que el language switcher no lo "pierda".
 *   - `PUBLIC_ADMIN_PATHS` cubre login + forgot + reset + reset-success
 *     + 2fa (ver `lib/path-helpers.ts`).
 *
 * F0.5 — role-check agregado: además de autenticado, el user debe
 * tener `role.slug` en `ADMIN_ROLE_SLUGS`. Si es Customer (o cualquier
 * user sin rol admin/staff), se redirige al dashboard de cliente
 * `/account`. Defensa en profundidad — el backend ya rechaza en F0.1
 * con `EnsureUserHasRole`.
 */
export default function AuthGuard(): ReactElement | null {
  const { user, isAuthenticated, isHydrating } = useAuth();
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
      // No autenticado → enviarlo al login preservando el pathname
      // actual (incluido locale + query) en `?redirect=`. El decode
      // lo hace el LoginForm en el destino.
      const redirect = encodeURIComponent(window.location.pathname);
      const destination = `${withLocale('/account/login', locale)}?redirect=${redirect}`;
      window.location.replace(destination);
      return;
    }

    if (!isAdminUser(user)) {
      // Autenticado pero sin permiso admin → área de cliente. Sin
      // `?redirect=` porque ya sabemos que no debe estar en /admin/*.
      window.location.replace(withLocale('/account', locale));
    }
  }, [isAuthenticated, isHydrating, user]);

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
