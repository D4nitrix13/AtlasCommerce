import { useEffect, type ReactElement } from 'react';
import { authStore, useAuth } from '../../../stores/auth';
import { useTranslations } from '../../../i18n';
import { isPublicAdminPath, stripLocaleFromPath, withLocale } from '../../../lib/path-helpers';

/**
 * Guard client-side para rutas `/account/*` del cliente.
 *
 * Espejo de `components/admin/auth/AuthGuard` para la sección post-login
 * del cliente (dashboard, orders, wishlist, security, etc.). El token
 * vive en cookie httpOnly de Sanctum (CR2), así que este guard corre
 * en el cliente y redirige vía `window.location` si el usuario no
 * está autenticado.
 *
 * Mount:
 *   - El layout `AccountLayout.astro` lo wrappea (F0.4) — no es
 *   necesario agregarlo en cada page. Las páginas de auth (login,
 *   signup, forgot, reset, 2fa) usan `AuthLayout` y NO pasan por acá.
 *   - Las rutas públicas bajo `/account/*` (login, signup, etc.) están
 *   cubiertas por `PUBLIC_ADMIN_PATHS` (ver `lib/path-helpers.ts`) y
 *   el redirect se saltea para no entrar en loop.
 *
 * Locale awareness:
 *   - Astro i18n (`prefixDefaultLocale: true`) genera URLs
 *   `/<locale>/account/...` para ambos locales.
 *   - El pathname del usuario conserva el locale al redirigir al
 *   login para que el language switcher no lo "pierda".
 */
export default function AccountAuthGuard(): ReactElement | null {
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

    // Las páginas /account/login, /account/signup, etc. son públicas.
    // Si el guard corre en una de ellas (p.ej. por un render anidado
    // o un layout compartido), NO redirigir — sería un loop.
    if (isPublicAdminPath(barePath)) {
      return;
    }

    if (isHydrating) {
      return;
    }

    if (!isAuthenticated) {
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
        className="fixed inset-0 z-50 flex items-center justify-center bg-slate-50 text-sm text-slate-500"
      >
        {t('auth.guard.redirecting')}
      </div>
    );
  }

  return null;
}