import type { QueryClient } from "@tanstack/react-query"

import { operationKeys } from "@/api/operations"
import { PRODUCTION_AREAS, type ItemStatus, type KdsItem, type KdsTicket, type ProductionArea } from "@/types/operations"

/** Estados que siguen visibles en cocina (igual que OrderItemStatus::kitchenQueue()). */
const LIVE_STATUSES: readonly ItemStatus[] = ["PENDING", "PREPARING", "READY"]

export function ticketKey(ticket: Pick<KdsTicket, "order" | "sent_at">): string {
  return `${ticket.order.id}|${ticket.sent_at ?? ""}`
}

function eachKdsCache(queryClient: QueryClient, update: (tickets: KdsTicket[], area: ProductionArea | null) => KdsTicket[]): void {
  for (const area of [null, ...PRODUCTION_AREAS]) {
    queryClient.setQueryData<KdsTicket[]>(operationKeys.kds(area), (tickets) => (tickets === undefined ? tickets : update(tickets, area)))
  }
}

/** Nueva ronda recibida por WebSocket: se agrega al final (FIFO) en cada vista filtrada. */
export function appendTicket(queryClient: QueryClient, ticket: KdsTicket): void {
  eachKdsCache(queryClient, (tickets, area) => {
    const items = area === null ? ticket.items : ticket.items.filter((item) => item.production_area === area)

    if (items.length === 0 || tickets.some((current) => ticketKey(current) === ticketKey(ticket))) {
      return tickets
    }

    return [...tickets, { ...ticket, items }]
  })
}

/** Cambio de estado (local u otra pantalla): entregados y anulados salen de la cola. */
export function applyItemStatus(queryClient: QueryClient, itemIds: readonly string[], status: ItemStatus): void {
  const ids = new Set(itemIds)

  eachKdsCache(queryClient, (tickets) =>
    tickets
      .map((ticket) => ({
        ...ticket,
        items: ticket.items
          .map((item): KdsItem => (ids.has(item.id) ? { ...item, status, ready_at: status === "READY" ? new Date().toISOString() : item.ready_at } : item))
          .filter((item) => LIVE_STATUSES.includes(item.status)),
      }))
      .filter((ticket) => ticket.items.length > 0),
  )
}
