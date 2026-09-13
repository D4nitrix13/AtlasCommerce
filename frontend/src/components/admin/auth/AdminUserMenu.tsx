import { useState, type ReactElement } from 'react';
import { authStore, useAuth } from '../../../stores/auth';
import { useTranslations } from '../../../i18n';
import LanguageSwitcher from '../../ui/LanguageSwitcher';

/**
 * Header de la sidebar admin: brand + email + rol del user, language
 * switcher y botón de logout.
 */
export default function AdminUserMenu(): ReactElement {
  const { user } = useAuth();
  const { t } = useTranslations();
  const [isLoggingOut, setIsLoggingOut] = useState(false);

  const handleLogout = async (): Promise<void> => {
    setIsLoggingOut(true);

    try {
      await authStore.logout();
    } finally {
      // Preserva el locale en la URL post-logout.
      const localeMatch = window.location.pathname.match(/^\/(es|en)/);
      const localePrefix = localeMatch !== null ? localeMatch[0] : '/es';
      window.location.href = `${localePrefix}/auth/login`;
    }
  };

  if (user === null) {
    return (
      <div className="flex flex-col gap-3 px-3 py-3 text-xs text-slate-400">
        <span>{t('auth.userMenu.loading')}</span>
        <LanguageSwitcher />
      </div>
    );
  }

  const roleLabel = user.role?.name ?? '—';

  return (
    <div className="flex flex-col gap-3 px-3 py-3">
      <div className="flex flex-col gap-0.5 text-xs">
        <span className="font-semibold text-slate-900">{user.full_name}</span>
        <span className="truncate text-slate-500" title={user.email}>
          {user.email}
        </span>
        <span className="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-slate-700">
          {roleLabel}
        </span>
      </div>

      <button
        type="button"
        onClick={() => {
          void handleLogout();
        }}
        disabled={isLoggingOut}
        className="rounded-md border border-slate-300 bg-white px-2 py-1.5 text-xs font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
      >
        {isLoggingOut ? t('auth.logout.loading') : t('auth.logout.button')}
      </button>

      <LanguageSwitcher />
    </div>
  );
}
