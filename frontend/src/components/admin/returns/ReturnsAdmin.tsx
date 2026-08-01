import { useEffect, useState, type ReactElement } from "react";
import { apiFetch, type ApiError } from "../../../lib/api";
import { API_ENDPOINTS } from "../../../lib/endpoints";
import { formatDate, formatDateTime } from "../../../lib/formatters";
import type {
  ReturnRequest,
  ReturnRequestStatus,
  ReturnRequestWithRelations,
} from "../../../types/returns";
import type { PaginatedResponse } from "../../../types/api";
import {
  RETURN_REQUEST_STATUS_LABELS,
  RETURN_REQUEST_STATUS_COLORS,
} from "../../../types/returns";

const RETURN_TRANSITIONS: Record<ReturnRequestStatus, ReturnRequestStatus[]> = {
  pending: ["approved", "rejected", "cancelled"],
  approved: ["cancelled"],
  rejected: [],
  completed: [],
  cancelled: [],
};

export default function ReturnsAdmin(): ReactElement {
  const [returns, setReturns] = useState<ReturnRequestWithRelations[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pendingId, setPendingId] = useState<number | null>(null);

  const loadReturns = async (): Promise<void> => {
    setIsLoading(true);
    setError(null);
    try {
      const response = await apiFetch<PaginatedResponse<ReturnRequestWithRelations>>(
        API_ENDPOINTS.returns.requests,
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
    targetStatus: ReturnRequestStatus,
  ): Promise<void> => {
    setPendingId(returnRequest.id);
    try {
      const endpoint =
        targetStatus === "approved"
          ? `${API_ENDPOINTS.returns.requests}/${returnRequest.id}/approve`
          : targetStatus === "rejected"
            ? `${API_ENDPOINTS.returns.requests}/${returnRequest.id}/reject`
            : `${API_ENDPOINTS.returns.requests}/${returnRequest.id}/cancel`;

      await apiFetch<{ data: ReturnRequest }>(endpoint, { method: "POST" });
      await loadReturns();
    } catch (apiError) {
      const err = apiError as ApiError;
      window.alert(err.message || "No se pudo actualizar la solicitud.");
    } finally {
      setPendingId(null);
    }
  };

  if (isLoading) {
    return (
      <div className="p-8 text-center text-gray-500">Cargando solicitudes...</div>
    );
  }

  if (error) {
    return (
      <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">
        No se pudieron cargar las solicitudes: {error}
      </div>
    );
  }

  if (returns.length === 0) {
    return (
      <div className="rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-500">
        No hay solicitudes de devolución todavía.
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
      <table className="w-full text-left text-sm">
        <thead className="bg-gray-50 text-xs uppercase text-gray-600">
          <tr>
            <th className="px-4 py-3">ID</th>
            <th className="px-4 py-3">Orden</th>
            <th className="px-4 py-3">Razón</th>
            <th className="px-4 py-3">Estado</th>
            <th className="px-4 py-3">Solicitado</th>
            <th className="px-4 py-3">Resuelto</th>
            <th className="px-4 py-3 text-right">Acciones</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-200">
          {returns.map((returnRequest) => {
            const transitions = RETURN_TRANSITIONS[returnRequest.status];
            return (
              <tr key={returnRequest.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-mono text-xs">#{returnRequest.id}</td>
                <td className="px-4 py-3">
                  {returnRequest.order?.order_number ??
                    `#${returnRequest.order_id}`}
                </td>
                <td className="px-4 py-3 max-w-xs truncate" title={returnRequest.reason}>
                  {returnRequest.reason}
                </td>
                <td className="px-4 py-3">
                  <span
                    className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${RETURN_REQUEST_STATUS_COLORS[returnRequest.status]}`}
                  >
                    {RETURN_REQUEST_STATUS_LABELS[returnRequest.status]}
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
                          {RETURN_REQUEST_STATUS_LABELS[target]}
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