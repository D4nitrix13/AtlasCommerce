import { useEffect, useState, type ReactElement } from "react";
import { authedFetch } from "../../../lib/auth";
import { type ApiError } from "../../../lib/api";
import { API_ENDPOINTS } from "../../../lib/endpoints";
import { formatMoney, formatDateTime } from "../../../lib/formatters";
import { useTranslations } from "../../../i18n";
import type {
  Shipment,
  ShipmentStatus,
  ShipmentWithRelations,
} from "../../../types/shipping";
import type { PaginatedResponse } from "../../../types/api";
import { SHIPMENT_STATUS_COLORS } from "../../../types/shipping";

const SHIPPING_TRANSITIONS: Record<ShipmentStatus, ShipmentStatus[]> = {
  pending: ["shipped", "cancelled"],
  shipped: ["delivered"],
  in_transit: ["delivered"],
  delivered: [],
  cancelled: [],
};

const STATUS_LABEL_KEYS = {
  pending: "admin.shipments.status.pending",
  shipped: "admin.shipments.status.shipped",
  in_transit: "admin.shipments.status.in_transit",
  delivered: "admin.shipments.status.delivered",
  cancelled: "admin.shipments.status.cancelled",
} as const satisfies Record<ShipmentStatus, string>;

export default function ShipmentsAdmin(): ReactElement {
  const { t } = useTranslations();
  const [shipments, setShipments] = useState<ShipmentWithRelations[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [pendingId, setPendingId] = useState<number | null>(null);

  const loadShipments = async (): Promise<void> => {
    setIsLoading(true);
    setError(null);
    try {
      const response = await authedFetch<PaginatedResponse<ShipmentWithRelations>>(
        API_ENDPOINTS.shipping.shipments,
      );
      setShipments(response.data);
    } catch (apiError) {
      setError((apiError as ApiError).message);
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    void loadShipments();
  }, []);

  const handleTransition = async (
    shipment: ShipmentWithRelations,
    targetStatus: ShipmentStatus,
  ): Promise<void> => {
    setPendingId(shipment.id);
    try {
      const endpoint =
        targetStatus === "shipped"
          ? `${API_ENDPOINTS.shipping.shipments}/${shipment.id}/ship`
          : targetStatus === "delivered"
            ? `${API_ENDPOINTS.shipping.shipments}/${shipment.id}/deliver`
            : `${API_ENDPOINTS.shipping.shipments}/${shipment.id}/cancel`;

      await authedFetch<{ data: Shipment }>(endpoint, { method: "POST" });
      await loadShipments();
    } catch (apiError) {
      const err = apiError as ApiError;
      window.alert(err.message || "No se pudo actualizar el envío.");
    } finally {
      setPendingId(null);
    }
  };

  if (isLoading) {
    return (
      <div className="p-8 text-center text-gray-500">{t("admin.shipments.loading")}</div>
    );
  }

  if (error) {
    return (
      <div className="rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">
        {t("admin.shipments.error", { message: error })}
      </div>
    );
  }

  if (shipments.length === 0) {
    return (
      <div className="rounded-xl border border-gray-200 bg-white p-8 text-center text-gray-500">
        {t("admin.shipments.table.empty")}
      </div>
    );
  }

  return (
    <div className="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
      <table className="w-full text-left text-sm">
        <thead className="bg-gray-50 text-xs uppercase text-gray-600">
          <tr>
            <th className="px-4 py-3">{t("admin.shipments.table.tracking")}</th>
            <th className="px-4 py-3">{t("admin.shipments.table.order")}</th>
            <th className="px-4 py-3">{t("admin.shipments.table.status")}</th>
            <th className="px-4 py-3">{t("admin.shipments.table.cost")}</th>
            <th className="px-4 py-3">{t("admin.shipments.table.zone")}</th>
            <th className="px-4 py-3">{t("admin.shipments.table.shippedAt")}</th>
            <th className="px-4 py-3">{t("admin.shipments.table.deliveredAt")}</th>
            <th className="px-4 py-3 text-right">{t("admin.shipments.table.actions")}</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-200">
          {shipments.map((shipment) => {
            const transitions = SHIPPING_TRANSITIONS[shipment.status];
            return (
              <tr key={shipment.id} className="hover:bg-gray-50">
                <td className="px-4 py-3 font-mono text-xs">
                  {shipment.tracking_code ?? "—"}
                </td>
                <td className="px-4 py-3">#{shipment.order_id}</td>
                <td className="px-4 py-3">
                  <span
                    className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${SHIPMENT_STATUS_COLORS[shipment.status]}`}
                  >
                    {t(STATUS_LABEL_KEYS[shipment.status])}
                  </span>
                </td>
                <td className="px-4 py-3">{formatMoney(shipment.shipping_cost)}</td>
                <td className="px-4 py-3">{shipment.shipping_zone?.name ?? "—"}</td>
                <td className="px-4 py-3 text-xs text-gray-600">
                  {formatDateTime(shipment.shipped_at)}
                </td>
                <td className="px-4 py-3 text-xs text-gray-600">
                  {formatDateTime(shipment.delivered_at)}
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
                          disabled={pendingId === shipment.id}
                          onClick={() => void handleTransition(shipment, target)}
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