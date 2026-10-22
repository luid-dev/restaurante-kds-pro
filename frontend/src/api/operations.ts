import { queryOptions } from "@tanstack/react-query"
import { z } from "zod"

import { http, unwrap } from "@/api/http"
import {
  cashContextSchema,
  floorPlanSchema,
  kdsTicketSchema,
  menuSchema,
  orderItemSchema,
  orderSchema,
  saleSchema,
  type ChargePayload,
  type ItemStatus,
  type Order,
  type OrderLinePayload,
  type ProductionArea,
  type Sale,
} from "@/types/operations"

/**
 * API de la operación (salón, comandas, KDS y caja). Toda respuesta se valida
 * con Zod; el backend vuelve a exigir cada permiso en su ruta.
 */

export const operationKeys = {
  all: ["operations"] as const,
  floorPlan: () => [...operationKeys.all, "floor-plan"] as const,
  menu: () => [...operationKeys.all, "menu"] as const,
  order: (orderId: string) => [...operationKeys.all, "order", orderId] as const,
  kds: (area: ProductionArea | null) => [...operationKeys.all, "kds", area ?? "ALL"] as const,
  cash: () => [...operationKeys.all, "cash"] as const,
}

export const operationQueries = {
  floorPlan: () =>
    queryOptions({
      queryKey: operationKeys.floorPlan(),
      queryFn: async ({ signal }) => unwrap(await http.get<unknown>("/floor-plan", { signal }), floorPlanSchema),
      // Respaldo por si se pierde un evento del socket.
      refetchInterval: 60_000,
    }),
  menu: () =>
    queryOptions({
      queryKey: operationKeys.menu(),
      queryFn: async ({ signal }) => unwrap(await http.get<unknown>("/menu", { signal }), menuSchema),
      staleTime: 5 * 60_000,
    }),
  order: (orderId: string) =>
    queryOptions({
      queryKey: operationKeys.order(orderId),
      queryFn: async ({ signal }) => unwrap(await http.get<unknown>(`/orders/${orderId}`, { signal }), orderSchema),
    }),
  kds: (area: ProductionArea | null) =>
    queryOptions({
      queryKey: operationKeys.kds(area),
      queryFn: async ({ signal }) =>
        unwrap(await http.get<unknown>("/kds/tickets", { signal, params: area === null ? {} : { area } }), z.array(kdsTicketSchema)),
      refetchInterval: 60_000,
    }),
  cash: () =>
    queryOptions({
      queryKey: operationKeys.cash(),
      queryFn: async ({ signal }) => unwrap(await http.get<unknown>("/cash-sessions/current", { signal }), cashContextSchema),
    }),
}

export async function openTable(tableId: string, dinersCount: number): Promise<Order> {
  return unwrap(await http.post<unknown>(`/tables/${tableId}/open`, { diners_count: dinersCount }), orderSchema)
}

export async function transferTable(tableId: string, targetTableId: string): Promise<Order> {
  return unwrap(await http.post<unknown>(`/tables/${tableId}/transfer`, { target_table_id: targetTableId }), orderSchema)
}

export interface CounterOrderPayload {
  readonly order_type: "TAKEOUT" | "DELIVERY"
  readonly customer_name: string | null
  readonly delivery_address: string | null
  readonly delivery_phone: string | null
}

export async function createCounterOrder(payload: CounterOrderPayload): Promise<Order> {
  return unwrap(await http.post<unknown>("/orders", payload), orderSchema)
}

export async function sendItemsToKitchen(orderId: string, items: readonly OrderLinePayload[]): Promise<Order> {
  return unwrap(await http.post<unknown>(`/orders/${orderId}/items`, { items }), orderSchema)
}

export async function voidOrderItem(orderId: string, itemId: string, reason: string) {
  return unwrap(await http.post<unknown>(`/orders/${orderId}/items/${itemId}/void`, { reason }), orderItemSchema)
}

export async function requestPreBill(orderId: string): Promise<Order> {
  return unwrap(await http.post<unknown>(`/orders/${orderId}/pre-bill`), orderSchema)
}

export async function advanceKitchenItems(itemIds: readonly string[], status: Extract<ItemStatus, "PREPARING" | "READY" | "SERVED">) {
  return unwrap(
    await http.post<unknown>("/kds/items/advance", { item_ids: itemIds, status }),
    z.object({ item_ids: z.array(z.uuid()), status: z.string() }),
  )
}

export async function openCashSession(cashRegisterId: string, openingAmount: string) {
  return unwrap(await http.post<unknown>("/cash-sessions", { cash_register_id: cashRegisterId, opening_amount: openingAmount }), z.unknown())
}

export const chargeQuoteSchema = z.object({
  mode: z.enum(["FULL", "ITEMS", "AMOUNT"]),
  gross: z.string(),
  service_charge: z.string(),
  total: z.string(),
  settles_order: z.boolean(),
  balance: z.string(),
  balance_after: z.string(),
})

export type ChargeQuote = z.infer<typeof chargeQuoteSchema>

export type QuotePayload = Pick<ChargePayload, "mode" | "format" | "amount" | "lines">

/** Total exacto del cobro (prorrateo del recargo incluido) calculado por el backend. */
export async function quoteCharge(orderId: string, payload: QuotePayload, signal?: AbortSignal): Promise<ChargeQuote> {
  return unwrap(await http.post<unknown>(`/orders/${orderId}/charges/quote`, payload, signal === undefined ? {} : { signal }), chargeQuoteSchema)
}

export async function chargeOrder(orderId: string, payload: ChargePayload): Promise<Sale> {
  return unwrap(await http.post<unknown>(`/orders/${orderId}/charges`, payload), saleSchema)
}
