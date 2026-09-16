/**
 * CustomersAdmin — Panel admin de clientes (RF-CUST-040).
 *
 * Lista clientes del tenant activo con:
 *  - Filtros: q (búsqueda por nombre/email), customer_type, is_active.
 *  - Modal de detalle con order_count + lifetime_value_cents.
 *  - Acciones: suspender / reactivar (con confirmación).
 *
 * Aislamiento: el backend filtra por tenant via `customer_stores` pivot
 * (`accessibleStoreIds` filtra customers que tengan al menos una tienda
 * accesible al admin). El frontend no aplica su propio filtro.
 */
import { useEffect, useState, type ReactElement } from 'react';
import { apiFetch } from '../../../lib/api';
import { API_ENDPOINTS } from '../../../lib/endpoints';
import { useTranslations } from '../../../i18n';

type CustomerType = 'retail' | 'wholesale' | 'vip';

interface CustomerSummary {
  id: number;
  customer_type: CustomerType;
  first_name: string;
  last_name: string;
  full_name: string;
  email: string;
  is_active: boolean;
}

interface CustomerDetail extends CustomerSummary {
  phone: string | null;
  registered_at: string | null;
  is_anonymized: boolean;
  order_count: number | null;
  lifetime_value_cents: number | null;
}

interface PageMeta {
  current_page: number;
  per_page: number;
  total: number;
  last_page: number;
}

interface Filters {
  q: string;
  customer_type: '' | CustomerType;
  is_active: '' | 'true' | 'false';
}

const EMPTY_FILTERS: Filters = { q: '', customer_type: '', is_active: '' };

