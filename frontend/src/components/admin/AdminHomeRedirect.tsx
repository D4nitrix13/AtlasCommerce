import { useEffect, type ReactElement } from 'react';
import { authStore, useAuth } from '../../stores/auth';
import { useTranslations } from '../../i18n';
import { stripLocaleFromPath, withLocale } from '../../lib/path-helpers';

/**
 * Componente client-only que decide el redirect de `/admin/`:
 *   - autenticado  → `/admin/dashboard`
 *   - no autenticado → `/auth/login?redirect=<current path>`
 *
 * Hidrata el auth store al mount (lee token de localStorage) y decide
 * el destino una vez que `isHydrating === false`. Mientras tanto,
 * muestra un skeleton neutro para evitar flashing.
 *
 * Diferencia con `AuthGuard`: `AuthGuard` se monta en páginas
 * internas del admin y redirige SOLO si no está autenticado.
 * `AdminHomeRedirect` se monta en el entrypoint del admin (`/admin/`)
 * y decide el destino según auth (login o dashboard).
 */
export default function AdminHomeRedirect(): ReactElement {
  const { isAuthenticated, isHydrating } = useAuth();
  const { t } = useTranslations();

  useEffect(() => {
    void authStore.hydrate();
  }, []);

  useEffect(() => {
    if (typeof window === 'undefined') return;
    if (isHydrating) return;

    const { locale, barePath } = stripLocaleFromPath(window.location.pathname);
    const target = isAuthenticated
      ? withLocale('/admin/dashboard', locale)
      : `${withLocale('/auth/login', locale)}?redirect=${encodeURIComponent(barePath)}`;
    window.location.replace(target);
  }, [isAuthenticated, isHydrating]);

  return (
    <div
      role="status"
      className="flex min-h-screen items-center justify-center bg-slate-950 text-sm text-slate-400"
    >
      {isHydrating ? t('auth.guard.hydrating') : t('auth.guard.redirecting')}
    </div>
  );
}
