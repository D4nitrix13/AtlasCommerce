import { useEffect, useState, type ReactElement } from 'react';
import { authedFetch } from '../../../lib/auth';
import { type ApiError } from '../../../lib/api';
import { API_ENDPOINTS } from '../../../lib/endpoints';
import { formatMoney, formatDateTime } from '../../../lib/formatters';
import { useTranslations } from '../../../i18n';
import type { Refund, RefundStatus, RefundWithRelations } from '../../../types/returns';
import type { PaginatedResponse } from '../../../types/api';
import { REFUND_STATUS_COLORS } from '../../../types/returns';

/**
 * RefundsAdmin — Panel admin para gestionar reembolsos.
 *
 * Lista paginada de refunds del tenant. Flujo de transiciones es el
 * más simple de los admin panels:
 *
 *   pending   → completed | failed
 *   completed → (terminal)
 *   failed    → (terminal)
 *
 * **Cuidado**: el endpoint backend `POST /api/v1/refunds/{id}/process`
 * (completed) es IRREVERSIBLE — dispara reversión de stock + impacto
 * en invoice (ver `RefundController::process()` en backend).
 * Por eso `useState(pendingId)` deshabilita el botón durante el request.
 *
 * **Estados** (AGENTS.md §11): isLoading/error/empty/success/pendingId.
 * **Multi-tenant**: scoping via `store.domain` middleware en backend.
 */
const REFUND_TRANSITIONS: Record<RefundStatus, RefundStatus[]> = {
  pending: ['completed', 'failed'],
  completed: [],
  failed: [],
};

/**
 * I18n keys por status.
 */
const STATUS_LABEL_KEYS = {
  pending: 'admin.refunds.status.pending',
  completed: 'admin.refunds.status.completed',
  failed: 'admin.refunds.status.failed',
} as const satisfies Record<RefundStatus, string>;

export default function RefundsAdmin(): ReactElement {
  const { t } = useTranslations();
  const [refunds, setRefunds] = useState<RefundWithRelations[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pendingId, setPendingId] = useState<number | null>(null);

  const loadRefunds = async (): Promise<void> => {
    setIsLoading(true);
    setError(null);
    try {
      const response = await authedFetch<PaginatedResponse<RefundWithRelations>>(
        API_ENDPOINTS.returns.refunds
      );
      setRefunds(response.data);
    } catch (apiError) {
      setError((apiError as ApiError).message);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadRefunds();
  }, []);

  const handleTransition = async (
    refund: RefundWithRelations,
    targetStatus: RefundStatus
  ): Promise<void> => {
    setPendingId(refund.id);
    try {
      const endpoint =
        targetStatus === 'completed'
          ? `${API_ENDPOINTS.returns.refunds}/${refund.id}/process`
          : `${API_ENDPOINTS.returns.refunds}/${refund.id}/fail`;

      await authedFetch<{ data: Refund }>(endpoint, { method: 'POST' });
      await loadRefunds();
    } catch (apiError) {
      window.alert((apiError as ApiError).message || 'No se pudo procesar el refund.');
    } finally {
      setPendingId(null);
    }
  };

  if (isLoading) {
    return <div className="p-8 text-center text-gray-500">{t('admin.refunds.loading')}</div>;
  }

  if (error) {
    return (
      <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">
        {t('admin.refunds.error', { message: error })}
      </div>
    );
  }

  if (refunds.length === 0) {
    return (
      <div className="rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-500">
        {t('admin.refunds.table.empty')}
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
      <table className="w-full text-left text-sm">
        <thead className="bg-gray-50 text-xs uppercase text-gray-600">
          <tr>
            <th className="px-4 py-3">{t('admin.refunds.table.id')}</th>
            <th className="px-4 py-3">{t('admin.refunds.table.returnRequest')}</th>
            <th className="px-4 py-3">{t('admin.refunds.table.amount')}</th>
            <th className="px-4 py-3">{t('admin.refunds.table.status')}</th>
            <th className="px-4 py-3">{t('admin.refunds.table.processedAt')}</th>
            <th className="px-4 py-3 text-right">{t('admin.refunds.table.actions')}</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-200">
          {refunds.map((refund) => {
            const transitions = REFUND_TRANSITIONS[refund.status];
            return (
              <tr key={refund.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-mono text-xs">#{refund.id}</td>
                <td className="px-4 py-3">
                  #{refund.return_request_id}
                  {refund.return_request && (
                    <span className="ml-2 text-xs text-gray-500">
                      ({refund.return_request.status})
                    </span>
                  )}
                </td>
                <td className="px-4 py-3">{formatMoney(refund.amount)}</td>
                <td className="px-4 py-3">
                  <span
                    className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${REFUND_STATUS_COLORS[refund.status]}`}
                  >
                    {t(STATUS_LABEL_KEYS[refund.status])}
                  </span>
                </td>
                <td className="px-4 py-3 text-xs text-gray-600">
                  {formatDateTime(refund.refunded_at)}
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
                          disabled={pendingId === refund.id}
                          onClick={() => void handleTransition(refund, target)}
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
