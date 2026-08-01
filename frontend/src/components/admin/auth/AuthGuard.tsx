import { useEffect, type ReactElement } from "react";
import { authStore, useAuth } from "../../../stores/auth";
import { useTranslations } from "../../../i18n";

const PUBLIC_ADMIN_PATHS = new Set(["/admin/auth/login"]);

function isPublicAdminPath(pathname: string): boolean {
  return PUBLIC_ADMIN_PATHS.has(pathname);
}

/**
 * Guard client-side para rutas admin.
 *
 * El token vive en localStorage (vía `stores/auth`), así que este
 * guard corre en el cliente y redirige vía `window.location` si
 * el usuario no está autenticado.
 */
export default function AuthGuard(): ReactElement | null {
  const { isAuthenticated, isHydrating } = useAuth();
  const { t } = useTranslations();

  useEffect(() => {
    void authStore.hydrate();
  }, []);

  useEffect(() => {
    if (typeof window === "undefined") {
      return;
    }

    const pathname = window.location.pathname;

    if (isPublicAdminPath(pathname)) {
      return;
    }

    if (isHydrating) {
      return;
    }

    if (!isAuthenticated) {
      // El pathname ya incluye `/es/` o `/en/` — lo preservamos.
      const redirect = encodeURIComponent(pathname);
      window.location.replace(`/admin/auth/login?redirect=${redirect}`);
    }
  }, [isAuthenticated, isHydrating]);

  if (isHydrating && !isPublicAdminPath(window.location.pathname)) {
    return (
      <div
        role="status"
        className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950 text-sm text-slate-400"
      >
        {t("auth.guard.redirecting")}
      </div>
    );
  }

  return null;
}