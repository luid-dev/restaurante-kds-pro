import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query"
import { ChefHat } from "lucide-react"
import { useMemo } from "react"
import { toast } from "sonner"

import { advanceKitchenItems, operationKeys, operationQueries } from "@/api/operations"
import { findNavigationItem } from "@/components/layout/navigation"
import { ModuleHeader } from "@/components/shared/module-header"
import { Skeleton } from "@/components/ui/skeleton"
import { appendTicket, applyItemStatus, ticketKey } from "@/features/kds/kds-cache"
import { KdsTicketCard } from "@/features/kds/kds-ticket-card"
import { KitchenTicket } from "@/features/printing/kitchen-ticket"
import { usePrintStore } from "@/features/printing/print-store"
import { useActiveSession, useCan } from "@/hooks/use-active-session"
import { listener, usePrivateChannel } from "@/hooks/use-private-channel"
import { cn } from "@/lib/utils"
import { useKdsStore } from "@/stores/kds-store"
import { itemStatusEventSchema, orderSentEventSchema, PRODUCTION_AREA_LABELS, PRODUCTION_AREAS, type ItemStatus, type KdsTicket, type ProductionArea } from "@/types/operations"

type Target = Extract<ItemStatus, "PREPARING" | "READY" | "SERVED">

const FILTERS: readonly { readonly area: ProductionArea | null; readonly label: string }[] = [
  { area: null, label: "Todas las áreas" },
  ...PRODUCTION_AREAS.map((area) => ({ area, label: PRODUCTION_AREA_LABELS[area] })),
]

/**
 * Pantalla de cocina/horno de alto contraste. Las comandas llegan por
 * private-company.{uuid}.kds en menos de un segundo, ordenadas FIFO.
 */
export default function KitchenDisplayPage() {
  const item = findNavigationItem("kds")
  const queryClient = useQueryClient()
  const companyId = useActiveSession().user.company.id
  const canUpdate = useCan("kds.update_status")
  const area = useKdsStore((state) => state.area)
  const setArea = useKdsStore((state) => state.setArea)
  const print = usePrintStore((state) => state.print)
  const tickets = useQuery(operationQueries.kds(area))

  const listeners = useMemo(
    () => [
      listener(".kds.order.sent", orderSentEventSchema, (event) => {
        const items = PRODUCTION_AREAS.flatMap((productionArea) => event.areas[productionArea] ?? [])
        appendTicket(queryClient, { order: event.order, sent_at: event.sent_at, items })
      }),
      listener(".kds.item.status", itemStatusEventSchema, (event) => applyItemStatus(queryClient, event.item_ids, event.status)),
    ],
    [queryClient],
  )

  usePrivateChannel(`company.${companyId}.kds`, listeners)

  const advance = useMutation({
    mutationFn: (payload: { itemIds: readonly string[]; status: Target }) => advanceKitchenItems(payload.itemIds, payload.status),
    onMutate: (payload) => applyItemStatus(queryClient, payload.itemIds, payload.status),
    onError: (error) => {
      toast.error(error.message)
      void queryClient.invalidateQueries({ queryKey: [...operationKeys.all, "kds"] })
    },
  })

  const counts = useMemo(() => {
    const items = tickets.data?.flatMap((ticket) => ticket.items) ?? []

    return {
      tickets: tickets.data?.length ?? 0,
      pending: items.filter((entry) => entry.status === "PENDING").length,
      preparing: items.filter((entry) => entry.status === "PREPARING").length,
      ready: items.filter((entry) => entry.status === "READY").length,
    }
  }, [tickets.data])

  return (
    <div className="dark flex min-h-full flex-col bg-zinc-950 text-zinc-100">
      <ModuleHeader item={item} />
      <div className="flex flex-wrap items-center gap-2 border-b border-zinc-800 px-4 py-2.5 md:px-6">
        <fieldset className="flex flex-wrap gap-1 m-0 min-w-0 border-0 p-0">
          <legend className="sr-only">Estación de trabajo</legend>
          {FILTERS.map((filter) => (
            <button
              key={filter.label}
              type="button"
              aria-pressed={area === filter.area}
              onClick={() => setArea(filter.area)}
              className={cn(
                "h-9 rounded-md border px-3 text-sm transition-colors",
                area === filter.area ? "border-zinc-100 bg-zinc-100 text-zinc-950" : "border-zinc-800 text-zinc-300 hover:bg-zinc-900",
              )}
            >
              {filter.label}
            </button>
          ))}
        </fieldset>
        <dl className="ml-auto flex gap-4 font-mono text-xs text-zinc-400 tabular-nums">
          <div className="flex gap-1.5"><dt>Comandas</dt><dd className="text-zinc-100">{counts.tickets}</dd></div>
          <div className="flex gap-1.5"><dt>En cola</dt><dd className="text-zinc-100">{counts.pending}</dd></div>
          <div className="flex gap-1.5"><dt>Preparando</dt><dd className="text-amber-300">{counts.preparing}</dd></div>
          <div className="flex gap-1.5"><dt>Listos</dt><dd className="text-emerald-400">{counts.ready}</dd></div>
        </dl>
      </div>

      <div className="flex-1 p-4 md:p-6">
        {tickets.isPending ? (
          <div className="grid grid-cols-[repeat(auto-fill,minmax(19rem,1fr))] gap-3">
            {[0, 1, 2].map((index) => (
              <Skeleton key={index} className="h-56 bg-zinc-900" />
            ))}
          </div>
        ) : tickets.isError ? (
          <p className="text-sm text-red-400">{tickets.error.message}</p>
        ) : tickets.data.length === 0 ? (
          <div className="flex flex-col items-center justify-center gap-2 rounded-md border border-dashed border-zinc-800 py-20 text-zinc-500">
            <ChefHat className="size-6" strokeWidth={1.5} aria-hidden="true" />
            <p className="text-sm">Sin comandas pendientes en esta estación.</p>
          </div>
        ) : (
          <div className="grid grid-cols-[repeat(auto-fill,minmax(19rem,1fr))] items-start gap-3">
            {tickets.data.map((ticket: KdsTicket) => (
              <KdsTicketCard
                key={ticketKey(ticket)}
                ticket={ticket}
                busy={advance.isPending}
                canUpdate={canUpdate}
                onAdvance={(itemIds, status) => (itemIds.length === 0 ? undefined : advance.mutate({ itemIds, status }))}
                onPrint={(printable) => print(<KitchenTicket ticket={printable} />)}
              />
            ))}
          </div>
        )}
      </div>
    </div>
  )
}
