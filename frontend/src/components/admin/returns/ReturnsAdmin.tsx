import { useEffect, useState, type ReactElement } from 'react';
import { authedFetch } from '../../../lib/auth';
import { type ApiError } from '../../../lib/api';
import { API_ENDPOINTS } from '../../../lib/endpoints';
import { formatDate, formatDateTime } from '../../../lib/formatters';
import { useTranslations } from '../../../i18n';
import type {
  ReturnRequest,
  ReturnRequestStatus,
  ReturnRequestWithRelations,
} from '../../../types/returns';
import type { PaginatedResponse } from '../../../types/api';
import { RETURN_REQUEST_STATUS_COLORS } from '../../../types/returns';

/**
 * ReturnsAdmin — Panel admin para gestionar solicitudes de devolución (RMA).
 *
 * Lista paginada de return requests del tenant actual, con acciones de
 * transición de estado segun `RETURN_TRANSITIONS`:
 *
 *   pending   → approved | rejected | cancelled
 *   approved  → cancelled
 *   rejected  → (terminal)
 *   completed → (terminal)
 *   cancelled → (terminal)
 *
 * Las transiciones se disparan via endpoints API backend (no via service
 * directo): usan `POST /api/v1/returns/{id}/{transition}` con CSRF + auth
 * via `authedFetch`. Errores (ej: 422 por transición inválida) se
 * muestran inline con el mensaje del API.
 *
 * **Estados del componente** (AGENTS.md §11):
 *   - isLoading: spinners durante fetch
 *   - error: banner rojo si fetch falla
 *   - empty: "No hay solicitudes" si array vacío
 *   - success: lista con badges de status + botones de transición
 *   - pendingId: id con transición en curso (botón deshabilitado)
 *
 * **Multi-tenant**: el tenant se resuelve por middleware `store.domain`
 * en el backend. El componente no filtra por tienda (viene en la API).
 */
const RETURN_TRANSITIONS: Record<ReturnRequestStatus, ReturnRequestStatus[]> = {
  pending: ['approved', 'rejected', 'cancelled'],
  approved: ['cancelled'],
  rejected: [],
  completed: [],
  cancelled: [],
};

/**
 * Mapa de cada estado a su i18n key (frontend/src/i18n/<locale>/...).
 *
 * Usado via `t(STATUS_LABEL_KEYS[status])` para resolver label
 * traducido en runtime.
 */
const STATUS_LABEL_KEYS = {
  pending: 'admin.returns.status.pending',
  approved: 'admin.returns.status.approved',
  rejected: 'admin.returns.status.rejected',
  completed: 'admin.returns.status.completed',
  cancelled: 'admin.returns.status.cancelled',
} as const satisfies Record<ReturnRequestStatus, string>;

export default function ReturnsAdmin(): ReactElement {
  const { t } = useTranslations();
  const [returns, setReturns] = useState<ReturnRequestWithRelations[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pendingId, setPendingId] = useState<number | null>(null);

  const loadReturns = async (): Promise<void> => {
    setIsLoading(true);
    setError(null);
    try {
      const response = await authedFetch<PaginatedResponse<ReturnRequestWithRelations>>(
        API_ENDPOINTS.returns.requests
      );
      setReturns(response.data);
    } catch (apiError) {
      setError((apiError as ApiError).message);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadReturns();
  }, []);

  const handleTransition = async (
    returnRequest: ReturnRequestWithRelations,
    targetStatus: ReturnRequestStatus
  ): Promise<void> => {
    setPendingId(returnRequest.id);
    try {
      const endpoint =
        targetStatus === 'approved'
          ? `${API_ENDPOINTS.returns.requests}/${returnRequest.id}/approve`
          : targetStatus === 'rejected'
            ? `${API_ENDPOINTS.returns.requests}/${returnRequest.id}/reject`
            : `${API_ENDPOINTS.returns.requests}/${returnRequest.id}/cancel`;

      await authedFetch<{ data: ReturnRequest }>(endpoint, { method: 'POST' });
      await loadReturns();
    } catch (apiError) {
      window.alert((apiError as ApiError).message || 'No se pudo actualizar la solicitud.');
    } finally {
      setPendingId(null);
    }
  };

  if (isLoading) {
    return <div className="p-8 text-center text-gray-500">{t('admin.returns.loading')}</div>;
  }

  if (error) {
    return (
      <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">
        {t('admin.returns.error', { message: error })}
      </div>
    );
  }

  if (returns.length === 0) {
    return (
      <div className="rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-500">
        {t('admin.returns.table.empty')}
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
      <table className="w-full text-left text-sm">
        <thead className="bg-gray-50 text-xs uppercase text-gray-600">
          <tr>
            <th className="px-4 py-3">{t('admin.returns.table.id')}</th>
            <th className="px-4 py-3">{t('admin.returns.table.order')}</th>
            <th className="px-4 py-3">{t('admin.returns.table.reason')}</th>
            <th className="px-4 py-3">{t('admin.returns.table.status')}</th>
            <th className="px-4 py-3">{t('admin.returns.table.requestedAt')}</th>
            <th className="px-4 py-3">{t('admin.returns.table.resolvedAt')}</th>
            <th className="px-4 py-3 text-right">{t('admin.returns.table.actions')}</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-200">
          {returns.map((returnRequest) => {
            const transitions = RETURN_TRANSITIONS[returnRequest.status];
            return (
              <tr key={returnRequest.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-mono text-xs">#{returnRequest.id}</td>
                <td className="px-4 py-3">
                  {returnRequest.order?.order_number ?? `#${returnRequest.order_id}`}
                </td>
                <td className="px-4 py-3 max-w-xs truncate" title={returnRequest.reason}>
                  {returnRequest.reason}
                </td>
                <td className="px-4 py-3">
                  <span
                    className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${RETURN_REQUEST_STATUS_COLORS[returnRequest.status]}`}
                  >
                    {t(STATUS_LABEL_KEYS[returnRequest.status])}
                  </span>
                </td>
                <td className="px-4 py-3 text-xs text-gray-600">
                  {formatDate(returnRequest.requested_at)}
                </td>
                <td className="px-4 py-3 text-xs text-gray-600">
                  {formatDateTime(returnRequest.resolved_at)}
                </td>
                <td className="px-4 py-3 text-right">
                  <div className="flex justify-end gap-2">
                    {transitions.length === 0 ? (
                      <span className="text-xs text-gray-400">—</span>
                    ) : (
                      transitions.map((target) => (
                        <button
                          key={target}
                          type="button"
                          disabled={pendingId === returnRequest.id}
                          onClick={() => void handleTransition(returnRequest, target)}
                          className="rounded-md border border-gray-300 bg-white px-2 py-1 text-xs font-medium text-gray-700 transition hover:bg-gray-100 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                          {t(STATUS_LABEL_KEYS[target])}
                        </button>
                      ))
                    )}
                  </div>
                </td>
              </tr>
            );
          })}
        </tbody>
      </table>
    </div>
  );
}
