import { useEffect, type ReactElement } from 'react';
import { authStore, isAdminUser, useAuth } from '../../stores/auth';
import { useTranslations } from '../../i18n';
import { stripLocaleFromPath, withLocale } from '../../lib/path-helpers';

/**
 * Componente client-only que decide el redirect de `/admin/`:
 *   - autenticado + rol admin/staff → `/admin/dashboard`
 *   - autenticado + otro rol (Customer, etc) → `/account` (customer area)
 *   - no autenticado → `/account/login?redirect=<current path>`
 *
 * Hidrata el auth store al mount (lee token de localStorage) y decide
 * el destino una vez que `isHydrating === false`. Mientras tanto,
 * muestra un skeleton neutro para evitar flashing.
 *
 * Diferencia con `AuthGuard`: `AuthGuard` se monta en páginas
 * internas del admin y redirige SOLO si no está autenticado.
 * `AdminHomeRedirect` se monta en el entrypoint del admin (`/admin/`)
 * y decide el destino según auth + role.
 *
 * F0.5 — agregado el role-check: Customer que tipea `/admin/` no debe
 * caer en el dashboard aunque esté autenticado con el User store
 * (defensa en profundidad — el backend ya rechaza en F0.1).
 */
export default function AdminHomeRedirect(): ReactElement {
  const { user, isAuthenticated, isHydrating } = useAuth();
  const { t } = useTranslations();

  useEffect(() => {
    void authStore.hydrate();
  }, []);

  useEffect(() => {
    if (typeof window === 'undefined') return;
    if (isHydrating) return;

    const { locale, barePath } = stripLocaleFromPath(window.location.pathname);

    let target: string;
    if (!isAuthenticated || !isAdminUser(user)) {
      // No autenticado O no es admin/staff — enviarlo al área de cliente.
      // Mantenemos `?redirect=` solo cuando es "no autenticado" (es un
      // login con vuelta atrás); cuando es "no es admin" mandamos al
      // dashboard de cliente sin redirect (no tiene sentido volver a
      // /admin/ — sigue sin tener acceso).
      target = isAuthenticated
        ? withLocale('/account', locale)
        : `${withLocale('/account/login', locale)}?redirect=${encodeURIComponent(barePath)}`;
    } else {
      target = withLocale('/admin/dashboard', locale);
    }

    window.location.replace(target);
  }, [isAuthenticated, isHydrating, user]);

  return (
    <div
      role="status"
      className="flex min-h-screen items-center justify-center bg-slate-950 text-sm text-slate-400"
    >
      {isHydrating ? t('auth.guard.hydrating') : t('auth.guard.redirecting')}
    </div>
  );
}
