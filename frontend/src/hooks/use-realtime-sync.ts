import { useQueryClient } from "@tanstack/react-query"
import { useMemo } from "react"

import { operationKeys } from "@/api/operations"
import { listener, usePrivateChannel } from "@/hooks/use-private-channel"
import {
  itemStatusEventSchema,
  orderUpdatedEventSchema,
  tableUpdatedEventSchema,
  type FloorPlan,
  type RestaurantTable,
} from "@/types/operations"

function replaceTable(plan: FloorPlan, table: RestaurantTable): FloorPlan {
  return {
    ...plan,
    zones: plan.zones.map((zone) => ({
      ...zone,
      tables: zone.tables.map((current) => (current.id === table.id ? table : current)),
    })),
  }
}

/**
 * Canal private-company.{uuid}: mapa de mesas y comandas al instante en todas
 * las terminales. La mesa llega completa y se reemplaza en caché sin consultar;
 * los cambios de comanda invalidan su detalle (la API vuelve a aplicar permisos).
 */
export function useRealtimeSync(companyId: string): void {
  const queryClient = useQueryClient()

  const listeners = useMemo(
    () => [
      listener(".table.updated", tableUpdatedEventSchema, ({ table }) => {
        queryClient.setQueryData<FloorPlan>(operationKeys.floorPlan(), (plan) => (plan === undefined ? plan : replaceTable(plan, table)))
        void queryClient.invalidateQueries({ queryKey: operationKeys.floorPlan(), refetchType: "none" })
      }),
      listener(".order.updated", orderUpdatedEventSchema, ({ order_id }) => {
        void queryClient.invalidateQueries({ queryKey: operationKeys.order(order_id) })
        void queryClient.invalidateQueries({ queryKey: operationKeys.floorPlan() })
      }),
      listener(".kds.item.status", itemStatusEventSchema, ({ order_id }) => {
        void queryClient.invalidateQueries({ queryKey: operationKeys.order(order_id) })
      }),
    ],
    [queryClient],
  )

  usePrivateChannel(`company.${companyId}`, listeners)
}