export default function CustomersAdmin(): ReactElement {
  const { t } = useTranslations();
  const [filters, setFilters] = useState<Filters>(EMPTY_FILTERS);
  const [applied, setApplied] = useState<Filters>(EMPTY_FILTERS);
  const [customers, setCustomers] = useState<CustomerSummary[] | null>(null);
  const [meta, setMeta] = useState<PageMeta | null>(null);
  const [loadError, setLoadError] = useState<boolean>(false);
  const [selected, setSelected] = useState<CustomerDetail | null>(null);
  const [pendingId, setPendingId] = useState<number | null>(null);

  const fetchPage = async (page: number, f: Filters): Promise<void> => {
    const params = new URLSearchParams();
    params.set('page', String(page));
    if (f.q !== '') params.set('q', f.q);
    if (f.customer_type !== '') params.set('customer_type', f.customer_type);
    if (f.is_active !== '') params.set('is_active', f.is_active);

    const response = await apiFetch<{
      data: CustomerSummary[];
      meta: PageMeta;
    }>(`${API_ENDPOINTS.admin.customers}?${params.toString()}`);

    setCustomers(response.data);
    setMeta(response.meta);
  };

  useEffect(() => {
    let cancelled = false;
    void (async (): Promise<void> => {
      try {
        await fetchPage(1, applied);
        if (!cancelled) setLoadError(false);
      } catch {
        if (!cancelled) setLoadError(true);
      }
    })();
    return () => {
      cancelled = true;
    };
  }, [applied]);

  const handleApplyFilters = (event: React.FormEvent<HTMLFormElement>): void => {
    event.preventDefault();
    setApplied(filters);
  };

  const handleClearFilters = (): void => {
    setFilters(EMPTY_FILTERS);
    setApplied(EMPTY_FILTERS);
  };

  const openDetail = async (customer: CustomerSummary): Promise<void> => {
    setSelected(null);
    try {
      const response = await apiFetch<{ data: CustomerDetail }>(
        API_ENDPOINTS.admin.customer(customer.id),
      );
      setSelected(response.data);
    } catch {
      setLoadError(true);
    }
  };

  const closeDetail = (): void => {
    setSelected(null);
  };

  const handleSuspend = async (customer: CustomerSummary): Promise<void> => {
    if (!window.confirm(t('admin.customers.confirmSuspend'))) return;
    setPendingId(customer.id);
    try {
      await apiFetch(API_ENDPOINTS.admin.customerSuspend(customer.id), {
        method: 'POST',
      });
      await fetchPage(meta?.current_page ?? 1, applied);
    } finally {
      setPendingId(null);
    }
  };

  const handleReactivate = async (customer: CustomerSummary): Promise<void> => {
    if (!window.confirm(t('admin.customers.confirmReactivate'))) return;
    setPendingId(customer.id);
    try {
      await apiFetch(API_ENDPOINTS.admin.customerReactivate(customer.id), {
        method: 'POST',
      });
      await fetchPage(meta?.current_page ?? 1, applied);
    } finally {
      setPendingId(null);
    }
  };

  if (loadError) {
    return (
      <div className="rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
        {t('admin.customers.error')}
      </div>
    );
  }

  return (
    <div className="space-y-4">
      <form
        onSubmit={handleApplyFilters}
        className="grid gap-2 rounded-lg border border-neutral-200 bg-white p-3 sm:grid-cols-4"
      >
        <input
          type="text"
          value={filters.q}
          onChange={(event) => setFilters({ ...filters, q: event.target.value })}
          placeholder={t('admin.customers.filters.search')}
          className="rounded-md border border-neutral-300 px-3 py-2 text-sm"
          data-testid="customers-filter-search"
        />
        <select
          value={filters.customer_type}
          onChange={(event) =>
            setFilters({
              ...filters,
              customer_type: event.target.value as Filters['customer_type'],
            })
          }
          className="rounded-md border border-neutral-300 px-3 py-2 text-sm"
          data-testid="customers-filter-type"
        >
          <option value="">{t('admin.customers.filters.allTypes')}</option>
          <option value="retail">{t('admin.customers.type.retail')}</option>
          <option value="wholesale">{t('admin.customers.type.wholesale')}</option>
          <option value="vip">{t('admin.customers.type.vip')}</option>
        </select>
        <select
          value={filters.is_active}
          onChange={(event) =>
            setFilters({
              ...filters,
              is_active: event.target.value as Filters['is_active'],
            })
          }
          className="rounded-md border border-neutral-300 px-3 py-2 text-sm"
          data-testid="customers-filter-status"
        >
          <option value="">{t('admin.customers.filters.allStatuses')}</option>
          <option value="true">{t('admin.customers.filters.active')}</option>
          <option value="false">{t('admin.customers.filters.inactive')}</option>
        </select>
        <div className="flex gap-2">
          <button
            type="submit"
            className="flex-1 rounded-md bg-neutral-900 px-3 py-2 text-sm font-medium text-white hover:bg-neutral-800"
            data-testid="customers-filter-apply"
          >
            Aplicar
          </button>
          <button
            type="button"
            onClick={handleClearFilters}
            className="rounded-md border border-neutral-300 px-3 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50"
            data-testid="customers-filter-clear"
          >
            Limpiar
          </button>
        </div>
      </form>

      {customers === null ? (
        <p className="text-sm text-neutral-500" data-testid="customers-loading">
          {t('admin.customers.loading')}
        </p>
      ) : customers.length === 0 ? (
        <p
          className="rounded-lg border border-dashed border-neutral-300 bg-neutral-50 p-6 text-center text-sm text-neutral-600"
          data-testid="customers-empty"
        >
          {t('admin.customers.empty')}
        </p>
      ) : (
        <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white">
          <table className="w-full text-sm">
            <thead className="bg-neutral-50 text-left text-xs uppercase text-neutral-500">
              <tr>
                <th className="px-3 py-2">Nombre</th>
                <th className="px-3 py-2">Email</th>
                <th className="px-3 py-2">Tipo</th>
                <th className="px-3 py-2">Estado</th>
                <th className="px-3 py-2 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              {customers.map((customer) => (
                <tr
                  key={customer.id}
                  className="border-t border-neutral-100"
                  data-testid={`customer-row-${customer.id}`}
                >
                  <td className="px-3 py-2 font-medium text-neutral-900">
                    {customer.full_name}
                  </td>
                  <td className="px-3 py-2 text-neutral-700">{customer.email}</td>
                  <td className="px-3 py-2 text-neutral-700">
                    {t(`admin.customers.type.${customer.customer_type}`)}
                  </td>
                  <td className="px-3 py-2">
                    <span
                      className={`rounded px-2 py-0.5 text-xs font-medium ${
                        customer.is_active
                          ? 'bg-emerald-100 text-emerald-800'
                          : 'bg-rose-100 text-rose-700'
                      }`}
                    >
                      {t(
                        customer.is_active
                          ? 'admin.customers.statusActive'
                          : 'admin.customers.statusInactive',
                      )}
                    </span>
                  </td>
                  <td className="px-3 py-2 text-right">
                    <button
                      type="button"
                      onClick={() => void openDetail(customer)}
                      className="mr-2 text-xs font-medium text-sky-700 hover:text-sky-800"
                      data-testid={`customer-view-${customer.id}`}
                    >
                      {t('admin.customers.actions.view')}
                    </button>
                    {customer.is_active ? (
                      <button
                        type="button"
                        onClick={() => void handleSuspend(customer)}
                        disabled={pendingId === customer.id}
                        className="text-xs font-medium text-rose-600 hover:text-rose-700 disabled:text-neutral-400"
                        data-testid={`customer-suspend-${customer.id}`}
                      >
                        {t('admin.customers.actions.suspend')}
                      </button>
                    ) : (
                      <button
                        type="button"
                        onClick={() => void handleReactivate(customer)}
                        disabled={pendingId === customer.id}
                        className="text-xs font-medium text-emerald-700 hover:text-emerald-800 disabled:text-neutral-400"
                        data-testid={`customer-reactivate-${customer.id}`}
                      >
                        {t('admin.customers.actions.reactivate')}
                      </button>
                    )}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {meta !== null && (
        <p className="text-xs text-neutral-500">
          Página {meta.current_page} de {meta.last_page} — {meta.total} clientes
        </p>
      )}

      {selected !== null && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4"
          data-testid="customer-detail-modal"
        >
          <div className="w-full max-w-md space-y-3 rounded-lg bg-white p-6 shadow-lg">
            <h2 className="text-lg font-semibold text-neutral-900">
              {selected.full_name}
            </h2>
            <dl className="space-y-2 text-sm">
              <div className="flex justify-between">
                <dt className="text-neutral-500">
                  {t('admin.customers.detail.email')}
                </dt>
                <dd className="text-neutral-900">{selected.email}</dd>
              </div>
              {selected.phone !== null && (
                <div className="flex justify-between">
                  <dt className="text-neutral-500">
                    {t('admin.customers.detail.phone')}
                  </dt>
                  <dd className="text-neutral-900">{selected.phone}</dd>
                </div>
              )}
              {selected.order_count !== null && (
                <div className="flex justify-between">
                  <dt className="text-neutral-500">
                    {t('admin.customers.detail.orders')}
                  </dt>
                  <dd className="text-neutral-900">{selected.order_count}</dd>
                </div>
              )}
              {selected.lifetime_value_cents !== null && (
                <div className="flex justify-between">
                  <dt className="text-neutral-500">
                    {t('admin.customers.detail.lifetimeValue')}
                  </dt>
                  <dd className="text-neutral-900">
                    {selected.lifetime_value_cents / 100}
                  </dd>
                </div>
              )}
              {selected.registered_at !== null && (
                <div className="flex justify-between">
                  <dt className="text-neutral-500">
                    {t('admin.customers.detail.registeredAt')}
                  </dt>
                  <dd className="text-neutral-900">
                    {new Date(selected.registered_at).toLocaleDateString()}
                  </dd>
                </div>
              )}
              {selected.is_anonymized && (
                <div className="rounded bg-amber-100 px-2 py-1 text-xs text-amber-800">
                  {t('admin.customers.detail.anonymized')}
                </div>
              )}
            </dl>
            <div className="flex justify-end pt-2">
              <button
                type="button"
                onClick={closeDetail}
                className="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800"
                data-testid="customer-detail-close"
              >
                {t('admin.customers.close')}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
