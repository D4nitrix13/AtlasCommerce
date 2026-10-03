/**
 * StoresAdmin — Panel admin de tiendas (RF-STOR-132).
 *
 * Lista las tiendas accesibles al admin y permite:
 *  - Ver estado de mantenimiento por tienda.
 *  - Toggle del modo mantenimiento (POST /api/admin/stores/{id}/maintenance).
 *  - Indicar visualmente cuando el storefront está caído.
 *
 * Aislamiento: el backend filtra por tenant via `accessibleStoreIds()`.
 * Si una tienda está inactiva (`is_active=false`), el switch queda
 * deshabilitado (no se puede activar maintenance sobre tienda desactivada).
 */
import { useEffect, useState, type ReactElement } from 'react';
import { apiFetch, type ApiError } from '../../../lib/api';
import { API_ENDPOINTS } from '../../../lib/endpoints';
import { useTranslations } from '../../../i18n';
import type { TranslationKey } from '../../../i18n/types';

function resolveErrorKey(error: string): TranslationKey {
  switch (error) {
    case 'forbidden':
      return 'admin.stores.forbidden';
    case 'generic':
      return 'admin.stores.generic';
    default:
      return 'admin.stores.error';
  }
}

interface Store {
  id: number;
  name: string;
  slug: string;
  is_active: boolean;
  maintenance_mode: boolean;
}

interface StoresAdminProps {
  locale: string;
}

export default function StoresAdmin({ locale: _locale }: StoresAdminProps): ReactElement {
  const { t } = useTranslations();
  const [stores, setStores] = useState<Store[] | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [pendingId, setPendingId] = useState<number | null>(null);

  useEffect(() => {
    let cancelled = false;
    void (async (): Promise<void> => {
      try {
        const response = await apiFetch<{
          data: Store[];
          meta: { current_page: number; per_page: number; total: number };
        }>(API_ENDPOINTS.admin.stores);
        if (!cancelled) {
          setStores(response.data);
        }
      } catch {
        if (!cancelled) {
          setError('error');
        }
      }
    })();
    return () => {
      cancelled = true;
    };
  }, []);

  const handleToggle = async (store: Store): Promise<void> => {
    setPendingId(store.id);
    setError(null);
    try {
      const response = await apiFetch<{ data: Store }>(
        `${API_ENDPOINTS.admin.stores}/${store.id}/maintenance`,
        {
          method: 'POST',
          body: { enabled: !store.maintenance_mode },
        }
      );
      setStores((prev) =>
        prev === null ? prev : prev.map((s) => (s.id === store.id ? response.data : s))
      );
    } catch (error_) {
      const apiError = error_ as ApiError;
      setError(apiError.status === 403 ? 'forbidden' : 'generic');
    } finally {
      setPendingId(null);
    }
  };

  if (error !== null) {
    return (
      <div className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
        {t(resolveErrorKey(error))}
      </div>
    );
  }

  if (stores === null) {
    return (
      <p className="text-sm text-neutral-500" data-testid="stores-loading">
        {t('admin.stores.loading')}
      </p>
    );
  }

  if (stores.length === 0) {
    return (
      <p className="rounded-lg border border-dashed border-neutral-300 bg-neutral-50 p-6 text-center text-sm text-neutral-600">
        {t('admin.stores.empty')}
      </p>
    );
  }

  return (
    <div className="space-y-3">
      {stores.map((store) => {
        const isPending = pendingId === store.id;
        const isDisabled = !store.is_active;
        return (
          <article
            key={store.id}
            className="flex items-center justify-between gap-4 rounded-lg border border-neutral-200 bg-white p-4 shadow-sm"
            data-testid={`store-row-${store.id}`}
          >
            <div>
              <h3 className="text-base font-medium text-neutral-900">{store.name}</h3>
              <p className="text-xs text-neutral-500">{store.slug}</p>
              {isDisabled && (
                <p className="mt-1 text-xs text-rose-600">{t('admin.stores.disabled')}</p>
              )}
            </div>
            <div className="flex items-center gap-3">
              <span
                className={`text-xs font-medium ${
                  store.maintenance_mode ? 'text-amber-700' : 'text-emerald-700'
                }`}
                data-testid={`store-status-${store.id}`}
              >
                {t(
                  store.maintenance_mode
                    ? 'admin.stores.maintenanceOn'
                    : 'admin.stores.maintenanceOff'
                )}
              </span>
              <button
                type="button"
                onClick={() => void handleToggle(store)}
                disabled={isPending || isDisabled}
                className="rounded-md bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-neutral-800 disabled:cursor-not-allowed disabled:bg-neutral-400"
                data-testid={`store-toggle-${store.id}`}
              >
                {t(
                  store.maintenance_mode
                    ? 'admin.stores.disableMaintenance'
                    : 'admin.stores.enableMaintenance'
                )}
              </button>
            </div>
          </article>
        );
      })}
    </div>
  );
}
